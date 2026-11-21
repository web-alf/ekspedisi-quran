<?php

namespace App\Support;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Beranda (tujuan awal) tiap peran setelah login.
 *
 * Peran gudang — manager-repacking (PJ), spv-gudang, dan warehouse (freelancer) —
 * bekerja di dashboard gudang (/admin/warehouse); itu satu-satunya dashboard yang
 * ada di menu sidebar mereka (keputusan pemilik sistem, lihat AdminLayout.svelte).
 * Kurir beranda di halaman "Hari Ini & Rute" (/admin/kurir): muatan yang sedang
 * dibawa dan tujuan berikutnya. Peran lain beranda di dashboard umum (/admin/dashboard).
 *
 * Kelas ini satu-satunya sumber kebenaran untuk beranda, dipakai oleh tombol login,
 * middleware guest, dan redirect /dashboard — supaya jalur-jalur itu tidak saling
 * menyeret pengguna ke beranda yang bukan miliknya (25/10/2026: SPV login
 * mendarat di /admin/dashboard padahal menu Dashboard-nya /admin/warehouse).
 */
class BerandaPeran
{
    /** URL beranda pengguna setelah login (atau saat membuka halaman guest). */
    public static function url(User $user): string
    {
        if (self::adalahPeranGudang($user)) {
            return route('admin.warehouse.dashboard');
        }

        if (self::adalahKurir($user)) {
            return route('admin.kurir.dashboard');
        }

        return '/admin/dashboard';
    }

    /** Apakah pengguna punya beranda sendiri, bukan dashboard umum. */
    public static function punyaBerandaKhusus(User $user): bool
    {
        return self::adalahPeranGudang($user) || self::adalahKurir($user);
    }

    /**
     * Apakah pengguna kurir yang boleh membuka beranda kurir.
     *
     * Izin muatan.read ikut diperiksa: rute /admin/kurir menuntutnya, dan kurir tanpa
     * izin itu akan mendarat di 403 tepat setelah login.
     */
    public static function adalahKurir(User $user): bool
    {
        return $user->hasRole(RoleEnum::COURIER->value)
            && $user->can(PermissionEnum::MUATAN_READ->value);
    }

    /**
     * Beranda untuk user yang sedang login.
     *
     * Menerima hasil Auth::user()/guard()->user() apa adanya — guard lain bisa
     * saja memakai model selain App\Models\User; yang bukan User (atau guest)
     * dianggap beranda umum, jadi pemanggil tidak perlu meng-cast sendiri.
     */
    public static function dariAuth(?Authenticatable $user): string
    {
        return $user instanceof User ? self::url($user) : '/admin/dashboard';
    }

    /** Apakah pengguna memegang salah satu peran gudang. */
    public static function adalahPeranGudang(User $user): bool
    {
        return $user->hasAnyRole([
            RoleEnum::WAREHOUSE->value,
            RoleEnum::MANAGER_REPACKING->value,
            RoleEnum::SPV_GUDANG->value,
        ]);
    }

    /**
     * Apakah URL ini dashboard umum, bukan beranda peran gudang.
     *
     * url.intended yang menunjuk ke sini biasanya artefak lama — bookmark atau
     * riwayat browser sebelum rombakan gudang — bukan halaman yang pengguna
     * gudang sengaja mau buka, jadi aman dibuang saat login.
     */
    public static function adalahUrlDashboardUmum(string $url): bool
    {
        $jalur = parse_url($url, PHP_URL_PATH) ?: '';

        return in_array(rtrim($jalur, '/'), ['/dashboard', '/admin/dashboard'], true);
    }
}
