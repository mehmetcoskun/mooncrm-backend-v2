<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = [
        'organization_id',
        'title',
        'slug',
        'is_custom',
        'is_global',
    ];

    protected $casts = [
        'is_custom' => 'boolean',
        'is_global' => 'boolean'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
}
