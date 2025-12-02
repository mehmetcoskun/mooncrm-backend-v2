<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Segment extends Model
{
    protected $fillable = [
        'organization_id',
        'title',
        'language',
        'filters',
    ];

    protected $casts = [
        'filters' => 'array',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
