<?php

namespace Database\Seeders;

use App\Models\User;
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
            
            // Data transaksional bisa ditambah later
            // WakifSeeder::class,     // Optional: sample data
            // PengirimanSeeder::class, // Optional: sample data
        ]);
    }
}
