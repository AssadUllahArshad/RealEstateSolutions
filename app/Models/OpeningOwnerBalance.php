<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $plot_owner_id
 * @property numeric-string $amount
 * @property string $effective_on
 * @property string $source_note
 * @property string|null $source_reference
 * @property int $created_by
 */
#[Fillable(['plot_owner_id', 'amount', 'effective_on', 'source_note', 'source_reference', 'created_by', 'command_key', 'payload_hash'])]
class OpeningOwnerBalance extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<PlotOwner, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(PlotOwner::class, 'plot_owner_id');
    }
}
