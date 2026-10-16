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
        Schema::create('packing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packing_box_id')->constrained('packing_boxes')->onDelete('cascade');
            $table->foreignId('pengiriman_id')->constrained('pengiriman');
            $table->foreignId('packed_by')->constrained('users');
            $table->timestamp('packed_at');
            $table->integer('urutan_dalam_box'); // Urutan 1-20 dalam kerdus
            $table->string('scan_method')->default('qr'); // qr atau manual
            $table->timestamps();
            
            // Unique constraint: satu pengiriman hanya bisa di satu box
            $table->unique('pengiriman_id');
            
            // Index untuk query
            $table->index(['packing_box_id', 'urutan_dalam_box']);
            $table->index(['packed_by', 'packed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packing_items');
    }
};