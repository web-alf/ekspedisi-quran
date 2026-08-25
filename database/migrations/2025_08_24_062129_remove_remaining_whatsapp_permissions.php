<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove all remaining WhatsApp permissions that might still exist
        $whatsappPermissions = [
            // Original permissions from the remove migration
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
            // Additional permissions found in other migrations
            'whatsapp.messages.read',
            'whatsapp.messages.reply',
            'whatsapp.messages.manage',
            'whatsapp.analytics.read',
        ];

        // Delete all WhatsApp permissions
        Permission::whereIn('name', $whatsappPermissions)->delete();
        
        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is irreversible - WhatsApp functionality has been removed
    }
};