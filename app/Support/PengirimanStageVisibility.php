<?php

namespace App\Support;

use App\Enums\RoleEnum;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Batas tahap pengiriman yang boleh dilihat role manager.
 *
 * Role manager (Nalurita Firdausyah) hanya menangani pengiriman yang SUDAH
 * selesai dikerjakan gudang: Selesai Packing, Proses Pengiriman, Diterima
 * Penerima. Tahap awal (pemesanan, produksi, kedatangan, packing) adalah ranah
 * gudang/kustomer servis dan tidak boleh terlihat olehnya.
 *
 * Karena itu pembatasan ini TIDAK cukup di tampilan. Semua jalur yang bisa
 * mengembalikan baris pengiriman harus tunduk padanya: daftar, pencarian,
 * penomoran baris, statistik, halaman detail, form edit, ekspor, dan endpoint
 * JSON per-resi. Kalau hanya tabelnya yang disaring, manager masih bisa
 * membuka /admin/pengiriman/{id}/edit atau ?search= dan melihat data itu.
 *
 * Gagal-tertutup: bila daftar tahap di config kosong, manager tidak melihat
 * apa pun — bukan sebaliknya.
 */
class PengirimanStageVisibility
{
    /**
     * Slug tahap yang boleh dilihat manager.
     *
     * @return array<int, string>
     */
    public static function allowedSlugs(): array
    {
        // config() bisa bernilai null bila berkas konfigurasi gagal dimuat atau
        // kuncinya dihapus; tanpa penjagaan ini array_values() fatal error.
        return array_values(config('pengiriman.manager_visible_stages') ?? []);
    }

    /**
     * Apakah pembatasan berlaku untuk user ini.
     */
    public static function appliesTo(?User $user): bool
    {
        return $user !== null && $user->hasRole(RoleEnum::MANAGER->value);
    }

    /**
     * Apakah user ini perlu dibatasi.
     *
     * Sengaja TIDAK memeriksa apakah daftar tahap kosong. Kalau daftar kosong
     * dianggap "tanpa pembatasan", salah konfigurasi (mis. berkas config gagal
     * dimuat) justru membuka seluruh data ke manager. Sebaliknya, daftar kosong
     * membuat `allowedStatusIds()` kosong sehingga tidak ada baris yang lolos —
     * gagal-tertutup, sesuai janji di config/pengiriman.php.
     */
    public static function restricts(?User $user): bool
    {
        return static::appliesTo($user);
    }

    /**
     * ID status yang boleh dilihat user ini.
     *
     * @return array<int, int>
     */
    public static function allowedStatusIds(?User $user): array
    {
        if (! static::restricts($user)) {
            return [];
        }

        return StatusPengiriman::whereIn('slug', static::allowedSlugs())
            ->pluck('id')
            ->all();
    }

    /**
     * Terapkan batas tahap pada query pengiriman.
     *
     * @param  Builder<Pengiriman>  $query
     * @return Builder<Pengiriman>
     */
    public static function applyToQuery(Builder $query, ?User $user): Builder
    {
        if (! static::restricts($user)) {
            return $query;
        }

        return $query->whereIn('status_id', static::allowedStatusIds($user));
    }

    /**
     * Apakah satu baris pengiriman boleh dilihat user ini.
     */
    public static function isVisible(?User $user, Pengiriman $pengiriman): bool
    {
        if (! static::restricts($user)) {
            return true;
        }

        return in_array($pengiriman->status_id, static::allowedStatusIds($user), true);
    }

    /**
     * Daftar status untuk dropdown filter, sudah disaring sesuai wewenang.
     *
     * @return Collection<int, StatusPengiriman>
     */
    public static function visibleStatuses(?User $user): Collection
    {
        $statuses = StatusPengiriman::active()
            ->select('id', 'nama', 'slug', 'warna')
            ->get();

        if (! static::restricts($user)) {
            return $statuses;
        }

        $allowed = static::allowedSlugs();

        return $statuses->filter(fn ($status) => in_array($status->slug, $allowed, true))->values();
    }

    /**
     * Metadata untuk frontend: apakah dibatasi dan tahap apa saja yang tampil.
     *
     * @return array{restricted: bool, slugs: array<int, string>}
     */
    public static function frontendContext(?User $user): array
    {
        return [
            'restricted' => static::restricts($user),
            'slugs' => static::restricts($user) ? static::allowedSlugs() : [],
        ];
    }
}
