<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** @property numeric-string $amount */
#[Fillable(['financial_account_id', 'payment_receipt_id', 'actor_id', 'kind', 'amount', 'business_date'])]
class AccountMovement extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Posted movements are immutable.'));
        static::deleting(fn () => throw new LogicException('Posted movements are immutable.'));
    }
}
