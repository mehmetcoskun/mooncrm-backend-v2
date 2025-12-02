<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $fillable = [
        'organization_id',
        'title',
        'language',
        'welcome_message',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'tag_category');
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
