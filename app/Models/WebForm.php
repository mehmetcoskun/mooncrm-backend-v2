<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebForm extends Model
{
    protected $fillable = [
        'organization_id',
        'uuid',
        'title',
        'fields',
        'styles',
        'redirect_url',
        'email_recipients',
        'domain',
        'category_id',
        'rate_limit_settings',
    ];

    protected $casts = [
        'fields' => 'array',
        'styles' => 'array',
        'rate_limit_settings' => 'array',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
