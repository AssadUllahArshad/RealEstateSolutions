<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['receipt_draft_id', 'actor_id', 'version', 'details'])]
class ReceiptDraftEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Receipt preparation history is append-only.'));
        static::deleting(fn () => throw new LogicException('Receipt preparation history is append-only.'));
    }
}
