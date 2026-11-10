<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['payment_receipt_id', 'actor_id', 'version', 'event', 'details', 'private_note'])]
class PaymentReceiptEvent extends Model
{
    public const UPDATED_AT = null;

    protected $hidden = ['private_note'];

    protected function casts(): array
    {
        return ['details' => 'array', 'private_note' => 'encrypted'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Posting history is immutable.'));
        static::deleting(fn () => throw new LogicException('Posting history is immutable.'));
    }
}
