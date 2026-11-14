<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $created_by
 * @property string $note
 * @property string $due_date
 * @property Carbon|null $completed_at
 * @property int|null $completed_by
 */
#[Fillable(['customer_id', 'created_by', 'note', 'due_date', 'completed_at', 'completed_by'])]
class FollowUp extends Model
{
    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
