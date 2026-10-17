<?php

namespace App\Services;

use App\Models\Pengiriman;
use App\Models\SerahTerimaKurir;
use App\Models\StatusHistory;
use App\Models\StatusPengiriman;
use App\Models\TrackingHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Riwayat perjalanan resi untuk HALAMAN PUBLIK (lacak resi, API lacak).
 *
 * Kenapa tidak menampilkan riwayat mentah (keputusan pemilik sistem, 16/10/2026):
 * riwayat mentah adalah log kerja internal — setiap pindai massal ("Bulk update status"),
 * koreksi mundur (Selesai Packing → Pemesanan → Packing), nama petugas, catatan uji, sampai
 * isi doa donatur yang tertulis di catatan impor. Semua itu ikut terkirim ke peramban
 * publik. Yang perlu dilihat donatur/penerima hanya tahap-tahap penting perjalanannya.
 *
 * Aturannya:
 *   1. Hanya tahap di `config('pengiriman.riwayat_publik_tahap')`.
 *   2. Satu entri per tahap — waktu TERAKHIR tahap itu dicapai, sehingga koreksi bolak-
 *      balik tidak tampil berulang.
 *   3. Tahap yang sudah "dibatalkan" oleh koreksi mundur (urutannya di atas status
 *      sekarang) tidak ditampilkan — publik tidak perlu melihat langkah yang ditarik.
 *   4. Keterangan = deskripsi baku tahap, BUKAN catatan petugas. Pengecualian hanya untuk
 *      serah terima: nama penerima memang informasi yang ditunggu publik.
 *   5. Nama petugas tidak pernah dikirim. Foto dokumentasi dan lokasi tetap ditampilkan.
 */
class RiwayatPublikService
{
    /**
     * @return array<int, array{slug: string, nama: string, icon: ?string, waktu: string, keterangan: ?string, lokasi: ?string, foto: array<int, string>}>
     */
    public function susun(Pengiriman $pengiriman): array
    {
        $tahapPublik = config('pengiriman.riwayat_publik_tahap', []);
        $status = StatusPengiriman::query()->whereIn('slug', $tahapPublik)->get()->keyBy('id');
        // Diambil utuh, bukan dari relasi `status`: pemanggil sering memuat relasi itu
        // dengan kolom terbatas (tanpa `urutan`), dan perbandingan dengan null diam-diam
        // membuang hampir semua tahap.
        $sekarang = StatusPengiriman::query()->find($pengiriman->status_id);

        $kejadian = $this->kejadianDariStatusHistory($pengiriman->statusHistory)
            ->concat($this->kejadianDariTrackingHistory($pengiriman->trackingHistory))
            ->filter(fn (array $k) => $status->has($k['status_id']));

        // Langkah yang ditarik kembali oleh koreksi mundur tidak ikut tampil. Status "batal"
        // (urutan 99) dikecualikan: perjalanan sampai pembatalannya tetap relevan.
        if ($sekarang && $sekarang->slug !== 'batal') {
            $kejadian = $kejadian->filter(fn (array $k) => $status[$k['status_id']]->urutan <= $sekarang->urutan);
        }

        // Waktu resi dibuat selalu menjadi calon waktu "Pemesanan": resi impor sering
        // dibuat langsung di tahap lanjut tanpa riwayat pemesanan.
        $pemesanan = $status->firstWhere('slug', 'pemesanan');
        if ($pemesanan && $pengiriman->created_at) {
            $kejadian->push(['status_id' => $pemesanan->id, 'waktu' => $pengiriman->created_at, 'lokasi' => null, 'foto' => []]);
        }

        $perTahap = $kejadian->groupBy('status_id')->map(fn (Collection $grup, int $statusId) => [
            'status' => $status[$statusId],
            'kandidat' => $grup->pluck('waktu')->filter()->sort()->values(),
            'lokasi' => $grup->sortBy('waktu')->pluck('lokasi')->filter()->last(),
            'foto' => $grup->pluck('foto')->flatten()->filter()->unique()->values()->all(),
        ]);

        // Status sekarang selalu tampil walau tidak punya riwayat (mis. diubah lewat jalur
        // yang tidak mencatat riwayat).
        if ($sekarang && $status->has($sekarang->id) && ! $perTahap->has($sekarang->id)) {
            $perTahap->put($sekarang->id, [
                'status' => $status[$sekarang->id],
                'kandidat' => collect([$pengiriman->updated_at ?? $pengiriman->created_at])->filter(),
                'lokasi' => null,
                'foto' => [],
            ]);
        }

        // Status sekarang yang riwayat terakhirnya LEBIH LAMA daripada tahap lain berarti
        // dicapai ulang lewat jalur tanpa riwayat (mis. penyegelan kerdus menjadikan resi
        // "Selesai Packing" tanpa StatusHistory). Waktu terbaik yang ada: updated_at resi.
        // Contoh nyata EQ-2026-21534: packing 16/10 08:30, disegel 08:38, tapi riwayat
        // "Selesai Packing" terakhir 13/10.
        if ($sekarang && $perTahap->has($sekarang->id) && $pengiriman->updated_at) {
            $terakhirSekarang = $perTahap[$sekarang->id]['kandidat']->last();
            $terakhirLain = $perTahap->except($sekarang->id)->flatMap(fn (array $t) => $t['kandidat'])->max();

            if ($terakhirLain && $terakhirSekarang && $terakhirSekarang->lt($terakhirLain) && $pengiriman->updated_at->gte($terakhirLain)) {
                $perTahap->put($sekarang->id, array_merge($perTahap[$sekarang->id], [
                    'kandidat' => $perTahap[$sekarang->id]['kandidat']->push($pengiriman->updated_at)->sort()->values(),
                ]));
            }
        }

        // Waktu per tahap: dari tahap tertinggi turun, tiap tahap memakai waktu TERAKHIR
        // yang tidak melewati tahap di atasnya. Tanpa ini, koreksi mundur (selesai packing →
        // pemesanan pada 15/10) membuat "Pemesanan" tampil lebih baru daripada "Produksi".
        $batas = null;
        $perTahap = $perTahap
            ->sortByDesc(fn (array $t) => $t['status']->urutan)
            ->map(function (array $t) use (&$batas) {
                $kandidat = $t['kandidat'];
                // Bila tak ada waktu yang tidak melewati tahap di atasnya (data janggal), pakai
                // waktu tahap di atasnya itu — urutan waktu tetap tidak terbalik.
                $waktu = $batas
                    ? ($kandidat->filter(fn ($w) => $w->lte($batas))->last() ?? $batas)
                    : $kandidat->last();
                $batas = $waktu ?? $batas;

                return $t + ['waktu' => $waktu];
            });

        $serahTerima = $pengiriman->relationLoaded('serahTerimaKurir')
            ? $pengiriman->serahTerimaKurir->sortByDesc('id')->first()
            : $pengiriman->serahTerimaKurir()->latest('id')->first();

        return $perTahap
            ->values()
            ->map(fn (array $t) => [
                'slug' => $t['status']->slug,
                'nama' => $t['status']->nama,
                'icon' => $t['status']->icon,
                'waktu' => $t['waktu']?->format('d/m/Y H:i'),
                'keterangan' => $this->keterangan($t['status'], $pengiriman, $serahTerima),
                'lokasi' => $t['lokasi'],
                'foto' => $t['foto'],
            ])
            ->all();
    }

