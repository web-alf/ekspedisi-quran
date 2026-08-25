<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reset cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        // Create missing permissions if they don't exist
        $missingPermissions = [
            'system.monitor',
        ];
        
        foreach ($missingPermissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName]);
        }
        
        // Get supervisor role
        $supervisor = Role::where('name', 'supervisor')->first();
        
        if ($supervisor) {
            // Remove warehouse operational permissions from supervisor
            $warehouseOperationalPermissions = [
                'warehouse.dashboard',
                'warehouse.packing.view',
                'warehouse.packing.scan', 
                'warehouse.packing.seal',
                'warehouse.qr.generate',
                'warehouse.qr.scan',
                'warehouse.qr.bulk_generate',
            ];
            
            foreach ($warehouseOperationalPermissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission && $supervisor->hasPermissionTo($permission)) {
                    $supervisor->revokePermissionTo($permission);
                }
            }
            
            // WhatsApp permissions have been removed - skipping assignment
        }
        
        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Get supervisor role
        $supervisor = Role::where('name', 'supervisor')->first();
        
        if ($supervisor) {
            // Restore warehouse.dashboard permission (previous state)
            $warehouseDashboard = Permission::where('name', 'warehouse.dashboard')->first();
            if ($warehouseDashboard && !$supervisor->hasPermissionTo($warehouseDashboard)) {
                $supervisor->givePermissionTo($warehouseDashboard);
            }
            
            // Remove added permissions
            $addedPermissions = [
                'whatsapp.system.test',
                'whatsapp.notifications.send',
                'whatsapp.messages.read',
                'whatsapp.analytics.read',
            ];
            
            foreach ($addedPermissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission && $supervisor->hasPermissionTo($permission)) {
                    $supervisor->revokePermissionTo($permission);
                }
            }
        }
        
        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
