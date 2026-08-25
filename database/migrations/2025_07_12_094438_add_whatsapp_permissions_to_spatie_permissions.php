<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create WhatsApp-related permissions - SIMPLIFIED
        $permissions = [
            // WhatsApp settings management
            'whatsapp.settings.read' => 'View WhatsApp settings and configurations',
            'whatsapp.settings.write' => 'Modify WhatsApp API settings and configurations',
            
            // Contact management
            'whatsapp.contacts.read' => 'View WhatsApp contact list',
            'whatsapp.contacts.write' => 'Add, edit, and delete WhatsApp contacts',
            
            // Template management
            'whatsapp.templates.read' => 'View WhatsApp message templates',
            'whatsapp.templates.write' => 'Create, edit, and delete WhatsApp templates',
            
            // Notification management
            'whatsapp.notifications.read' => 'View WhatsApp notification logs and history',
            'whatsapp.notifications.send' => 'Send test messages and manual notifications',
            'whatsapp.notifications.retry' => 'Retry failed notifications',
            
            // System testing
            'whatsapp.system.test' => 'Test WhatsApp API connection and functionality',
        ];

        // Create permissions
        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name],
                ['guard_name' => 'web']
            );
        }

        // Assign permissions to roles
        $this->assignPermissionsToRoles();
    }

    /**
     * Assign permissions to appropriate roles - SIMPLIFIED
     */
    private function assignPermissionsToRoles(): void
    {
        // Super Admin - Full access
        if ($superAdmin = Role::where('name', 'super-admin')->first()) {
            $superAdmin->givePermissionTo([
                'whatsapp.settings.read',
                'whatsapp.settings.write',
                'whatsapp.contacts.read',
                'whatsapp.contacts.write',
                'whatsapp.templates.read',
                'whatsapp.templates.write',
                'whatsapp.notifications.read',
                'whatsapp.notifications.send',
                'whatsapp.notifications.retry',
                'whatsapp.system.test',
            ]);
        }

        // CS (Customer Service) - Communication management
        if ($cs = Role::where('name', 'customer-service')->first()) {
            $cs->givePermissionTo([
                'whatsapp.settings.read',
                'whatsapp.settings.write',
                'whatsapp.contacts.read',
                'whatsapp.contacts.write',
                'whatsapp.templates.read',
                'whatsapp.templates.write',
                'whatsapp.notifications.read',
                'whatsapp.notifications.send',
                'whatsapp.notifications.retry',
                'whatsapp.system.test',
            ]);
        }

        // Supervisor - Monitoring only
        if ($supervisor = Role::where('name', 'supervisor')->first()) {
            $supervisor->givePermissionTo([
                'whatsapp.settings.read',
                'whatsapp.contacts.read',
                'whatsapp.templates.read',
                'whatsapp.notifications.read',
                'whatsapp.notifications.send', // Can send test messages
                'whatsapp.system.test',
            ]);
        }

        // Warehouse - Notifications only
        if ($warehouse = Role::where('name', 'warehouse')->first()) {
            $warehouse->givePermissionTo([
                'whatsapp.notifications.read', // Can view notifications (read-only)
            ]);
        }

        // Courier - Notifications only 
        if ($courier = Role::where('name', 'courier')->first()) {
            $courier->givePermissionTo([
                'whatsapp.notifications.read', // Can view notifications (read-only)
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove WhatsApp permissions
        $permissions = [
            'whatsapp.settings.read',
            'whatsapp.settings.write',
            'whatsapp.contacts.read',
            'whatsapp.contacts.write',
            'whatsapp.templates.read',
            'whatsapp.templates.write',
            'whatsapp.notifications.read',
            'whatsapp.notifications.send',
            'whatsapp.notifications.retry',
            'whatsapp.system.test',
        ];

        Permission::whereIn('name', $permissions)->delete();
    }
};