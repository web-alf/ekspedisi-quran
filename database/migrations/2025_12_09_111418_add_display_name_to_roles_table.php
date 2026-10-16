<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
        });

        // Populate existing roles with display names
        DB::table('roles')->update([
            'display_name' => DB::raw("CASE name
                WHEN 'super-admin' THEN 'Super Admin'
                WHEN 'customer-service' THEN 'Customer Service'
                WHEN 'warehouse' THEN 'Staff Gudang'
                WHEN 'supervisor' THEN 'Supervisor Gudang'
                WHEN 'courier' THEN 'Kurir'
                ELSE name
            END")
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
