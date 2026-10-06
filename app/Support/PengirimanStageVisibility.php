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
     * Apakah pembatasan PILIHAN STATUS berlaku untuk user ini.
     *
     * Berbeda dari restricts(): yang ini membatasi status mana yang boleh DIPILIH
     * (dropdown/filter), bukan baris pengiriman mana yang boleh DILIHAT. Kurir
     * perlu melihat resi lintas status supaya pekerjaannya terlihat, tetapi tidak
     * boleh memindahkannya ke tahap gudang.
     *
     * Sengaja terpisah: menggabungkannya dengan appliesTo() akan ikut memotong
     * daftar resi kurir menjadi "pengiriman" saja.
     */
    public static function restrictsProgressChoice(?User $user): bool
    {
        return $user !== null && $user->hasRole(RoleEnum::COURIER->value);
    }

    /**
     * Slug status yang boleh dipilih user ini saat memindahkan status.
     *
     * @return array<int, string>
     */
    public static function allowedProgressSlugs(): array
    {
        return array_values(config('pengiriman.courier_progress_stages') ?? []);
    }

    /**
     * Daftar status untuk dropdown/filter PEMINDAHAN STATUS.
     *
     * Dipakai halaman detail Muatan ("Pindahkan Status Perjalanan"), filter status
     * di daftar Pengiriman, dan halaman ubah status — tiga jalur yang dulu memuat
     * daftarnya sendiri-sendiri sehingga bisa berbeda isi.
     *
     * Kurir dibatasi ke tahap perjalanan + batal. Role lain tidak dibatasi di sini:
     * pembatasan data mereka sudah ditangani applyToQuery()/isVisible().
     *
     * Gagal-tertutup mengikuti aturan yang sama seperti manager: daftar kosong
     * berarti tidak ada status yang bisa dipilih — bukan berarti boleh semua.
     *
     * @return Collection<int, StatusPengiriman>
     */
    public static function visibleProgressStatuses(?User $user): Collection
    {
        $statuses = StatusPengiriman::active()
            ->ordered()
            ->select('id', 'nama', 'slug', 'warna')
            ->get();

        // DUA batas berlaku di sini dan keduanya DIGABUNG, bukan saling
        // menggantikan:
        //
        //   batas data (manager)   — tahap yang memang tidak boleh ia lihat
        //   batas pilihan (kurir)  — tahap yang tidak boleh ia pindahkan
        //
        // Kalau batas data dilewati begitu saja, manager akan melihat pilihan
        // tahap awal di filter padahal datanya sendiri tidak pernah muncul untuk
        // dia — pilihan yang tidak mungkin dipakai.
        if (static::restricts($user)) {
            $allowed = static::allowedSlugs();
            $statuses = $statuses->filter(fn ($s) => in_array($s->slug, $allowed, true))->values();
        }

        if (static::restrictsProgressChoice($user)) {
            $bolehDipilih = static::allowedProgressSlugs();
            $statuses = $statuses->filter(fn ($s) => in_array($s->slug, $bolehDipilih, true))->values();
        }

        return $statuses;
    }

    /**
     * Boleh memindahkan pengiriman ke status ini?
     *
     * Dipakai server saat menerima permintaan perubahan status, supaya pembatasan
     * tidak bergantung pada tampilan saja: kurir yang mengirim status_id tahap
     * gudang langsung ke endpoint tetap ditolak.
     */
    public static function bolehPilihStatus(?User $user, StatusPengiriman $status): bool
    {
        if (! static::restrictsProgressChoice($user)) {
            return true;
        }

        return in_array($status->slug, static::allowedProgressSlugs(), true);
    }

    /**
     * Alasan penolakan yang bisa ditampilkan ke pengguna.
     */
    public static function alasanTidakBolehPilih(?User $user, StatusPengiriman $status): ?string
    {
        if (static::bolehPilihStatus($user, $status)) {
            return null;
        }

        return 'Kurir hanya boleh memindahkan status perjalanan pengiriman. '
            ."Status \"{$status->nama}\" bukan bagian dari perjalanan.";
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
