<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** @property numeric-string $amount */
#[Fillable(['financial_account_id', 'number', 'category', 'amount', 'business_date', 'payee', 'reference', 'note', 'account_snapshot', 'posted_by', 'command_key', 'payload_hash'])]
class OfficeExpense extends Model
{
    public const UPDATED_AT = null;

    protected $hidden = ['command_key', 'payload_hash'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'account_snapshot' => 'array'];
    }

    /** @return BelongsTo<FinancialAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Posted office expenses are immutable.'));
        static::deleting(fn () => throw new LogicException('Posted office expenses are immutable.'));
    }
}
