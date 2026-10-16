<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrator System',
                'email' => 'admin@ekspedisiquran.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password123'),
                'is_active' => true,
                'spatie_role' => 'super-admin',
            ],
            [
                'name' => 'Customer Service',
                'email' => 'cs@ekspedisiquran.com',
                'email_verified_at' => now(),
                'password' => Hash::make('cs123'),
                'is_active' => true,
                'spatie_role' => 'customer-service',
            ],
            [
                'name' => 'Staff Gudang',
                'email' => 'gudang@ekspedisiquran.com',
                'email_verified_at' => now(),
                'password' => Hash::make('gudang123'),
                'is_active' => true,
                'spatie_role' => 'warehouse',
            ],
            [
                'name' => 'Supervisor Gudang',
                'email' => 'supervisor@ekspedisiquran.com',
                'email_verified_at' => now(),
                'password' => Hash::make('supervisor123'),
                'is_active' => true,
                'spatie_role' => 'supervisor',
            ],
            [
                'name' => 'Kurir Pengiriman',
                'email' => 'kurir@ekspedisiquran.com',
                'email_verified_at' => now(),
                'password' => Hash::make('kurir123'),
                'is_active' => true,
                'spatie_role' => 'courier',
            ],
        ];

        // Use updateOrCreate to avoid duplicates
        foreach ($users as $userData) {
            $spatieRole = $userData['spatie_role'];
            unset($userData['spatie_role']);
            
            $user = User::updateOrCreate(
                ['email' => $userData['email']], // Find by email
                $userData // Update or create with this data
            );
            
            // Assign Spatie role
            if (!$user->hasRole($spatieRole)) {
                $user->assignRole($spatieRole);
            }
        }
        
        $this->command->info('✅ Default users seeded successfully!');
        $this->command->info('📧 Admin: admin@ekspedisiquran.com / password123');
        $this->command->info('📧 CS: cs@ekspedisiquran.com / cs123');
        $this->command->info('📧 Warehouse: gudang@ekspedisiquran.com / gudang123');
        $this->command->info('📧 Supervisor: supervisor@ekspedisiquran.com / supervisor123');
        $this->command->info('📧 Courier: kurir@ekspedisiquran.com / kurir123');
    }
}
