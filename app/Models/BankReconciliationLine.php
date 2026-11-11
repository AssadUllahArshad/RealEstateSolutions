<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['bank_reconciliation_id', 'statement_date', 'amount', 'direction', 'description', 'reference', 'source_type', 'source_id'])]
class BankReconciliationLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<BankReconciliation, $this> */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Bank reconciliation lines are immutable.'));
        static::deleting(fn () => throw new LogicException('Bank reconciliation lines cannot be deleted.'));
    }
}
