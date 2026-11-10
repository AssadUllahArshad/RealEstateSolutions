<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['booking_transfer_id', 'customer_id', 'share'])]
class BookingTransferParty extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['share' => 'decimal:4'];
    }
}
