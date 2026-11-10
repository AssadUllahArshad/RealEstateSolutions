<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** @property numeric-string $amount */
#[Fillable(['booking_id', 'number', 'amount', 'business_date', 'reason', 'note', 'evidence_reference', 'posted_by', 'command_key', 'payload_hash'])]
class BookingCharge extends Model
{
    public const UPDATED_AT = null;

    protected $hidden = ['command_key', 'payload_hash'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'note' => 'encrypted'];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return HasMany<BookingChargeWaiver, $this> */
    public function waivers(): HasMany
    {
        return $this->hasMany(BookingChargeWaiver::class);
    }

    /** @return HasMany<BookingChargePaymentAllocation, $this> */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(BookingChargePaymentAllocation::class)->whereDoesntHave('batch.reversal');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Posted booking charges are immutable.'));
        static::deleting(fn () => throw new LogicException('Posted booking charges cannot be deleted.'));
    }
}
