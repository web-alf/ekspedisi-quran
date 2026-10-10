<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin untuk notifikasi WhatsApp.
 *
 * Sengaja lewat MIGRASI, bukan seeder: `RolePermissionSeeder` memakai
 * `syncPermissions()`, yang MENGHAPUS izin apa pun yang tidak ada di daftarnya.
 * Kalau izin ini hanya dibuat oleh seeder, menjalankan seeder di produksi bisa
 * mencabutnya. Seeder tetap diperbarui supaya keduanya konsisten — tapi yang
 * menjamin keberadaannya di produksi adalah migrasi ini.
 *
 * Pemberian ke peran: hanya super-admin. Peran lain sengaja TIDAK diberi dulu —
 * siapa yang boleh melihat antrean dan mengirim ulang adalah keputusan pemilik,
 * bukan default yang saya pilihkan. super-admin otomatis memegang semua izin
 * lewat `syncPermissions(Permission::all())` di seeder, tapi di produksi seeder
 * tidak dijalankan ulang, jadi di sini izinnya diberikan eksplisit.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $permissions = [
        'whatsapp.settings.read',
        'whatsapp.settings.write',
        'whatsapp.templates.read',
        'whatsapp.templates.write',
        'whatsapp.notifications.read',
        'whatsapp.notifications.send',
        'whatsapp.system.test',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $superAdmin = Role::where('name', 'super-admin')->first();

        if ($superAdmin) {
            $superAdmin->givePermissionTo($this->permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', $this->permissions)->get()->each->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
