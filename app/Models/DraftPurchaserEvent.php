<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @property list<array{customer_id:int,share:string}> $before
 * @property list<array{customer_id:int,share:string}> $after
 */
#[Fillable(['booking_draft_id', 'actor_id', 'version', 'before', 'after'])]
class DraftPurchaserEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Purchaser history is append-only.'));
        static::deleting(fn () => throw new LogicException('Purchaser history is append-only.'));
    }
}
