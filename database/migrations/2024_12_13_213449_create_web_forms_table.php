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
        Schema::create('web_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid()->unique();
            $table->string('title');
            $table->json('fields')->nullable();
            $table->json('styles')->nullable();
            $table->string('redirect_url')->nullable();
            $table->string('email_recipients')->nullable();
            $table->string('domain')->default('*');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->json('rate_limit_settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_forms');
    }
};
