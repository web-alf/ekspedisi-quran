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
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Admin name
            $table->string('phone_number'); // WhatsApp number
            $table->enum('type', ['admin', 'supervisor'])->default('admin'); // Contact type
            $table->string('role')->nullable(); // Additional role info
            $table->boolean('is_active')->default(true); // Enable/disable
            $table->text('notes')->nullable(); // Additional notes
            $table->timestamps();
            
            // Indexes
            $table->index(['type', 'is_active']);
            $table->unique(['phone_number', 'type']); // Prevent duplicate numbers per type
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_contacts');
    }
};