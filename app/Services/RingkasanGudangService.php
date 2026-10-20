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
use Illuminate\Database\Eloquent\Builder;

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
            'stok' => $this->stokPerJenis($gudang->id),
        ];
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
     * Stok gudang per jenis, dalam eks dan dus (satuannya sama dengan halaman Stok Mushaf).
     *
     * @return array<int, array<string, mixed>>
     */
    private function stokPerJenis(int $gudangId): array
    {
        return StokMushaf::query()
            ->where('gudang_id', $gudangId)
            ->with('jenisQuran:id,kode_jenis,nama_jenis')
            ->orderBy('jenis_quran_id')
            ->orderByDesc('terjemahan')
            ->get()
            ->map(fn (StokMushaf $s) => [
                'jenis' => $s->jenisQuran?->nama_jenis ?? $s->jenisQuran?->kode_jenis ?? '-',
                // Varian wajib disebut (stok per jenis × varian sejak 19/10/2026): tanpa ini
                // dua baris "Al-Quran A5" terlihat identik.
                'varian' => $s->terjemahan ? 'Ada terjemahan' : 'Tanpa terjemahan',
                'eks' => $s->jumlahEks(),
                'dus' => $s->jumlahDus(),
            ])
            ->values()
            ->all();
    }
}
