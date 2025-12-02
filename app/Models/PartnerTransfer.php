<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerTransfer extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'chat_id',
        'message_templates'
    ];

    protected $casts = [
        'message_templates' => 'array'
    ];
}
