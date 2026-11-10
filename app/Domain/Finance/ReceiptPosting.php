<?php

namespace App\Domain\Finance;

use App\Domain\Configuration\BusinessPolicies;
use App\Domain\Configuration\PolicyDefinitions;
use App\Models\AccountMovement;
use App\Models\BusinessPolicy;
use App\Models\FinancialAccount;
use App\Models\PaymentReceipt;
use App\Models\PaymentReceiptEvent;
use App\Models\Society;
use App\Models\User;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReceiptPosting
{
    public function canPost(User $actor): bool
    {
        return $actor->can('finance.receipts.post') && $actor->hasAnyRole(['admin', 'super_admin', 'accountant']);
    }

    public function canReverse(User $actor): bool
    {
        return $actor->can('finance.receipts.reverse') && $actor->hasAnyRole(['admin', 'super_admin']);
    }

    /** @return Builder<PaymentReceipt> */
    public function query(User $actor): Builder
    {
        return PaymentReceipt::whereIn('customer_id', app(ReceiptDrafts::class)->customers($actor)->select('id'));
    }

    /** @return array<string,mixed> */
    public function settings(bool $lock = false): array
    {
        $records = BusinessPolicy::whereIn('key', ['collections', 'receipt_posting'])->orderBy('key')->when($lock, fn ($query) => $query->lockForUpdate())->get()->keyBy('key');
        $result = ['versions' => [], 'values' => [], 'missing' => []];
        $setup = app(BusinessPolicies::class);
        foreach (['collections', 'receipt_posting'] as $key) {
            $values = array_replace($setup->defaults($key), $records->get($key)->values ?? []);
            $result['versions'][$key] = $records->get($key)->version ?? 0;
            $result['values'][$key] = $values;
            $result['missing'][$key] = array_map(fn ($field) => PolicyDefinitions::all()[$key]['fields'][$field]['label'], $setup->missing($key, $values));
        }

        return $result;
    }

    /** @param array<string,mixed> $input */
    public function accept(User $actor, int $draft, array $input): PaymentReceipt
    {
        return $this->execute($actor, $draft, 'accept', $input);
    }

    /** @param array<string,mixed> $input */
    public function advance(User $actor, int $receipt, string $operation, array $input): PaymentReceipt
    {
        abort_unless(in_array($operation, ['clear', 'reverse'], true), 404);

        return $this->execute($actor, $receipt, $operation, $input);
    }

    /** @param array<string,mixed> $input */
    private function execute(User $actor, int $id, string $operation, array $input): PaymentReceipt
    {
        $data = Validator::make($input, [
            'command_key' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:1'],
            'posting_version' => ['required', 'integer', 'min:1'], 'collection_version' => [$operation === 'accept' ? 'required' : 'nullable', 'integer', 'min:1'],
            'business_date' => ['required', 'date_format:Y-m-d'], 'note' => ['required', 'string', 'min:10', 'max:1000'],
            'confirmed' => ['required', 'accepted'], 'stage' => [$operation === 'accept' ? 'required' : 'prohibited', Rule::in(['confirmed', 'deposited', 'cleared'])],
            'amount' => ['prohibited'], 'number' => ['prohibited'], 'state' => ['prohibited'], 'actor_id' => ['prohibited'],
        ])->validate();
        $note = trim($data['note']);
        if (mb_strlen($note) < 10) {
            throw ValidationException::withMessages(['note' => 'Record at least 10 characters of verification or reversal context.']);
        }
        $payload = ['operation' => $operation, 'id' => $id, 'expected_version' => (int) $data['expected_version'], 'posting_version' => (int) $data['posting_version'],
            'collection_version' => isset($data['collection_version']) ? (int) $data['collection_version'] : null, 'business_date' => $data['business_date'], 'note' => $note, 'stage' => $data['stage'] ?? null];
        $hashes = $this->hashes(json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $id, $operation, $data, $note, $hashes) {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize($operation === 'reverse' ? 'finance.receipts.reverse' : 'finance.receipts.post');
            abort_unless($operation === 'reverse' ? $this->canReverse($fresh) : $this->canPost($fresh), 403);
            // Serializes numbering, duplicate checks, policy updates and initial series creation.
            $issuer = Society::whereKey(1)->lockForUpdate()->firstOrFail();
            $visible = $operation === 'accept' ? app(ReceiptDrafts::class)->query($fresh)->findOrFail($id) : $this->query($fresh)->findOrFail($id);
            $customer = app(ReceiptDrafts::class)->customers($fresh)->whereKey($visible->customer_id)->lockForUpdate()->firstOrFail();
            $command = DB::table('receipt_posting_commands')->where('actor_id', $fresh->id)->where('command_key', $data['command_key'])->lockForUpdate()->first();
            if ($command) {
                if (! in_array($command->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This verification command was already used with different details. Reload the record.');
                }

                return $this->query($fresh)->whereKey($command->payment_receipt_id)->firstOrFail();
            }
            $account = FinancialAccount::query()->whereKey($visible->financial_account_id)->lockForUpdate()->firstOrFail();
            if ($operation !== 'reverse' && ! $account->active) {
                throw ValidationException::withMessages(['posting' => 'Reactivate the destination account before accepting or clearing money.']);
            }
            $settings = $this->settings(true);
            if ($settings['versions']['receipt_posting'] !== (int) $data['posting_version']) {
                throw new ConflictHttpException('Posting controls changed. Reload and review the current rules.');
            }
            if ($settings['missing']['receipt_posting'] !== []) {
                throw ValidationException::withMessages(['posting' => 'Main admin must complete receipt posting controls.']);
            }
            $posting = $settings['values']['receipt_posting'];
            $this->validateDate($data['business_date'], $posting);
            if ($operation === 'accept') {
                $draft = app(ReceiptDrafts::class)->query($fresh)->lockForUpdate()->findOrFail($id);
                if ($draft->version !== (int) $data['expected_version'] || $draft->financial_account_id !== $account->id) {
                    throw new ConflictHttpException('The preparation changed. Reload before verifying it.');
                }
                if (PaymentReceipt::where('receipt_draft_id', $id)->lockForUpdate()->first()) {
                    throw new ConflictHttpException('This preparation already has an issued receipt. Open that receipt instead.');
                }
                if ($settings['missing']['collections'] !== [] || $settings['versions']['collections'] !== (int) $data['collection_version']) {
                    throw ValidationException::withMessages(['posting' => 'Receiving-payment settings are incomplete or changed. Reload after main admin completes them.']);
                }
                $collections = $settings['values']['collections'];
                if ($posting['unallocated'] !== 'allow' || $collections['overpayment'] !== 'unallocated') {
                    throw ValidationException::withMessages(['posting' => 'Unallocated customer credit must be explicitly enabled in both receiving-payment and posting settings. Installment allocation is not available yet.']);
                }
                if (! in_array($draft->method, $collections['methods'], true)) {
                    throw ValidationException::withMessages(['posting' => 'This payment method is no longer accepted.']);
                }
                $destination = match ($draft->method) {
                    'cash' => 'cash', 'bank_transfer', 'cheque' => 'bank', 'mobile_wallet' => 'wallet', default => 'other'
                };
                if ($posting['routing'] === 'matching' && $account->type !== $destination) {
                    throw ValidationException::withMessages(['posting' => 'The destination type does not match the configured method routing. Correct the preparation first.']);
                }
                if ($data['business_date'] < $draft->reported_on) {
                    throw ValidationException::withMessages(['business_date' => 'Acceptance cannot precede the reported payment date.']);
                }
                $cheque = $draft->method === 'cheque';
                if (($cheque && ! in_array($data['stage'], ['deposited', 'cleared'], true)) || (! $cheque && $data['stage'] !== 'confirmed')) {
                    throw ValidationException::withMessages(['stage' => 'Choose the appropriate verified payment state.']);
                }
                $reference = trim($draft->reference ?? '');
                if ($draft->method !== 'cash' && $reference === '') {
                    throw ValidationException::withMessages(['posting' => 'A non-cash payment needs an external reference.']);
                }
                $globalHashes = $draft->method === 'cash' ? [] : $this->hashes($draft->method."\0".$reference);
                $accountHashes = $draft->method === 'cash' ? [] : $this->hashes($account->id."\0".$draft->method."\0".$reference);
                if ($accountHashes !== [] && PaymentReceipt::whereIn('account_reference_hash', $accountHashes)->lockForUpdate()->first()) {
                    throw ValidationException::withMessages(['posting' => 'This exact external reference/method already has an issued receipt in this account, including reversed receipts. Review the original.']);
                }
                if ($posting['duplicates'] === 'global_method' && $globalHashes !== [] && PaymentReceipt::whereIn('global_reference_hash', $globalHashes)->lockForUpdate()->first()) {
                    throw ValidationException::withMessages(['posting' => 'This exact external reference/method already has an issued receipt in another account.']);
                }
                $series = DB::table('receipt_series')->where('prefix', $posting['prefix'])->lockForUpdate()->first();
                if (! $series) {
                    DB::table('receipt_series')->insert(['prefix' => $posting['prefix'], 'starting_number' => $posting['starting_number'], 'next_number' => $posting['starting_number']]);
                    $sequence = $posting['starting_number'];
                } else {
                    $sequence = (int) $series->next_number;
                    if ((int) $series->starting_number !== $posting['starting_number']) {
                        throw ValidationException::withMessages(['posting' => 'The configured series start does not match the issued sequence. Main admin must correct it.']);
                    }
                }
                if ($sequence > 999999999) {
                    throw ValidationException::withMessages(['posting' => 'This receipt series is exhausted. Configure a new prefix.']);
                }
                DB::table('receipt_series')->where('prefix', $posting['prefix'])->update(['next_number' => $sequence + 1]);
                $recognized = ! $cheque || $data['stage'] === 'cleared' || $collections['cheque_recognition'] === 'deposited';
                $receipt = PaymentReceipt::create(['receipt_draft_id' => $draft->id, 'customer_id' => $draft->customer_id, 'financial_account_id' => $account->id,
                    'number' => $posting['prefix'].'-'.str_pad((string) $sequence, $posting['padding'], '0', STR_PAD_LEFT), 'series_prefix' => $posting['prefix'], 'sequence' => $sequence,
                    'amount' => $draft->amount, 'method' => $draft->method, 'state' => $recognized ? 'recognized' : 'pending_clearance', 'bank_stage' => $cheque ? $data['stage'] : null,
                    'accepted_on' => $data['business_date'], 'recognized_on' => $recognized ? $data['business_date'] : null, 'cleared_on' => $cheque && $data['stage'] === 'cleared' ? $data['business_date'] : null,
                    'reference' => $reference, 'verification_note' => $note, 'account_reference_hash' => $accountHashes[0] ?? null, 'global_reference_hash' => $globalHashes[0] ?? null,
                    'snapshot' => ['issuer' => $issuer->only(['name', 'office_name', 'phone', 'email', 'address']), 'preparation_version' => $draft->version, 'reported_on' => $draft->reported_on, 'customer_name' => $customer->name, 'booking_draft_id' => $draft->booking_draft_id,
                        'reported_account' => $draft->account_snapshot, 'posted_account' => $account->only(['code', 'name', 'type']), 'collections' => ['version' => $settings['versions']['collections'], 'values' => $collections], 'posting' => ['version' => $settings['versions']['receipt_posting'], 'values' => $posting]],
                    'version' => 1, 'accepted_by' => $fresh->id]);
                if ($recognized) {
                    $this->movement($receipt, $fresh, 'receipt_credit', $data['business_date']);
                }
                $event = $recognized ? 'accepted_recognized' : 'accepted_pending';
            } else {
                $receipt = $this->query($fresh)->lockForUpdate()->findOrFail($id);
                if ($receipt->version !== (int) $data['expected_version'] || $receipt->state === 'reversed') {
                    throw new ConflictHttpException('The receipt changed or was reversed. Reload before continuing.');
                }
                $latestDate = max($receipt->accepted_on, $receipt->recognized_on ?? '', $receipt->cleared_on ?? '');
                if ($data['business_date'] < $latestDate) {
                    throw ValidationException::withMessages(['business_date' => 'The new event cannot precede an existing receipt event.']);
                }
                if ($operation === 'clear') {
                    if ($receipt->method !== 'cheque' || $receipt->bank_stage !== 'deposited') {
                        throw new ConflictHttpException('Only a deposited, uncleared cheque can be marked cleared.');
                    }
                    $receipt->bank_stage = 'cleared';
                    $receipt->cleared_on = $data['business_date'];
                    if ($receipt->state === 'pending_clearance') {
                        $receipt->state = 'recognized';
                        $receipt->recognized_on = $data['business_date'];
                        $this->movement($receipt, $fresh, 'receipt_credit', $data['business_date']);
                    }
                    $event = 'cheque_cleared';
                } else {
                    if (bccomp(app(ReceiptCredit::class)->usedPrincipal($receipt->id, true), '0', 2) > 0) {
                        throw ValidationException::withMessages(['posting' => 'This receipt funds a confirmed booking. A linked admin booking correction is required before reversing it.']);
                    }
                    app(ChargePayments::class)->reverseReceipt($receipt->id, $fresh->id, $data['business_date']);
                    if (AccountMovement::where('payment_receipt_id', $receipt->id)->where('kind', 'receipt_credit')->lockForUpdate()->first()) {
                        $this->movement($receipt, $fresh, 'receipt_reversal', $data['business_date']);
                    }
                    $receipt->state = 'reversed';
                    $receipt->reversed_on = $data['business_date'];
                    $event = 'accounting_reversal';
                }
                $receipt->version++;
                $receipt->save();
            }
            PaymentReceiptEvent::create(['payment_receipt_id' => $receipt->id, 'actor_id' => $fresh->id, 'version' => $receipt->version, 'event' => $event,
                'details' => ['state' => $receipt->state, 'bank_stage' => $receipt->bank_stage, 'business_date' => $data['business_date'], 'amount' => $receipt->amount, 'posting_policy_version' => $settings['versions']['receipt_posting']], 'private_note' => $note]);
            DB::table('receipt_posting_commands')->insert(['actor_id' => $fresh->id, 'command_key' => $data['command_key'], 'payload_hash' => $hashes[0], 'payment_receipt_id' => $receipt->id, 'created_at' => now()]);

            return $receipt;
        }, attempts: 3);
    }

    /** @param array<string,mixed> $policy */
    private function validateDate(string $date, array $policy): void
    {
        $today = app(BusinessClock::class)->today();
        $earliest = CarbonImmutable::parse($today, 'Asia/Karachi')->subDays($policy['max_backdate_days'])->toDateString();
        if ($date > $today || $date < $policy['open_from'] || $date < $earliest) {
            throw ValidationException::withMessages(['business_date' => 'Choose a non-future Karachi business date within the configured open period and backdating limit.']);
        }
    }

    private function movement(PaymentReceipt $receipt, User $actor, string $kind, string $date): void
    {
        AccountMovement::create(['financial_account_id' => $receipt->financial_account_id, 'payment_receipt_id' => $receipt->id, 'actor_id' => $actor->id, 'kind' => $kind, 'amount' => $receipt->amount, 'business_date' => $date]);
    }

    /** @return list<string> */
    private function hashes(string $payload): array
    {
        return array_values(array_map(fn ($key) => hash_hmac('sha256', $payload, $key), [config('app.key'), ...config('app.previous_keys', [])]));
    }
}
