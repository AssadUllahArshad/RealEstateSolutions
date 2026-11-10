<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['booking_id', 'kind', 'reason', 'proposed_change', 'state', 'requested_by', 'decided_by', 'decision_note', 'decided_at', 'command_key'])]
class BookingChangeRequest extends Model
{
    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Booking change requests cannot be deleted.'));
    }
}
