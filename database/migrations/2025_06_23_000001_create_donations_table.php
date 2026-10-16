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
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->string('donatur_name');
            $table->string('donatur_phone', 20);
            $table->string('donatur_email')->nullable();
            $table->date('donation_date');
            
            // Jumlah per jenis wakaf
            $table->integer('total_a5_count')->default(0);
            $table->integer('total_a6_count')->default(0);
            $table->integer('total_iqra_count')->default(0);
            
            // Jenis wakaf yang dipilih (JSON array)
            $table->json('jenis_wakaf_dipilih');
            
            // Setting wakif
            $table->boolean('semua_atas_nama_donatur')->default(true);
            $table->text('doa_untuk_semua')->nullable();
            $table->boolean('customize_individual')->default(false);
            
            // Future expansion
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->text('catatan')->nullable();
            
            // Audit fields
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            // Indexes
            $table->index('donation_date');
            $table->index('created_by');
            $table->index('donatur_phone');
            
            // Foreign key
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};