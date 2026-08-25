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
        Schema::table('status_pengiriman', function (Blueprint $table) {
            $table->string('icon', 10)->nullable()->after('warna');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('status_pengiriman', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
