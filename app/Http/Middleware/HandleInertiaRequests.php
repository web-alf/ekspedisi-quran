<?php

namespace App\Http\Middleware;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Permission\PermissionRegistrar;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $authData = null;

        if ($user) {
            $permissions = $user->hasRole(RoleEnum::SUPER_ADMIN->value)
                ? $this->semuaNamaIzin()
                : $user->getAllPermissions()->pluck('name')->toArray();
            $authData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role, // Primary role (backward compatibility)
                'role_display' => $user->role_display, // Primary role display
                'roles' => $user->roles_array, // All roles (multi-role support)
                'roles_display' => $user->roles_display, // All role displays
                'is_active' => $user->is_active,
                'permissions' => $permissions,
            ];

        }

        // Get basic settings for layout (cached for performance)
        $basicSettings = cache()->remember('basic_settings_for_layout', 3600, function () {
            return Setting::whereIn('key', ['app_name', 'app_logo', 'admin_logo'])
                ->where('is_active', true)
                ->pluck('value', 'key');
        });

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $authData,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
                'warning' => $request->session()->get('warning'),
                // Detail baris yang gagal saat import. Tanpa diteruskan di sini,
                // pengguna hanya diberi tahu "ada N baris gagal" tanpa bisa tahu
                // baris MANA dan KENAPA — datanya sudah dihitung controller, tetapi
                // hilang di middleware.
                'import_errors' => $request->session()->get('import_errors'),
            ],
            'settings' => $basicSettings,
        ];
    }

    /**
     * Seluruh nama izin yang dikenal sistem.
     *
     * Hanya dipakai untuk super-admin. Tiga lapisan lain sudah meloloskannya tanpa
     * membaca tabel permissions — Gate::before di AppServiceProvider dan cabang
     * hasRole di PermissionMiddleware — tetapi daftar menu di frontend
     * (AdminLayout.svelte) menyaring murni dengan `permissions.includes(...)`.
     * Karena itu daftar yang dikirim ke browser tidak boleh bergantung pada
     * kelengkapan isi tabel: produksi tidak menjalankan seeder, sehingga setiap izin
     * baru yang belum diberikan lewat migrasi akan menghilangkan menunya dari
     * super-admin, walau URL-nya tetap bisa dibuka (200).
     *
     * Gabungan basis data + PermissionEnum: yang di basis data menjangkau izin dari
     * migrasi, yang di enum menjangkau izin yang sudah dipakai kode tetapi belum
     * sempat masuk basis data.
     *
     * @return array<int, string>
     */
    private function semuaNamaIzin(): array
    {
        $diBasisData = app(PermissionRegistrar::class)->getPermissions()->pluck('name')->all();
        $diKode = array_map(fn (PermissionEnum $izin): string => $izin->value, PermissionEnum::cases());

        return array_values(array_unique([...$diBasisData, ...$diKode]));
    }
}
