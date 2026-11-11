<?php

namespace App\Domain\Finance;

use App\Models\FinancialAccount;
use App\Models\FinancialAccountOpeningBalance;
use App\Models\FinancialAccountOpeningCorrection;
use App\Models\Society;
use App\Models\User;
use App\Rules\SignedDecimal;
use App\Support\BusinessClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FinancialAccountOpenings
{
    /** @param array<string,mixed> $input */
    public function create(User $actor, int $accountId, array $input): FinancialAccountOpeningBalance
    {
        $data = Validator::make($input, [
            'amount' => ['required', new SignedDecimal],
            'effective_on' => ['required', 'date_format:Y-m-d'],
            'source_note' => ['required', 'string', 'min:10', 'max:2000'],
            'source_reference' => ['nullable', 'string', 'max:160'],
            'command_key' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted'],
            'financial_account_id' => ['prohibited'], 'created_by' => ['prohibited'],
        ])->validate();
        $today = app(BusinessClock::class)->today();
        if ($data['effective_on'] > $today) {
            throw ValidationException::withMessages(['effective_on' => 'The opening balance cannot start on a future business date.']);
        }
        $values = [
            'financial_account_id' => $accountId,
            'amount' => bcadd($data['amount'], '0', 2),
            'effective_on' => $data['effective_on'],
            'source_note' => trim($data['source_note']),
            'source_reference' => trim($data['source_reference'] ?? '') ?: null,
        ];
        if (mb_strlen($values['source_note']) < 10) {
            throw ValidationException::withMessages(['source_note' => 'Describe the verified source for this opening balance.']);
        }
        $hashes = $this->hashes($values);

        return DB::transaction(function () use ($actor, $accountId, $data, $values, $hashes): FinancialAccountOpeningBalance {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize('finance.accounts.manage');
            abort_unless($fresh->hasAnyRole(['admin', 'super_admin']), 403);
            Society::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $account = FinancialAccount::query()->whereKey($accountId)->lockForUpdate()->firstOrFail();
            $prior = FinancialAccountOpeningBalance::query()->where('created_by', $fresh->id)
                ->where('command_key', strtolower($data['command_key']))->lockForUpdate()->first();
            if ($prior) {
                if (! in_array($prior->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This opening-balance key was already used with different details.');
                }

                return $prior;
            }
            if (FinancialAccountOpeningBalance::query()->where('financial_account_id', $accountId)->lockForUpdate()->exists()) {
                throw new ConflictHttpException('An opening balance already exists for this account. Use a linked correction instead.');
            }
            $firstMovement = app(CashbookLedger::class)->firstMovementDate($accountId);
            if ($firstMovement !== null && $values['effective_on'] > $firstMovement) {
                throw ValidationException::withMessages(['effective_on' => 'The opening balance must be effective no later than the account’s first posted movement.']);
            }
            if ($account->type !== 'bank' && bccomp($values['amount'], '0', 2) < 0) {
                throw ValidationException::withMessages(['amount' => 'Negative opening balances are allowed only for bank accounts.']);
            }

            return FinancialAccountOpeningBalance::create([...$values,
                'created_by' => $fresh->id, 'command_key' => strtolower($data['command_key']), 'payload_hash' => $hashes[0]]);
        }, attempts: 3);
    }

    /** @param array<string,mixed> $input */
    public function correct(User $actor, int $openingId, array $input): FinancialAccountOpeningCorrection
    {
        $data = Validator::make($input, [
            'amount' => ['required', new SignedDecimal(false)],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'command_key' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted'],
            'opening_balance_id' => ['prohibited'], 'actor_id' => ['prohibited'], 'effective_on' => ['prohibited'],
        ])->validate();
        $today = app(BusinessClock::class)->today();
        $values = ['opening_balance_id' => $openingId, 'amount' => bcadd($data['amount'], '0', 2),
            'effective_on' => $today, 'reason' => trim($data['reason'])];
        $hashes = $this->hashes($values);

        return DB::transaction(function () use ($actor, $openingId, $data, $values, $hashes, $today): FinancialAccountOpeningCorrection {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize('finance.accounts.manage');
            abort_unless($fresh->hasAnyRole(['admin', 'super_admin']), 403);
            Society::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $opening = FinancialAccountOpeningBalance::query()->whereKey($openingId)->lockForUpdate()->firstOrFail();
            $account = FinancialAccount::query()->whereKey($opening->financial_account_id)->lockForUpdate()->firstOrFail();
            $prior = FinancialAccountOpeningCorrection::query()->where('actor_id', $fresh->id)
                ->where('command_key', strtolower($data['command_key']))->lockForUpdate()->first();
            if ($prior) {
                if (! in_array($prior->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This opening correction key was already used with different details.');
                }

                return $prior;
            }
            app(BankReconciliations::class)->assertPostingAllowed($account->id, $today);
            $currentOpening = app(CashbookLedger::class)->openingBalanceAt($account->id, $today);
            if ($currentOpening === null) {
                throw new \LogicException('The opening balance was not available for correction.');
            }
            $next = bcadd($currentOpening, $values['amount'], 2);
            if ($account->type !== 'bank' && bccomp($next, '0', 2) < 0) {
                throw ValidationException::withMessages(['amount' => 'This correction would make a non-bank account balance negative.']);
            }

            return FinancialAccountOpeningCorrection::create([...$values,
                'actor_id' => $fresh->id, 'command_key' => strtolower($data['command_key']), 'payload_hash' => $hashes[0]]);
        }, attempts: 3);
    }

    /** @param array<string,mixed> $values
     * @return list<string>
     */
    private function hashes(array $values): array
    {
        $payload = json_encode($values, JSON_THROW_ON_ERROR);

        return array_values(array_map(fn (string $key): string => hash_hmac('sha256', $payload, $key), [config('app.key'), ...config('app.previous_keys', [])]));
    }
}
