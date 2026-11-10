<?php

namespace App\Domain\Finance;

use App\Models\FinancialAccount;
use App\Models\FinancialAccountEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class Accounts
{
    /** @param array<string,mixed> $input */
    public function save(User $actor, array $input, ?int $id = null): FinancialAccount
    {
        $data = Validator::make($input, [
            'code' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]{0,29}$/D'], 'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['cash', 'bank', 'wallet', 'other'])], 'active' => ['required', 'boolean'],
            'version' => [$id ? 'required' : 'prohibited', 'integer', 'min:1'], 'reason' => [$id ? 'required' : 'nullable', 'string', 'max:500'],
            'creation_key' => [$id ? 'prohibited' : 'required', 'uuid'], 'balance' => ['prohibited'], 'created_by' => ['prohibited'],
        ])->validate();
        $values = ['code' => strtoupper($data['code']), 'name' => trim($data['name']), 'type' => $data['type'], 'active' => (bool) $data['active']];
        if ($values['name'] === '' || ($id && trim($data['reason']) === '')) {
            throw ValidationException::withMessages(['name' => 'Enter an account name and a reason for changes.']);
        }
        $hash = hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $id, $data, $values, $hash) {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize('finance.accounts.manage');
            abort_unless($fresh->hasAnyRole(['super_admin', 'admin']), 403);
            DB::table('societies')->where('id', 1)->lockForUpdate()->firstOrFail();
            if (! $id) {
                $existing = FinancialAccount::where('created_by', $fresh->id)->where('creation_key', $data['creation_key'])->first();
                if ($existing) {
                    if ($existing->creation_hash !== $hash) {
                        throw new ConflictHttpException('This account form was already saved with different values. Start a new form.');
                    }

                    return $existing;
                }
            }
            $account = $id ? FinancialAccount::query()->lockForUpdate()->findOrFail($id) : new FinancialAccount;
            if ($id && $account->version !== (int) $data['version']) {
                throw new ConflictHttpException('This account changed in another session. Reload before saving.');
            }
            if ($id && ($account->code !== $values['code'] || $account->type !== $values['type'])) {
                throw ValidationException::withMessages(['code' => 'Account code and type cannot change. Create a separate account instead.']);
            }
            if (FinancialAccount::where('code', $values['code'])->when($id, fn ($query) => $query->where('id', '<>', $id))->exists()) {
                throw ValidationException::withMessages(['code' => 'This account code is already in use.']);
            }
            $before = $id ? $account->only(['code', 'name', 'type', 'active']) : null;
            $account->fill($values);
            if ($id && ! $account->isDirty()) {
                return $account;
            }
            $account->version = $id ? $account->version + 1 : 1;
            if (! $id) {
                $account->fill(['created_by' => $fresh->id, 'creation_key' => $data['creation_key'], 'creation_hash' => $hash]);
            }
            $account->save();
            FinancialAccountEvent::create(['financial_account_id' => $account->id, 'actor_id' => $fresh->id, 'version' => $account->version, 'details' => ['before' => $before, 'after' => $values, 'reason' => trim($data['reason'] ?? '')]]);

            return $account;
        }, attempts: 3);
    }
}
