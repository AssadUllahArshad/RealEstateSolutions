<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $template
 * @property int $template_version
 * @property int $actor_id
 * @property string $state
 * @property string|null $source_filename
 * @property int $row_count
 * @property int $error_count
 * @property string $command_key
 * @property string $payload_hash
 */
#[Fillable(['template', 'template_version', 'actor_id', 'state', 'source_filename', 'row_count', 'error_count', 'command_key', 'payload_hash', 'committed_at'])]
class ImportBatch extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['committed_at' => 'datetime'];
    }

    /** @return HasMany<ImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
