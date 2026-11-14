<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $kind
 * @property int $opening_id
 * @property int $actor_id
 */
#[Fillable(['kind', 'opening_id', 'actor_id', 'command_key', 'payload_hash'])]
class OpeningBalanceActivation extends Model
{
    public const UPDATED_AT = null;
}
