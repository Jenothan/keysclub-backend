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
        if (!Schema::hasColumn('users', 'is_member')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_member')->default(false)->after('is_guest');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_member')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_member');
            });
        }
    }
};
