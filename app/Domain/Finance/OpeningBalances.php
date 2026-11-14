<?php

namespace App\Domain\Finance;

use App\Models\BusinessPolicy;
use App\Models\OpeningBalanceActivation;
use App\Models\OpeningCommissionBalance;
use App\Models\OpeningCustomerBalance;
use App\Models\OpeningOwnerBalance;
use App\Models\Society;
use App\Models\User;
use App\Rules\SignedDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OpeningBalances
{
    public function cutoverDate(): ?string
    {
        $policy = BusinessPolicy::whereKey('cutover')->first();

        return $policy?->values['cutover_date'] ?? null;
    }

    /** @param array<string,mixed> $input */
    public function createCustomer(User $actor, array $input): OpeningCustomerBalance
    {
        $data = Validator::make($input, [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'amount' => ['required', new SignedDecimal(false)],
            'effective_on' => ['required', 'date_format:Y-m-d'],
            'source_note' => ['required', 'string', 'min:10', 'max:2000'],
            'source_reference' => ['nullable', 'string', 'max:160'],
            'command_key' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted'],
            'created_by' => ['prohibited'],
        ])->validate();
        $this->assertEffectiveOnCutover($data['effective_on']);
        $values = [
            'customer_id' => (int) $data['customer_id'],
            'booking_id' => isset($data['booking_id']) ? (int) $data['booking_id'] : null,
            'amount' => bcadd($data['amount'], '0', 2),
            'effective_on' => $data['effective_on'],
            'source_note' => trim($data['source_note']),
            'source_reference' => trim($data['source_reference'] ?? '') ?: null,
        ];
        $hashes = $this->hashes($values);

        return DB::transaction(function () use ($actor, $data, $values, $hashes): OpeningCustomerBalance {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize('finance.accounts.manage');
            abort_unless($fresh->hasAnyRole(['admin', 'super_admin']), 403);
            Society::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $prior = OpeningCustomerBalance::query()->where('created_by', $fresh->id)
                ->where('command_key', strtolower($data['command_key']))->lockForUpdate()->first();
            if ($prior) {
                if (! in_array($prior->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This opening-balance key was already used with different details.');
                }

                return $prior;
            }

            return OpeningCustomerBalance::create([...$values,
                'created_by' => $fresh->id, 'command_key' => strtolower($data['command_key']), 'payload_hash' => $hashes[0]]);
        }, attempts: 3);
    }

    /** @param array<string,mixed> $input */
    public function createOwner(User $actor, array $input): OpeningOwnerBalance
    {
        $data = Validator::make($input, [
            'plot_owner_id' => ['required', 'integer', 'exists:plot_owners,id'],
            'amount' => ['required', new SignedDecimal(false)],
            'effective_on' => ['required', 'date_format:Y-m-d'],
            'source_note' => ['required', 'string', 'min:10', 'max:2000'],
            'source_reference' => ['nullable', 'string', 'max:160'],
            'command_key' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted'],
            'created_by' => ['prohibited'],
        ])->validate();
        $this->assertEffectiveOnCutover($data['effective_on']);
        $values = [
            'plot_owner_id' => (int) $data['plot_owner_id'],
            'amount' => bcadd($data['amount'], '0', 2),
            'effective_on' => $data['effective_on'],
            'source_note' => trim($data['source_note']),
            'source_reference' => trim($data['source_reference'] ?? '') ?: null,
        ];
        $hashes = $this->hashes($values);

        return DB::transaction(function () use ($actor, $data, $values, $hashes): OpeningOwnerBalance {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize('finance.accounts.manage');
            abort_unless($fresh->hasAnyRole(['admin', 'super_admin']), 403);
            Society::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $prior = OpeningOwnerBalance::query()->where('created_by', $fresh->id)
                ->where('command_key', strtolower($data['command_key']))->lockForUpdate()->first();
            if ($prior) {
                if (! in_array($prior->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This opening-balance key was already used with different details.');
                }

                return $prior;
            }

            return OpeningOwnerBalance::create([...$values,
                'created_by' => $fresh->id, 'command_key' => strtolower($data['command_key']), 'payload_hash' => $hashes[0]]);
        }, attempts: 3);
    }

    /** @param array<string,mixed> $input */
    public function createCommission(User $actor, array $input): OpeningCommissionBalance
    {
        $data = Validator::make($input, [
            'agent_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', new SignedDecimal(false)],
            'effective_on' => ['required', 'date_format:Y-m-d'],
            'source_note' => ['required', 'string', 'min:10', 'max:2000'],
            'source_reference' => ['nullable', 'string', 'max:160'],
            'command_key' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted'],
            'created_by' => ['prohibited'],
        ])->validate();
        $this->assertEffectiveOnCutover($data['effective_on']);
        $values = [
            'agent_id' => (int) $data['agent_id'],
            'amount' => bcadd($data['amount'], '0', 2),
            'effective_on' => $data['effective_on'],
            'source_note' => trim($data['source_note']),
            'source_reference' => trim($data['source_reference'] ?? '') ?: null,
        ];
        $hashes = $this->hashes($values);

        return DB::transaction(function () use ($actor, $data, $values, $hashes): OpeningCommissionBalance {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize('finance.accounts.manage');
            abort_unless($fresh->hasAnyRole(['admin', 'super_admin']), 403);
            Society::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $prior = OpeningCommissionBalance::query()->where('created_by', $fresh->id)
                ->where('command_key', strtolower($data['command_key']))->lockForUpdate()->first();
            if ($prior) {
                if (! in_array($prior->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This opening-balance key was already used with different details.');
                }

                return $prior;
            }

            return OpeningCommissionBalance::create([...$values,
                'created_by' => $fresh->id, 'command_key' => strtolower($data['command_key']), 'payload_hash' => $hashes[0]]);
        }, attempts: 3);
    }

    /** @param array<string,mixed> $input */
    public function activate(User $actor, string $kind, int $openingId, array $input): OpeningBalanceActivation
    {
        $data = Validator::make($input, [
            'command_key' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted'],
            'kind' => ['prohibited'], 'opening_id' => ['prohibited'], 'actor_id' => ['prohibited'],
        ])->validate();
        if (! in_array($kind, ['customer', 'owner', 'commission'], true)) {
            throw ValidationException::withMessages(['kind' => 'Choose a supported opening balance kind.']);
        }
        $values = ['kind' => $kind, 'opening_id' => $openingId];
        $hashes = $this->hashes($values);

        return DB::transaction(function () use ($actor, $kind, $openingId, $data, $values, $hashes): OpeningBalanceActivation {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize('finance.accounts.manage');
            abort_unless($fresh->hasAnyRole(['admin', 'super_admin']), 403);
            Society::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $prior = OpeningBalanceActivation::query()->where('actor_id', $fresh->id)
                ->where('command_key', strtolower($data['command_key']))->lockForUpdate()->first();
            if ($prior) {
                if (! in_array($prior->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This activation key was already used with different details.');
                }

                return $prior;
            }
            $exists = match ($kind) {
                'customer' => OpeningCustomerBalance::whereKey($openingId)->lockForUpdate()->exists(),
                'owner' => OpeningOwnerBalance::whereKey($openingId)->lockForUpdate()->exists(),
                'commission' => OpeningCommissionBalance::whereKey($openingId)->lockForUpdate()->exists(),
            };
            if (! $exists) {
                throw ValidationException::withMessages(['opening_id' => 'The opening balance does not exist.']);
            }
            if (OpeningBalanceActivation::where('kind', $kind)->where('opening_id', $openingId)->lockForUpdate()->exists()) {
                throw new ConflictHttpException('This opening balance has already been activated.');
            }

            return OpeningBalanceActivation::create([...$values,
                'actor_id' => $fresh->id, 'command_key' => strtolower($data['command_key']), 'payload_hash' => $hashes[0]]);
        }, attempts: 3);
    }

    private function assertEffectiveOnCutover(string $effectiveOn): void
    {
        $cutover = $this->cutoverDate();
        if ($cutover === null) {
            throw ValidationException::withMessages(['effective_on' => 'Set the legacy cutover date in business settings before recording opening balances.']);
        }
        if ($effectiveOn !== $cutover) {
            throw ValidationException::withMessages(['effective_on' => 'Opening balances must be effective on the cutover date ('.$cutover.').']);
        }
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
