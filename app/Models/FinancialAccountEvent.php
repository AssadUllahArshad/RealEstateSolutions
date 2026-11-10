<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['financial_account_id', 'actor_id', 'version', 'details'])]
class FinancialAccountEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Account history is append-only.'));
        static::deleting(fn () => throw new LogicException('Account history is append-only.'));
    }
}
