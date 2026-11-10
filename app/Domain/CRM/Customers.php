<?php

namespace App\Domain\CRM;

use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class Customers
{
    /** @return Builder<Customer> */
    public function query(User $actor): Builder
    {
        Gate::forUser($actor)->authorize('customers.view');

        return Customer::query()->when(! $actor->can('customers.view_all'), fn (Builder $query) => $query->where('assigned_to', $actor->id));
    }

    /** @param array{name:string,phone?:?string,email?:?string,assigned_to?:int,creation_key?:string,cnic?:?string,address?:?string} $data */
    public function save(User $actor, array $data, ?int $id = null): Customer
    {
        return DB::transaction(function () use ($actor, $data, $id) {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize('customers.manage');
            Gate::forUser($fresh)->authorize('customers.view');
            if (! $id) {
                Validator::make($data, ['creation_key' => ['required', 'uuid']])->validate();
            }
            $customer = $id ? $this->query($fresh)->lockForUpdate()->findOrFail($id) : new Customer;
            $assignee = $fresh->can('customers.assign') ? ($data['assigned_to'] ?? $customer->assigned_to ?? $fresh->id) : $fresh->id;
            $values = ['name' => $data['name'], 'phone' => $data['phone'] ?? null, 'email' => $data['email'] ?? null, 'assigned_to' => $assignee];
            $identity = Validator::make($data, ['cnic' => ['nullable', 'string', 'regex:/^(?:[0-9]{13}|[0-9]{5}-[0-9]{7}-[0-9])$/D'], 'address' => ['nullable', 'string', 'max:1000']])->validate();
            foreach (['cnic', 'address'] as $field) {
                if (array_key_exists($field, $identity)) {
                    $values[$field] = $field === 'cnic' && $identity[$field] !== null ? str_replace('-', '', $identity[$field]) : $identity[$field];
                }
            }
            if (! $id) {
                $payload = json_encode($values, JSON_THROW_ON_ERROR);
                $hashes = $identity === [] ? [hash('sha256', $payload)] : array_map(fn (string $key) => hash_hmac('sha256', $payload, $key), [config('app.key'), ...config('app.previous_keys', [])]);
                $hash = $hashes[0];
                $existing = Customer::where('created_by', $fresh->id)->where('creation_key', $data['creation_key'] ?? null)->first();
                if ($existing) {
                    abort_unless($this->query($fresh)->whereKey($existing->id)->exists(), 404);
                    if (! in_array($existing->creation_hash, $hashes, true)) {
                        throw new ConflictHttpException('This form was already submitted with different details. Refresh before starting another record.');
                    }

                    return $existing;
                }
                $customer->fill(['created_by' => $fresh->id, 'creation_key' => $data['creation_key'] ?? null, 'creation_hash' => $hash]);
            } elseif (! $fresh->can('customers.assign')) {
                $values['assigned_to'] = $customer->assigned_to;
            }
            $previousAssignee = $customer->exists ? $customer->assigned_to : null;
            $customer->fill($values);
            $changed = array_keys($customer->getDirty());
            if ($id && ! $customer->isDirty()) {
                return $customer;
            }
            $customer->save();
            $this->activity($fresh, $customer->id, $id ? 'customer.updated' : 'customer.created', ['fields' => array_values(array_intersect($changed, ['name', 'phone', 'email', 'assigned_to', 'cnic', 'address'])), 'assigned_from' => $previousAssignee, 'assigned_to' => $customer->assigned_to]);

            return $customer;
        }, attempts: 3);
    }

    /** @param array<string,mixed> $metadata */
    public function activity(User $actor, int $customerId, string $event, array $metadata = []): void
    {
        CustomerActivity::create(['customer_id' => $customerId, 'actor_id' => $actor->id, 'event' => $event, 'metadata' => $metadata]);
    }
}
