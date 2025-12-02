<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'organization_id',
        'title',
        'language',
        'subject',
        'template',
        'html',
    ];

    protected $casts = [
        'template' => 'array',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
