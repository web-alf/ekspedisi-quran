<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Assign thermal print permissions to admin user
        $admin = User::where('email', 'admin@ekspedisiquran.com')->first();
        
        if ($admin) {
            // Give admin all thermal print related permissions
            $permissions = [
                'qr.generate',
                'warehouse.qr.generate', 
                'warehouse.qr.bulk_generate',
                'qr.verify',
                'warehouse.qr.verify'
            ];
            
            foreach ($permissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission && !$admin->hasPermissionTo($permission)) {
                    $admin->givePermissionTo($permission);
                }
            }
            
            $this->command->info('✅ Admin user permissions updated for thermal print access!');
        } else {
            $this->command->error('❌ Admin user not found!');
        }
    }
}
