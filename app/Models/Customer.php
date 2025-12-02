<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'category_id',
        'status_id',
        'name',
        'email',
        'phone',
        'country',
        'notes',
        'duplicate_count',
        'duplicate_checked',
        'phone_calls',
        'reminder',
        'sales_info',
        'travel_info',
        'payment_notes',
        'ad_name',
        'adset_name',
        'campaign_name',
        'lead_form_id',
        'created_at',
    ];

    protected $casts = [
        'duplicate_checked' => 'boolean',
        'phone_calls' => 'array',
        'reminder' => 'array',
        'sales_info' => 'array',
        'travel_info' => 'array',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'customer_service');
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function files()
    {
        return $this->hasMany(CustomerFile::class);
    }
}
