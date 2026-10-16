<?php

namespace Database\Seeders;

use App\Models\JenisQuran;
use Illuminate\Database\Seeder;

class JenisQuranSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jenisQuran = [
            [
                'kode_jenis' => 'A5',
                'nama_jenis' => 'Al-Quran Ukuran A5',
                'deskripsi' => 'Al-Quran standar ukuran A5 dengan font yang mudah dibaca',
                'harga' => 100000,
                // Isi 1 doz (= 1 kerdus penuh) per ukuran. Nilai ini SENGAJA
                // ditulis di seeder: sebelumnya kolom ini tidak disebut sama
                // sekali, sehingga updateOrCreate menimpanya kembali ke default
                // skema (20) setiap kali seeder dijalankan — A6 dan IQRO ikut
                // jadi 20, padahal aslinya 40 dan 160.
                'default_capacity' => 20,
                'is_active' => true,
            ],
            [
                'kode_jenis' => 'A6',
                'nama_jenis' => 'Al-Quran Ukuran A6',
                'deskripsi' => 'Al-Quran kecil ukuran A6, praktis untuk dibawa',
                'harga' => 50000,
                'default_capacity' => 40,
                'is_active' => true,
            ],
            [
                'kode_jenis' => 'IQRO',
                'nama_jenis' => 'Buku Iqro Jilid 1-6',
                'deskripsi' => 'Buku Iqro lengkap jilid 1-6 untuk pembelajaran membaca Al-Quran dengan metode Iqro',
                'harga' => 20000,
                'default_capacity' => 160,
                'is_active' => true,
            ],
        ];

        // Use updateOrCreate to avoid duplicates
        foreach ($jenisQuran as $jenis) {
            JenisQuran::updateOrCreate(
                ['kode_jenis' => $jenis['kode_jenis']], // Find by kode_jenis
                $jenis // Update or create with this data
            );
        }

        $this->command->info('✅ JenisQuran seeded successfully!');
    }
}
