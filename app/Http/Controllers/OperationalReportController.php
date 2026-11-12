<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Receivables;
use App\Models\AccountMovement;
use App\Models\Booking;
use App\Models\CommissionPayment;
use App\Models\OwnerPayout;
use App\Models\PaymentReceipt;
use App\Models\Plot;
use App\Models\PlotOwner;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('reports.operations.view');
        $today = app(BusinessClock::class)->today();
        $filters = $this->filters($request, $today);
        $report = $this->build($request->user(), $filters, $today);

        return Inertia::render('reports/operations', [
            'businessDate' => $today,
            'filters' => $filters,
            'report' => $report,
            'basis' => 'Inventory is current. Bookings, collections, payouts and commissions use the selected period. Receivables aging uses the as-of date. Unallocated receipt credit is excluded from allocated collections.',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('reports.operations.view');
        $today = app(BusinessClock::class)->today();
        $filters = $this->filters($request, $today);
        $report = $this->build($request->user(), $filters, $today);

        return response()->streamDownload(function () use ($report, $filters): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open export stream.');
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['EstateFlow operational report'], ',', '"', '');
            fputcsv($stream, ['As of', $filters['as_of']], ',', '"', '');
            fputcsv($stream, ['Period from', $filters['from']], ',', '"', '');
            fputcsv($stream, ['Period to', $filters['to']], ',', '"', '');
            fputcsv($stream, [], ',', '"', '');

            fputcsv($stream, ['Inventory (current)'], ',', '"', '');
            fputcsv($stream, ['Total plots', $report['inventory']['total']], ',', '"', '');
            fputcsv($stream, ['Available', $report['inventory']['available']], ',', '"', '');
            fputcsv($stream, ['Held', $report['inventory']['held']], ',', '"', '');
            fputcsv($stream, ['Booked', $report['inventory']['booked']], ',', '"', '');
            fputcsv($stream, [], ',', '"', '');
            fputcsv($stream, ['By block'], ',', '"', '');
            fputcsv($stream, ['Block code', 'Block name', 'Plots'], ',', '"', '');
            foreach ($report['inventory']['byBlock'] as $row) {
                fputcsv($stream, [$this->safeCell((string) $row['code']), $this->safeCell((string) $row['name']), $row['plots']], ',', '"', '');
            }
            fputcsv($stream, [], ',', '"', '');
            fputcsv($stream, ['By size'], ',', '"', '');
            fputcsv($stream, ['Size label', 'Area (marla)', 'Plots'], ',', '"', '');
            foreach ($report['inventory']['bySize'] as $row) {
                fputcsv($stream, [$this->safeCell((string) $row['label']), $row['area_marla'], $row['plots']], ',', '"', '');
            }
            fputcsv($stream, [], ',', '"', '');
            fputcsv($stream, ['By owner'], ',', '"', '');
            fputcsv($stream, ['Owner', 'Plots'], ',', '"', '');
            foreach ($report['inventory']['byOwner'] as $row) {
                fputcsv($stream, [$this->safeCell((string) $row['owner_name']), $row['plots']], ',', '"', '');
            }
            fputcsv($stream, [], ',', '"', '');

            fputcsv($stream, ['Bookings (period)'], ',', '"', '');
            fputcsv($stream, ['Confirmed bookings', $report['bookings']['count']], ',', '"', '');
            fputcsv($stream, ['Contract value (PKR)', $report['bookings']['contract_value']], ',', '"', '');
            fputcsv($stream, ['Down payment (PKR)', $report['bookings']['down_payment']], ',', '"', '');
            fputcsv($stream, [], ',', '"', '');

            fputcsv($stream, ['Collections (period)'], ',', '"', '');
            fputcsv($stream, ['Recognized receipts', $report['collections']['recognized_count']], ',', '"', '');
            fputcsv($stream, ['Recognized amount (PKR)', $report['collections']['recognized_amount']], ',', '"', '');
            fputcsv($stream, ['Reversals (PKR)', $report['collections']['reversals']], ',', '"', '');
            fputcsv($stream, ['Allocated to down payments (PKR)', $report['collections']['allocated_down']], ',', '"', '');
            fputcsv($stream, ['Allocated to installments (PKR)', $report['collections']['allocated_installments']], ',', '"', '');
            fputcsv($stream, ['Unallocated (PKR)', $report['collections']['unallocated']], ',', '"', '');
            fputcsv($stream, [], ',', '"', '');

            fputcsv($stream, ['Receivables aging (as of '.$filters['as_of'].')'], ',', '"', '');
            fputcsv($stream, ['Bucket', 'Installments', 'Remaining (PKR)'], ',', '"', '');
            foreach ($report['aging'] as $row) {
                fputcsv($stream, [$row['bucket'], $row['installments'], $row['remaining']], ',', '"', '');
            }
            fputcsv($stream, [], ',', '"', '');

            fputcsv($stream, ['Owner payouts'], ',', '"', '');
            fputcsv($stream, ['Approved payouts (period, PKR)', $report['ownerPayouts']['approved_total']], ',', '"', '');
            fputcsv($stream, ['Owner', 'Outstanding (PKR)'], ',', '"', '');
            foreach ($report['ownerPayouts']['outstanding'] as $row) {
                fputcsv($stream, [$this->safeCell((string) $row['owner_name']), $row['outstanding']], ',', '"', '');
            }
            fputcsv($stream, [], ',', '"', '');

            fputcsv($stream, ['Agent commissions'], ',', '"', '');
            fputcsv($stream, ['Approved commission payments (period, PKR)', $report['commissions']['approved_total']], ',', '"', '');
            fputcsv($stream, ['Agent', 'Outstanding (PKR)'], ',', '"', '');
            foreach ($report['commissions']['outstanding'] as $row) {
                fputcsv($stream, [$this->safeCell((string) $row['agent_name']), $row['outstanding']], ',', '"', '');
            }
            fclose($stream);
        }, 'estateflow-operational-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @return array{from:string,to:string,as_of:string} */
    private function filters(Request $request, string $today): array
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'as_of' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return [
            'from' => $filters['from'] ?? substr($today, 0, 8).'01',
            'to' => $filters['to'] ?? $today,
            'as_of' => $filters['as_of'] ?? $today,
        ];
    }

    /** @param array{from:string,to:string,as_of:string} $filters
     * @return array<string,mixed>
     */
    private function build(User $actor, array $filters, string $today): array
    {
        [$from, $to, $asOf] = [$filters['from'], $filters['to'], $filters['as_of']];

        $totalPlots = Plot::count();
        $bookedPlots = Plot::whereHas('activeBooking')->count();
        $heldPlots = Plot::whereHas('activeHold')->whereDoesntHave('activeBooking')->count();
        $availablePlots = $totalPlots - $bookedPlots - $heldPlots;

        $byBlock = DB::table('plots')->join('blocks', 'blocks.id', '=', 'plots.block_id')
            ->groupBy('blocks.id', 'blocks.code', 'blocks.name')
            ->selectRaw('blocks.code, blocks.name, COUNT(*) AS plots')
            ->orderBy('blocks.code')->get()
            ->map(fn ($row) => ['code' => $row->code, 'name' => $row->name, 'plots' => (int) $row->plots])->all();

        $bySize = DB::table('plots')->join('plot_sizes', 'plot_sizes.id', '=', 'plots.plot_size_id')
            ->groupBy('plot_sizes.id', 'plot_sizes.label', 'plot_sizes.area_marla')
            ->selectRaw('plot_sizes.label, plot_sizes.area_marla, COUNT(*) AS plots')
            ->orderBy('plot_sizes.area_marla')->get()
            ->map(fn ($row) => ['label' => $row->label, 'area_marla' => $row->area_marla, 'plots' => (int) $row->plots])->all();

        $byOwner = DB::table('plots')->leftJoin('plot_owners', 'plot_owners.id', '=', 'plots.plot_owner_id')
            ->groupBy('plot_owners.id', 'plot_owners.name')
            ->selectRaw("plot_owners.id AS owner_id, COALESCE(plot_owners.name, 'Unassigned') AS owner_name, COUNT(*) AS plots")
            ->orderBy('owner_name')->get()
            ->map(fn ($row) => ['owner_id' => $row->owner_id === null ? null : (int) $row->owner_id, 'owner_name' => $row->owner_name, 'plots' => (int) $row->plots])->all();

        $bookingsQuery = Booking::query()->whereBetween('confirmed_on', [$from, $to]);
        $bookingCount = (clone $bookingsQuery)->count();
        $contractValue = $this->numeric((clone $bookingsQuery)->sum('net'));
        $downPayment = $this->numeric((clone $bookingsQuery)->sum('down_payment'));

        $recognizedQuery = PaymentReceipt::query()->where('state', 'recognized')->whereBetween('recognized_on', [$from, $to]);
        $recognizedCount = (clone $recognizedQuery)->count();
        $recognizedAmount = $this->numeric((clone $recognizedQuery)->sum('amount'));
        $reversals = $this->decimal(AccountMovement::query()->where('kind', 'receipt_reversal')->whereBetween('business_date', [$from, $to])->sum('amount'));
        $allocatedDown = $this->decimal(DB::table('booking_allocations')
            ->join('payment_receipts', 'payment_receipts.id', '=', 'booking_allocations.payment_receipt_id')
            ->where('payment_receipts.state', 'recognized')
            ->whereBetween('payment_receipts.recognized_on', [$from, $to])
            ->sum('booking_allocations.amount'));
        $allocatedInstallments = $this->decimal(DB::table('installment_allocations')
            ->join('payment_receipts', 'payment_receipts.id', '=', 'installment_allocations.payment_receipt_id')
            ->where('payment_receipts.state', 'recognized')
            ->whereBetween('payment_receipts.recognized_on', [$from, $to])
            ->sum('installment_allocations.amount'));
        $unallocated = bcsub(bcsub($recognizedAmount, $allocatedDown, 2), $allocatedInstallments, 2);

        $agingRows = DB::query()->fromSub(app(Receivables::class)->installments($actor, $asOf), 'aging')
            ->where('remaining', '>', 0)
            ->selectRaw("CASE WHEN due_date >= ? THEN 'Current' WHEN DATEDIFF(?, due_date) BETWEEN 1 AND 30 THEN '1-30 days' WHEN DATEDIFF(?, due_date) BETWEEN 31 AND 60 THEN '31-60 days' WHEN DATEDIFF(?, due_date) BETWEEN 61 AND 90 THEN '61-90 days' ELSE '90+ days' END AS bucket", [$asOf, $asOf, $asOf, $asOf])
            ->selectRaw('COUNT(*) AS installments, CAST(SUM(remaining) AS DECIMAL(18,2)) AS remaining')
            ->groupBy('bucket')
            ->get();
        $agingOrder = ['Current', '1-30 days', '31-60 days', '61-90 days', '90+ days'];
        $aging = [];
        foreach ($agingOrder as $bucket) {
            $row = $agingRows->firstWhere('bucket', $bucket);
            $aging[] = ['bucket' => $bucket, 'installments' => $row ? (int) $row->installments : 0, 'remaining' => $row ? $this->decimal($row->remaining) : '0.00'];
        }

        $ownerPayoutsApproved = $this->decimal(OwnerPayout::query()->where('state', 'approved')->whereBetween('business_date', [$from, $to])->sum('amount'));
        $entitled = DB::table('owner_entitlement_events')->groupBy('plot_owner_id')->selectRaw('plot_owner_id, SUM(entitlement_amount) AS amount')->pluck('amount', 'plot_owner_id');
        $ownerPaid = OwnerPayout::query()->where('state', 'approved')->groupBy('plot_owner_id')->selectRaw('plot_owner_id, SUM(amount) AS amount')->pluck('amount', 'plot_owner_id');
        $ownerOutstanding = [];
        foreach (PlotOwner::query()->orderBy('name')->get(['id', 'name']) as $owner) {
            $ownerOutstanding[] = [
                'owner_id' => $owner->id,
                'owner_name' => $owner->name,
                'outstanding' => bcsub($this->numeric($entitled[$owner->id] ?? '0.00'), $this->numeric($ownerPaid[$owner->id] ?? '0.00'), 2),
            ];
        }

        $commissionPaymentsApproved = $this->decimal(CommissionPayment::query()->where('state', 'approved')->whereBetween('business_date', [$from, $to])->sum('amount'));
        $earned = DB::table('commission_events')->groupBy('agent_id')->selectRaw('agent_id, SUM(commission_amount) AS amount')->pluck('amount', 'agent_id');
        $agentPaid = CommissionPayment::query()->where('state', 'approved')->groupBy('agent_id')->selectRaw('agent_id, SUM(amount) AS amount')->pluck('amount', 'agent_id');
        $agentIds = $earned->keys()->merge($agentPaid->keys())->unique()->values();
        $agentOutstanding = [];
        foreach (User::query()->whereIn('id', $agentIds)->orderBy('name')->get(['id', 'name']) as $agent) {
            $agentOutstanding[] = [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'outstanding' => bcsub($this->numeric($earned[$agent->id] ?? '0.00'), $this->numeric($agentPaid[$agent->id] ?? '0.00'), 2),
            ];
        }

        return [
            'inventory' => [
                'total' => $totalPlots,
                'available' => $availablePlots,
                'held' => $heldPlots,
                'booked' => $bookedPlots,
                'byBlock' => $byBlock,
                'bySize' => $bySize,
                'byOwner' => $byOwner,
            ],
            'bookings' => [
                'count' => $bookingCount,
                'contract_value' => $contractValue,
                'down_payment' => $downPayment,
            ],
            'collections' => [
                'recognized_count' => $recognizedCount,
                'recognized_amount' => $recognizedAmount,
                'reversals' => $reversals,
                'allocated_down' => $allocatedDown,
                'allocated_installments' => $allocatedInstallments,
                'unallocated' => $unallocated,
            ],
            'aging' => $aging,
            'ownerPayouts' => [
                'approved_total' => $ownerPayoutsApproved,
                'outstanding' => $ownerOutstanding,
            ],
            'commissions' => [
                'approved_total' => $commissionPaymentsApproved,
                'outstanding' => $agentOutstanding,
            ],
        ];
    }

    private function safeCell(string $cell): string
    {
        return preg_match('/^[\s\x00-\x20]*[=+@-]|^[\t\r\n]/u', $cell) ? "'".$cell : $cell;
    }

    /** @return numeric-string */
    private function numeric(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new \UnexpectedValueException('Expected a numeric database value.');
        }
        $value = (string) $value;
        if (! is_numeric($value)) {
            throw new \UnexpectedValueException('Expected a numeric database value.');
        }

        return $value;
    }

    /** @return numeric-string */
    private function decimal(mixed $value): string
    {
        if (is_string($value) && is_numeric($value)) {
            return bcadd($value, '0', 2);
        }
        if (is_int($value) || is_float($value)) {
            return number_format($value, 2, '.', '');
        }

        return '0.00';
    }
}
