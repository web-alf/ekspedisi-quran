<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FixedRolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create comprehensive permissions
        $permissions = [
            // Dashboard permissions
            'dashboard.view',
            'dashboard.analytics',

            // User management
            'users.create',
            'users.read',
            'users.update',
            'users.delete',
            'users.toggle',

            // Role management
            'roles.create',
            'roles.read',
            'roles.update',
            'roles.delete',

            // Permission management
            'permissions.create',
            'permissions.read',
            'permissions.update',
            'permissions.delete',

            // Donatur management
            'donatur.create',
            'donatur.read',
            'donatur.update',
            'donatur.delete',
            'donatur.import',
            'donatur.export',

            // Shipment management
            'shipments.create',
            'shipments.read',
            'shipments.update',
            'shipments.delete',
            'shipments.export',
            'shipments.track',
            'shipments.bulk-update',
            'shipments.update-status',

            // Mushaf request management
            'mushaf-requests.create',
            'mushaf-requests.read',
            'mushaf-requests.update',
            'mushaf-requests.delete',
            'mushaf-requests.approve',
            'mushaf-requests.reject',
            'mushaf-requests.process',

            // Certificate management
            'certificates.create',
            'certificates.read',
            'certificates.update',
            'certificates.delete',
            'certificates.generate',
            'certificates.download',

            // Template management
            'templates.create',
            'templates.read',
            'templates.update',
            'templates.delete',
            'templates.set-default',
            'templates.toggle',

            // Warehouse specific permissions (granular)
            'warehouse.dashboard',
            'warehouse.packing.view',
            'warehouse.packing.scan',
            'warehouse.packing.seal',
            'warehouse.qr.generate',
            'warehouse.qr.scan',
            'warehouse.qr.verify',
            'warehouse.qr.bulk_generate',
            'warehouse.performance.view',
            'warehouse.tasks.view',
            'warehouse.tasks.update',
            'warehouse.boxes.view',
            'warehouse.boxes.seal',

            // Supervisor permissions
            'supervisor.dashboard',
            'supervisor.warehouse.monitor',
            'supervisor.warehouse.redistribute',
            'supervisor.warehouse.assign',
            'supervisor.performance.view',
            'supervisor.performance.reports',

            // QR Code system permissions
            'qr.generate',
            'qr.scan',
            'qr.verify',
            'qr.bulk_operations',

            // Status management
            'status.update',
            'status.track',

            // Wakaf batch permissions
            'wakaf-batch.create',
            'wakaf-batch.read',
            'wakaf-batch.update',
            'wakaf-batch.delete',

            // System monitoring
            'system.monitor',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions based on business functions

        // 🔐 SUPER ADMIN - Strategic Management & Full System Control
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdmin->syncPermissions([
            // Full system management
            'dashboard.view', 'dashboard.analytics',
            'users.create', 'users.read', 'users.update', 'users.delete', 'users.toggle',
            'roles.create', 'roles.read', 'roles.update', 'roles.delete',
            'permissions.create', 'permissions.read', 'permissions.update', 'permissions.delete',

            // Data management
            'donatur.create', 'donatur.read', 'donatur.update', 'donatur.delete', 'donatur.import', 'donatur.export',
            'shipments.create', 'shipments.read', 'shipments.update', 'shipments.delete', 'shipments.export', 'shipments.track', 'shipments.bulk-update', 'shipments.update-status',
            'mushaf-requests.create', 'mushaf-requests.read', 'mushaf-requests.update', 'mushaf-requests.delete', 'mushaf-requests.approve', 'mushaf-requests.reject', 'mushaf-requests.process',

            // Certificate & template management
            'certificates.create', 'certificates.read', 'certificates.update', 'certificates.delete', 'certificates.generate', 'certificates.download',
            'templates.create', 'templates.read', 'templates.update', 'templates.delete', 'templates.set-default', 'templates.toggle',

            // Warehouse monitoring (NOT operational)
            'warehouse.dashboard', 'warehouse.performance.view', 'warehouse.tasks.view', 'warehouse.boxes.view',

            // Supervisor functions
            'supervisor.dashboard', 'supervisor.warehouse.monitor', 'supervisor.warehouse.redistribute', 'supervisor.warehouse.assign', 'supervisor.performance.view', 'supervisor.performance.reports',

            // QR management (strategic level)
            'qr.verify', 'qr.bulk_operations',

            // Status & wakaf management
            'status.update', 'status.track',
            'wakaf-batch.create', 'wakaf-batch.read', 'wakaf-batch.update', 'wakaf-batch.delete',

            // System monitoring
            'system.monitor',
        ]);

        // 👥 CUSTOMER SERVICE - Customer Interface & Communication
        $cs = Role::firstOrCreate(['name' => 'customer-service']);
        $cs->syncPermissions([
            // Basic access
            'dashboard.view',

            // Customer data management
            'donatur.create', 'donatur.read', 'donatur.update', 'donatur.import', 'donatur.export',

            // Mushaf request management
            'mushaf-requests.read', 'mushaf-requests.update', 'mushaf-requests.approve', 'mushaf-requests.reject', 'mushaf-requests.process',

            // Shipment customer service
            'shipments.read', 'shipments.track', 'shipments.update-status',

            // Certificate generation for customers
            'certificates.generate', 'certificates.read', 'certificates.download',

            // Status tracking
            'status.track',
        ]);

        // 📦 WAREHOUSE - Operations Specialist
        $warehouse = Role::firstOrCreate(['name' => 'warehouse']);
        $warehouse->syncPermissions([
            // Basic access
            'dashboard.view',

            // Limited donatur access (operational needs)
            'donatur.read',

            // Shipment operations
            'shipments.create', 'shipments.read', 'shipments.update', 'shipments.update-status', 'shipments.bulk-update',

            // Mushaf processing
            'mushaf-requests.read', 'mushaf-requests.process',

            // Full warehouse operations
            'warehouse.dashboard', 'warehouse.packing.view', 'warehouse.packing.scan', 'warehouse.packing.seal',
            'warehouse.qr.generate', 'warehouse.qr.scan', 'warehouse.qr.verify', 'warehouse.qr.bulk_generate',
            'warehouse.performance.view', 'warehouse.tasks.view', 'warehouse.tasks.update',
            'warehouse.boxes.view', 'warehouse.boxes.seal',

            // QR system operations
            'qr.generate', 'qr.scan', 'qr.verify', 'qr.bulk_operations',

            // Status management
            'status.update', 'status.track',

            // Wakaf batch operations
            'wakaf-batch.read',

        ]);

        // 👨‍💼 SUPERVISOR - Management & Oversight
        $supervisor = Role::firstOrCreate(['name' => 'supervisor']);
        $supervisor->syncPermissions([
            // Management dashboard
            'dashboard.view', 'dashboard.analytics',

            // Shipment management
            'shipments.read', 'shipments.track', 'shipments.bulk-update', 'shipments.export',

            // Mushaf request approval
            'mushaf-requests.read', 'mushaf-requests.approve', 'mushaf-requests.reject', 'mushaf-requests.process',

            // Certificate monitoring
            'certificates.read',

            // Full supervisor functions
            'supervisor.dashboard', 'supervisor.warehouse.monitor', 'supervisor.warehouse.redistribute', 'supervisor.warehouse.assign',
            'supervisor.performance.view', 'supervisor.performance.reports',

            // Warehouse monitoring (NOT operational)
            'warehouse.performance.view', 'warehouse.tasks.view', 'warehouse.boxes.view',

            // QR verification (quality control)
            'qr.verify',

            // Status tracking
            'status.track',

            // Wakaf batch management
            'wakaf-batch.read', 'wakaf-batch.update',

            // System monitoring (limited)
            'system.monitor',
        ]);

        // 🚚 COURIER - Delivery Specialist
        $courier = Role::firstOrCreate(['name' => 'courier']);
        $courier->syncPermissions([
            // Basic access
            'dashboard.view',

            // Delivery operations
            'shipments.read', 'shipments.track', 'shipments.update-status',

            // Customer info for delivery
            'donatur.read',

            // QR verification for packages
            'qr.verify',

            // Delivery status management
            'status.update', 'status.track',

            // Delivery documents
            'certificates.download',

        ]);

        $this->command->info('✅ Fixed roles and permissions created successfully!');
        $this->command->info('🔐 Super Admin: Full system control + strategic oversight');
        $this->command->info('👥 Customer Service: Customer interface + communication');
        $this->command->info('📦 Warehouse: Operations specialist + QR management');
        $this->command->info('👨‍💼 Supervisor: Management oversight + performance monitoring');
        $this->command->info('🚚 Courier: Delivery specialist + status updates');

        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
