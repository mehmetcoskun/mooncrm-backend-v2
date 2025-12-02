<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('country')->nullable();
            $table->longText('notes')->nullable();
            $table->integer('duplicate_count')->default(0);
            $table->boolean('duplicate_checked')->default(false);
            $table->json('phone_calls')->nullable();
            $table->json('reminder')->nullable();
            $table->json('sales_info')->nullable();
            $table->json('travel_info')->nullable();
            $table->longText('payment_notes')->nullable();
            $table->string('ad_name')->nullable();
            $table->string('adset_name')->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('lead_form_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
