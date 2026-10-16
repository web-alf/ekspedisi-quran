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
        Schema::create('no_resi_sequences', function (Blueprint $table) {
            $table->id();
            $table->year('year')->unique()->comment('Tahun untuk sequence (YYYY)');
            $table->unsignedInteger('last_number')->default(0)->comment('Nomor terakhir yang digunakan');
            $table->timestamps();

            // Index untuk performa
            $table->index('year');
        });

        // Insert sequence untuk tahun saat ini (idempotent)
        // updateOrInsert: jika year sudah ada, skip; jika belum ada, insert
        DB::table('no_resi_sequences')->updateOrInsert(
            ['year' => date('Y')],
            [
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('no_resi_sequences');
    }
};
