<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerFile extends Model
{
    protected $fillable = [
        'customer_id',
        'title',
        'key',
    ];
}
