<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['plot_id', 'kind', 'state', 'purpose', 'created_by', 'released_by', 'release_reason', 'released_at'])]
class PlotHold extends Model
{
    protected function casts(): array
    {
        return ['released_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Hold history cannot be deleted.'));
    }
}
