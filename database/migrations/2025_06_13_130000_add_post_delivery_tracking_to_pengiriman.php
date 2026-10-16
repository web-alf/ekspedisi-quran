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
        Schema::table('pengiriman', function (Blueprint $table) {
            // Post-delivery tracking columns
            $table->timestamp('received_at')->nullable()->after('catatan');
            $table->string('received_by', 255)->nullable()->after('received_at');
            $table->string('receiver_contact', 20)->nullable()->after('received_by');
            $table->json('delivery_proof')->nullable()->after('receiver_contact');
            $table->text('delivery_notes')->nullable()->after('delivery_proof');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->dropColumn([
                'received_at',
                'received_by', 
                'receiver_contact',
                'delivery_proof',
                'delivery_notes'
            ]);
        });
    }
};
