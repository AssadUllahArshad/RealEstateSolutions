<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;
/**
 * @property numeric-string $amount
 * @property int $agent_id
 * @property string $state
 * @property string $payload_hash
 * @property ?string $decision_key
 * @property string $business_date
 * @property string $number
 */
#[Fillable(['agent_id','financial_account_id','number','amount','business_date','state','reference','verification_note','agent_snapshot','account_snapshot','payable_snapshot','prepared_by','decided_by','decision_note','decided_at','command_key','payload_hash','decision_key'])]
class CommissionPayment extends Model {protected $hidden=['command_key','payload_hash','decision_key'];protected function casts():array{return ['amount'=>'decimal:2','payable_snapshot'=>'decimal:2','agent_snapshot'=>'array','account_snapshot'=>'array','decided_at'=>'datetime'];}protected static function booted():void{static::updating(function(self $r):void{if(array_diff(array_keys($r->getDirty()),['state','decided_by','decision_note','decided_at','decision_key','updated_at'])!==[])throw new LogicException('Prepared commission payment details are immutable.');});static::deleting(fn()=>throw new LogicException('Commission payments cannot be deleted.'));}}
