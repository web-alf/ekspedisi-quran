<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use App\Models\StokMushaf;
use App\Models\TransferMushaf;
use App\Models\TransferStok;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ringkasan gudang untuk dashboard SPV (18/10/2026).
 *
 * Dashboard gudang sebelumnya hanya berisi tugas pribadi orang yang login, jadi SPV
 * Semarang dan Boyolali melihat halaman yang sama dengan staf packing — padahal pekerjaan
 * SPV adalah mengawasi gudangnya. Layanan ini menyusun apa yang perlu dipantau SPV,
 * SELALU terbatas ke gudang akunnya (gudang_id), sama seperti Stok Mushaf dan
 * Perpindahan Mushaf. Tanpa gudang (mis. super-admin) tidak menerima ringkasan.
 */
class RingkasanGudangService
{
    /**
     * @return array<string, mixed>|null null bila akun bukan SPV atau tidak bergudang.
     */
    public function untukSpv(User $user): ?array
    {
        if (! $user->hasRole(RoleEnum::SPV_GUDANG->value) || ! $user->gudang_id) {
            return null;
        }

        $gudang = Gudang::find($user->gudang_id);

        if (! $gudang) {
            return null;
        }

        $beban = $this->bebanGudang($gudang->id);
        $stokKartu = $this->stokKartu($gudang->id);

        return [
            'gudang' => ['id' => $gudang->id, 'kode' => $gudang->kode, 'nama' => $gudang->nama],
            'packing' => $this->packingHariIni($gudang->id),
            'perlu_tindakan' => $this->perluTindakan($gudang->id),
            'beban' => $beban,
            'stok_kartu' => $stokKartu,
            // Seksi "Stok" dashboard (keputusan pemilik 27/10/2026): tahap resi + saldo
            // mushaf belum ada muwakif per jenis/varian, semuanya milik gudang ini.
            'stok' => $this->stokGudang([[
                'id' => $gudang->id, 'kode' => $gudang->kode, 'nama' => $gudang->nama,
                'beban' => $beban, 'stok_kartu' => $stokKartu,
            ]], semuaGudang: false),
        ];
    }

