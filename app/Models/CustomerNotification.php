<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerNotification extends Model
{
    protected $fillable = [
        'customer_id',
        'organization_id',
        'type',
        'variant',
        'channel',
        'status',
        'skip_reason',
        'request',
        'response_status',
        'response_body',
        'error',
        'triggered_by',
        'triggered_by_user_id',
    ];

    protected $casts = [
        'request' => 'array',
        'response_status' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function triggeredByUser()
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
