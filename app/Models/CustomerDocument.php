<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['customer_id', 'uploaded_by', 'kind', 'path', 'mime', 'bytes'])]
#[Hidden(['path'])]
class CustomerDocument extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Uploaded evidence is append-only. Upload a new document instead.'));
        static::deleting(fn () => throw new LogicException('Document retention requires a separate authorized workflow.'));
    }
}
