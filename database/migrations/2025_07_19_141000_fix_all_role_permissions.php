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
        
        // WhatsApp permissions have been removed - skipping creation
        
        // Update each role with corrected permissions
        $this->updateSuperAdminRole();
        $this->updateCustomerServiceRole();
        $this->updateWarehouseRole();
        $this->updateSupervisorRole();
        $this->updateCourierRole();
        
        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function updateSuperAdminRole()
    {
        $superAdmin = Role::where('name', 'super-admin')->first();
        if (!$superAdmin) return;
        
        // Add missing permissions to super admin
        $newPermissions = [
            'warehouse.dashboard',
            'system.monitor',
        ];
        
        foreach ($newPermissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission && !$superAdmin->hasPermissionTo($permission)) {
                $superAdmin->givePermissionTo($permission);
            }
        }
    }
    
    private function updateCustomerServiceRole()
    {
        $cs = Role::where('name', 'customer-service')->first();
        if (!$cs) return;
        
        // Add missing permissions to customer service
        $newPermissions = [
            'certificates.generate',
            'certificates.download',
            'shipments.update-status',
            'mushaf-requests.process',
        ];
        
        foreach ($newPermissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission && !$cs->hasPermissionTo($permission)) {
                $cs->givePermissionTo($permission);
            }
        }
    }
    
    private function updateWarehouseRole()
    {
        $warehouse = Role::where('name', 'warehouse')->first();
        if (!$warehouse) return;
        
        // Add missing permissions to warehouse
        $newPermissions = [
            'shipments.update-status',
            'shipments.bulk-update',
            'whatsapp.notifications.send',
        ];
        
        foreach ($newPermissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission && !$warehouse->hasPermissionTo($permission)) {
                $warehouse->givePermissionTo($permission);
            }
        }
        
        // Remove inappropriate permissions from warehouse
        $removePermissions = [
            'certificates.generate',
            'certificates.read',
            'certificates.download',
            'shipments.export',
        ];
        
        foreach ($removePermissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission && $warehouse->hasPermissionTo($permission)) {
                $warehouse->revokePermissionTo($permission);
            }
        }
    }
    
    private function updateSupervisorRole()
    {
        $supervisor = Role::where('name', 'supervisor')->first();
        if (!$supervisor) return;
        
        // Remove warehouse.dashboard from supervisor (already done in previous migration)
        $warehouseDashboard = Permission::where('name', 'warehouse.dashboard')->first();
        if ($warehouseDashboard && $supervisor->hasPermissionTo($warehouseDashboard)) {
            $supervisor->revokePermissionTo($warehouseDashboard);
        }
        
        // Add missing permissions to supervisor
        $newPermissions = [
            'dashboard.analytics',
            'shipments.bulk-update',
            'shipments.export',
            'certificates.read',
            'mushaf-requests.approve',
            'mushaf-requests.reject',
            'mushaf-requests.process',
            'system.monitor',
        ];
        
        foreach ($newPermissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission && !$supervisor->hasPermissionTo($permission)) {
                $supervisor->givePermissionTo($permission);
            }
        }
        
        // Remove granular donatur access (keep read-only)
        $removePermissions = [
            'donatur.create',
            'donatur.update',
            'donatur.delete',
            'donatur.import',
            'donatur.export',
            'shipments.update', // Use bulk-update instead
        ];
        
        foreach ($removePermissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission && $supervisor->hasPermissionTo($permission)) {
                $supervisor->revokePermissionTo($permission);
            }
        }
    }
    
    private function updateCourierRole()
    {
        $courier = Role::where('name', 'courier')->first();
        if (!$courier) return;
        
        // Add missing permissions to courier
        $newPermissions = [
            'qr.verify',
            'shipments.update-status',
            'certificates.download',
            'whatsapp.notifications.send',
        ];
        
        foreach ($newPermissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission && !$courier->hasPermissionTo($permission)) {
                $courier->givePermissionTo($permission);
            }
        }
        
        // Remove inappropriate permissions from courier
        $removePermissions = [
            'shipments.update', // Use update-status instead
        ];
        
        foreach ($removePermissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission && $courier->hasPermissionTo($permission)) {
                $courier->revokePermissionTo($permission);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration improves permissions, reverting would be complex
        // and not recommended. Create a new migration if changes needed.
        $this->command->warn('Reverting permission fixes is not recommended.');
        $this->command->warn('Create a new migration if permission changes are needed.');
    }
};
