<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'type', 'active', 'version', 'created_by', 'creation_key', 'creation_hash'])]
#[Hidden(['creation_key', 'creation_hash'])]
class FinancialAccount extends Model
{
    protected function casts(): array
    {
        return ['active' => 'boolean', 'version' => 'integer'];
    }
}
