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
        Schema::create('jenis_quran', function (Blueprint $table) {
            $table->id();
            $table->string('kode_jenis', 20)->unique();
            $table->string('nama_jenis', 100);
            $table->text('deskripsi')->nullable();
            $table->decimal('harga', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Index
            $table->index('kode_jenis');
            $table->index('is_active');
        });
        
        // Insert default jenis quran - DATA DIPINDAH KE SEEDER
        // DB::table('jenis_quran')->insert($defaultJenis);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jenis_quran');
    }
};
