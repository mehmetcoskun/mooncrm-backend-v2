<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'organization_id',
        'mail_settings',
        'sms_settings',
        'whatsapp_settings',
        'lead_assignment_settings',
        'welcome_message_settings',
        'daily_report_settings',
        'sales_mail_settings',
        'sales_notification_settings',
        'vapi_settings',
        'user_notification_settings',
        'facebook_settings',
    ];

    protected $casts = [
        'mail_settings' => 'array',
        'sms_settings' => 'array',
        'whatsapp_settings' => 'array',
        'lead_assignment_settings' => 'array',
        'welcome_message_settings' => 'array',
        'daily_report_settings' => 'array',
        'sales_mail_settings' => 'array',
        'sales_notification_settings' => 'array',
        'vapi_settings' => 'array',
        'user_notification_settings' => 'array',
        'facebook_settings' => 'array',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
