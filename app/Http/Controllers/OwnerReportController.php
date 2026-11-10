<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Plot;
use App\Models\PlotOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OwnerReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->can('reports.owners.view'), 403);
        $filters = $request->validate([
            'owner_id' => ['nullable', 'integer', Rule::exists('plot_owners', 'id')],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $owners = PlotOwner::query()->orderBy('name')->get(['id', 'name']);
        $selectedOwner = isset($filters['owner_id']) ? (int) $filters['owner_id'] : null;
        $rows = [];
        foreach ($owners as $owner) {
            if ($selectedOwner !== null && $selectedOwner !== $owner->id) {
                continue;
            }
            $rows[(string) $owner->id] = $this->blankRow($owner->id, $owner->name);
        }
        if ($selectedOwner === null) {
            $rows['unassigned'] = $this->blankRow(null, 'Unassigned owner');
        }

        $plots = Plot::query()->get(['id', 'plot_owner_id']);
        $bookedPlotIds = Booking::query()->pluck('plot_id')->flip();
        foreach ($plots as $plot) {
            $key = $plot->plot_owner_id === null ? 'unassigned' : (string) $plot->plot_owner_id;
            if (! isset($rows[$key])) {
                continue;
            }
            $rows[$key]['inventory_plots']++;
            if ($bookedPlotIds->has($plot->id)) {
                $rows[$key]['inventory_booked']++;
            }
        }

        $bookingsQuery = Booking::query()->orderByDesc('confirmed_on')->orderByDesc('id');
        if (! empty($filters['from'])) {
            $bookingsQuery->where('confirmed_on', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $bookingsQuery->where('confirmed_on', '<=', $filters['to']);
        }
        $bookings = $bookingsQuery->get(['id', 'confirmed_on', 'net', 'down_payment', 'snapshot']);
        $bookingIds = $bookings->pluck('id');
        $down = $bookingIds->isEmpty() ? collect() : DB::table('booking_allocations')
            ->whereIn('booking_id', $bookingIds)->groupBy('booking_id')
            ->selectRaw('booking_id, SUM(amount) AS amount')->pluck('amount', 'booking_id');
        $installments = $bookingIds->isEmpty() ? collect() : DB::table('installment_allocations')
            ->whereIn('booking_id', $bookingIds)->groupBy('booking_id')
            ->selectRaw('booking_id, SUM(amount) AS amount')->pluck('amount', 'booking_id');
        $allocationCorrections = $bookingIds->isEmpty() ? collect() : DB::table('installment_allocation_corrections')
            ->whereIn('booking_id', $bookingIds)->where('state', 'approved')->groupBy('booking_id')
            ->selectRaw('booking_id, SUM(amount) AS amount')->pluck('amount', 'booking_id');
        $entitlementQuery = DB::table('owner_entitlement_events');
        if (! empty($filters['from'])) {
            $entitlementQuery->where('business_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $entitlementQuery->where('business_date', '<=', $filters['to']);
        }
        $ownerEntitlements = (clone $entitlementQuery)->groupBy('plot_owner_id')->selectRaw('plot_owner_id, SUM(entitlement_amount) AS amount')->pluck('amount', 'plot_owner_id');
        $bookingEntitlements = $bookingIds->isEmpty() ? collect() : (clone $entitlementQuery)->whereIn('booking_id', $bookingIds)->groupBy('booking_id')->selectRaw('booking_id, SUM(entitlement_amount) AS amount')->pluck('amount', 'booking_id');
        foreach ($rows as $key => $row) {
            if ($row['owner_id'] !== null) {
                $rows[$key]['owner_entitlement'] = $this->numeric($ownerEntitlements[$row['owner_id']] ?? '0.00');
            }
        }

        $recent = [];
        foreach ($bookings as $booking) {
            $owner = $booking->snapshot['plot']['owner'] ?? null;
            $key = isset($owner['id']) ? (string) $owner['id'] : 'unassigned';
            if (! isset($rows[$key])) {
                continue;
            }
            $netInstallments = bcsub($this->numeric($installments[$booking->id] ?? '0.00'), $this->numeric($allocationCorrections[$booking->id] ?? '0.00'), 2);
            $allocated = $this->add($down[$booking->id] ?? '0.00', $netInstallments);
            $rows[$key]['confirmed_bookings']++;
            $rows[$key]['contract_value'] = $this->add($rows[$key]['contract_value'], $booking->net);
            $rows[$key]['allocated_collections'] = $this->add($rows[$key]['allocated_collections'], $allocated);
            $outstanding = $this->subtract($booking->net, $allocated);
            $rows[$key]['outstanding_principal'] = $this->add($rows[$key]['outstanding_principal'], $outstanding);
            if (count($recent) < 50) {
                $recent[] = [
                    'id' => $booking->id,
                    'owner_name' => $rows[$key]['owner_name'],
                    'customer_name' => $booking->snapshot['customer_name'],
                    'plot' => ($booking->snapshot['plot']['block']['code'] ?? '').' / '.($booking->snapshot['plot']['number'] ?? ''),
                    'confirmed_on' => $booking->confirmed_on,
                    'contract_value' => $booking->net,
                    'allocated_collections' => $allocated,
                    'outstanding_principal' => $outstanding,
                    'owner_entitlement' => $this->numeric($bookingEntitlements[$booking->id] ?? '0.00'),
                ];
            }
        }

        return Inertia::render('reports/owners', [
            'owners' => $owners,
            'filters' => ['owner_id' => $selectedOwner, 'from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''],
            'rows' => array_values($rows),
            'recent' => $recent,
            'basis' => 'Collections include exact recognized receipt credit allocated to confirmed booking down payment or installments. Unallocated receipt credit is excluded.',
        ]);
    }

    /** @return array{owner_id:int|null,owner_name:string,inventory_plots:int,inventory_booked:int,confirmed_bookings:int,contract_value:string,allocated_collections:string,outstanding_principal:string} */
    private function blankRow(?int $id, string $name): array
    {
        return ['owner_id' => $id, 'owner_name' => $name, 'inventory_plots' => 0, 'inventory_booked' => 0, 'confirmed_bookings' => 0, 'contract_value' => '0.00', 'allocated_collections' => '0.00', 'outstanding_principal' => '0.00', 'owner_entitlement' => '0.00'];
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
    private function add(mixed $left, mixed $right): string
    {
        return bcadd($this->numeric($left), $this->numeric($right), 2);
    }

    /** @return numeric-string */
    private function subtract(mixed $left, mixed $right): string
    {
        return bcsub($this->numeric($left), $this->numeric($right), 2);
    }
}
