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
            $table->unsignedBigInteger('mushaf_request_id')->nullable()->after('created_by');
            $table->foreign('mushaf_request_id')->references('id')->on('mushaf_requests')->onDelete('set null');
            $table->index('mushaf_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->dropForeign(['mushaf_request_id']);
            $table->dropIndex(['mushaf_request_id']);
            $table->dropColumn('mushaf_request_id');
        });
    }
};
