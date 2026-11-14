<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_id
 * @property int|null $booking_id
 * @property numeric-string $amount
 * @property string $effective_on
 * @property string $source_note
 * @property string|null $source_reference
 * @property int $created_by
 */
#[Fillable(['customer_id', 'booking_id', 'amount', 'effective_on', 'source_note', 'source_reference', 'created_by', 'command_key', 'payload_hash'])]
class OpeningCustomerBalance extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
