<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $purchasers_version
 * @property numeric-string $quoted_price
 */
#[Fillable(['customer_id', 'plot_id', 'created_by', 'quoted_price', 'catalogue_price_snapshot', 'notes', 'version', 'creation_key', 'creation_hash'])]
class BookingDraft extends Model
{
    /** @return HasOne<Booking, $this> */
    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }

    protected $hidden = ['creation_key', 'creation_hash'];

    protected function casts(): array
    {
        return ['quoted_price' => 'decimal:2', 'catalogue_price_snapshot' => 'decimal:2', 'purchasers_version' => 'integer'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Plot, $this> */
    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }
}
