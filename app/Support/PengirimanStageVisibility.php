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
     * Slug tahap awal distribusi yang menjadi ranah gudang.
     *
     * @return array<int, string>
     */
    public static function allowedGudangSlugs(): array
    {
        return array_values(config('pengiriman.warehouse_stage_slugs') ?? []);
    }

    /**
     * Apakah user ini staff gudang.
     *
     * Gudang memegang izin `shipments.update-status` supaya bisa mencatat tahap
     * awal distribusi (pemesanan, produksi, kedatangan, packing). Izin itu
     * sendiri TIDAK membatasi status tujuan — ia hanya mengatakan "boleh
     * mengubah status". Karena itu batas tahapnya harus ditegakkan di sini,
     * pada satu-satunya predikat yang dipakai baik untuk menyusun pilihan
     * maupun untuk menolak permintaan di server.
     */
    public static function adalahGudang(?User $user): bool
    {
        return $user !== null && $user->hasRole(RoleEnum::WAREHOUSE->value);
    }

    /**
     * ID status yang termasuk ranah gudang.
     *
     * @return array<int, int>
     */
    public static function allowedGudangStatusIds(): array
    {
        return StatusPengiriman::whereIn('slug', static::allowedGudangSlugs())
            ->pluck('id')
            ->all();
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
     * Boleh memindahkan PENGIRIMAN INI ke status tersebut?
     *
     * Aturan yang sama seperti bolehPilihStatus(), tetapi memeriksa keadaan
     * resinya juga. Dua hal yang ditambahkan:
     *
     * 1. Staff gudang hanya boleh menyentuh resi yang statusnya SEKARANG masih
     *    di tahap awal. Tanpa ini, `bolehPilihStatus` saja tidak cukup: resi
     *    yang sudah "Diterima" pun masih boleh dipindahkan kembali ke
     *    "Produksi" (urutan mundur ditolak, tetapi tahap awal selalu berurutan
     *    lebih rendah). Gudang tidak boleh menarik kembali pekerjaan yang sudah
     *    lewat tangannya.
     *
     * 2. Untuk role yang datanya dibatasi (manager), resi di luar tahap yang
     *    boleh ia lihat tidak boleh disentuh sama sekali.
     */
    public static function bolehPindahkan(?User $user, Pengiriman $pengiriman, StatusPengiriman $status): bool
    {
        if (! static::bolehPilihStatus($user, $status)) {
            return false;
        }

        if (static::adalahGudang($user)) {
            return $pengiriman->status_id === null
                || in_array($pengiriman->status_id, static::allowedGudangStatusIds(), true);
        }

        return static::isVisible($user, $pengiriman);
    }

    /**
     * Daftar status untuk dropdown/filter PEMINDAHAN STATUS.
     *
     * Memakai PREDIKAT YANG SAMA dengan penegakan di server (bolehPilihStatus),
     * supaya yang ditawarkan dan yang diterima tidak pernah berbeda. Dulu tiap
     * jalur menyusun daftarnya sendiri, dan sempat juga dua batas diterapkan
     * sebagai irisan — yang membuat manager kehilangan pilihan "Diterima" dan
     * "Selesai Packing" padahal justru itu pekerjaannya.
     *
     * @return Collection<int, StatusPengiriman>
     */
    public static function visibleProgressStatuses(?User $user): Collection
    {
        $statuses = StatusPengiriman::active()
            ->ordered()
            ->select('id', 'nama', 'slug', 'warna')
            ->get();

        // Batas pilihan hanya berlaku untuk kurir, manager, dan gudang; role
        // lain memakai batas DATA-nya sendiri (restricts), bukan daftar ini.
        if (! static::restrictsProgressChoice($user) && ! static::adalahManager($user) && ! static::adalahGudang($user)) {
            return $statuses;
        }

        return $statuses
            ->filter(fn ($status) => static::bolehPilihStatus($user, $status))
            ->values();
    }

    /**
     * Daftar status untuk dropdown FILTER daftar pengiriman.
     *
     * Berbeda dari visibleProgressStatuses(): filter tidak memindahkan apa pun,
     * jadi yang pantas muncul adalah status resi yang MEMANG boleh dilihat role
     * itu — bukan status yang boleh ia pilih. Untuk manager keduanya kebetulan
     * sama (data-nya memang dibatasi), tetapi untuk gudang tidak: mereka melihat
     * resi lintas status supaya pekerjaannya terlihat, sehingga filternya juga
     * harus mencakup tahap perjalanan.
     *
     * @return Collection<int, StatusPengiriman>
     */
    public static function visibleStatuses(?User $user): Collection
    {
        $statuses = StatusPengiriman::active()
            ->ordered()
            ->select('id', 'nama', 'slug', 'warna')
            ->get();

        if (static::adalahGudang($user)) {
            return $statuses;
        }

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
     *
     * CATATAN: manager TIDAK termasuk di sini. Batas manajer adalah batas DATA
     * (restricts), bukan batas pilihan — lihat visibleProgressStatuses().
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
     * Apakah user ini manager distribusi.
     *
     * Manager punya peran ganda: batas DATA-nya sempit (hanya tahap akhir), tetapi
     * sebagai pengawas kurir ia juga mengerjakan perjalanan — termasuk mencatat
     * resi yang gagal diantar ("Batal").
     */
    public static function adalahManager(?User $user): bool
    {
        return $user !== null && $user->hasRole(RoleEnum::MANAGER->value);
    }

    /**
     * Boleh memindahkan pengiriman ke status ini?
     *
     * Ini SATU-SATUNYA sumber aturan tentang status yang boleh dipilih. Dipakai
     * untuk menyusun pilihan di tampilan sekaligus untuk menolak permintaan di
     * server, sehingga kurir yang mengirim status_id tahap gudang langsung ke
     * endpoint tetap ditolak — dan pilihan yang tampil tidak pernah mengejutkan.
     *
     * Aturannya:
     *   kurir    → tahap perjalanan saja (pengiriman, batal)
     *   manager  → tahap perjalanan DItambah tahap yang datanya ia tangani
     *              (termasuk "diterima", yang diselesaikan dengan verifikasi
     *              manual). Tahap gudang tetap tertutup baginya.
     *   gudang   → tahap awal distribusi saja (pemesanan, produksi,
     *              kedatangan, packing). Tahap perjalanan dan penyelesaian
     *              tetap tertutup baginya; pengiriman/diterima adalah wewenang
     *              kurir, manager, dan role distribusi.
     *   lainnya  → tidak dibatasi di sini
     */
    public static function bolehPilihStatus(?User $user, StatusPengiriman $status): bool
    {
        if ($user === null) {
            return false;
        }

        if (static::adalahGudang($user)) {
            return in_array($status->slug, static::allowedGudangSlugs(), true);
        }

        $kurirAtauManager = static::restrictsProgressChoice($user) || static::adalahManager($user);

        if (! $kurirAtauManager) {
            return true;
        }

        // Tahap perjalanan selalu boleh — inilah yang dikerjakan kurir di jalan,
        // dan yang diawasi manager.
        if (in_array($status->slug, static::allowedProgressSlugs(), true)) {
            return true;
        }

        // Manager juga menangani tahap yang datanya memang ia lihat. Ini yang
        // membuat "Diterima Penerima" tetap bisa ia pilih untuk verifikasi
        // manual, tanpa membuka tahap gudang untuknya.
        if (static::adalahManager($user) && in_array($status->slug, static::allowedSlugs(), true)) {
            return true;
        }

        return false;
    }

    /**
     * Alasan penolakan yang bisa ditampilkan ke pengguna.
     */
    public static function alasanTidakBolehPilih(?User $user, StatusPengiriman $status): ?string
    {
        if (static::bolehPilihStatus($user, $status)) {
            return null;
        }

        if (static::adalahGudang($user)) {
            return 'Tahap awal distribusi (pemesanan, produksi, kedatangan, packing) '
                ."adalah wewenang gudang. Status \"{$status->nama}\" bukan bagian dari tahap itu.";
        }

        return 'Status yang boleh dipindahkan hanya tahap perjalanan pengiriman. '
            ."Status \"{$status->nama}\" bukan bagian dari perjalanan.";
    }

    /**
     * Metadata untuk frontend: apakah dibatasi dan tahap apa saja yang tampil.
     *
     * `restricted`/`slugs` mengikuti batas DATA (manager). Batas PILIHAN untuk
     * gudang dikirim terpisah sebagai `gudangSlugs`, karena keduanya menjawab
     * pertanyaan berbeda: yang satu "resi mana yang boleh saya lihat", yang lain
     * "tahap mana yang boleh saya pindahkan".
     *
     * @return array{restricted: bool, slugs: array<int, string>, gudang: bool, gudangSlugs: array<int, string>}
     */
    public static function frontendContext(?User $user): array
    {
        return [
            'restricted' => static::restricts($user),
            'slugs' => static::restricts($user) ? static::allowedSlugs() : [],
            'gudang' => static::adalahGudang($user),
            'gudangSlugs' => static::adalahGudang($user) ? static::allowedGudangSlugs() : [],
        ];
    }
}
