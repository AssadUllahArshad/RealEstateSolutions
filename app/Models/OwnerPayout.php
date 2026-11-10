<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property numeric-string $amount
 * @property int $plot_owner_id
 * @property string $payload_hash
 * @property string|null $decision_key
 * @property string $state
 * @property array<string,mixed> $account_snapshot
 * @property string|null $reference
 * @property string $business_date
 * @property string $number
 */
#[Fillable(['plot_owner_id', 'financial_account_id', 'number', 'amount', 'business_date', 'state', 'reference', 'verification_note', 'owner_snapshot', 'account_snapshot', 'payable_snapshot', 'prepared_by', 'decided_by', 'decision_note', 'decided_at', 'command_key', 'payload_hash', 'decision_key'])]
class OwnerPayout extends Model
{
    protected $hidden = ['command_key', 'payload_hash', 'decision_key'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'payable_snapshot' => 'decimal:2', 'owner_snapshot' => 'array', 'account_snapshot' => 'array', 'decided_at' => 'datetime'];
    }

    /** @return BelongsTo<PlotOwner, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(PlotOwner::class, 'plot_owner_id');
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

    protected static function booted(): void
    {
        static::updating(function (self $record): void {
            if (array_diff(array_keys($record->getDirty()), ['state', 'decided_by', 'decision_note', 'decided_at', 'decision_key', 'updated_at']) !== []) {
                throw new LogicException('Prepared owner payout details are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Owner payouts cannot be deleted.'));
    }
}
