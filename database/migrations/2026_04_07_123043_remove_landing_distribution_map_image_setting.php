<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove landing_distribution_map_image setting as it's replaced by video
        DB::table('settings')->where('key', 'landing_distribution_map_image')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Setting will be recreated by seeder if needed
    }
};
