<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $legacyPermissions = [
            'qr.bulk_operations',
            'qr.bulk.operations',
            'qr.bulk.generate',
            'warehouse.qr.bulk.generate',
        ];

        $targetPermission = 'warehouse.qr.bulk_generate';

        /** @var PermissionRegistrar $permissionRegistrar */
        $permissionRegistrar = app(PermissionRegistrar::class);

        $permissionRegistrar->forgetCachedPermissions();

        DB::transaction(function () use ($legacyPermissions, $targetPermission, $permissionRegistrar): void {
            $permission = Permission::firstOrCreate([
                'name' => $targetPermission,
                'guard_name' => 'web',
            ]);

            $roles = Role::query()
                ->whereHas('permissions', static function ($query) use ($legacyPermissions): void {
                    $query->whereIn('name', $legacyPermissions);
                })
                ->get();

            foreach ($roles as $role) {
                $role->givePermissionTo($permission);
            }

            $permissionRegistrar->forgetCachedPermissions();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        /** @var PermissionRegistrar $permissionRegistrar */
        $permissionRegistrar = app(PermissionRegistrar::class);

        $permissionRegistrar->forgetCachedPermissions();

        $permission = Permission::where('name', 'warehouse.qr.bulk_generate')->first();

        if ($permission === null) {
            return;
        }

        $roles = Role::query()
            ->whereHas('permissions', static function ($query) use ($permission): void {
                $query->where('id', $permission->id);
            })
            ->get();

        foreach ($roles as $role) {
            $role->revokePermissionTo($permission);
        }

        $permission->delete();

        $permissionRegistrar->forgetCachedPermissions();
    }
};
