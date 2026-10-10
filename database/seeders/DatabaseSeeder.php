<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Jalankan semua seeder dalam urutan yang benar
        $this->call([
            // Master data harus didahulukan
            JenisQuranSeeder::class,
            StatusPengirimanSeeder::class,
            SystemSettingsSeeder::class,
            SettingSeeder::class,
            LegalSettingsSeeder::class,

            // Spatie role & permission setup
            RolePermissionSeeder::class,
            UserSeeder::class,

            // Landing page content seeders
            TestimonialSeeder::class,
            GallerySeeder::class,
            FaqSeeder::class,

            // Notifikasi WhatsApp: template bawaan (bisa disunting dari halaman
            // pengaturan; seeder ini tidak menimpa yang sudah ada).
            WhatsAppTemplateSeeder::class,

            // Data transaksional bisa ditambah later
            // WakifSeeder::class,     // Optional: sample data
            // PengirimanSeeder::class, // Optional: sample data
        ]);
    }
}
