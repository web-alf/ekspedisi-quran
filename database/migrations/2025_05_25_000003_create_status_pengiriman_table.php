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
        Schema::create('status_pengiriman', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->string('slug', 100)->unique();
            $table->text('deskripsi')->nullable();
            $table->string('warna', 20)->default('gray'); // gray, blue, yellow, green, red, purple
            $table->integer('urutan')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_final')->default(false); // Status akhir seperti 'diterima', 'batal'
            $table->timestamps();
            
            // Indexes
            $table->index('slug');
            $table->index(['is_active', 'urutan']);
        });
        
        // Insert default status - DATA DIPINDAH KE SEEDER
        // DB::table('status_pengiriman')->insert($defaultStatuses);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_pengiriman');
    }
};
