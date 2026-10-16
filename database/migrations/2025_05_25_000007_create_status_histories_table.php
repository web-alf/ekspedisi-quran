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
        // Create status_histories table only if not exists
        if (!Schema::hasTable('status_histories')) {
            Schema::create('status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pengiriman_id')->constrained('pengiriman')->onDelete('cascade');
                $table->foreignId('status_from')->nullable()->constrained('status_pengiriman')->onDelete('set null');
                $table->foreignId('status_to')->constrained('status_pengiriman')->onDelete('cascade');
                $table->text('catatan')->nullable();
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
                $table->timestamps();
                
                // Indexes
                $table->index(['pengiriman_id', 'created_at']);
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_histories');
    }
};
