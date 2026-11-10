<?php

namespace App\Domain\Finance;

use App\Models\BookingDraft;
use App\Models\BusinessPolicy;
use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\PaymentReceipt;
use App\Models\ReceiptDraft;
use App\Models\ReceiptDraftEvent;
use App\Models\User;
use App\Rules\PositiveDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReceiptDrafts
{
    public function wideAccess(User $actor): bool
    {
        return $actor->can('finance.receipts.view_all') && $actor->hasAnyRole(['super_admin', 'admin', 'accountant']);
    }

    /** Finance only uses customer ID/name projections, never grants full CRM identity access.
     * @return Builder<Customer>
     */
    public function customers(User $actor): Builder
    {
        Gate::forUser($actor)->authorize('finance.receipts.view');

        return Customer::query()->when(! $this->wideAccess($actor), fn ($query) => $query->where('assigned_to', $actor->id));
    }

    /** @return Builder<ReceiptDraft> */
    public function query(User $actor): Builder
    {
        return ReceiptDraft::whereIn('customer_id', $this->customers($actor)->select('id'));
    }

    /** @param array<string,mixed> $input */
    public function save(User $actor, array $input, ?int $id = null): ReceiptDraft
    {
        $data = Validator::make($input, [
            'customer_id' => ['required', 'integer'], 'financial_account_id' => ['required', 'integer'], 'booking_draft_id' => ['nullable', 'integer'],
            'amount' => ['required', new PositiveDecimal], 'reported_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'method' => ['required', Rule::in(['cash', 'bank_transfer', 'cheque', 'mobile_wallet', 'other'])],
            'reference' => ['nullable', 'string', 'max:160'], 'notes' => ['nullable', 'string', 'max:1000'],
            'policy_version' => ['required', 'integer', 'min:1'], 'version' => [$id ? 'required' : 'prohibited', 'integer', 'min:1'],
            'creation_key' => [$id ? 'prohibited' : 'required', 'uuid'], 'created_by' => ['prohibited'], 'status' => ['prohibited'], 'recognized_at' => ['prohibited'], 'receipt_number' => ['prohibited'],
        ])->validate();
        $values = ['customer_id' => (int) $data['customer_id'], 'financial_account_id' => (int) $data['financial_account_id'], 'booking_draft_id' => isset($data['booking_draft_id']) ? (int) $data['booking_draft_id'] : null,
            'amount' => bcadd($data['amount'], '0', 2), 'reported_on' => $data['reported_on'], 'method' => $data['method'], 'reference' => trim($data['reference'] ?? ''), 'notes' => trim($data['notes'] ?? '')];
        if ($values['method'] !== 'cash' && $values['reference'] === '') {
            throw ValidationException::withMessages(['reference' => 'Enter the external payment or instrument reference for this method.']);
        }
        $encoded = json_encode([...$values, 'policy_version' => (int) $data['policy_version']], JSON_THROW_ON_ERROR);
        $hashes = array_map(fn ($key) => hash_hmac('sha256', $encoded, $key), [config('app.key'), ...config('app.previous_keys', [])]);

        return DB::transaction(function () use ($actor, $id, $data, $values, $hashes) {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize('finance.receipts.prepare');
            if ($id) {
                $visible = $this->query($fresh)->findOrFail($id);
                if ($visible->customer_id !== $values['customer_id']) {
                    throw ValidationException::withMessages(['customer_id' => 'The customer cannot change on an existing preparation. Start a separate record.']);
                }
            }
            $this->customers($fresh)->whereKey($values['customer_id'])->lockForUpdate()->firstOrFail();
            if (! $id) {
                $existing = ReceiptDraft::where('created_by', $fresh->id)->where('creation_key', $data['creation_key'])->first();
                if ($existing) {
                    $this->query($fresh)->findOrFail($existing->id);
                    if (! in_array($existing->creation_hash, $hashes, true)) {
                        throw new ConflictHttpException('This receipt form was already saved with different details. Start a new preparation.');
                    }

                    return $existing;
                }
            }
            $account = FinancialAccount::query()->whereKey($values['financial_account_id'])->lockForUpdate()->firstOrFail();
            if (! $account->active) {
                throw ValidationException::withMessages(['financial_account_id' => 'Choose an active financial account.']);
            }
            $policy = BusinessPolicy::query()->whereKey('collections')->lockForUpdate()->first();
            if (! $policy || $policy->version !== (int) $data['policy_version']) {
                throw new ConflictHttpException('Payment settings changed or are missing. Reload before saving.');
            }
            if (! in_array($values['method'], $policy->values['methods'] ?? [], true)) {
                throw ValidationException::withMessages(['method' => 'This payment method is not enabled in Business settings.']);
            }
            if ($values['booking_draft_id']) {
                BookingDraft::whereKey($values['booking_draft_id'])->where('customer_id', $values['customer_id'])->lockForUpdate()->firstOrFail();
            }
            $receipt = $id ? $this->query($fresh)->lockForUpdate()->findOrFail($id) : new ReceiptDraft;
            if ($id && PaymentReceipt::where('receipt_draft_id', $id)->lockForUpdate()->first()) {
                throw new ConflictHttpException('This preparation has an issued receipt and is frozen. Use the posted receipt workflow.');
            }
            if ($id && $receipt->version !== (int) $data['version']) {
                throw new ConflictHttpException('This preparation changed in another session. Reload before saving.');
            }
            $changed = [];
            foreach ($values as $field => $value) {
                if (! $id || $receipt->{$field} !== $value) {
                    $changed[] = $field;
                }
            }
            $snapshot = ['version' => $policy->version, 'values' => $policy->values];
            $samePolicy = $id && $receipt->policy_snapshot['version'] === $policy->version;
            if ($id && $changed === [] && $samePolicy) {
                return $receipt;
            }
            if (! $id || $receipt->financial_account_id !== $account->id) {
                $receipt->account_snapshot = $account->only(['code', 'name', 'type']);
            }
            $receipt->fill([...$values, 'policy_snapshot' => $snapshot, 'version' => $id ? $receipt->version + 1 : 1]);
            if (! $id) {
                $receipt->fill(['created_by' => $fresh->id, 'creation_key' => $data['creation_key'], 'creation_hash' => $hashes[0]]);
            }
            $receipt->save();
            ReceiptDraftEvent::create(['receipt_draft_id' => $receipt->id, 'actor_id' => $fresh->id, 'version' => $receipt->version, 'details' => [
                'changed_fields' => $changed, 'amount' => $receipt->amount, 'reported_on' => $receipt->reported_on, 'method' => $receipt->method,
                'financial_account_id' => $account->id, 'booking_draft_id' => $receipt->booking_draft_id, 'policy_version' => $policy->version,
            ]]);

            return $receipt;
        }, attempts: 3);
    }
}
