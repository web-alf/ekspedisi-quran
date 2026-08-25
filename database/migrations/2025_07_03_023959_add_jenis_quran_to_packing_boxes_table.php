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
        Schema::table('packing_boxes', function (Blueprint $table) {
            $table->foreignId('jenis_quran_id')->nullable()->after('kapasitas')
                ->constrained('jenis_quran')->nullOnDelete();
        });
        
        // Add unique constraint to packing_items for urutan to prevent duplicate sequence numbers
        Schema::table('packing_items', function (Blueprint $table) {
            $table->unique(['packing_box_id', 'urutan_dalam_box'], 'unique_box_urutan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packing_items', function (Blueprint $table) {
            $table->dropUnique('unique_box_urutan');
        });
        
        Schema::table('packing_boxes', function (Blueprint $table) {
            $table->dropForeign(['jenis_quran_id']);
            $table->dropColumn('jenis_quran_id');
        });
    }
};