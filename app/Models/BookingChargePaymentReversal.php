<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['booking_charge_payment_batch_id', 'payment_receipt_id', 'actor_id', 'business_date'])]
class BookingChargePaymentReversal extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Charge payment reversals are immutable.'));
        static::deleting(fn () => throw new LogicException('Charge payment reversals cannot be deleted.'));
    }
}
