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
        Schema::table('whatsapp_notifications', function (Blueprint $table) {
            // Add indexes for common queries
            $table->index(['status', 'created_at'], 'idx_status_created_at');
            $table->index(['message_type', 'created_at'], 'idx_message_type_created_at');
            $table->index(['recipient_number', 'created_at'], 'idx_recipient_created_at');
            $table->index(['created_at', 'status'], 'idx_created_at_status');
            
            // Composite index for filtering
            $table->index(['status', 'message_type', 'created_at'], 'idx_status_type_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_notifications', function (Blueprint $table) {
            $table->dropIndex('idx_status_created_at');
            $table->dropIndex('idx_message_type_created_at');
            $table->dropIndex('idx_recipient_created_at');
            $table->dropIndex('idx_created_at_status');
            $table->dropIndex('idx_status_type_created');
        });
    }
};