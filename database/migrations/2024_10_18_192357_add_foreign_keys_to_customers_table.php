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
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->after('id');
            $table->unsignedBigInteger('user_id')->nullable()->after('organization_id');
            $table->unsignedBigInteger('category_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('status_id')->nullable()->after('category_id');

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('status_id')->references('id')->on('statuses')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['category_id']);
            $table->dropForeign(['status_id']);
            $table->dropColumn(['organization_id', 'user_id', 'category_id', 'status_id']);
        });
    }
};
