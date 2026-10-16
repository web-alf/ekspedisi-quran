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
        Schema::create('wakaf_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('donation_id');
            $table->unsignedBigInteger('pengiriman_id')->nullable(); // Will be filled when pengiriman is created
            
            // Wakaf type and sequence
            $table->enum('wakaf_type', ['A5', 'A6', 'IQRA']);
            $table->integer('sequence_in_type'); // 1, 2, 3, ... untuk setiap type
            $table->integer('global_sequence'); // 1, 2, 3, ... untuk seluruh donation
            
            // Wakif details
            $table->string('wakif_name');
            $table->text('doa_request')->nullable();
            $table->string('relationship_to_donatur', 100)->nullable(); // "Diri sendiri", "Orang tua", dll
            
            // Status tracking
            $table->enum('status', ['pending', 'processed', 'shipped', 'delivered'])->default('pending');
            $table->text('catatan')->nullable();
            
            // Audit fields
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            // Indexes
            $table->index(['donation_id', 'wakaf_type']);
            $table->index(['donation_id', 'global_sequence']);
            $table->index('pengiriman_id');
            $table->index('status');
            
            // Foreign keys
            $table->foreign('donation_id')->references('id')->on('donations')->onDelete('cascade');
            $table->foreign('pengiriman_id')->references('id')->on('pengiriman')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wakaf_items');
    }
};