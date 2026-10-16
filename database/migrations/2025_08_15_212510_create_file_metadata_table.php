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
        Schema::create('file_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique(); // File path relative to storage
            $table->string('original_name'); // Original filename
            $table->string('filename'); // Stored filename
            $table->unsignedBigInteger('size'); // File size in bytes
            $table->string('mime_type');
            $table->string('purpose')->default('general'); // images, documents, etc.
            $table->string('hash')->nullable(); // File hash for deduplication
            $table->json('metadata')->nullable(); // Additional metadata (dimensions, etc.)
            $table->unsignedInteger('download_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('last_accessed')->nullable();
            $table->timestamp('last_optimized')->nullable();
            $table->boolean('is_optimized')->default(false);
            $table->boolean('is_orphaned')->default(false);
            $table->boolean('is_quarantined')->default(false);
            $table->string('created_by')->nullable(); // User ID or system
            $table->timestamps();

            // Indexes for performance
            $table->index(['purpose', 'created_at']);
            $table->index(['is_orphaned', 'created_at']);
            $table->index(['last_accessed']);
            $table->index(['size']);
            $table->index(['mime_type']);
            $table->index(['hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_metadata');
    }
};
