<?php

namespace App\Domain\Configuration;

use App\Models\BusinessPolicy;
use App\Models\BusinessPolicyEvent;
use App\Models\User;
use App\Rules\Percentage;
use App\Rules\PositiveDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BusinessPolicies
{
    public function authorize(User $actor): void
    {
        Gate::forUser($actor)->authorize('business-settings.manage');
        abort_unless($actor->hasRole('super_admin'), 403);
    }

    /** @return array<string,mixed> */
    public function defaults(string $key): array
    {
        $fields = $this->definition($key)['fields'];
        $values = [];
        foreach ($fields as $name => $field) {
            $values[$name] = $field['type'] === 'multi' ? [] : null;
        }
        // Explicitly requested plan/method choices; never monetary/date/earning defaults.
        if ($key === 'installments') {
            $values['frequencies'] = array_keys($fields['frequencies']['options']);
        }
        if ($key === 'collections') {
            $values['methods'] = array_keys($fields['methods']['options']);
        }

        return $values;
    }

    /** @return array{title:string,description:string,fields:array<string,array<string,mixed>>} */
    private function definition(string $key): array
    {
        $definitions = PolicyDefinitions::all();
        abort_unless(isset($definitions[$key]), 404);

        return $definitions[$key];
    }

    /** @param array<string,mixed> $input */
    public function save(User $actor, string $key, array $input): BusinessPolicy
    {
        $this->authorize($actor);
        $definition = $this->definition($key);
        $rules = ['version' => ['required', 'integer', 'min:0'], 'values' => ['required', 'array:'.implode(',', array_keys($definition['fields']))], 'updated_by' => ['prohibited']];
        foreach ($definition['fields'] as $name => $field) {
            $path = 'values.'.$name;
            $rules[$path] = match ($field['type']) {
                'select' => ['nullable', 'string', Rule::in(array_keys($field['options']))],
                'multi' => ['present', 'array', 'max:20'],
                'integer' => ['nullable', 'integer', 'min:0', 'max:365'],
                'percentage' => ['nullable', new Percentage],
                'money' => ['nullable', new PositiveDecimal],
                'prefix' => ['nullable', 'string', 'regex:/^[A-Z0-9][A-Z0-9-]{0,14}$/D'],
                'counter' => ['nullable', 'integer', 'min:1', 'max:999999999'],
                'width' => ['nullable', 'integer', 'min:1', 'max:12'],
                'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
                default => ['nullable', 'string', 'max:32'],
            };
            if ($field['type'] === 'multi') {
                $rules[$path.'.*'] = ['required', 'string', 'distinct', Rule::in(array_keys($field['options']))];
            }
        }
        $data = Validator::make($input, $rules)->validate();
        $values = array_replace($this->defaults($key), $data['values']);
        foreach ($definition['fields'] as $name => $field) {
            $value = $values[$name];
            if ($field['type'] === 'multi') {
                $values[$name] = array_values(array_intersect(array_keys($field['options']), $value));
            } elseif ($value !== null && $value !== '') {
                if ($field['type'] === 'percentage') {
                    $values[$name] = bcadd($value, '0', 4);
                }
                if ($field['type'] === 'money') {
                    $values[$name] = bcadd($value, '0', 2);
                }
                if (in_array($field['type'], ['integer', 'counter', 'width'], true)) {
                    $values[$name] = (int) $value;
                }
            } else {
                $values[$name] = null;
            }
        }
        $this->normalizeVariableAmount($key, $values);
        $this->validateCombinations($key, $values);

        return DB::transaction(function () use ($actor, $key, $data, $values) {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            $this->authorize($fresh);
            // Shared installation lock also serializes first saves by different main admins.
            DB::table('societies')->where('id', 1)->lockForUpdate()->firstOrFail();
            $record = BusinessPolicy::query()->whereKey($key)->lockForUpdate()->first();
            if ($key === 'receipt_posting' && $values['prefix'] && $values['starting_number']) {
                $series = DB::table('receipt_series')->where('prefix', $values['prefix'])->first();
                if ($series && (int) $series->starting_number !== $values['starting_number']) {
                    throw ValidationException::withMessages(['values.starting_number' => 'This prefix has already issued receipts. Its starting number cannot change.']);
                }
            }
            if (($record->version ?? 0) !== (int) $data['version']) {
                throw new ConflictHttpException('These settings changed in another session. Reload this section before saving again.');
            }
            if ($record) {
                $previous = $record->values;
                $comparable = $values;
                ksort($previous);
                ksort($comparable);
                if ($previous === $comparable) {
                    return $record;
                }
            }
            $before = $record?->values;
            $record ??= new BusinessPolicy(['key' => $key, 'version' => 0]);
            $record->fill(['values' => $values, 'version' => $record->version + 1, 'updated_by' => $fresh->id])->save();
            BusinessPolicyEvent::create(['policy_key' => $key, 'version' => $record->version, 'actor_id' => $fresh->id, 'before' => $before, 'after' => $values]);

            return $record;
        }, attempts: 3);
    }

    /** @param array<string,mixed> $values */
    private function normalizeVariableAmount(string $key, array &$values): void
    {
        [$field, $mode] = match ($key) {
            'booking' => ['down_payment_value', $values['down_payment_type']],
            'charges' => ['late_fee_value', $values['late_fee_mode']],
            'commissions' => ['value', $values['type']],
            default => [null, null],
        };
        if ($field === null || $values[$field] === null) {
            return;
        }
        if (! in_array($mode, ['fixed', 'percentage'], true)) {
            throw ValidationException::withMessages(['values.'.$field => 'Choose a fixed amount or percentage basis before entering a value.']);
        }
        Validator::make(['values' => [$field => $values[$field]]], ['values.'.$field => [$mode === 'percentage' ? new Percentage : new PositiveDecimal]])->validate();
        if ($key !== 'commissions' && bccomp($values[$field], '0', 4) <= 0) {
            throw ValidationException::withMessages(['values.'.$field => 'Enter a value greater than zero.']);
        }
        $values[$field] = bcadd($values[$field], '0', $mode === 'percentage' ? 4 : 2);
    }

    /** @param array<string,mixed> $values */
    private function validateCombinations(string $key, array $values): void
    {
        if ($key === 'booking' && $values['required_documents'] !== [] && ! in_array('signed_booking_form', $values['required_documents'], true)) {
            throw ValidationException::withMessages(['values.required_documents' => 'Include the signed booking form in the required document checklist.']);
        }
        if ($key === 'charges' && in_array($values['late_fee_mode'], ['disabled', 'manual'], true)) {
            foreach (['late_fee_value', 'grace_days', 'basis', 'frequency', 'cap_mode', 'cap_value'] as $name) {
                if ($values[$name] !== null) {
                    throw ValidationException::withMessages(['values.'.$name => 'Clear late-fee details when fees are disabled.']);
                }
            }
        }
        if ($key === 'charges' && $values['cap_mode'] === 'none' && $values['cap_value'] !== null) {
            throw ValidationException::withMessages(['values.cap_value' => 'Clear the cap amount when no cap is selected.']);
        }
        if (in_array($key, ['owners', 'commissions'], true) && $values['basis'] && $values['trigger']) {
            $collections = $values['basis'] === 'cleared_collections';
            if ($collections !== ($values['trigger'] === 'collection_cleared')) {
                throw ValidationException::withMessages(['values.trigger' => 'A collections basis requires the cleared-collection trigger; a contract basis requires a one-time booking/down-payment trigger.']);
            }
        }
        if ($key === 'commissions' && $values['type'] === 'fixed' && ($values['basis'] !== null || $values['trigger'] === 'collection_cleared')) {
            throw ValidationException::withMessages(['values.basis' => 'Fixed commission is per booking. Clear the percentage basis and choose a one-time earning trigger.']);
        }
    }

    /** @param array<string,mixed> $values
     * @return list<string>
     */
    public function missing(string $key, array $values): array
    {
        $fields = array_keys($this->definition($key)['fields']);
        if ($key === 'charges') {
            $fields = in_array($values['late_fee_mode'], ['disabled', 'manual'], true) ? ['late_fee_mode'] : ['late_fee_mode', 'late_fee_value', 'grace_days', 'frequency', 'cap_mode'];
            if ($values['late_fee_mode'] === 'percentage') {
                $fields[] = 'basis';
            }
            if ($values['cap_mode'] === 'fixed') {
                $fields[] = 'cap_value';
            }
        }
        if ($key === 'collections' && ! in_array('cheque', $values['methods'], true)) {
            $fields = array_values(array_diff($fields, ['cheque_recognition']));
        }
        if ($key === 'commissions' && $values['type'] === 'fixed') {
            $fields = array_values(array_diff($fields, ['basis']));
        }

        return array_values(array_filter($fields, fn ($field) => $values[$field] === null || $values[$field] === '' || $values[$field] === []));
    }
}
