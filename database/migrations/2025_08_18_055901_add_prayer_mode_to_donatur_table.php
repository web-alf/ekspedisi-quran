<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('donatur', function (Blueprint $table) {
            // Add new prayer_mode enum field
            $table->enum('prayer_mode', ['semua_donatur', 'customize_individual', 'mixed'])
                ->default('semua_donatur')
                ->after('doa_untuk_semua')
                ->comment('Mode for prayer/wakif name: semua_donatur = all use donatur name, customize_individual = each can have different name, mixed = combination');
        });

        // Migrate existing data based on current boolean fields
        DB::statement("
            UPDATE donatur 
            SET prayer_mode = CASE 
                WHEN semua_atas_nama_donatur = 1 THEN 'semua_donatur'
                WHEN customize_individual = 1 THEN 'customize_individual'
                ELSE 'semua_donatur'
            END
        ");

        // After migration, we can drop the old boolean fields
        Schema::table('donatur', function (Blueprint $table) {
            $table->dropColumn(['semua_atas_nama_donatur', 'customize_individual']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donatur', function (Blueprint $table) {
            // Re-add the old boolean fields
            $table->boolean('semua_atas_nama_donatur')->default(true);
            $table->boolean('customize_individual')->default(false);
        });

        // Migrate data back to boolean fields
        DB::statement("
            UPDATE donatur 
            SET semua_atas_nama_donatur = CASE 
                WHEN prayer_mode = 'semua_donatur' THEN 1
                ELSE 0
            END,
            customize_individual = CASE 
                WHEN prayer_mode = 'customize_individual' THEN 1
                ELSE 0
            END
        ");

        Schema::table('donatur', function (Blueprint $table) {
            $table->dropColumn('prayer_mode');
        });
    }
};
