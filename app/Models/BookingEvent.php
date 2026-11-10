<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['booking_id', 'actor_id', 'details'])]
class BookingEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Booking events are immutable.'));
        static::deleting(fn () => throw new LogicException('Booking events cannot be deleted.'));
    }
}
