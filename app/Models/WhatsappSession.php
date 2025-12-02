<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappSession extends Model
{
    protected $fillable = [
        'organization_id',
        'title',
        'phone',
        'is_admin'
    ];

    protected $casts = [
        'is_admin' => 'boolean'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
