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
        Schema::create('whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('starsender'); // Provider name
            $table->string('api_key')->nullable(); // API Key
            $table->string('base_url')->default('https://api.starsender.online'); // Base URL
            $table->string('sender_number')->nullable(); // WhatsApp sender number
            $table->json('settings')->nullable(); // Additional settings
            $table->boolean('is_active')->default(false); // Enable/disable
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_settings');
    }
};