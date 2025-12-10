<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $developerRole = Role::create(['title' => 'Developer', 'is_global' => true]);
        Role::create(['title' => 'Admin', 'is_global' => true]);
        Role::create(['title' => 'Danışman', 'is_global' => true]);
        Role::create(['title' => 'Hasta Koordinatörü', 'is_global' => true]);
        Role::create(['title' => 'Dijital Pazarlama Uzmanı', 'is_global' => true]);
        Role::create(['title' => 'Yönetici', 'is_global' => true]);

        $permissions = [
            // Kontrol Paneli
            ['title' => 'Kontrol Paneli', 'slug' => 'dashboard_Access', 'is_custom' => false, 'is_global' => true],

            // Firma
            ['title' => 'Firma', 'slug' => 'organization_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Firma', 'slug' => 'organization_Create', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Firma', 'slug' => 'organization_Edit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Firma', 'slug' => 'organization_Delete', 'is_custom' => false, 'is_global' => true],

            // Müşteri
            ['title' => 'Müşteri', 'slug' => 'customer_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Müşteri', 'slug' => 'customer_Create', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Müşteri', 'slug' => 'customer_Edit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Müşteri', 'slug' => 'customer_Delete', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Müşteri', 'slug' => 'customer_Export', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Müşteri', 'slug' => 'customer_BulkUpdate', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Müşteri', 'slug' => 'customer_BulkDelete', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Müşteri', 'slug' => 'customer_LogAccess', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Müşteri', 'slug' => 'customer_FileAccess', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Müşteri', 'slug' => 'customer_FileUpload', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Müşteri', 'slug' => 'customer_FileDelete', 'is_custom' => false, 'is_global' => true],

            // WhatsApp Sohbet
            ['title' => 'WhatsApp Sohbet', 'slug' => 'whatsapp_chat_Access', 'is_custom' => false, 'is_global' => true],

            // WhatsApp Oturum
            ['title' => 'WhatsApp Oturum', 'slug' => 'whatsapp_session_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'WhatsApp Oturum', 'slug' => 'whatsapp_session_Create', 'is_custom' => false, 'is_global' => true],
            ['title' => 'WhatsApp Oturum', 'slug' => 'whatsapp_session_Delete', 'is_custom' => false, 'is_global' => true],

            // İstatistik
            ['title' => 'İstatistik', 'slug' => 'statistic_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'İstatistik', 'slug' => 'statistic_Export', 'is_custom' => false, 'is_global' => true],

            // Rapor
            ['title' => 'Rapor', 'slug' => 'report_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Rapor', 'slug' => 'report_Export', 'is_custom' => false, 'is_global' => true],

            // Segment
            ['title' => 'Segment', 'slug' => 'segment_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Segment', 'slug' => 'segment_Create', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Segment', 'slug' => 'segment_Edit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Segment', 'slug' => 'segment_Delete', 'is_custom' => false, 'is_global' => true],

            // Pazarlama
            ['title' => 'Pazarlama', 'slug' => 'marketing_BulkMail', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Pazarlama', 'slug' => 'marketing_BulkSms', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Pazarlama', 'slug' => 'marketing_BulkWhatsapp', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Pazarlama', 'slug' => 'marketing_BulkCall', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Pazarlama', 'slug' => 'marketing_SendMail', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Pazarlama', 'slug' => 'marketing_SendSms', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Pazarlama', 'slug' => 'marketing_SendWhatsapp', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Pazarlama', 'slug' => 'marketing_SendCall', 'is_custom' => false, 'is_global' => true],

            // Şablon
            ['title' => 'E-Posta Şablon', 'slug' => 'email_template_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'E-Posta Şablon', 'slug' => 'email_template_Create', 'is_custom' => false, 'is_global' => true],
            ['title' => 'E-Posta Şablon', 'slug' => 'email_template_Edit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'E-Posta Şablon', 'slug' => 'email_template_Delete', 'is_custom' => false, 'is_global' => true],

            // SMS Şablon
            ['title' => 'SMS Şablon', 'slug' => 'sms_template_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'SMS Şablon', 'slug' => 'sms_template_Create', 'is_custom' => false, 'is_global' => true],
            ['title' => 'SMS Şablon', 'slug' => 'sms_template_Edit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'SMS Şablon', 'slug' => 'sms_template_Delete', 'is_custom' => false, 'is_global' => true],

            // WhatsApp Şablon
            ['title' => 'WhatsApp Şablon', 'slug' => 'whatsapp_template_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'WhatsApp Şablon', 'slug' => 'whatsapp_template_Create', 'is_custom' => false, 'is_global' => true],
            ['title' => 'WhatsApp Şablon', 'slug' => 'whatsapp_template_Edit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'WhatsApp Şablon', 'slug' => 'whatsapp_template_Delete', 'is_custom' => false, 'is_global' => true],

            // WhatsApp Mesaj Gönderim Durumu
            ['title' => 'WhatsApp Mesaj Gönderim Durumu', 'slug' => 'whatsapp_message_status_Access', 'is_custom' => false, 'is_global' => true],

            // E-Posta Mesaj Gönderim Durumu
            ['title' => 'E-Posta Mesaj Gönderim Durumu', 'slug' => 'email_message_status_Access', 'is_custom' => false, 'is_global' => true],

            // Web Form
            ['title' => 'Web Form', 'slug' => 'web_form_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Web Form', 'slug' => 'web_form_Create', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Web Form', 'slug' => 'web_form_Edit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Web Form', 'slug' => 'web_form_Delete', 'is_custom' => false, 'is_global' => true],

            // Kullanıcı
            ['title' => 'Kullanıcı', 'slug' => 'user_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Kullanıcı', 'slug' => 'user_Create', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Kullanıcı', 'slug' => 'user_Edit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Kullanıcı', 'slug' => 'user_Delete', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Kullanıcı', 'slug' => 'user_ApiKeyAccess', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Kullanıcı', 'slug' => 'user_ApiKeyCreate', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Kullanıcı', 'slug' => 'user_ApiKeyDelete', 'is_custom' => false, 'is_global' => true],

            // Rol
            ['title' => 'Rol', 'slug' => 'role_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Rol', 'slug' => 'role_Create', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Rol', 'slug' => 'role_Edit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Rol', 'slug' => 'role_Delete', 'is_custom' => false, 'is_global' => true],

            // Yetki
            ['title' => 'Yetki', 'slug' => 'permission_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Yetki', 'slug' => 'permission_Create', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Yetki', 'slug' => 'permission_Edit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Yetki', 'slug' => 'permission_Delete', 'is_custom' => false, 'is_global' => true],

            // Kategori
            ['title' => 'Kategori', 'slug' => 'category_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Kategori', 'slug' => 'category_Create', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Kategori', 'slug' => 'category_Edit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Kategori', 'slug' => 'category_Delete', 'is_custom' => false, 'is_global' => true],

            // Hizmet
            ['title' => 'Hizmet', 'slug' => 'service_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Hizmet', 'slug' => 'service_Create', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Hizmet', 'slug' => 'service_Edit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Hizmet', 'slug' => 'service_Delete', 'is_custom' => false, 'is_global' => true],

            // Durum
            ['title' => 'Durum', 'slug' => 'status_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Durum', 'slug' => 'status_Create', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Durum', 'slug' => 'status_Edit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Durum', 'slug' => 'status_Delete', 'is_custom' => false, 'is_global' => true],

            // Etiket
            ['title' => 'Etiket', 'slug' => 'tag_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Etiket', 'slug' => 'tag_Create', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Etiket', 'slug' => 'tag_Edit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Etiket', 'slug' => 'tag_Delete', 'is_custom' => false, 'is_global' => true],

            // Doktor
            ['title' => 'Doktor', 'slug' => 'doctor_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Doktor', 'slug' => 'doctor_Create', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Doktor', 'slug' => 'doctor_Edit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Doktor', 'slug' => 'doctor_Delete', 'is_custom' => false, 'is_global' => true],

            // Otel
            ['title' => 'Otel', 'slug' => 'hotel_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Otel', 'slug' => 'hotel_Create', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Otel', 'slug' => 'hotel_Edit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Otel', 'slug' => 'hotel_Delete', 'is_custom' => false, 'is_global' => true],

            // Transfer
            ['title' => 'Transfer', 'slug' => 'transfer_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Transfer', 'slug' => 'transfer_Create', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Transfer', 'slug' => 'transfer_Edit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Transfer', 'slug' => 'transfer_Delete', 'is_custom' => false, 'is_global' => true],

            // Randevu
            ['title' => 'Randevu', 'slug' => 'appointment_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Randevu', 'slug' => 'appointment_Export', 'is_custom' => false, 'is_global' => true],

            // Takvim
            ['title' => 'Takvim', 'slug' => 'calendar_Access', 'is_custom' => false, 'is_global' => true],

            // Ayarlar
            ['title' => 'Ayarlar', 'slug' => 'setting_Access', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Ayarlar', 'slug' => 'setting_Mail', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Ayarlar', 'slug' => 'setting_Sms', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Ayarlar', 'slug' => 'setting_Whatsapp', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Ayarlar', 'slug' => 'setting_DailyReport', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Ayarlar', 'slug' => 'setting_SalesMail', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Ayarlar', 'slug' => 'setting_SalesNotification', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Ayarlar', 'slug' => 'setting_LeadAssignment', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Ayarlar', 'slug' => 'setting_WelcomeMessage', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Ayarlar', 'slug' => 'setting_Vapi', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Ayarlar', 'slug' => 'setting_UserNotification', 'is_custom' => false, 'is_global' => true],
            ['title' => 'Ayarlar', 'slug' => 'setting_Facebook', 'is_custom' => false, 'is_global' => true],

            // AI Sesli Asistan
            ['title' => 'AI Sesli Asistan', 'slug' => 'vapi_AssistantAccess', 'is_custom' => false, 'is_global' => true],
            ['title' => 'AI Sesli Asistan', 'slug' => 'vapi_AssistantCreate', 'is_custom' => false, 'is_global' => true],
            ['title' => 'AI Sesli Asistan', 'slug' => 'vapi_AssistantEdit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'AI Sesli Asistan', 'slug' => 'vapi_AssistantDelete', 'is_custom' => false, 'is_global' => true],
            ['title' => 'AI Sesli Asistan', 'slug' => 'vapi_PhoneNumberAccess', 'is_custom' => false, 'is_global' => true],
            ['title' => 'AI Sesli Asistan', 'slug' => 'vapi_PhoneNumberCreate', 'is_custom' => false, 'is_global' => true],
            ['title' => 'AI Sesli Asistan', 'slug' => 'vapi_PhoneNumberEdit', 'is_custom' => false, 'is_global' => true],
            ['title' => 'AI Sesli Asistan', 'slug' => 'vapi_PhoneNumberDelete', 'is_custom' => false, 'is_global' => true],
            ['title' => 'AI Sesli Asistan', 'slug' => 'vapi_PhoneNumberCall', 'is_custom' => false, 'is_global' => true],

            // Facebook Lead
            ['title' => 'Facebook Lead', 'slug' => 'facebook_lead_Access', 'is_custom' => false, 'is_global' => true],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

        $user = User::create([
            'name' => 'Mehmet',
            'email' => 'mehmet@moonworkshop.com',
            'password' => 'sjL&0gos5WPIT$$U',
        ]);
        $user->roles()->attach($developerRole);

        $user2 = User::create([
            'name' => 'Murat',
            'email' => 'murat@moonworkshop.com',
            'password' => 'LCJ@FNdTYq6h7hlb',
        ]);
        $user2->roles()->attach($developerRole);

        $user3 = User::create([
            'name' => 'Emirhan',
            'email' => 'emirhan@moonworkshop.com',
            'password' => 'O*1S2I*fr#BrK6Uv',
        ]);
        $user3->roles()->attach($developerRole);

        $categories = [
            ['title' => 'Website', 'channel' => 'whatsapp', 'is_global' => true],
            ['title' => 'Landing Page', 'channel' => 'whatsapp', 'is_global' => true],
            ['title' => 'Şirket Hattı', 'channel' => 'whatsapp', 'is_global' => true],
            ['title' => 'Meta', 'channel' => 'whatsapp', 'is_global' => true],
            ['parent_id' => 4, 'title' => 'Instagram', 'channel' => 'whatsapp', 'is_global' => true],
            ['parent_id' => 4, 'title' => 'Messenger', 'channel' => 'whatsapp', 'is_global' => true],
            ['title' => 'TikTok', 'channel' => 'whatsapp', 'is_global' => true],
            ['title' => 'Referans', 'channel' => 'whatsapp', 'is_global' => true],
            ['title' => 'Acente', 'channel' => 'whatsapp', 'is_global' => true],
            ['title' => 'Kurum İçi', 'channel' => 'whatsapp', 'is_global' => true],
            ['title' => 'WhatClinic', 'channel' => 'whatsapp', 'is_global' => true],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }

        $statuses = [
            ['title' => 'Yeni Form', 'background_color' => '#3B82F6', 'is_global' => true],
            ['title' => 'Ön Bilgi', 'background_color' => '#6366F1', 'is_global' => true],
            ['title' => 'Fotoğraf Bekleniyor', 'background_color' => '#8B5CF6', 'is_global' => true],
            ['title' => 'Teklif Bekliyor', 'background_color' => '#F59E0B', 'is_global' => true],
            ['title' => 'Teklif Yollandı', 'background_color' => '#FBBF24', 'is_global' => true],
            ['title' => 'Olumlu', 'background_color' => '#FACC15', 'is_global' => true],
            ['title' => 'Bilet Bekliyor / Bilet Takip', 'background_color' => '#10B981', 'is_global' => true],
            ['title' => 'Satış', 'background_color' => '#059669', 'is_global' => true],
            ['title' => 'Satış İptali', 'background_color' => '#EF4444', 'is_global' => true],
            ['title' => 'Olumsuz', 'background_color' => '#DC2626', 'is_global' => true],
            ['title' => 'Engelli/Spam', 'background_color' => '#9CA3AF', 'is_global' => true],
            ['title' => 'Cevap Vermedi', 'background_color' => '#6B7280', 'is_global' => true],
            ['title' => 'İlgisiz', 'background_color' => '#4B5563', 'is_global' => true],
            ['title' => 'Ulaşılamadı', 'background_color' => '#6B7280', 'is_global' => true],
        ];

        foreach ($statuses as $status) {
            Status::create($status);
        }
    }
}
