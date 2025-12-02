<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerHotel extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'chat_id',
        'message_templates'
    ];

    protected $casts = [
        'message_templates' => 'array'
    ];
}
