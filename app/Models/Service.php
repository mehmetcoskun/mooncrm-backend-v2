<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'organization_id',
        'title',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'customer_service');
    }
}
