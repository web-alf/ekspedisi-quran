<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing wakif permissions to donatur permissions
        $wakifPermissions = [
            'wakif.create' => 'donatur.create',
            'wakif.read' => 'donatur.read',
            'wakif.update' => 'donatur.update',
            'wakif.delete' => 'donatur.delete',
            'wakif.import' => 'donatur.import',
            'wakif.export' => 'donatur.export',
        ];

        foreach ($wakifPermissions as $oldPermission => $newPermission) {
            // Check if old permission exists
            $oldPerm = Permission::where('name', $oldPermission)->first();
            if ($oldPerm) {
                // Check if new permission already exists
                $newPerm = Permission::where('name', $newPermission)->first();
                if (!$newPerm) {
                    // Update the name
                    $oldPerm->update(['name' => $newPermission]);
                    // Permission updated
                } else {
                    // If new permission exists, transfer roles from old to new
                    $roles = $oldPerm->roles;
                    foreach ($roles as $role) {
                        $role->givePermissionTo($newPermission);
                        $role->revokePermissionTo($oldPermission);
                    }
                    // Delete old permission
                    $oldPerm->delete();
                    // Permission transferred and deleted
                }
            }
        }

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the permission name changes
        $donaturPermissions = [
            'donatur.create' => 'wakif.create',
            'donatur.read' => 'wakif.read',
            'donatur.update' => 'wakif.update',
            'donatur.delete' => 'wakif.delete',
            'donatur.import' => 'wakif.import',
            'donatur.export' => 'wakif.export',
        ];

        foreach ($donaturPermissions as $newPermission => $oldPermission) {
            $newPerm = Permission::where('name', $newPermission)->first();
            if ($newPerm) {
                $newPerm->update(['name' => $oldPermission]);
            }
        }

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};