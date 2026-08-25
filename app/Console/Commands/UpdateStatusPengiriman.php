<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StatusPengiriman;
use Illuminate\Support\Facades\DB;

class UpdateStatusPengiriman extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:status-pengiriman';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update status pengiriman with 7 tahapan proses wakaf quran';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating status pengiriman...');

        // Status baru
        $statusBaru = [
            [
                'nama' => 'Proses Pemesanan',
                'slug' => 'pemesanan',
                'deskripsi' => 'Quran sedang dalam proses pemesanan',
                'warna' => 'purple',
                'urutan' => 1,
                'is_active' => true,
                'is_final' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Proses Produksi',
                'slug' => 'produksi',
                'deskripsi' => 'Quran sedang dalam proses produksi',
                'warna' => 'blue',
                'urutan' => 2,
                'is_active' => true,
                'is_final' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Proses Kedatangan/Penurunan',
                'slug' => 'kedatangan',
                'deskripsi' => 'Quran sudah datang dan dalam proses penurunan',
                'warna' => 'cyan',
                'urutan' => 3,
                'is_active' => true,
                'is_final' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Proses Packing',
                'slug' => 'packing',
                'deskripsi' => 'Quran sedang dalam proses penulisan nama, dokumentasi foto/video, dan wrapping',
                'warna' => 'teal',
                'urutan' => 4,
                'is_active' => true,
                'is_final' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Selesai Packing',
                'slug' => 'selesai-packing',
                'deskripsi' => 'Quran telah selesai dikemas dan siap untuk dikirim',
                'warna' => 'green',
                'urutan' => 5,
                'is_active' => true,
                'is_final' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Proses Pengiriman',
                'slug' => 'pengiriman',
                'deskripsi' => 'Quran sedang dalam proses pengiriman ke penerima manfaat',
                'warna' => 'yellow',
                'urutan' => 6,
                'is_active' => true,
                'is_final' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Diterima Penerima',
                'slug' => 'diterima',
                'deskripsi' => 'Quran telah sampai di tangan penerima manfaat',
                'warna' => 'green',
                'urutan' => 7,
                'is_active' => true,
                'is_final' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Batal',
                'slug' => 'batal',
                'deskripsi' => 'Pengiriman dibatalkan',
                'warna' => 'red',
                'urutan' => 99,
                'is_active' => true,
                'is_final' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Bersihkan status lama
        $this->info('Clearing old statuses...');
        DB::table('status_pengiriman')->truncate();
        
        // Masukkan status baru
        $this->info('Inserting new statuses...');
        foreach ($statusBaru as $status) {
            DB::table('status_pengiriman')->updateOrInsert(
                ['slug' => $status['slug']],
                $status
            );
        }

        $this->info('Status pengiriman updated successfully!');
        
        // Tampilkan status yang ada
        $statuses = StatusPengiriman::orderBy('urutan')->get();
        $this->table(
            ['ID', 'Nama', 'Slug', 'Warna', 'Urutan', 'Is Active', 'Is Final'],
            $statuses->map(function ($status) {
                return [
                    $status->id,
                    $status->nama,
                    $status->slug,
                    $status->warna,
                    $status->urutan,
                    $status->is_active ? 'Yes' : 'No',
                    $status->is_final ? 'Yes' : 'No',
                ];
            })
        );
        
        return Command::SUCCESS;
    }
}
