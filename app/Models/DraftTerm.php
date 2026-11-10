<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property array<string,mixed> $snapshot */
#[Fillable(['booking_draft_id', 'version', 'snapshot', 'updated_by'])]
class DraftTerm extends Model
{
    protected function casts(): array
    {
        return ['snapshot' => 'array', 'version' => 'integer'];
    }
}
