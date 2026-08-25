<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\JenisQuran;

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
                'is_active' => true,
            ],
            [
                'kode_jenis' => 'A6',
                'nama_jenis' => 'Al-Quran Ukuran A6',
                'deskripsi' => 'Al-Quran kecil ukuran A6, praktis untuk dibawa',
                'harga' => 50000,
                'is_active' => true,
            ],
            [
                'kode_jenis' => 'IQRO',
                'nama_jenis' => 'Buku Iqro Jilid 1-6',
                'deskripsi' => 'Buku Iqro lengkap jilid 1-6 untuk pembelajaran membaca Al-Quran dengan metode Iqro',
                'harga' => 20000,
                'is_active' => true,
            ]
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
