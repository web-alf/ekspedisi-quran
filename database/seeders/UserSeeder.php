<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\Gudang;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Akun lama yang tidak dipakai lagi: gudang tidak punya Supervisor — penanggung jawabnya
     * Manager Gudang (keputusan pemilik, ditegaskan 30/10/2026). Akun yang sudah ada
     * dinonaktifkan, bukan dihapus, karena namanya bisa tercatat di data lama.
     */
    private const AKUN_PENSIUN = ['supervisor@ekspedisiquran.com'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrator System',
                'email' => 'admin@ekspedisiquran.com',
                'password' => 'password123',
                'spatie_role' => 'super-admin',
            ],
            [
                'name' => 'Customer Service',
                'email' => 'cs@ekspedisiquran.com',
                'password' => 'cs123',
                'spatie_role' => 'customer-service',
            ],
            [
                'name' => 'Kurir Pengiriman',
                'email' => 'kurir@ekspedisiquran.com',
                'password' => 'kurir123',
                'spatie_role' => 'courier',
            ],
        ];

        foreach ($users as $userData) {
            $user = $this->simpanAkun($userData, gudang: null);

            if (! $user->hasRole($userData['spatie_role'])) {
                $user->assignRole($userData['spatie_role']);
            }
        }

        $this->seedAkunGudang();
        $this->pensiunkanAkunLama();

        $this->command?->info('✅ Default users seeded successfully!');
        $this->command?->info('📧 Admin: admin@ekspedisiquran.com / password123');
        $this->command?->info('📧 CS: cs@ekspedisiquran.com / cs123');
        $this->command?->info('📧 Manager Gudang: gudang@ekspedisiquran.com / gudang123');
        $this->command?->info('📧 SPV Semarang: spv.semarang@ekspedisiquran.com / spv123');
        $this->command?->info('📧 SPV Boyolali: spv.boyolali@ekspedisiquran.com / spv123');
        $this->command?->info('📧 Freelancer Semarang: freelancer.semarang@ekspedisiquran.com / freelancer123');
        $this->command?->info('📧 Freelancer Boyolali: freelancer.boyolali@ekspedisiquran.com / freelancer123');
        $this->command?->info('📧 Courier: kurir@ekspedisiquran.com / kurir123');
    }

    /**
     * Hierarki gudang: Manager Gudang (tanpa gudang, kedua gudang) → SPV per gudang →
     * freelancer. Peran akun-akun ini DITETAPKAN (syncRoles), bukan ditambah, supaya
     * menjalankan seeder ulang juga membetulkan peran yang melenceng.
     */
    private function seedAkunGudang(): void
    {
        $semarang = Gudang::byKode('SMG');
        $boyolali = Gudang::byKode('BTL');

        $akunGudang = [
            // Manager Gudang (PJ): warehouse + manager-repacking, sama dengan akun produksi.
            [
                'name' => 'Manager Gudang',
                'email' => 'gudang@ekspedisiquran.com',
                'password' => 'gudang123',
                'roles' => [RoleEnum::WAREHOUSE->value, RoleEnum::MANAGER_REPACKING->value],
                'gudang' => null,
            ],
            // SPV memegang manager-repacking seperti di produksi; cakupannya tetap satu
            // gudang karena role spv-gudang.
            [
                'name' => 'SPV Gudang Semarang',
                'email' => 'spv.semarang@ekspedisiquran.com',
                'password' => 'spv123',
                'roles' => [RoleEnum::SPV_GUDANG->value, RoleEnum::MANAGER_REPACKING->value],
                'gudang' => $semarang,
            ],
            [
                'name' => 'SPV Gudang Boyolali',
                'email' => 'spv.boyolali@ekspedisiquran.com',
                'password' => 'spv123',
                'roles' => [RoleEnum::SPV_GUDANG->value, RoleEnum::MANAGER_REPACKING->value],
                'gudang' => $boyolali,
            ],
            [
                'name' => 'Freelancer Semarang',
                'email' => 'freelancer.semarang@ekspedisiquran.com',
                'password' => 'freelancer123',
                'roles' => [RoleEnum::WAREHOUSE->value],
                'gudang' => $semarang,
            ],
            [
                'name' => 'Freelancer Boyolali',
                'email' => 'freelancer.boyolali@ekspedisiquran.com',
                'password' => 'freelancer123',
                'roles' => [RoleEnum::WAREHOUSE->value],
                'gudang' => $boyolali,
            ],
        ];

        foreach ($akunGudang as $akun) {
            $this->simpanAkun($akun, $akun['gudang'])->syncRoles($akun['roles']);
        }
    }

    private function pensiunkanAkunLama(): void
    {
        User::query()
            ->whereIn('email', self::AKUN_PENSIUN)
            ->get()
            ->each(function (User $user): void {
                $user->syncRoles([]);
                $user->forceFill(['gudang_id' => null, 'is_active' => false])->save();
            });
    }

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    private function simpanAkun(array $data, ?Gudang $gudang): User
    {
        return User::updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'email_verified_at' => now(),
                'password' => Hash::make($data['password']),
                'is_active' => true,
                'gudang_id' => $gudang?->id,
            ],
        );
    }
}
