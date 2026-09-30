<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Hapus akses "Kelola Donatur" dari role manager.
     *
     * Permintaan tim: manajer fokus pada alur permintaan mushaf → pengiriman,
     * bukan mengelola data donatur (wilayah customer-service).
     *
     * Kenapa lewat migrasi, bukan hanya seeder: story `deploy` di Envoy.blade.php
     * TIDAK menjalankan seeder (seeder hanya jalan di story `deploy:seed`), jadi
     * perubahan RolePermissionSeeder saja tidak akan sampai ke server.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $manager = Role::where('name', 'manager')->first();

        if (! $manager) {
            return;
        }

        // Dicabut satu per satu supaya izin yang tidak ada tidak menggagalkan
        // migrasi, dan supaya jelas persisnya apa yang dihapus.
        foreach ([
            'donatur.read',
            'donatur.create',
            'donatur.update',
            'donatur.import',
            'donatur.export',
        ] as $permissionName) {
            $manager->revokePermissionTo($permissionName);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Kembalikan akses donatur ke manager (tanpa delete).
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $manager = Role::where('name', 'manager')->first();

        if ($manager) {
            foreach ([
                'donatur.create',
                'donatur.read',
                'donatur.update',
                'donatur.import',
                'donatur.export',
            ] as $permissionName) {
                $manager->givePermissionTo($permissionName);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
