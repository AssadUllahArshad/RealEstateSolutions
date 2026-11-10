<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['actor_id', 'subject_type', 'subject_id', 'event', 'before', 'after'])]
class InventoryEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Inventory history is append-only.'));
        static::deleting(fn () => throw new LogicException('Inventory history is append-only.'));
    }
}
