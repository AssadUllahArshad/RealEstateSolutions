<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['financial_account_id', 'amount', 'effective_on', 'source_note', 'source_reference', 'created_by', 'command_key', 'payload_hash'])]
class FinancialAccountOpeningBalance extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<FinancialAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    /** @return HasMany<FinancialAccountOpeningCorrection, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(FinancialAccountOpeningCorrection::class, 'opening_balance_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Opening balances are immutable.'));
        static::deleting(fn () => throw new LogicException('Opening balances cannot be deleted.'));
    }
}
