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
        Schema::create('whatsapp_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_number'); // Phone number
            $table->string('message_type'); // text, template, test
            $table->string('template_name')->nullable(); // Template identifier
            $table->json('template_data')->nullable(); // Template variables
            $table->text('message_content'); // Final message content
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('attempts')->default(0);
            $table->string('external_id')->nullable(); // API response ID
            $table->json('api_response')->nullable(); // API response for debugging
            
            // Relations - Simplified
            $table->foreignId('mushaf_request_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('pengiriman_id')->nullable()->constrained('pengiriman')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Admin user
            
            $table->timestamps();
            
            // Essential indexes only
            $table->index(['status', 'created_at']);
            $table->index(['recipient_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notifications');
    }
};