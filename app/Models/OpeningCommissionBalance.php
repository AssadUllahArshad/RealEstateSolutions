<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $agent_id
 * @property numeric-string $amount
 * @property string $effective_on
 * @property string $source_note
 * @property string|null $source_reference
 * @property int $created_by
 */
#[Fillable(['agent_id', 'amount', 'effective_on', 'source_note', 'source_reference', 'created_by', 'command_key', 'payload_hash'])]
class OpeningCommissionBalance extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