    private function keterangan(StatusPengiriman $status, Pengiriman $pengiriman, ?SerahTerimaKurir $serahTerima): ?string
    {
        if ($status->slug === Pengiriman::SLUG_TIBA && $serahTerima) {
            return $serahTerima->jenis === SerahTerimaKurir::JENIS_TENGAH_JALAN
                ? "Diserahkan dalam perjalanan kepada {$serahTerima->nama_penerima}."
                : "Diserahkan kepada {$serahTerima->nama_penerima}.";
        }

        if ($status->slug === 'diterima' && $pengiriman->received_by) {
            return "Quran telah diterima oleh {$pengiriman->received_by}.";
        }

        return $status->deskripsi;
    }

    /**
     * @param  iterable<StatusHistory>  $riwayat
     * @return Collection<int, array{status_id: int, waktu: Carbon, lokasi: ?string, foto: array<int, string>}>
     */
    private function kejadianDariStatusHistory(iterable $riwayat): Collection
    {
        return collect($riwayat)->map(fn (StatusHistory $h) => [
            'status_id' => (int) $h->status_to,
            'waktu' => $h->created_at,
            'lokasi' => $h->lokasi,
            'foto' => $this->fotoStatusHistory($h),
        ]);
    }

    /**
     * @param  iterable<TrackingHistory>  $riwayat
     * @return Collection<int, array{status_id: int, waktu: Carbon, lokasi: ?string, foto: array<int, string>}>
     */
    private function kejadianDariTrackingHistory(iterable $riwayat): Collection
    {
        return collect($riwayat)->map(fn (TrackingHistory $h) => [
            'status_id' => (int) $h->status_id,
            'waktu' => $h->tanggal_update ?? $h->created_at,
            'lokasi' => $h->lokasi,
            'foto' => $h->photo_urls,
        ]);
    }

    /**
     * Kolom `dokumentasi` lama tersimpan dalam beberapa bentuk (larik path, JSON ganda,
     * larik objek {path,url}) — dibaca mentah supaya tidak bergantung pada cast model.
     *
     * @return array<int, string>
     */
    private function fotoStatusHistory(StatusHistory $h): array
    {
        $mentah = $h->getRawOriginal('dokumentasi');
        $terurai = $mentah ? json_decode($mentah, true) : null;

        if (is_string($terurai)) {
            $terurai = json_decode($terurai, true);
        }

        if (! is_array($terurai)) {
            return [];
        }

        return collect($terurai)->map(function ($item) {
            if (is_string($item)) {
                return asset('storage/'.$item);
            }

            if (is_array($item) && isset($item['path'])) {
                return $item['url'] ?? asset('storage/'.$item['path']);
            }

            return null;
        })->filter()->values()->all();
    }
}
