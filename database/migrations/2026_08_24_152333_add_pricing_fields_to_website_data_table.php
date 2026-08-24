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
        Schema::table('website_data', function (Blueprint $table) {
            $table->string('court_pricing')->nullable();
            $table->string('membership_pricing')->nullable();
            $table->string('full_day_pricing')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_data', function (Blueprint $table) {
            $table->dropColumn(['court_pricing', 'membership_pricing', 'full_day_pricing']);
        });
    }
};
