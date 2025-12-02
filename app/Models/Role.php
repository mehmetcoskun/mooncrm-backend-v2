<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'organization_id',
        'title',
        'has_status_filter',
        'is_global',
    ];

    protected $casts = [
        'has_status_filter' => 'boolean',
        'is_global' => 'boolean'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function statuses()
    {
        return $this->belongsToMany(Status::class, 'role_status');
    }
}
