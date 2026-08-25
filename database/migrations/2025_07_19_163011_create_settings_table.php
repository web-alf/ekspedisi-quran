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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->text('value')->nullable();
            $table->string('type')->default('text'); // text, textarea, number, boolean, image, file
            $table->text('description')->nullable();
            $table->json('options')->nullable(); // For select options, validation rules, etc.
            $table->string('group')->default('general'); // Group settings by category
            $table->integer('sort_order')->default(0);
            $table->boolean('is_public')->default(false); // Can be displayed on public pages
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
