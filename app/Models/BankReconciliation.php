<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['financial_account_id', 'period_start', 'period_end', 'statement_opening_balance', 'statement_closing_balance', 'state', 'note', 'prepared_by', 'decided_by', 'decision_note', 'decided_at', 'command_key', 'payload_hash', 'decision_key'])]
class BankReconciliation extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['statement_opening_balance' => 'decimal:2', 'statement_closing_balance' => 'decimal:2', 'decided_at' => 'datetime'];
    }

    /** @return BelongsTo<FinancialAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return HasMany<BankReconciliationLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(BankReconciliationLine::class, 'bank_reconciliation_id');
    }

    protected static function booted(): void
    {
        static::updating(function (self $reconciliation): void {
            if (array_diff(array_keys($reconciliation->getDirty()), ['state', 'decided_by', 'decision_note', 'decided_at', 'decision_key']) !== []) {
                throw new LogicException('Prepared bank reconciliation details are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Bank reconciliations cannot be deleted.'));
    }
}
