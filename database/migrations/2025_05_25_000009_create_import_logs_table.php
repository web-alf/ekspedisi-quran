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
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('filename');
            $table->integer('total_rows');
            $table->integer('success_rows');
            $table->integer('failed_rows');
            $table->json('error_details')->nullable();
            $table->string('file_path');
            $table->enum('status', ['processing', 'completed', 'completed_with_errors', 'failed'])->default('processing');
            $table->timestamps();
            
            // Index
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
