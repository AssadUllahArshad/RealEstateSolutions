<?php

namespace App\Http\Controllers;

use App\Domain\Configuration\BusinessPolicies;
use App\Domain\Configuration\PolicyDefinitions;
use App\Domain\Sales\InstallmentCalculator;
use App\Models\BusinessPolicy;
use App\Rules\Percentage;
use App\Rules\PositiveDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CalculatorController extends Controller
{
    public function show(Request $request): Response
    {
        abort_unless($request->user()->can('sales.drafts'), 403);

        return Inertia::render('calculator', $this->context());
    }

    public function calculate(Request $request, InstallmentCalculator $calculator): Response|RedirectResponse
    {
        abort_unless($request->user()->can('sales.drafts'), 403);
        $discountRules = $request->input('discount_type') === 'percentage'
            ? ['required', 'string', new Percentage]
            : ['required', 'string', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/D'];
        $data = Validator::make($request->all(), [
            'gross' => ['required', new PositiveDecimal],
            'discount_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'discount_value' => $discountRules,
            'down_payment' => ['required', new PositiveDecimal],
            'frequency' => ['required', Rule::in(['full', 'monthly', 'quarterly', 'half_yearly', 'yearly', 'custom'])],
            'count' => ['nullable', 'integer', 'min:1', 'max:360'],
            'first_due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'custom' => ['present', 'array', 'max:360'],
            'custom.*' => ['array:due_date,amount'],
            'custom.*.due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'custom.*.amount' => ['required', new PositiveDecimal],
        ])->validate();
        if (($data['frequency'] ?? null) !== 'custom') {
            $data['custom'] = [];
        }
        if (in_array($data['frequency'], ['custom', 'full'], true)) {
            $data['count'] = null;
            $data['first_due_date'] = null;
        }
        if ($data['frequency'] === 'custom' && $data['custom'] === []) {
            return back()->withErrors(['custom' => 'Add at least one custom installment.']);
        }
        if (! in_array($data['frequency'], ['custom', 'full'], true) && (empty($data['count']) || empty($data['first_due_date']))) {
            return back()->withErrors(['plan' => 'Enter the installment count and first due date.']);
        }

        $setup = app(BusinessPolicies::class);
        $policies = BusinessPolicy::whereIn('key', ['booking', 'installments', 'commissions'])->get()->keyBy('key');
        $booking = $setup->effective('booking', $policies->get('booking')?->values);
        $installments = $setup->effective('installments', $policies->get('installments')?->values);
        $commissions = array_replace($setup->defaults('commissions'), $policies->get('commissions')->values ?? []);
        $missing = array_merge(
            array_map(fn ($key) => PolicyDefinitions::all()['booking']['fields'][$key]['label'], $setup->missing('booking', $booking)),
            array_map(fn ($key) => PolicyDefinitions::all()['installments']['fields'][$key]['label'], $setup->missing('installments', $installments)),
        );
        if ($missing !== []) {
            return back()->withErrors(['plan' => 'Main admin must complete booking and installment settings first.']);
        }

        $gross = bcadd($data['gross'], '0', 2);
        $plannedDownPayment = bcadd($data['down_payment'], '0', 2);
        $discount = $data['discount_type'] === 'percentage'
            ? $calculator->round(bcdiv(bcmul($plannedDownPayment, $data['discount_value'], 6), '100', 8), $installments['rounding'])
            : bcadd($data['discount_value'], '0', 2);
        $input = [
            'discount' => $discount,
            'down_payment' => $plannedDownPayment,
            'frequency' => $data['frequency'],
            'count' => isset($data['count']) ? (int) $data['count'] : null,
            'first_due_date' => $data['first_due_date'] ?? null,
            'custom' => array_map(fn ($row) => ['due_date' => $row['due_date'], 'amount' => bcadd($row['amount'], '0', 2)], $data['custom']),
        ];
        $calculated = $calculator->calculate($gross, $input, $booking, $installments);
        $result = [...$calculated, 'discount_type' => $data['discount_type'], 'discount_value' => $data['discount_value'],
            'commission' => $this->commissionPreview($commissions, $calculated['net'], $setup),
            'policy_versions' => ['booking' => $policies->get('booking')->version ?? 0, 'installments' => $policies->get('installments')->version ?? 0, 'commissions' => $policies->get('commissions')->version ?? 0]];

        return Inertia::render('calculator', [...$this->context(), 'result' => $result, 'input' => $data]);
    }

    /** @return array<string,mixed> */
    private function context(): array
    {
        $setup = app(BusinessPolicies::class);
        $policies = BusinessPolicy::whereIn('key', ['booking', 'installments', 'commissions'])->get()->keyBy('key');
        $booking = $setup->effective('booking', $policies->get('booking')?->values);
        $installments = $setup->effective('installments', $policies->get('installments')?->values);
        $commissions = array_replace($setup->defaults('commissions'), $policies->get('commissions')->values ?? []);

        return [
            'policies' => ['booking' => $booking, 'installments' => $installments, 'commissions' => $commissions],
            'missingSetup' => array_merge(
                array_map(fn ($key) => PolicyDefinitions::all()['booking']['fields'][$key]['label'], $setup->missing('booking', $booking)),
                array_map(fn ($key) => PolicyDefinitions::all()['installments']['fields'][$key]['label'], $setup->missing('installments', $installments)),
            ),
            'commissionMissing' => $setup->missing('commissions', $commissions) !== [],
            'result' => null,
            'input' => null,
        ];
    }

    /** @param array<string,mixed> $commissions
     * @param  numeric-string  $net
     * @return array{amount:?string,formula:string,note:string}
     */
    private function commissionPreview(array $commissions, string $net, BusinessPolicies $setup): array
    {
        if ($setup->missing('commissions', $commissions) !== []) {
            return ['amount' => null, 'formula' => 'Not configured', 'note' => 'Main admin must complete agent commission settings before a preview is available.'];
        }
        if ($commissions['type'] === 'fixed') {
            return ['amount' => bcadd($commissions['value'], '0', 2), 'formula' => 'Fixed PKR '.bcadd($commissions['value'], '0', 2).' per booking', 'note' => 'Earned on '.$commissions['trigger'].'; estimate only.'];
        }
        if ($commissions['basis'] === 'net_contract') {
            $raw = bcdiv(bcmul($net, $commissions['value'], 6), '100', 6);
            $amount = bcdiv(bcadd($raw, '0.005000', 6), '1', 2);

            return ['amount' => $amount, 'formula' => 'Net price × '.$commissions['value'].'%', 'note' => 'Earned on '.$commissions['trigger'].'; estimate only.'];
        }

        return ['amount' => null, 'formula' => $commissions['value'].'% of each cleared collection', 'note' => 'Accrues per cleared collection; there is no single preview amount.'];
    }
}
