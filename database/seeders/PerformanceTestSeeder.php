<?php

namespace Database\Seeders;

use App\Models\DailyPackingTask;
use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Models\WakafBatch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PerformanceTestSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Seeding performance test data...');

        // Create users
        $this->seedUsers();

        // Create reference data
        $this->seedReferenceData();

        // Create test donatur
        $this->seedDonatur();

        // Create pengiriman data
        $this->seedPengiriman();

        // Create warehouse data
        $this->seedWarehouseData();

        $this->command->info('✅ Performance test data seeded successfully!');
    }

    private function seedUsers(): void
    {
        $this->command->info('Creating test users...');

        // Admin user
        User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Performance Test Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Warehouse users
        User::factory(10)->create();
    }

    private function seedReferenceData(): void
    {
        $this->command->info('Creating reference data...');

        // Jenis Quran
        $jenisQuranData = [
            ['nama_jenis' => 'Al-Quran A5', 'deskripsi' => 'Al-Quran ukuran A5'],
            ['nama_jenis' => 'Al-Quran A4', 'deskripsi' => 'Al-Quran ukuran A4'],
            ['nama_jenis' => 'Al-Quran Pocket', 'deskripsi' => 'Al-Quran ukuran saku'],
            ['nama_jenis' => 'Al-Quran Jumbo', 'deskripsi' => 'Al-Quran ukuran jumbo'],
        ];

        foreach ($jenisQuranData as $data) {
            JenisQuran::firstOrCreate(['nama_jenis' => $data['nama_jenis']], $data);
        }

        // Status Pengiriman
        $statusData = [
            ['nama_status' => 'Diproses', 'deskripsi' => 'Sedang diproses', 'icon' => 'clock'],
            ['nama_status' => 'Dikemas', 'deskripsi' => 'Sedang dikemas', 'icon' => 'package'],
            ['nama_status' => 'Dikirim', 'deskripsi' => 'Sedang dikirim', 'icon' => 'truck'],
            ['nama_status' => 'Sampai', 'deskripsi' => 'Sudah sampai', 'icon' => 'check'],
        ];

        foreach ($statusData as $data) {
            StatusPengiriman::firstOrCreate(['nama_status' => $data['nama_status']], $data);
        }
    }

    private function seedDonatur(): void
    {
        $this->command->info('Creating test donatur...');

        // Create test donatur for consistent testing
        $provinces = [
            'DKI Jakarta', 'Jawa Barat', 'Jawa Tengah', 'Jawa Timur',
            'Sumatera Utara', 'Sumatera Barat', 'Sulawesi Selatan', 'Kalimantan Timur',
        ];

        foreach ($provinces as $province) {
            Donatur::factory(25)->create([
                'provinsi' => $province,
            ]);
        }

        // Create additional random donatur
        Donatur::factory(500)->create();
    }

    private function seedPengiriman(): void
    {
        $this->command->info('Creating test pengiriman data...');

        $donaturs = Donatur::all();
        $jenisQurans = JenisQuran::all();
        $statuses = StatusPengiriman::all();

        if ($donaturs->isEmpty() || $jenisQurans->isEmpty() || $statuses->isEmpty()) {
            $this->command->warn('Reference data not found, creating minimal data...');

            return;
        }

        // Create pengiriman distributed across different dates
        $dates = [
            now()->subMonths(3),
            now()->subMonths(2),
            now()->subMonth(),
            now()->subWeeks(3),
            now()->subWeeks(2),
            now()->subWeek(),
            now()->subDays(7),
            now()->subDays(3),
            now()->subDay(),
            now(),
        ];

        foreach ($dates as $date) {
            $count = rand(20, 80);

            Pengiriman::factory($count)->create([
                'donatur_id' => $donaturs->random()->id,
                'jenis_quran_id' => $jenisQurans->random()->id,
                'status_id' => $statuses->random()->id,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        // Create additional recent pengiriman for today's performance tests
        Pengiriman::factory(100)->create([
            'donatur_id' => $donaturs->random()->id,
            'jenis_quran_id' => $jenisQurans->random()->id,
            'status_id' => $statuses->random()->id,
            'created_at' => today(),
            'updated_at' => today(),
        ]);
    }

    private function seedWarehouseData(): void
    {
        $this->command->info('Creating warehouse test data...');

        $users = User::all();
        $jenisQurans = JenisQuran::all();

        if ($users->isEmpty() || $jenisQurans->isEmpty()) {
            $this->command->warn('Users or JenisQuran data not found, skipping warehouse data...');

            return;
        }

        // Create daily packing tasks for the last 7 days
        $dates = collect(range(0, 6))->map(fn ($i) => today()->subDays($i));

        foreach ($dates as $date) {
            $userCount = rand(3, 8);
            $selectedUsers = $users->random($userCount);

            foreach ($selectedUsers as $user) {
                $task = DailyPackingTask::create([
                    'user_id' => $user->id,
                    'assigned_by' => $users->random()->id,
                    'tanggal_tugas' => $date,
                    'target_packing' => rand(20, 50),
                    'jumlah_selesai' => $date->isPast() ? rand(15, 45) : rand(0, 10),
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);

                // Create packing boxes for this task
                $boxCount = rand(3, 8);
                for ($i = 0; $i < $boxCount; $i++) {
                    $kapasitas = rand(10, 30);
                    $jumlahTerisi = $date->isPast() ? rand(5, $kapasitas) : rand(0, $kapasitas - 5);

                    PackingBox::create([
                        'daily_packing_task_id' => $task->id,
                        'jenis_quran_id' => $jenisQurans->random()->id,
                        'kode_box' => 'BOX-'.$date->format('Ymd').'-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                        'kapasitas' => $kapasitas,
                        'jumlah_terisi' => $jumlahTerisi,
                        'status' => $jumlahTerisi >= $kapasitas ? 'completed' : ($jumlahTerisi > 0 ? 'filling' : 'empty'),
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);
                }
            }
        }

        // Create wakaf batches for testing
        $this->seedWakafBatches();
    }

    private function seedWakafBatches(): void
    {
        $this->command->info('Creating wakaf batch test data...');

        $donaturs = Donatur::limit(50)->get();

        if ($donaturs->isEmpty()) {
            return;
        }

        foreach ($donaturs as $donatur) {
            if (rand(1, 3) === 1) { // 33% chance to have wakaf batch
                WakafBatch::create([
                    'donatur_id' => $donatur->id,
                    'kode_wakaf' => 'WB-'.now()->format('Y').'-'.str_pad(rand(1, 999), 5, '0', STR_PAD_LEFT),
                    'nama_wakif' => $donatur->nama_donatur,
                    'jumlah_quran' => rand(50, 500),
                    'status' => collect(['pending', 'processing', 'completed'])->random(),
                    'tanggal_wakaf' => now()->subDays(rand(1, 180)),
                ]);
            }
        }
    }
}
