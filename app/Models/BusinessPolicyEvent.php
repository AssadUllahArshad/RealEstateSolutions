<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['policy_key', 'version', 'actor_id', 'before', 'after'])]
class BusinessPolicyEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Policy history is append-only.'));
        static::deleting(fn () => throw new LogicException('Policy history is append-only.'));
    }
}
