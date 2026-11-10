<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['block_id', 'plot_size_id', 'number', 'list_price', 'plot_owner_id', 'owner_percentage'])]
class Plot extends Model
{
    /** @return BelongsTo<PlotOwner, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(PlotOwner::class, 'plot_owner_id');
    }

    /** @return HasOne<Booking, $this> */
    public function activeBooking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }

    /** @return HasOne<PlotHold, $this> */
    public function activeHold(): HasOne
    {
        return $this->hasOne(PlotHold::class)->where('state', 'active');
    }

    protected function casts(): array
    {
        return ['list_price' => 'decimal:2', 'owner_percentage' => 'decimal:4'];
    }

    /** @return BelongsTo<Block, $this> */
    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    /** @return BelongsTo<PlotSize, $this> */
    public function size(): BelongsTo
    {
        return $this->belongsTo(PlotSize::class, 'plot_size_id');
    }
}
