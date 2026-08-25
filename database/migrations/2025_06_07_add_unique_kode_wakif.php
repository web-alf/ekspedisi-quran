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
        Schema::table('wakif', function (Blueprint $table) {
            // Add unique constraint to kode_wakif
            $table->unique('kode_wakif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wakif', function (Blueprint $table) {
            $table->dropUnique(['kode_wakif']);
        });
    }
};
