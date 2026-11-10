<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['label', 'area_marla'])]
class PlotSize extends Model
{
    protected function casts(): array
    {
        return ['area_marla' => 'decimal:2'];
    }
}
