<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    protected $fillable = [
        'organization_id',
        'title',
        'language',
        'message',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
