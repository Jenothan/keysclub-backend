<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop unique constraint on booking_reference in PostgreSQL safely
        DB::statement("ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_booking_reference_unique;");
        DB::statement("ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_booking_reference_key;");

        // 2. Add non-unique index on booking_reference
        try {
            Schema::table('bookings', function (Blueprint $table) {
                $table->index('booking_reference');
            });
        } catch (\Exception $e) {
            // Ignore if index already exists
        }

        // 3. Change status column type to VARCHAR(50) in PostgreSQL
        DB::statement("ALTER TABLE bookings ALTER COLUMN status TYPE VARCHAR(50);");
        DB::statement("ALTER TABLE bookings ALTER COLUMN status SET DEFAULT 'Pending';");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_booking_reference_unique UNIQUE (booking_reference);");
    }
};
