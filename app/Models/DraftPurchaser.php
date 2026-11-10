<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property numeric-string $share */
class DraftPurchaser extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['share' => 'decimal:4'];
    }
}
