<?php

namespace App\Domain\Sales;

use App\Domain\Configuration\BusinessPolicies;
use App\Domain\Configuration\PolicyDefinitions;
use App\Domain\CRM\Customers;
use App\Models\Booking;
use App\Models\BusinessPolicy;
use App\Models\DraftTerm;
use App\Models\DraftTermEvent;
use App\Models\User;
use App\Rules\PositiveDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DraftTerms
{
    public function canDiscount(User $actor): bool
    {
        return $actor->can('sales.discounts') && $actor->hasAnyRole(['admin', 'super_admin']);
    }

    /** @return array<string,mixed> */
    public function context(User $actor, int $id): array
    {
        $draft = app(BookingDrafts::class)->query($actor)->findOrFail($id);
        $policies = BusinessPolicy::whereIn('key', ['booking', 'installments'])->get()->keyBy('key');
        $setup = app(BusinessPolicies::class);
        $booking = $setup->effective('booking', $policies->get('booking')?->values);
        $installments = $setup->effective('installments', $policies->get('installments')?->values);
        $term = DraftTerm::where('booking_draft_id', $id)->first();
        $versions = ['booking' => $policies->get('booking')->version ?? 0, 'installments' => $policies->get('installments')->version ?? 0];

        return ['draft' => $draft->load('customer:id,name', 'plot.block:id,code'), 'term' => $term,
            'canDiscount' => $this->canDiscount($actor), 'policies' => ['booking' => $booking, 'installments' => $installments], 'policyVersions' => $versions,
            'missingSetup' => array_merge(array_map(fn ($key) => PolicyDefinitions::all()['booking']['fields'][$key]['label'], $setup->missing('booking', $booking)), array_map(fn ($key) => PolicyDefinitions::all()['installments']['fields'][$key]['label'], $setup->missing('installments', $installments))),
            'stale' => $term && ($term->snapshot['source_version'] !== $draft->version || $term->snapshot['policy_versions'] !== $versions),
        ];
    }

    /** @param array<string,mixed> $input */
    public function save(User $actor, int $id, array $input): DraftTerm
    {
        // Ignore hidden fields from a previously selected plan type; never persist them.
        if (($input['frequency'] ?? null) !== 'custom') {
            $input['custom'] = [];
        }
        if (in_array($input['frequency'] ?? null, ['custom', 'full'], true)) {
            $input['count'] = null;
            $input['first_due_date'] = null;
        }
        $data = Validator::make($input, [
            'version' => ['required', 'integer', 'min:0'], 'source_version' => ['required', 'integer', 'min:1'],
            'policy_versions' => ['required', 'array:booking,installments'], 'policy_versions.booking' => ['required', 'integer', 'min:0'], 'policy_versions.installments' => ['required', 'integer', 'min:0'],
            'discount' => ['nullable', 'string', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/D'],
            'discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'discount_value' => ['nullable', 'string', 'regex:/^\d{1,16}(?:\.\d{1,4})?$/D'],
            'discount_reason' => ['nullable', 'string', 'max:500'],
            'down_payment' => ['required', new PositiveDecimal],
            'frequency' => ['required', Rule::in(['full', 'monthly', 'quarterly', 'half_yearly', 'yearly', 'custom'])],
            'count' => ['nullable', 'integer', 'min:1', 'max:360'], 'first_due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'custom' => ['present', 'array', 'max:360'], 'custom.*' => ['array:due_date,amount'], 'custom.*.due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'], 'custom.*.amount' => ['required', new PositiveDecimal],
            'gross' => ['prohibited'], 'net' => ['prohibited'], 'schedule' => ['prohibited'], 'updated_by' => ['prohibited'],
        ])->validate();
        $data['discount_type'] = $data['discount_type'] ?? 'fixed';
        $data['discount_value'] = $data['discount_value'] ?? $data['discount'] ?? '0';
        if ($data['discount_type'] === 'percentage' && bccomp($data['discount_value'], '100', 4) > 0) {
            throw ValidationException::withMessages(['discount_value' => 'Discount percentage cannot exceed 100.']);
        }
        $data['down_payment'] = bcadd($data['down_payment'], '0', 2);
        $data['discount_reason'] = trim($data['discount_reason'] ?? '');
        $data['count'] = isset($data['count']) ? (int) $data['count'] : null;
        foreach ($data['custom'] as &$row) {
            $row['amount'] = bcadd($row['amount'], '0', 2);
        }
        unset($row);
        if ($data['frequency'] === 'custom' && $data['custom'] === []) {
            throw ValidationException::withMessages(['custom' => 'Add at least one custom installment.']);
        }
        if (! in_array($data['frequency'], ['custom', 'full'], true) && (empty($data['count']) || empty($data['first_due_date']))) {
            throw ValidationException::withMessages(['plan' => 'Enter the installment count and first due date.']);
        }
        if ($data['frequency'] !== 'custom') {
            $data['custom'] = [];
        }
        if (in_array($data['frequency'], ['custom', 'full'], true)) {
            $data['count'] = null;
            $data['first_due_date'] = null;
        }

        return DB::transaction(function () use ($actor, $id, $data) {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            $visible = app(BookingDrafts::class)->query($fresh)->findOrFail($id);
            DB::table('societies')->where('id', 1)->lockForUpdate()->firstOrFail();
            app(Customers::class)->query($fresh)->whereKey($visible->customer_id)->lockForUpdate()->firstOrFail();
            $draft = app(BookingDrafts::class)->query($fresh)->lockForUpdate()->findOrFail($id);
            if (Booking::where('booking_draft_id', $id)->lockForUpdate()->first()) {
                throw new ConflictHttpException('Confirmed contract terms cannot be replaced through a proposal.');
            }
            if ($draft->version !== (int) $data['source_version'] || $draft->customer_id !== $visible->customer_id) {
                throw new ConflictHttpException('The proposal changed. Reload before preparing its terms.');
            }
            $records = BusinessPolicy::whereIn('key', ['booking', 'installments'])->orderBy('key')->lockForUpdate()->get()->keyBy('key');
            $effective = [];
            foreach (['booking', 'installments'] as $key) {
                $policy = $records->get($key);
                $currentVersion = $records->has($key) ? $policy->version : 0;
                if ($currentVersion !== (int) $data['policy_versions'][$key]) {
                    throw new ConflictHttpException('Business settings changed. Reload and review the current rules.');
                }
                $effective[$key] = app(BusinessPolicies::class)->effective($key, $policy?->values);
                if (app(BusinessPolicies::class)->missing($key, $effective[$key]) !== []) {
                    throw ValidationException::withMessages(['plan' => 'Booking rules are unavailable. Ask the main admin to review Business settings.']);
                }
            }
            $data['discount'] = $data['discount_type'] === 'percentage'
                ? app(InstallmentCalculator::class)->round(bcdiv(bcmul($data['down_payment'], $data['discount_value'], 6), '100', 8), $effective['installments']['rounding'])
                : bcadd($data['discount_value'], '0', 2);
            $term = DraftTerm::where('booking_draft_id', $id)->lockForUpdate()->first();
            $old = $term->snapshot ?? [];
            $discountChanged = ($old['input']['discount'] ?? '0.00') !== $data['discount']
                || ($old['input']['discount_type'] ?? 'fixed') !== $data['discount_type']
                || ($old['input']['discount_value'] ?? $old['input']['discount'] ?? '0.00') !== $data['discount_value']
                || ($old['input']['discount_reason'] ?? '') !== $data['discount_reason'];
            $discountRebased = bccomp($data['discount'], '0', 2) > 0 && ($old['gross'] ?? null) !== $draft->quoted_price;
            if (($discountChanged || $discountRebased) && ! $this->canDiscount($fresh)) {
                abort(403, 'Only an authorized admin may set, remove or rebase a discount.');
            }
            if (bccomp($data['discount'], '0', 2) > 0 && $data['discount_reason'] === '') {
                throw ValidationException::withMessages(['discount_reason' => 'Record the reason for this admin discount.']);
            }
            $calculated = app(InstallmentCalculator::class)->calculate($draft->quoted_price, $data, $effective['booking'], $effective['installments']);
            $snapshot = [...$calculated, 'source_version' => $draft->version,
                'policy_versions' => [
                    'booking' => $records->has('booking') ? $records->get('booking')->version : 0,
                    'installments' => $records->has('installments') ? $records->get('installments')->version : 0,
                ],
                'policy_snapshot' => $effective,
                'discount_authorized_by' => ($discountChanged || $discountRebased) ? $fresh->id : ($old['discount_authorized_by'] ?? null),
                'input' => array_intersect_key($data, array_flip(['discount', 'discount_type', 'discount_value', 'discount_reason', 'down_payment', 'frequency', 'count', 'first_due_date', 'custom'])),
            ];
            // MySQL JSON object key order is not insertion order; compare structures recursively.
            if ($term && $this->canonical($term->snapshot) === $this->canonical($snapshot)) {
                return $term;
            }
            if (($term->version ?? 0) !== (int) $data['version']) {
                throw new ConflictHttpException('Prepared terms changed in another session. Reload before saving.');
            }
            $term ??= new DraftTerm(['booking_draft_id' => $id, 'version' => 0]);
            $term->fill(['version' => $term->version + 1, 'snapshot' => $snapshot, 'updated_by' => $fresh->id])->save();
            DraftTermEvent::create(['booking_draft_id' => $id, 'version' => $term->version, 'actor_id' => $fresh->id, 'snapshot' => $snapshot]);

            return $term;
        }, attempts: 3);
    }

    /** @param array<mixed> $value
     * @return array<mixed>
     */
    private function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->canonical($item);
            }
        }

        return $value;
    }
}
