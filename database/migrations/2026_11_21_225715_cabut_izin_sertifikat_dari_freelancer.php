<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cabut certificates.read, .generate, dan .download dari role warehouse (Freelancer Gudang).
 *
 * Keputusan pemilik sistem 21/11/2026: sertifikat dibuat CS dan otomatis, bukan pekerjaan
 * freelancer. Menunya tidak ada di sidebar gudang, tapi lewat URL langsung izin ini membuka
 * /admin/certificates (daftar dan pencarian nama donatur) dan pembuatan token tautan publik.
 *
 * Dijalankan sebagai migrasi, bukan lewat seeder: deploy hanya menjalankan migrasi.
 */
return new class extends Migration
{
    /**
     * @return array<int, string>
     */
    private function izinSertifikat(): array
    {
        return [
            PermissionEnum::CERTIFICATES_READ->value,
            PermissionEnum::CERTIFICATES_GENERATE->value,
            PermissionEnum::CERTIFICATES_DOWNLOAD->value,
        ];
    }

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $freelancer = Role::where('name', RoleEnum::WAREHOUSE->value)->first();

        if ($freelancer) {
            $dipegang = $freelancer->permissions()->whereIn('name', $this->izinSertifikat())->get();

            if ($dipegang->isNotEmpty()) {
                $freelancer->revokePermissionTo($dipegang);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $freelancer = Role::where('name', RoleEnum::WAREHOUSE->value)->first();

        if ($freelancer) {
            foreach ($this->izinSertifikat() as $izin) {
                Permission::firstOrCreate(['name' => $izin, 'guard_name' => 'web']);
            }

            $freelancer->givePermissionTo($this->izinSertifikat());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
