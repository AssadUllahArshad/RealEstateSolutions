<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @property int $booking_id
 * @property int $installment_allocation_id
 * @property int $prepared_by
 * @property int|null $decided_by
 * @property numeric-string $amount
 * @property string $state
 * @property string|null $decision_key
 */
#[Fillable(['booking_id', 'installment_allocation_id', 'prepared_by', 'decided_by', 'amount', 'reason', 'state', 'decision_note', 'business_date', 'ledger_version', 'command_key', 'payload_hash', 'decision_key', 'decided_at'])]
class InstallmentAllocationCorrection extends Model
{
    protected $hidden = ['command_key', 'payload_hash', 'decision_key'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'decided_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $correction): void {
            if (array_diff(array_keys($correction->getDirty()), ['state', 'decided_by', 'decision_note', 'business_date', 'ledger_version', 'decision_key', 'decided_at', 'updated_at']) !== []) {
                throw new LogicException('Allocation correction details are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Allocation corrections cannot be deleted.'));
    }
}
