<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'organization_id',
        'parent_id',
        'title',
        'channel',
        'lead_form_id',
        'field_mappings',
        'vapi_assistant_id',
        'vapi_phone_number_id',
        'is_global',
    ];

    protected $casts = [
        'field_mappings' => 'array',
        'is_global' => 'boolean'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function getAllChildren()
    {
        return $this->children()->with('getAllChildren');
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'tag_category');
    }
}
