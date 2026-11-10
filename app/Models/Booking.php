<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string,mixed> $snapshot
 * @property array<string,mixed> $private_snapshot
 * @property numeric-string $net
 * @property numeric-string $down_payment
 * @property int|null $commission_agent_id
 * @property numeric-string|null $commission_percentage
 * @property array<string,mixed>|null $commission_policy_snapshot
 */
#[Fillable(['booking_draft_id', 'plot_id', 'customer_id', 'confirmed_by', 'commission_agent_id', 'commission_percentage', 'commission_policy_snapshot', 'confirmed_on', 'net', 'down_payment', 'snapshot', 'private_snapshot', 'command_key', 'payload_hash'])]
class Booking extends Model
{
    public const UPDATED_AT = null;

    protected $hidden = ['private_snapshot', 'command_key', 'payload_hash'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'private_snapshot' => 'encrypted:array', 'commission_percentage' => 'decimal:4', 'commission_policy_snapshot' => 'array', 'net' => 'decimal:2', 'down_payment' => 'decimal:2'];
    }

    /** @return HasMany<BookingChangeRequest, $this> */
    public function changes(): HasMany { return $this->hasMany(BookingChangeRequest::class); }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Confirmed bookings are immutable. Use a separately approved correction workflow.'));
        static::deleting(fn () => throw new LogicException('Confirmed bookings cannot be deleted.'));
    }
}
