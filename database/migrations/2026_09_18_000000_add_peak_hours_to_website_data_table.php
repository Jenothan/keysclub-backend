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
            if (!Schema::hasColumn('website_data', 'peak_start_time')) {
                $table->time('peak_start_time')->default('15:00:00')->after('full_day_pricing');
            }
            if (!Schema::hasColumn('website_data', 'peak_end_time')) {
                $table->time('peak_end_time')->default('20:00:00')->after('peak_start_time');
            }
            if (!Schema::hasColumn('website_data', 'peak_off_days')) {
                $table->json('peak_off_days')->nullable()->after('peak_end_time');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_data', function (Blueprint $table) {
            $table->dropColumn(['peak_start_time', 'peak_end_time', 'peak_off_days']);
        });
    }
};
