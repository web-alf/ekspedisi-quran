<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda siapa manager distribusi yang membawahi seorang pengguna.
 *
 * Kurir berada di bawah seorang manager distribusi (Nalurita dkk). Sebelum ini
 * hubungan itu hanya ada di luar sistem, sehingga keempat manager sama-sama
 * buta terhadap pembagian siapa membawahi siapa — dan satu-satunya penanda yang
 * ada hanyalah "kurir" sebagai role.
 *
 * Nullable dan nullOnDelete: pengguna lama tetap sah tanpa atasan, dan bila
 * managernya dihapus, bawahannya tidak ikut terhapus — hanya kehilangan
 * atasannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('manager_id')
                ->nullable()
                ->after('email')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropColumn('manager_id');
        });
    }
};
