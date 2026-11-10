<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** @property int $id @property int $booking_id @property int $agent_id @property numeric-string $percentage @property string $effective_from @property string $state @property string $reason @property mixed $created_at */
#[Fillable(['booking_id', 'agent_id', 'percentage', 'effective_from', 'reason', 'state', 'supersedes_id', 'assigned_by', 'policy_snapshot'])]
class LegacyCommissionAssignment extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['percentage' => 'decimal:4', 'policy_snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $r): void {
            if (array_diff(array_keys($r->getDirty()), ['state']) !== []) {
                throw new LogicException('Legacy commission assignments are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Legacy assignments cannot be deleted.'));
    }
}
