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
        Schema::create('bulk_operations_log', function (Blueprint $table) {
            $table->id();
            $table->string('operation_type'); // 'status', 'address', 'both'
            $table->string('box_code'); // kode_kerdus
            $table->string('box_seal_code')->nullable();
            $table->unsignedBigInteger('user_id'); // who performed the operation
            $table->integer('items_count'); // number of items affected
            $table->json('pengiriman_ids'); // array of affected pengiriman IDs
            
            // Operation details
            $table->unsignedBigInteger('old_status_id')->nullable();
            $table->unsignedBigInteger('new_status_id')->nullable();
            $table->text('old_address')->nullable();
            $table->text('new_address')->nullable();
            $table->text('notes')->nullable();
            
            // Metadata
            $table->json('qr_data'); // original QR scan data
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('operation_timestamp');
            $table->integer('processing_time_ms')->nullable(); // time taken to process
            
            $table->timestamps();
            
            // Indexes
            $table->index(['box_code', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['operation_type', 'created_at']);
            $table->index('operation_timestamp');
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('old_status_id')->references('id')->on('status_pengiriman')->onDelete('set null');
            $table->foreign('new_status_id')->references('id')->on('status_pengiriman')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_operations_log');
    }
};