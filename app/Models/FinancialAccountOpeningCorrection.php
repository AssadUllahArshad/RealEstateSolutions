<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['opening_balance_id', 'amount', 'effective_on', 'reason', 'actor_id', 'command_key', 'payload_hash'])]
class FinancialAccountOpeningCorrection extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<FinancialAccountOpeningBalance, $this> */
    public function openingBalance(): BelongsTo
    {
        return $this->belongsTo(FinancialAccountOpeningBalance::class, 'opening_balance_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Opening balance corrections are immutable.'));
        static::deleting(fn () => throw new LogicException('Opening balance corrections cannot be deleted.'));
    }
}
