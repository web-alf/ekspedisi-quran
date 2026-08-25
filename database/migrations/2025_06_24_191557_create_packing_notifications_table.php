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
        Schema::create('packing_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('daily_packing_task_id')->nullable()->constrained('daily_packing_tasks');
            $table->enum('type', ['reminder', 'warning', 'alert', 'completion']);
            $table->enum('level', ['info', 'warning', 'critical']);
            $table->string('title');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->json('meta_data')->nullable(); // Additional data like progress percentage
            $table->timestamps();
            
            // Index untuk query
            $table->index(['user_id', 'is_read']);
            $table->index(['created_at', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packing_notifications');
    }
};