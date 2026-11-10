<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property numeric-string $amount
 * @property numeric-string $deduction_amount
 * @property int $booking_id
 * @property string $payload_hash
 * @property ?string $decision_key
 * @property string $state
 * @property string $business_date
 */
#[Fillable(['booking_id', 'financial_account_id', 'number', 'amount', 'deduction_amount', 'business_date', 'state', 'deduction_reason', 'reference', 'verification_note', 'booking_snapshot', 'account_snapshot', 'refundable_snapshot', 'prepared_by', 'decided_by', 'decision_note', 'decided_at', 'command_key', 'payload_hash', 'decision_key'])]
class CustomerRefund extends Model
{
    protected $hidden = ['command_key', 'payload_hash', 'decision_key'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'deduction_amount' => 'decimal:2', 'refundable_snapshot' => 'decimal:2', 'booking_snapshot' => 'array', 'account_snapshot' => 'array', 'decided_at' => 'datetime'];
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
        static::updating(function (self $r): void {
            if (array_diff(array_keys($r->getDirty()), ['state', 'decided_by', 'decision_note', 'decided_at', 'decision_key', 'updated_at']) !== []) {
                throw new LogicException('Prepared refund details are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Refunds cannot be deleted.'));
    }
}
