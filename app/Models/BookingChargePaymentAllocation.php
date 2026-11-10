<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['booking_charge_payment_batch_id', 'booking_charge_id', 'amount'])]
class BookingChargePaymentAllocation extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<BookingCharge, $this> */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(BookingCharge::class, 'booking_charge_id');
    }

    /** @return BelongsTo<BookingChargePaymentBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(BookingChargePaymentBatch::class, 'booking_charge_payment_batch_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Charge payment allocations are immutable.'));
        static::deleting(fn () => throw new LogicException('Charge payment allocations cannot be deleted.'));
    }
}
