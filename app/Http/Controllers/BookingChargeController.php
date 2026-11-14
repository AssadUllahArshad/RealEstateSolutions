<?php

namespace App\Http\Controllers;

use App\Domain\Export\CsvExport;
use App\Domain\Finance\InstallmentLedger;
use App\Domain\Finance\ManualCharges;
use App\Support\BusinessClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use UnexpectedValueException;

class BookingChargeController extends Controller
{
    private const REMAINING_EXPRESSION = 'booking_charges.amount - COALESCE((SELECT SUM(allocations.amount) FROM booking_charge_payment_allocations allocations JOIN booking_charge_payment_batches batches ON batches.id = allocations.booking_charge_payment_batch_id LEFT JOIN booking_charge_payment_reversals reversals ON reversals.booking_charge_payment_batch_id = batches.id WHERE allocations.booking_charge_id = booking_charges.id AND reversals.id IS NULL), 0) - COALESCE((SELECT SUM(waivers.amount) FROM booking_charge_waivers waivers WHERE waivers.booking_charge_id = booking_charges.id), 0)';

    public function index(Request $request, ManualCharges $charges): Response
    {
        $filters = $request->validate(['booking' => ['nullable', 'integer', 'min:1'], 'state' => ['nullable', 'in:active,settled,waived']]);
        $query = $charges->query($request->user())->with(['booking:id,snapshot', 'waivers'])
            ->withSum('paymentAllocations as paid_amount', 'amount')
            ->withSum('waivers as waived_amount', 'amount')
            ->when($filters['booking'] ?? null, fn ($builder, $id) => $builder->where('booking_id', $id))
            ->when(($filters['state'] ?? null) === 'active', fn ($builder) => $builder->whereRaw(self::REMAINING_EXPRESSION.' > 0'))
            ->when(($filters['state'] ?? null) === 'settled', fn ($builder) => $builder->whereRaw(self::REMAINING_EXPRESSION.' = 0')
                ->whereRaw('COALESCE((SELECT SUM(waivers.amount) FROM booking_charge_waivers waivers WHERE waivers.booking_charge_id = booking_charges.id), 0) = 0'))
            ->when(($filters['state'] ?? null) === 'waived', fn ($builder) => $builder->whereRaw(self::REMAINING_EXPRESSION.' = 0')
                ->whereRaw('COALESCE((SELECT SUM(waivers.amount) FROM booking_charge_waivers waivers WHERE waivers.booking_charge_id = booking_charges.id), 0) > 0'));
        $activeTotal = (clone $query)->whereRaw(self::REMAINING_EXPRESSION.' > 0')->sum(DB::raw(self::REMAINING_EXPRESSION));
        $records = $query->latest('business_date')->latest('id')->paginate(25)->withQueryString();
        $records->getCollection()->transform(function ($charge): object {
            $paid = $this->decimal($charge->paid_amount ?? '0');
            $waived = $this->decimal($charge->waived_amount ?? '0');
            $balance = bcsub(bcsub($this->decimal($charge->amount), $paid, 2), $waived, 2);
            $charge->setAttribute('paidAmount', $paid);
            $charge->setAttribute('waivedAmount', $waived);
            $charge->setAttribute('remainingAmount', $balance);
            $charge->setAttribute('status', bccomp($balance, '0', 2) > 0 ? 'active' : (bccomp($waived, '0', 2) > 0 ? 'waived' : 'settled'));

            return $charge;
        });

        return Inertia::render('finance/charges', [
            'charges' => $records,
            'filters' => ['booking' => isset($filters['booking']) ? (string) $filters['booking'] : '', 'state' => $filters['state'] ?? ''],
            'activeTotal' => bcadd((string) $activeTotal, '0', 2),
            'bookings' => app(InstallmentLedger::class)->query($request->user())->latest('id')->limit(200)->get(['id', 'snapshot']),
            'businessDate' => app(BusinessClock::class)->today(), 'postKey' => (string) Str::uuid(), 'waiveKey' => (string) Str::uuid(),
            'canManage' => $charges->canManage($request->user()),
        ]);
    }

    public function export(Request $request, ManualCharges $charges, CsvExport $export): StreamedResponse
    {
        Gate::authorize('finance.charges.view');
        $filters = $request->validate(['booking' => ['nullable', 'integer', 'min:1'], 'state' => ['nullable', 'in:active,settled,waived'], 'format' => ['nullable', 'in:csv,xlsx']]);
        $query = $charges->query($request->user())->with(['booking:id,snapshot', 'waivers'])
            ->withSum('paymentAllocations as paid_amount', 'amount')
            ->withSum('waivers as waived_amount', 'amount')
            ->when($filters['booking'] ?? null, fn ($builder, $id) => $builder->where('booking_id', $id))
            ->when(($filters['state'] ?? null) === 'active', fn ($builder) => $builder->whereRaw(self::REMAINING_EXPRESSION.' > 0'))
            ->when(($filters['state'] ?? null) === 'settled', fn ($builder) => $builder->whereRaw(self::REMAINING_EXPRESSION.' = 0')
                ->whereRaw('COALESCE((SELECT SUM(waivers.amount) FROM booking_charge_waivers waivers WHERE waivers.booking_charge_id = booking_charges.id), 0) = 0'))
            ->when(($filters['state'] ?? null) === 'waived', fn ($builder) => $builder->whereRaw(self::REMAINING_EXPRESSION.' = 0')
                ->whereRaw('COALESCE((SELECT SUM(waivers.amount) FROM booking_charge_waivers waivers WHERE waivers.booking_charge_id = booking_charges.id), 0) > 0'));
        $rows = $query->latest('business_date')->latest('id')->lazy(500)->map(function ($charge) {
            $paid = $this->decimal($charge->paid_amount ?? '0');
            $waived = $this->decimal($charge->waived_amount ?? '0');
            $balance = bcsub(bcsub($this->decimal($charge->amount), $paid, 2), $waived, 2);

            return [
                $charge->id,
                $charge->booking_id,
                $charge->business_date,
                $charge->amount,
                $paid,
                $waived,
                $balance,
                $charge->reason,
            ];
        });
        $headers = ['ID', 'Booking', 'Business date', 'Amount (PKR)', 'Paid (PKR)', 'Waived (PKR)', 'Remaining (PKR)', 'Reason'];
        $format = $filters['format'] ?? 'csv';

        return $format === 'xlsx'
            ? $export->xlsx('estateflow-booking-charges.xlsx', $headers, $rows)
            : $export->stream('estateflow-booking-charges.csv', $headers, $rows);
    }

    public function store(Request $request, ManualCharges $charges): RedirectResponse
    {
        try {
            $charge = $charges->post($request->user(), $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Manual charge '.$charge->number.' posted.']);

        return back();
    }

    public function waive(Request $request, ManualCharges $charges, int $charge): RedirectResponse
    {
        try {
            $charges->waive($request->user(), $charge, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Waiver recorded. The original charge remains visible.']);

        return back();
    }

    /** @return numeric-string */
    private function decimal(mixed $value): string
    {
        if ((! is_string($value) && ! is_int($value)) || ! is_numeric((string) $value)) {
            throw new UnexpectedValueException('Expected an exact database decimal.');
        }

        return bcadd((string) $value, '0', 2);
    }
}
