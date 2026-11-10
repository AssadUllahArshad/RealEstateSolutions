<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @property int $booking_id
 * @property numeric-string $eligible_amount
 * @property numeric-string $percentage
 * @property string $business_date
 * @property int $plot_owner_id
 * @property numeric-string $entitlement_amount
 */
#[Fillable(['plot_owner_id', 'booking_id', 'source_kind', 'source_id', 'eligible_amount', 'percentage', 'entitlement_amount', 'business_date', 'policy_snapshot'])]
class OwnerEntitlementEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['eligible_amount' => 'decimal:2', 'percentage' => 'decimal:4', 'entitlement_amount' => 'decimal:2', 'policy_snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Owner entitlement events are immutable.'));
        static::deleting(fn () => throw new LogicException('Owner entitlement events cannot be deleted.'));
    }
}
