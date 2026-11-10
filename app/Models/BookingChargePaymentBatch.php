<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable(['booking_id', 'payment_receipt_id', 'actor_id', 'version', 'amount', 'business_date', 'command_key', 'payload_hash'])]
class BookingChargePaymentBatch extends Model
{
    public const UPDATED_AT = null;

    protected $hidden = ['command_key', 'payload_hash'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'version' => 'integer'];
    }

    /** @return HasMany<BookingChargePaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(BookingChargePaymentAllocation::class);
    }

    /** @return HasOne<BookingChargePaymentReversal, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(BookingChargePaymentReversal::class);
    }

    /** @return BelongsTo<PaymentReceipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class, 'payment_receipt_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Charge payment allocations are immutable.'));
        static::deleting(fn () => throw new LogicException('Charge payment allocations cannot be deleted.'));
    }
}
