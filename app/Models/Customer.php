<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'phone', 'email', 'assigned_to', 'created_by', 'creation_key', 'creation_hash', 'cnic', 'address'])]
#[Hidden(['creation_key', 'creation_hash', 'cnic', 'address'])]
class Customer extends Model
{
    protected function casts(): array
    {
        return ['cnic' => 'encrypted', 'address' => 'encrypted'];
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
