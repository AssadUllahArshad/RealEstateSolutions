<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** @property array<string,mixed> $snapshot */
#[Fillable(['booking_draft_id', 'version', 'actor_id', 'snapshot'])]
class DraftTermEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Terms history is append-only.'));
        static::deleting(fn () => throw new LogicException('Terms history is append-only.'));
    }
}
