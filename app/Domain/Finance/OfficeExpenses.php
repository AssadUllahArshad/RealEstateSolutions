<?php

namespace App\Domain\Finance;

use App\Models\FinancialAccount;
use App\Models\OfficeExpense;
use App\Models\Society;
use App\Models\User;
use App\Rules\PositiveDecimal;
use App\Support\BusinessClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OfficeExpenses
{
    /** @return Builder<OfficeExpense> */
    public function query(User $actor): Builder
    {
        Gate::forUser($actor)->authorize('finance.expenses.view');

        return OfficeExpense::query();
    }

    /** @param array<string,mixed> $input */
    public function post(User $actor, array $input): OfficeExpense
    {
        $data = Validator::make($input, [
            'financial_account_id' => ['required', 'integer', 'min:1'], 'category' => ['required', 'string', 'max:100'],
            'amount' => ['required', new PositiveDecimal], 'business_date' => ['required', 'date_format:Y-m-d'],
            'payee' => ['nullable', 'string', 'max:160'], 'reference' => ['nullable', 'string', 'max:500'],
            'note' => ['required', 'string', 'min:10', 'max:2000'], 'command_key' => ['required', 'uuid'],
            'confirmed' => ['required', 'accepted'], 'number' => ['prohibited'], 'posted_by' => ['prohibited'],
        ])->validate();
        foreach (['category', 'payee', 'reference', 'note'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }
        if ($data['business_date'] !== app(BusinessClock::class)->today()) {
            throw ValidationException::withMessages(['business_date' => 'Office expenses currently post only on today’s Karachi business date. Backdating needs a configured close-period policy.']);
        }
        $payload = json_encode(array_diff_key($data, ['command_key' => true, 'confirmed' => true]), JSON_THROW_ON_ERROR);
        $hashes = array_map(fn ($key) => hash_hmac('sha256', $payload, $key), [config('app.key'), ...config('app.previous_keys', [])]);

        return DB::transaction(function () use ($actor, $data, $hashes) {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize('finance.expenses.post');
            abort_unless($fresh->hasAnyRole(['super_admin', 'admin', 'accountant']), 403);
            Society::whereKey(1)->lockForUpdate()->firstOrFail();
            $prior = OfficeExpense::where('posted_by', $fresh->id)->where('command_key', strtolower($data['command_key']))->lockForUpdate()->first();
            if ($prior) {
                if (! in_array($prior->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This expense command was already used with different details. Start a new entry.');
                }

                return $prior;
            }
            $account = FinancialAccount::whereKey($data['financial_account_id'])->lockForUpdate()->firstOrFail();
            if (! $account->active) {
                throw ValidationException::withMessages(['financial_account_id' => 'Select an active financial account.']);
            }
            $next = (int) OfficeExpense::query()->lockForUpdate()->max('id') + 1;

            return OfficeExpense::create([
                ...$data, 'number' => 'EXP-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT),
                'amount' => bcadd($data['amount'], '0', 2), 'account_snapshot' => $account->only(['id', 'code', 'name', 'type']),
                'posted_by' => $fresh->id, 'command_key' => strtolower($data['command_key']), 'payload_hash' => $hashes[0],
            ]);
        }, attempts: 3);
    }
}
