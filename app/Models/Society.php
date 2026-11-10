<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'office_name', 'phone', 'email', 'address'])]
class Society extends Model
{
    public $incrementing = false;
}
