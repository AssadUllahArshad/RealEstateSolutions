<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['customer_id', 'actor_id', 'event', 'metadata'])]
class CustomerActivity extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Customer history is append-only.'));
        static::deleting(fn () => throw new LogicException('Customer history is append-only.'));
    }
}
