<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    protected $fillable = [
        'organization_id',
        'title',
        'background_color',
        'is_global',
    ];

    protected $casts = [
        'is_global' => 'boolean'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_status');
    }
}
