<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name'])]
class Block extends Model
{
    /** @return HasMany<Plot, $this> */
    public function plots(): HasMany
    {
        return $this->hasMany(Plot::class);
    }
}
