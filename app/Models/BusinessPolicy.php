<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property array<string,mixed> $values */
#[Fillable(['key', 'values', 'version', 'updated_by'])]
class BusinessPolicy extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['values' => 'array', 'version' => 'integer'];
    }
}
