<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerLog extends Model
{
    protected $fillable = [
        'customer_id',
        'user_id',
        'field_name',
        'old_value',
        'new_value',
        'action_type'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
