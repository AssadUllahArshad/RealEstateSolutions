<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['booking_charge_id', 'amount', 'business_date', 'reason', 'waived_by', 'command_key', 'payload_hash'])]
class BookingChargeWaiver extends Model
{
    public const UPDATED_AT = null;

    protected $hidden = ['command_key', 'payload_hash'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Charge waivers are immutable.'));
        static::deleting(fn () => throw new LogicException('Charge waivers cannot be deleted.'));
    }
}
