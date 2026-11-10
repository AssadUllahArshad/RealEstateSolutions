<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;
/**
 * @property int $agent_id
 * @property int $booking_id
 * @property numeric-string $eligible_amount
 * @property numeric-string $percentage
 * @property numeric-string $commission_amount
 * @property string $business_date
 * @property int $source_id
 * @property string $source_kind
 */
#[Fillable(['booking_id','agent_id','source_kind','source_id','eligible_amount','percentage','commission_amount','business_date','policy_snapshot'])]
class CommissionEvent extends Model { public const UPDATED_AT=null; protected function casts():array{return ['eligible_amount'=>'decimal:2','percentage'=>'decimal:4','commission_amount'=>'decimal:2','policy_snapshot'=>'array'];} protected static function booted():void{static::updating(fn()=>throw new LogicException('Commission events are immutable.'));static::deleting(fn()=>throw new LogicException('Commission events cannot be deleted.'));}}
