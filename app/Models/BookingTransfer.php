<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['booking_id', 'state', 'reason', 'outgoing_snapshot', 'incoming_snapshot', 'private_snapshot', 'document_snapshot', 'outstanding_snapshot', 'requested_by', 'command_key', 'payload_hash', 'decided_by', 'decision_note', 'effective_on', 'decided_at'])]
class BookingTransfer extends Model
{
    protected $hidden = ['private_snapshot', 'command_key', 'payload_hash'];

    protected function casts(): array
    {
        return ['outgoing_snapshot' => 'array', 'incoming_snapshot' => 'array', 'private_snapshot' => 'encrypted:array', 'document_snapshot' => 'array', 'outstanding_snapshot' => 'decimal:2', 'effective_on' => 'date:Y-m-d', 'decided_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Transfer history cannot be deleted.'));
    }
}
