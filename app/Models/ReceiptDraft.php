<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** @property numeric-string $amount
 * @property array<string,mixed> $policy_snapshot
 * @property array<string,mixed> $account_snapshot
 */
#[Fillable(['customer_id', 'financial_account_id', 'booking_draft_id', 'amount', 'reported_on', 'method', 'reference', 'notes', 'policy_snapshot', 'account_snapshot', 'version', 'created_by', 'creation_key', 'creation_hash'])]
#[Hidden(['creation_key', 'creation_hash', 'reference', 'notes', 'policy_snapshot'])]
class ReceiptDraft extends Model
{
    /** @return HasOne<PaymentReceipt, $this> */
    public function postedReceipt(): HasOne
    {
        return $this->hasOne(PaymentReceipt::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'version' => 'integer', 'reference' => 'encrypted', 'notes' => 'encrypted', 'policy_snapshot' => 'array', 'account_snapshot' => 'array'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
