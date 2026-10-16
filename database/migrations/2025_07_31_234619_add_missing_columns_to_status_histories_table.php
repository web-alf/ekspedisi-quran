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
        Schema::table('status_histories', function (Blueprint $table) {
            $table->string('lokasi')->nullable()->after('catatan');
            $table->decimal('latitude', 10, 8)->nullable()->after('lokasi');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->json('dokumentasi')->nullable()->after('longitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('status_histories', function (Blueprint $table) {
            $table->dropColumn(['lokasi', 'latitude', 'longitude', 'dokumentasi']);
        });
    }
};
