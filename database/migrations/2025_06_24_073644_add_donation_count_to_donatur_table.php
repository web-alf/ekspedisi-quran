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
        Schema::table('donatur', function (Blueprint $table) {
            $table->integer('donation_count')->default(1)->after('total_iqra_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donatur', function (Blueprint $table) {
            $table->dropColumn('donation_count');
        });
    }
};