<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'admin_user_id', 'default_percentage', 'created_by'])]
/** @property numeric-string|null $default_percentage */
class PlotOwner extends Model
{
    protected function casts(): array
    {
        return ['default_percentage' => 'decimal:4'];
    }
}
