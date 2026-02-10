<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->json('mail_settings')->nullable();
            $table->json('sms_settings')->nullable();
            $table->json('whatsapp_settings')->nullable();
            $table->json('lead_assignment_settings')->nullable();
            $table->json('welcome_message_settings')->nullable();
            $table->json('daily_report_settings')->nullable();
            $table->json('sales_mail_settings')->nullable();
            $table->json('sales_notification_settings')->nullable();
            $table->json('vapi_settings')->nullable();
            $table->json('user_notification_settings')->nullable();
            $table->json('group_notification_settings')->nullable();
            $table->json('facebook_settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
