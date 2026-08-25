<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop WhatsApp tables in correct order (considering foreign keys)
        Schema::dropIfExists('whatsapp_notifications');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_contacts');
        Schema::dropIfExists('whatsapp_settings');

        // Remove WhatsApp permissions from Spatie permissions system
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is irreversible as we're removing WhatsApp functionality
        // To restore WhatsApp functionality, you would need to:
        // 1. Restore the original WhatsApp migration files
        // 2. Run the WhatsApp permission seeder
        // 3. Restore the WhatsApp code files
        throw new \Exception('This migration cannot be reversed. WhatsApp functionality has been permanently removed.');
    }
};
