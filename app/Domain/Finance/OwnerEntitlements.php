<?php

namespace App\Domain\Finance;

use App\Models\Booking;
use App\Models\OwnerEntitlementEvent;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OwnerEntitlements
{
    public function record(Booking $booking, string $sourceKind, int $sourceId, string $eligibleAmount, string $businessDate): OwnerEntitlementEvent
    {
        $ownerId = data_get($booking->snapshot, 'plot.owner.id');
        $percentage = data_get($booking->snapshot, 'plot.owner_percentage');
        if (! is_int($ownerId) || ! is_numeric($percentage)) {
            throw ValidationException::withMessages(['owner_percentage' => 'The booking has no approved owner percentage snapshot.']);
        }
        $eligible = bcadd($this->numeric($eligibleAmount), '0', 2);
        $rate = bcadd($this->numeric($percentage), '0', 4);
        $raw = bcdiv(bcmul($eligible, $rate, 6), '100', 6);
        $entitlement = bcdiv(bcadd($raw, '0.005000', 6), '1', 2);

        $event = OwnerEntitlementEvent::firstOrCreate(
            ['source_kind' => $sourceKind, 'source_id' => $sourceId],
            ['plot_owner_id' => $ownerId, 'booking_id' => $booking->id, 'eligible_amount' => $eligible, 'percentage' => $rate,
                'entitlement_amount' => $entitlement, 'business_date' => $businessDate,
                'policy_snapshot' => ['basis' => 'allocated_collections', 'rounding' => 'half_up', 'deductions' => 'none', 'rate_source' => 'booking_plot_snapshot']],
        );
        if ($event->booking_id !== $booking->id || bccomp($event->eligible_amount, $eligible, 2) !== 0 || bccomp($event->percentage, $rate, 4) !== 0 || $event->business_date !== $businessDate) {
            throw new ConflictHttpException('This collection source already has a different owner entitlement event.');
        }

        return $event;
    }

    /** @return numeric-string */
    private function numeric(mixed $value): string
    {
        if ((! is_string($value) && ! is_int($value)) || ! is_numeric((string) $value)) {
            throw new \UnexpectedValueException('Expected an exact numeric value.');
        }

        return (string) $value;
    }
}
