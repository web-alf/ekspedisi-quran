<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use App\Models\StokMushaf;
use App\Models\TransferMushaf;
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

        return [
            'gudang' => ['id' => $gudang->id, 'kode' => $gudang->kode, 'nama' => $gudang->nama],
            'packing' => $this->packingHariIni($gudang->id),
            'perlu_tindakan' => $this->perluTindakan($gudang->id),
            'beban' => $this->bebanGudang($gudang->id),
            'stok_kartu' => $this->stokKartu($gudang->id),
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

        return [
            'gudangList' => $gudangList->map(fn (Gudang $g) => [
                'id' => $g->id, 'kode' => $g->kode, 'nama' => $g->nama,
            ])->values()->all(),

            // Gabungan seluruh tugas kedua gudang ini hari ini.
            'gabungan' => [
                'target' => DailyPackingTask::query()->whereDate('tanggal_tugas', today())->sum('total_target'),
                'selesai' => DailyPackingTask::query()->whereDate('tanggal_tugas', today())->sum('total_selesai'),
                'kerdus_tersegel' => PackingBox::query()->whereDate('sealed_at', today())->count(),
                'staf_bertugas' => DailyPackingTask::query()->whereDate('tanggal_tugas', today())->count(),
                'staf_belum_mulai' => DailyPackingTask::query()->whereDate('tanggal_tugas', today())->whereNull('started_at')->count(),
            ],

            'per_gudang' => $gudangList->map(function (Gudang $g) {
                $packing = $this->packingHariIni($g->id);

                return [
                    'id' => $g->id, 'nama' => $g->nama, 'kode' => $g->kode,
                    'target' => $packing['target'], 'selesai' => $packing['selesai'],
                    'kerdus_tersegel' => $packing['kerdus_tersegel'],
                    'staf_total' => $packing['jumlah_staf'],
                    'staf_belum_mulai' => $packing['staf_belum_mulai'],
                    'belum_nama' => collect($packing['per_staf'])->where('mulai', false)->pluck('nama')->values()->all(),
                    // Hitungan miring saat pusat membeku; buat selalu betul:
                    'persen' => $packing['target'] > 0 ? (int) round($packing['selesai'] / $packing['target'] * 100) : 0,
                    'kerdus_menunggu_muat' => $this->hitungKerdusMenungguMuat($g->id),
                    'beban' => $this->bebanGudang($g->id),
                    'stok_kartu' => $this->stokKartu($g->id),
                ];
            })->values()->all(),

            // Dokumen perpindahan yang menunggu diterima di gudang MANA PUN di bawah,
            // dengan arah asal → tujuan begitu jelas.
            'transfer_menunggu' => TransferMushaf::query()
                ->where('status', TransferMushaf::STATUS_DIPROSES)
                ->with(['gudangAsal:id,nama', 'gudangTujuan:id,nama'])
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn (TransferMushaf $t) => [
                    'kode' => $t->kode,
                    'asal' => $t->gudangAsal?->nama,
                    'tujuan' => $t->gudangTujuan?->nama,
                    'jumlah_resi' => (int) $t->jumlah_resi,
                    'jumlah_eks' => (int) $t->jumlah_eks,
                    'dibuat_pada' => $t->created_at?->format('d/m/Y H:i'),
                ])
                ->values()->all(),
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
            ->map(fn (DailyPackingTask $t) => [
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
            ])
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

    private function statusKerja(DailyPackingTask $tugas): string
    {
        if ($tugas->status === 'completed' || ($tugas->total_target > 0 && $tugas->total_selesai >= $tugas->total_target)) {
            return 'selesai';
        }

        return $tugas->started_at ? 'mulai' : 'tidak_mulai';
    }

    /** Hitungan kerdus penuh/tersegel yang belum dimuat — diekstrak dari perluTindakan supaya juga dipakai manager. */
    private function hitungKerdusMenungguMuat(int $gudangId): int
    {
        return $this->perluTindakan($gudangId)['kerdus_menunggu_muat'];
    }

    /**
     * Gabungan seluruh tugas staf gudang ini hari ini — bukan tugas SPV sendiri.
     *
     * @return array<string, mixed>
     */
    private function packingHariIni(int $gudangId): array
    {
        $tugas = DailyPackingTask::query()
            ->whereDate('tanggal_tugas', today())
            ->where('gudang_id', $gudangId)
            ->with('user:id,name')
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
                ->sortBy(fn (DailyPackingTask $t) => [$t->started_at ? 0 : 1, $t->user?->name ?? ''])
                ->map(fn (DailyPackingTask $t) => [
                    'nama' => $t->user?->name ?? '-',
                    'selesai' => (int) $t->total_selesai,
                    'target' => (int) $t->total_target,
                    'mulai' => (bool) $t->started_at,
                ])
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

        // Kerdus penuh/tersegel milik gudang ini yang isinya belum masuk muatan kurir —
        // rumus yang sama dengan PindaiKerdusService::kerdusSiap (satu aturan angka).
        $kerdusMenunggu = PackingBox::query()
            ->where('gudang_id', $gudangId)
            ->whereIn('status', [PackingBox::STATUS_FULL, PackingBox::STATUS_SEALED])
            ->where(fn (Builder $q) => $q->doesntHave('packingItems')
                ->orWhereHas('packingItems', fn (Builder $isi) => $isi->whereNotExists(
                    fn ($muatan) => $muatan->selectRaw('1')
                        ->from('muatan_items')
                        ->whereColumn('muatan_items.pengiriman_id', 'packing_items.pengiriman_id')
                )))
            ->count();

        return [
            'transfer_menunggu' => $menunggu->map(fn (TransferMushaf $t) => [
                'kode' => $t->kode,
                'asal' => $t->gudangAsal?->nama,
                'jumlah_resi' => (int) $t->jumlah_resi,
                'jumlah_eks' => (int) $t->jumlah_eks,
                'dibuat_pada' => $t->created_at?->format('d/m/Y H:i'),
            ])->values()->all(),
            'kerdus_menunggu_muat' => $kerdusMenunggu,
        ];
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
            ->whereIn('status_pengiriman.slug', ['pemesanan', 'produksi', 'packing', 'selesai-packing'])
            ->groupBy('status_pengiriman.slug')
            ->selectRaw('status_pengiriman.slug, count(*) as jumlah, sum(pengiriman.jumlah_quran) as eks')
            ->pluck('jumlah', 'slug');

        $dalamKerdus = Pengiriman::query()
            ->where('gudang_id', $gudangId)
            ->whereHas('packingItem')
            ->count();

        return [
            'pemesanan' => (int) ($perTahap['pemesanan'] ?? 0),
            'produksi' => (int) ($perTahap['produksi'] ?? 0),
            'packing' => (int) ($perTahap['packing'] ?? 0),
            'selesai_packing' => (int) ($perTahap['selesai-packing'] ?? 0),
            'dalam_kerdus' => $dalamKerdus,
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
            ->map(function (StokMushaf $s) use ($varianJenis) {
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
    private function statusStok(StokMushaf $s): string
    {
        if ($s->jumlahEks() <= 0) {
            return 'habis';
        }

        return $s->jumlahEks() < ($s->jenisQuran?->getDefaultCapacity() ?? 1) ? 'rendah' : 'normal';
    }
}
