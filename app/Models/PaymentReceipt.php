<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** @property numeric-string $amount
 * @property array<string,mixed> $snapshot
 */
#[Fillable(['receipt_draft_id', 'customer_id', 'financial_account_id', 'number', 'series_prefix', 'sequence', 'amount', 'method', 'state', 'bank_stage', 'accepted_on', 'recognized_on', 'cleared_on', 'reversed_on', 'reference', 'verification_note', 'account_reference_hash', 'global_reference_hash', 'snapshot', 'version', 'accepted_by'])]
class PaymentReceipt extends Model
{
    protected $hidden = ['reference', 'verification_note', 'account_reference_hash', 'global_reference_hash'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'snapshot' => 'array', 'version' => 'integer', 'reference' => 'encrypted', 'verification_note' => 'encrypted'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected static function booted(): void
    {
        static::updating(function (PaymentReceipt $receipt): void {
            if ($receipt->isDirty(['receipt_draft_id', 'customer_id', 'financial_account_id', 'number', 'series_prefix', 'sequence', 'amount', 'method', 'accepted_on', 'reference', 'verification_note', 'account_reference_hash', 'global_reference_hash', 'snapshot', 'accepted_by'])) {
                throw new LogicException('Issued receipt terms are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Issued receipts cannot be deleted.'));
    }
}
