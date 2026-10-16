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
        Schema::table('sertifikat', function (Blueprint $table) {
            $table->foreignId('donatur_id')->nullable()->after('wakaf_batch_id')->constrained('donatur')->onDelete('cascade');
            $table->boolean('is_consolidated')->default(false)->after('template_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sertifikat', function (Blueprint $table) {
            $table->dropForeign(['donatur_id']);
            $table->dropColumn(['donatur_id', 'is_consolidated']);
        });
    }
};
