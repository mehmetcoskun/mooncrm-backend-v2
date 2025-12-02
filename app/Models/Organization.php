<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'logo',
        'code',
        'trial_ends_at',
        'license_ends_at',
        'is_active',
    ];

    protected $casts = [
        'trial_ends_at' => 'date',
        'license_ends_at' => 'date',
        'is_active' => 'boolean',
    ];
}
