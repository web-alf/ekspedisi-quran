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
        Schema::create('job_progress', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->unique()->index();
            $table->string('job_type'); // bulk_status_update, bulk_box_processing, etc.
            $table->string('title'); // Human readable title
            $table->unsignedBigInteger('user_id')->index();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->integer('total_items')->default(0);
            $table->integer('processed_items')->default(0);
            $table->integer('failed_items')->default(0);
            $table->decimal('progress_percentage', 5, 2)->default(0); // 0.00 to 100.00
            $table->json('metadata')->nullable(); // Additional job-specific data
            $table->json('results')->nullable(); // Job results and statistics
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'status']);
            $table->index(['job_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_progress');
    }
};