    /**
     * Ringkasan DUA GUDANG untuk Manager Gudang (PJ, keputusan pemilik sistem 21/10/2026).
     *
     * Akun PJ tidak terikat satu gudang (gudang_id NULL) dan mengurus Semarang + Boyolali,
     * jadi dia butuh angka kedua gudang berdampingan — bukan ringkasan satu gudang. SPV
     * justru TERKECUALI: di produksi SPV ikut memegang role manager-repacking, sehingga
     * cakupannya tetap gudangnya sendiri (pakai untukSpv()).
     *
     * @return array<string, mixed>|null null bila bukan PJ.
     */
    public function untukManager(User $user): ?array
    {
        $bukanSpv = $user->hasRole(RoleEnum::MANAGER_REPACKING->value)
            && ! $user->hasRole(RoleEnum::SPV_GUDANG->value);

        if (! $bukanSpv) {
            return null;
        }

        $gudangList = Gudang::where('is_active', true)->orderBy('id')->get(['id', 'kode', 'nama']);

        $perGudang = $gudangList->map(function ($g) {
            $packing = $this->packingHariIni($g->id);

            return [
                'id' => $g->id, 'nama' => $g->nama, 'kode' => $g->kode,
                'target' => $packing['target'], 'selesai' => $packing['selesai'],
                'kerdus_tersegel' => $packing['kerdus_tersegel'],
                'staf_total' => $packing['jumlah_staf'],
                'staf_belum_mulai' => $packing['staf_belum_mulai'],
                // Seluruh freelancer yang ditugaskan hari ini (termasuk yang belum mulai),
                // dengan bentuk baris yang sama (status, jam, kerdus, persen) — dashboard PJ
                // memakai komponen FreelancerKerjaHariIni yang sama.
                'staf_kerja' => collect($packing['per_staf'])->values()->all(),
                // Hitungan miring saat pusat membeku; buat selalu betul:
                'persen' => $packing['target'] > 0 ? (int) round($packing['selesai'] / $packing['target'] * 100) : 0,
                'kerdus_menunggu_muat' => $this->hitungKerdusMenungguMuat($g->id),
                'beban' => $this->bebanGudang($g->id),
                'stok_kartu' => $this->stokKartu($g->id),
            ];
        })->values();

        // Seksi per gudang (keputusan pemilik 27/10/2026): rincian "Stok" tiap gudang,
        // dihitung dari baris yang sama dengan seksi total supaya angkanya bisa dijumlah.
        $perGudang = $perGudang->map(function ($baris) {
            $baris['stok'] = $this->stokGudang([[
                'id' => $baris['id'], 'kode' => $baris['kode'], 'nama' => $baris['nama'],
                'beban' => $baris['beban'], 'stok_kartu' => $baris['stok_kartu'],
            ]], semuaGudang: false);

            return $baris;
        });

        $stokTotal = $this->stokGudang($perGudang->all(), semuaGudang: true);

        // Selisih total dengan penjumlahan seksi per gudang = resi/kerdus lama yang belum
        // bertagih gudang (produksi: 26.079 resi pemesanan). Ditulis di seksi total supaya
        // pembaca tidak menyimpulkan "angkanya tidak nyambung".
        $stokTotal['tanpa_gudang'] = [
            'pemesanan' => max(0, $stokTotal['pemesanan']['resi'] - (int) $perGudang->sum(fn ($g) => $g['stok']['pemesanan']['resi'])),
            'packing' => max(0, $stokTotal['packing'] - (int) $perGudang->sum(fn ($g) => $g['stok']['packing'])),
            'selesai' => max(0, $stokTotal['selesai'] - (int) $perGudang->sum(fn ($g) => $g['stok']['selesai'])),
            'kerdus' => max(0, $stokTotal['kerdus']['jumlah'] - (int) $perGudang->sum(fn ($g) => $g['stok']['kerdus']['jumlah'])),
        ];

        return [
            'gudangList' => $gudangList->map(fn ($g) => [
                'id' => $g->id, 'kode' => $g->kode, 'nama' => $g->nama,
            ])->values()->all(),

            // Gabungan = JUMLAH kartu per gudang (25/10/2026). Dulu dihitung terpisah dari
            // seluruh tugas hari ini, termasuk yang belum bertagih gudang, sehingga total
            // tidak sama dengan Semarang + Boyolali dan bar bertumpuknya tidak pernah penuh.
            'gabungan' => [
                'target' => (int) $perGudang->sum('target'),
                'selesai' => (int) $perGudang->sum('selesai'),
                'kerdus_tersegel' => (int) $perGudang->sum('kerdus_tersegel'),
                'staf_bertugas' => (int) $perGudang->sum('staf_total'),
                'staf_belum_mulai' => (int) $perGudang->sum('staf_belum_mulai'),
                // Tugas hari ini yang tidak masuk gudang aktif mana pun — disebut terpisah
                // supaya tidak hilang dari pandangan PJ.
                'tugas_tanpa_gudang' => DailyPackingTask::query()
                    ->whereDate('tanggal_tugas', today())
                    ->where(fn (Builder $q) => $q->whereNull('gudang_id')->orWhereNotIn('gudang_id', $gudangList->pluck('id')))
                    ->count(),
            ],

            'per_gudang' => $perGudang->all(),

            // Empat kartu "Alur Mushaf" (keputusan pemilik 27/10/2026): satu bentuk untuk
            // SPV (satu gudang) dan Manager (dua gudang), dihitung dari angka yang SAMA
            // dengan beban & stok per gudang supaya tidak ada dua kebenaran.
            'stok' => $stokTotal,

            // Dokumen perpindahan yang menunggu diterima di gudang MANA PUN di bawah,
            // dengan arah asal → tujuan begitu jelas.
            'transfer_menunggu' => TransferMushaf::query()
                ->where('status', TransferMushaf::STATUS_DIPROSES)
                ->with(['gudangAsal:id,nama', 'gudangTujuan:id,nama'])
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn ($t) => [
                    'kode' => $t->kode,
                    'asal' => $t->gudangAsal?->nama,
                    'tujuan' => $t->gudangTujuan?->nama,
                    'jumlah_resi' => (int) $t->jumlah_resi,
                    'jumlah_eks' => (int) $t->jumlah_eks,
                    'dibuat_pada' => $t->created_at?->format('d/m/Y H:i'),
                ])
                ->values()->all(),

            // Dokumen transfer STOK yang menunggu diterima gudang mana pun di bawah PJ —
            // bentuknya sama dengan kartu "Perlu Tindakan" di dashboard SPV.
            'transfer_stok_menunggu' => $this->transferStokMenunggu(null),
        ];
    }

    /**
     * Daftar freelancer yang DITUGASKAN pada satu tanggal, untuk halaman Performa Kerja
     * (23/10/2026). Cakupannya sama dengan dashboard: SPV hanya gudangnya sendiri,
     * Manager Gudang (PJ) semua gudang aktif, peran lain null. Yang ditugaskan tapi
     * tidak mulai tetap dimuat (status `tidak_mulai`) supaya SPV tahu siapa yang absen.
     *
     * @return array{tanggal: string, hari_ini: string, gudang: array<int, array{id: int, kode: string, nama: string}>, ringkasan: array<string, int>, staf: array<int, array<string, mixed>>}|null
     */
    public function daftarKerja(User $user, CarbonInterface $tanggal): ?array
    {
        $gudangList = $this->cakupanGudang($user);

        if ($gudangList === null) {
            return null;
        }

        $tugas = DailyPackingTask::query()
            ->whereDate('tanggal_tugas', $tanggal)
            ->whereIn('gudang_id', $gudangList->pluck('id'))
            ->with(['user:id,name', 'gudang:id,kode,nama'])
            ->withCount('packingBoxes')
            ->get();

        $staf = $tugas
            ->map(function ($t) {
                /** @var DailyPackingTask $t */
                return [
                    'id' => $t->id,
                    'nama' => $t->user?->name ?? '-',
                    'gudang_id' => $t->gudang_id,
                    'gudang_kode' => $t->gudang?->kode,
                    'target' => (int) $t->total_target,
                    'selesai' => (int) $t->total_selesai,
                    'persen' => $t->total_target > 0 ? (int) round($t->total_selesai / $t->total_target * 100) : 0,
                    'status' => $this->statusKerja($t),
                    'jam_mulai' => $t->started_at?->format('H:i'),
                    'jam_selesai' => $t->completed_at?->format('H:i'),
                    'kerdus' => (int) $t->packing_boxes_count,
                ];
            })
            ->sortBy(fn (array $s) => [$s['status'] === 'tidak_mulai' ? 1 : 0, -$s['selesai'], $s['nama']])
            ->values();

        return [
            'tanggal' => $tanggal->format('Y-m-d'),
            'hari_ini' => today()->format('Y-m-d'),
            'gudang' => $gudangList->map(fn (Gudang $g) => ['id' => $g->id, 'kode' => $g->kode, 'nama' => $g->nama])->values()->all(),
            'ringkasan' => [
                'ditugaskan' => $staf->count(),
                'kerja' => $staf->where('status', '!=', 'tidak_mulai')->count(),
                'tidak_mulai' => $staf->where('status', 'tidak_mulai')->count(),
                'target' => (int) $staf->sum('target'),
                'selesai' => (int) $staf->sum('selesai'),
            ],
            'staf' => $staf->all(),
        ];
    }

    /**
     * Gudang yang boleh dipantau akun ini — aturan yang sama dengan untukSpv()/untukManager().
     *
     * @return Collection<int, Gudang>|null
     */
    private function cakupanGudang(User $user): ?Collection
    {
        if ($user->hasRole(RoleEnum::SPV_GUDANG->value)) {
            return $user->gudang_id
                ? Gudang::whereKey($user->gudang_id)->get(['id', 'kode', 'nama'])
                : null;
        }

        if ($user->hasRole(RoleEnum::MANAGER_REPACKING->value)) {
            return Gudang::where('is_active', true)->orderBy('id')->get(['id', 'kode', 'nama']);
        }

        return null;
    }

    /**
     * @param  DailyPackingTask|mixed  $tugas
     */
    private function statusKerja($tugas): string
    {
        if ($tugas->status === 'completed' || ($tugas->total_target > 0 && $tugas->total_selesai >= $tugas->total_target)) {
            return 'selesai';
        }

        return $tugas->started_at ? 'mulai' : 'tidak_mulai';
    }

    /**
     * Kerdus penuh/tersegel milik gudang ini yang isinya belum masuk muatan kurir —
     * rumus yang sama dengan PindaiKerdusService::kerdusSiap (satu aturan angka).
     */
    private function hitungKerdusMenungguMuat(int $gudangId): int
    {
        return PackingBox::query()
            ->where('gudang_id', $gudangId)
            ->whereIn('status', [PackingBox::STATUS_FULL, PackingBox::STATUS_SEALED])
            ->where(fn (Builder $q) => $q->doesntHave('packingItems')
                ->orWhereHas('packingItems', fn (Builder $isi) => $isi->whereNotExists(
                    fn ($muatan) => $muatan->selectRaw('1')
                        ->from('muatan_items')
                        ->whereColumn('muatan_items.pengiriman_id', 'packing_items.pengiriman_id')
                )))
            ->count();
    }

    /**
     * Gabungan seluruh tugas staf gudang ini hari ini — bukan tugas SPV sendiri.
     *
     * @return array<string, mixed>
     */
    private function packingHariIni(int $gudangId): array
    {
        $gudang = Gudang::find($gudangId);
        $tugas = DailyPackingTask::query()
            ->whereDate('tanggal_tugas', today())
            ->where('gudang_id', $gudangId)
            // roles ikut dimuat: baris daftar memakai label PERAN pemilik tugas, supaya
            // akun PJ/SPV yang juga punya tugas tidak tampil seolah-olah freelancer.
            ->with(['user:id,name', 'user.roles'])
            ->withCount('packingBoxes')
            ->get();

        return [
            'target' => (int) $tugas->sum('total_target'),
            'selesai' => (int) $tugas->sum('total_selesai'),
            'jumlah_staf' => $tugas->count(),
            'staf_belum_mulai' => $tugas->where('started_at', null)->count(),
            'kerdus_tersegel' => PackingBox::query()
                ->where('gudang_id', $gudangId)
                ->whereDate('sealed_at', today())
                ->count(),
            'per_staf' => $tugas
                ->sortBy(fn ($t) => [$t->started_at ? 0 : 1, $t->user?->name ?? ''])
                ->map(function ($t) use ($gudang) {
                    /** @var DailyPackingTask $t */
                    return [
                        'id' => $t->id,
                        'nama' => $t->user?->name ?? '-',
                        'kode' => $gudang?->kode,
                        // Label PERAN (role terluas akun itu): PJ dan SPV memang bisa ikut
                        // memegang tugas packing, dan tanpa label ini baris mereka terbaca
                        // sebagai freelancer biasa.
                        'peran' => $t->user?->role_display,
                        'selesai' => (int) $t->total_selesai,
                        'target' => (int) $t->total_target,
                        'mulai' => (bool) $t->started_at,
                        // Baris "Freelancer yang kerja hari ini" di dashboard memakai susunan
                        // yang sama dengan daftar di halaman Performa Kerja (status, jam, kerdus,
                        // bar progress), jadi keempat nilai itu ikut dikirim dari sini.
                        'status' => $this->statusKerja($t),
                        'persen' => $t->total_target > 0 ? (int) round($t->total_selesai / $t->total_target * 100) : 0,
                        'jam_mulai' => $t->started_at?->format('H:i'),
                        'jam_selesai' => $t->completed_at?->format('H:i'),
                        'kerdus' => (int) $t->packing_boxes_count,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * Yang menunggu keputusan SPV. Transfer mushaf paling mendesak: lokasi mushaf baru
     * berpindah saat SPV TUJUAN menekan Terima, dan tanpa angka ini di dashboard,
     * kiriman antar gudang bisa menggendut tanpa diketahui.
     *
     * @return array<string, mixed>
     */
    private function perluTindakan(int $gudangId): array
    {
        $menunggu = TransferMushaf::query()
            ->where('status', TransferMushaf::STATUS_DIPROSES)
            ->where('gudang_tujuan_id', $gudangId)
            ->with('gudangAsal:id,nama')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return [
            'transfer_menunggu' => $menunggu->map(fn ($t) => [
                'kode' => $t->kode,
                'asal' => $t->gudangAsal?->nama,
                'jumlah_resi' => (int) $t->jumlah_resi,
                'jumlah_eks' => (int) $t->jumlah_eks,
                'dibuat_pada' => $t->created_at?->format('d/m/Y H:i'),
            ])->values()->all(),
            // Dokumen transfer STOK (tanpa muwakif) yang menunggu diterima gudang ini.
            // Sebelumnya hanya daftar di halaman Stok Mushaf yang tahu, sehingga dokumen
            // bisa menggantung tanpa terlihat siapa pun (26/10/2026).
            'transfer_stok_menunggu' => $this->transferStokMenunggu($gudangId),
            'kerdus_menunggu_muat' => $this->hitungKerdusMenungguMuat($gudangId),
        ];
    }

    /**
     * Dokumen transfer stok berstatus `diproses` yang menunggu diterima.
     *
     * $gudangId null = semua gudang (dipakai dashboard PJ).
     *
     * @return array<int, array<string, mixed>>
     */
    private function transferStokMenunggu(?int $gudangId): array
    {
        return TransferStok::query()
            ->where('status', TransferStok::STATUS_DIPROSES)
            ->when($gudangId !== null, fn ($q) => $q->where('gudang_tujuan_id', $gudangId))
            ->with(['gudangAsal:id,nama', 'gudangTujuan:id,nama', 'jenisQuran:id,nama_jenis'])
            ->orderByDesc('id')->limit(10)->get()
            ->map(fn ($t) => [
                'kode' => $t->kode,
                'asal' => $t->gudangAsal?->nama,
                'tujuan' => $t->gudangTujuan?->nama,
                'jenis' => $t->jenisQuran?->nama_jenis,
                'varian' => $t->terjemahan ? 'Ada terjemahan' : 'Tanpa terjemahan',
                'jumlah_eks' => (int) $t->jumlah_eks,
                'dibuat_pada' => $t->created_at?->format('d/m/Y H:i'),
            ])->values()->all();
    }

    /**
     * Resi yang tercatat sedang dikerjakan gudang ini, per tahap. Resi lama yang belum
     * punya lokasi (null) memang tidak masuk — angka itu tidak bisa ditindaklanjuti
     * SPV dan hanya akan menyesatkan.
     *
     * @return array<string, mixed>
     */
    private function bebanGudang(int $gudangId): array
    {
        $perTahap = Pengiriman::query()
            ->join('status_pengiriman', 'status_pengiriman.id', '=', 'pengiriman.status_id')
            ->where('pengiriman.gudang_id', $gudangId)
            ->whereIn('status_pengiriman.slug', ['pemesanan', 'packing', 'selesai-packing'])
            ->groupBy('status_pengiriman.slug')
            ->selectRaw('status_pengiriman.slug, count(*) as jumlah, sum(pengiriman.jumlah_quran) as eks')
            ->pluck('jumlah', 'slug');

        $dalamKerdus = Pengiriman::query()
            ->where('gudang_id', $gudangId)
            ->whereHas('packingItem')
            ->count();

        return [
            'pemesanan' => (int) ($perTahap['pemesanan'] ?? 0),
            'packing' => (int) ($perTahap['packing'] ?? 0),
            'selesai_packing' => (int) ($perTahap['selesai-packing'] ?? 0),
            'dalam_kerdus' => $dalamKerdus,
        ];
    }

    /**
     * Angka seksi "Stok" dashboard gudang (keputusan pemilik 27/10/2026).
     *
     * Bentuknya satu untuk SPV dan Manager: tahap resi (Proses Pesan, Proses Packing,
     * Selesai Packing, Mushaf sudah dikarduskan & siap distribusi) dan saldo mushaf yang
     * BELUM ADA MUWAKIF per jenis/varian (A5 & A6 masing-masing dua varian, plus IQRA).
     *
     * `$semuaGudang` membedakan arti "seluruh":
     * - Manager: yang dihitung adalah SELURUH resi, termasuk baris lama yang belum bertagih
     *   gudang (di produksi 26.079 dari 26.080 resi pemesanan) — kalau dipotong per gudang,
     *   kartunya menampilkan 0 padahal barangnya ada.
     * - SPV: hanya resi/kerdus/stok gudangnya, sesuai janji halaman ini selalu ter-coup.
     *
     * @param  array<int, array<string, mixed>>  $perGudang  baris gudang berisi `id`, `kode`, `nama`, `beban`, `stok_kartu`
     * @return array<string, mixed>
     */
    private function stokGudang(array $perGudang, bool $semuaGudang): array
    {
        $ids = array_values(array_filter(array_map(fn (array $g) => $g['id'] ?? null, $perGudang)));

        $perTahap = Pengiriman::query()
            ->join('status_pengiriman', 'status_pengiriman.id', '=', 'pengiriman.status_id')
            ->whereIn('status_pengiriman.slug', ['pemesanan', 'packing', 'selesai-packing'])
            ->when(! $semuaGudang, fn ($q) => $q->whereIn('pengiriman.gudang_id', $ids))
            ->selectRaw("sum(case when status_pengiriman.slug = 'pemesanan' then 1 else 0 end) as pemesanan,
                         coalesce(sum(case when status_pengiriman.slug = 'pemesanan' then pengiriman.jumlah_quran else 0 end), 0) as pemesanan_eks,
                         sum(case when status_pengiriman.slug = 'packing' then 1 else 0 end) as packing,
                         sum(case when status_pengiriman.slug = 'selesai-packing' then 1 else 0 end) as selesai")
            ->first();

        // "Sudah dikarduskan & siap distribusi" = isi kerdus yang SUDAH TERSEGEL.
        $kerdus = PackingBox::query()
            ->where('status', PackingBox::STATUS_SEALED)
            ->when(! $semuaGudang, fn ($q) => $q->whereIn('gudang_id', $ids))
            ->withSum('packingItems as isi_eks', 'quantity')
            ->get(['id']);

        // Mushaf belum ada muwakif: lima kartu tetap (termasuk yang 0) supaya barisnya tidak
        // berubah-ubah saat stok kosong. IQRA mengikuti kode master `IQRO`.
        $petaStok = [];
        foreach ($perGudang as $g) {
            foreach ($g['stok_kartu'] ?? [] as $baris) {
                $kunci = ($baris['kode_jenis'] ?? '-').'|'.($baris['varian'] ?? '');
                $petaStok[$kunci] = ($petaStok[$kunci] ?? 0) + (int) ($baris['eks'] ?? 0);
            }
        }

        $muwakif = [];
        foreach ([
            ['A5', 'Dengan terjemahan', 'A5 · Terjemahan'],
            ['A5', 'Tanpa terjemahan', 'A5 · Tanpa Terjemahan'],
            ['A6', 'Dengan terjemahan', 'A6 · Terjemahan'],
            ['A6', 'Tanpa terjemahan', 'A6 · Tanpa Terjemahan'],
            ['IQRO', '', 'IQRA'],
        ] as [$kode, $varian, $label]) {
            $eks = $petaStok[$kode.'|'.$varian] ?? 0;

            if ($kode === 'IQRO') {
                $eks += $petaStok['IQRA|'] ?? 0;
            }

            $muwakif[] = ['label' => $label, 'eks' => (int) $eks];
        }

        return [
            'pemesanan' => [
                'resi' => (int) ($perTahap->pemesanan ?? 0),
                'eks' => (int) ($perTahap->pemesanan_eks ?? 0),
            ],
            'packing' => (int) ($perTahap->packing ?? 0),
            'selesai' => (int) ($perTahap->selesai ?? 0),
            'kerdus' => [
                'jumlah' => $kerdus->count(),
                'eks' => (int) $kerdus->sum('isi_eks'),
            ],
            'muwakif' => $muwakif,
        ];
    }

    /**
     * Saldo stok dalam bentuk KARTU per jenis × varian (A5/A6 punya varian terjemahan;
     * IQRA/IQRO tidak). Dipakai dashboard SPV dan manager gudang.
     *
     * @return array<int, array<string, mixed>>
     */
    public function stokKartu(int $gudangId): array
    {
        $varianJenis = config('pengiriman.stok_varian_jenis', ['A5', 'A6']);

        return StokMushaf::query()
            ->where('gudang_id', $gudangId)
            ->with('jenisQuran:id,kode_jenis,nama_jenis,default_capacity')
            ->orderBy('jenis_quran_id')
            ->orderBy('terjemahan')
            ->get()
            ->map(function ($s) use ($varianJenis) {
                /** @var StokMushaf $s */
                $adaVarian = in_array($s->jenisQuran?->kode_jenis, $varianJenis, true);

                return [
                    'kode_jenis' => $s->jenisQuran?->kode_jenis ?? '-',
                    'nama_jenis' => $s->jenisQuran?->nama_jenis ?? '-',
                    'varian' => $adaVarian ? $this->labelVarian((bool) $s->terjemahan) : '',
                    'eks' => $s->jumlahEks(),
                    'dus' => $s->jumlahDus(),
                    'sisa_eks' => $s->sisaEksDiLuarDus(),
                    'status' => $this->statusStok($s),
                ];
            })
            ->values()->all();
    }

    /** A5/A6/… punya varian terjemahan; IQRA/IQRO tidak memakai varian. */
    private function labelVarian(bool $terjemahan): ?string
    {
        return $terjemahan ? 'Dengan terjemahan' : 'Tanpa terjemahan';
    }

    /** Stok habis (0), rendah (di bawah satu dus penuh), atau normal. */
    /**
     * @param  StokMushaf|mixed  $s
     */
    private function statusStok($s): string
    {
        if ($s->jumlahEks() <= 0) {
            return 'habis';
        }

        return $s->jumlahEks() < ($s->jenisQuran?->getDefaultCapacity() ?? 1) ? 'rendah' : 'normal';
    }
}
