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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('title');
            $table->enum('channel', ['whatsapp', 'sms', 'email', 'phone'])->nullable();
            $table->string('lead_form_id')->nullable();
            $table->json('field_mappings')->nullable();
            $table->string('vapi_assistant_id')->nullable();
            $table->string('vapi_phone_number_id')->nullable();
            $table->boolean('is_global')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
