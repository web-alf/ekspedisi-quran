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
            // Extend kode_kerdus column from 20 to 30 characters
            // Format: KB-YYYYMMDD-XXX-JENIS-XX (max ~25 chars)
            $table->string('kode_kerdus', 30)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packing_boxes', function (Blueprint $table) {
            // Revert back to 20 characters (this may cause data loss)
            $table->string('kode_kerdus', 20)->change();
        });
    }
};
