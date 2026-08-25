<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->renamePermissions([
            'mushaf.requests.create' => 'mushaf-requests.create',
            'mushaf.requests.read' => 'mushaf-requests.read',
            'mushaf.requests.update' => 'mushaf-requests.update',
            'mushaf.requests.delete' => 'mushaf-requests.delete',
            'mushaf.requests.approve' => 'mushaf-requests.approve',
            'mushaf.requests.reject' => 'mushaf-requests.reject',
            'mushaf.requests.process' => 'mushaf-requests.process',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->renamePermissions([
            'mushaf-requests.create' => 'mushaf.requests.create',
            'mushaf-requests.read' => 'mushaf.requests.read',
            'mushaf-requests.update' => 'mushaf.requests.update',
            'mushaf-requests.delete' => 'mushaf.requests.delete',
            'mushaf-requests.approve' => 'mushaf.requests.approve',
            'mushaf-requests.reject' => 'mushaf.requests.reject',
            'mushaf-requests.process' => 'mushaf.requests.process',
        ]);
    }

    private function renamePermissions(array $mapping): void
    {
        foreach ($mapping as $from => $to) {
            $permission = Permission::where('name', $from)->first();
            if (! $permission) {
                continue;
            }

            $permission->update(['name' => $to]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
