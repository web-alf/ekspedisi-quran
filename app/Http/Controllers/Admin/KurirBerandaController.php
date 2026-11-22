<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Muatan;
use App\Models\MuatanItem;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use App\Models\SerahTerimaKurir;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda kurir: "Hari Ini & Rute".
 *
 * Kurir di jalan cukup perlu tahu satu hal — ke mana berikutnya. Halaman ini
 * menampilkan muatan yang sedang dibawa dan tujuan-tujuannya, dikelompokkan dari
 * resi di dalam muatan (satu tujuan = satu lembaga/penerima di satu alamat).
 *
 * Mushaf yang diminta orang di tengah jalan (22/11/2026) tidak menjadi tujuan baru:
 * resinya tetap dihitung pada tujuan asalnya sebagai "diserahkan di jalan" dan
 * tampil di daftar terpisah, supaya progres tujuan tidak naik tanpa kunjungan.
 *
 * Hanya membaca. Memuat barang dan serah terima tetap lewat halaman Pindai.
 */
class KurirBerandaController extends Controller
{
    /** Status resi yang sudah tidak perlu diantar lagi oleh kurir. */
    private const SLUG_SAMPAI = [Pengiriman::SLUG_TIBA, 'diterima'];

    public function index(Request $request): Response
    {
        $muatan = $this->muatanAktif($request->user()->id);

        if (! $muatan) {
            return Inertia::render('Admin/Kurir/Beranda', [
                'muatan' => null,
                'tujuan' => [],
                'diJalan' => [],
            ]);
        }

        $isi = $this->isiMuatan($muatan);
        $tujuanDikunjungi = $isi['tujuan']->where('status', '!=', 'di_jalan');

        return Inertia::render('Admin/Kurir/Beranda', [
            'muatan' => [
                'id' => $muatan->id,
                'kode_muatan' => $muatan->kode_muatan,
                'nama_muatan' => $muatan->nama_muatan,
                'tanggal_muatan' => $muatan->tanggal_muatan?->format('Y-m-d'),
                'total_resi' => $isi['total_resi'],
                'total_mushaf' => $isi['total_mushaf'],
                'total_kerdus' => $isi['total_kerdus'],
                'jumlah_tujuan' => $tujuanDikunjungi->count(),
                'jumlah_tujuan_tiba' => $tujuanDikunjungi->where('status', 'tiba')->count(),
            ],
            'tujuan' => $isi['tujuan']->values(),
            'diJalan' => $isi['diJalan'],
        ]);
    }

    /**
     * Muatan terbaru milik kurir yang masih perlu diantar: ada resi yang belum
     * sampai, atau muatan baru yang belum diisi sama sekali.
     */
    private function muatanAktif(int $kurirId): ?Muatan
    {
        return Muatan::query()
            ->forKurir($kurirId)
            ->where(function ($q): void {
                $q->whereHas('items.pengiriman.status', fn ($s) => $s->whereNotIn('slug', [...self::SLUG_SAMPAI, 'batal']))
                    ->orWhereDoesntHave('items');
            })
            ->orderByDesc('tanggal_muatan')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Isi muatan: tujuan, penyerahan di jalan, dan totalnya.
     *
     * Total kerdus dihitung unik per muatan — satu kerdus bisa berisi resi untuk
     * lebih dari satu tujuan (packing per jenis), jadi jumlah per tujuan tidak boleh
     * dijumlahkan.
     *
     * @return array{tujuan: Collection<string, array<string, mixed>>, diJalan: array<int, array<string, mixed>>, total_resi: int, total_mushaf: int, total_kerdus: int}
     */
    private function isiMuatan(Muatan $muatan): array
    {
        $resi = MuatanItem::query()
            ->where('muatan_id', $muatan->id)
            ->with([
                'pengiriman:id,nama_penerima,nama_lembaga,alamat_tujuan,no_hp_penerima,jumlah_quran,status_id',
                'pengiriman.status:id,slug',
            ])
            ->orderBy('urutan')
            ->orderBy('id')
            ->get()
            ->pluck('pengiriman')
            ->filter(fn (?Pengiriman $p) => $p !== null && $p->status?->slug !== 'batal');

        $ids = $resi->pluck('id');

        $kerdusPerResi = PackingItem::query()
            ->whereIn('pengiriman_id', $ids)
            ->pluck('packing_box_id', 'pengiriman_id');

        $serahTerima = SerahTerimaKurir::query()
            ->whereIn('pengiriman_id', $ids)
            ->orderBy('id')
            ->get(['pengiriman_id', 'jenis', 'nama_penerima', 'alamat_penerima', 'nama_lembaga_asal', 'nama_penerima_asal', 'alamat_asal', 'created_at']);

        $tibaPerResi = $serahTerima
            ->where('jenis', SerahTerimaKurir::JENIS_TUJUAN)
            ->groupBy('pengiriman_id')
            ->map(fn (Collection $baris) => $baris->max('created_at'));

        $diJalanPerResi = $serahTerima
            ->where('jenis', SerahTerimaKurir::JENIS_TENGAH_JALAN)
            ->keyBy('pengiriman_id');

        return [
            'tujuan' => $this->daftarTujuan($resi, $kerdusPerResi, $tibaPerResi, $diJalanPerResi),
            'diJalan' => $this->daftarDiJalan($resi, $diJalanPerResi),
            'total_resi' => $resi->count(),
            'total_mushaf' => (int) $resi->sum(fn (Pengiriman $p) => $this->jumlahMushaf($p)),
            'total_kerdus' => $kerdusPerResi->filter()->unique()->count(),
        ];
    }

    /**
     * Tujuan dalam muatan, urut sesuai urutan resi dimuat.
     *
     * Resi yang diserahkan di tengah jalan dikelompokkan ke tujuan ASALNYA dan hanya
     * menambah `diserahkan_di_jalan`. Status tujuan: `tiba` bila semua resi yang masih
     * diantar sudah Tiba di Tujuan/Diterima; `di_jalan` bila seluruh resinya diminta di
     * jalan; tujuan pertama yang belum tiba menjadi `berikutnya` (saran urutan);
     * sisanya `menunggu`.
     *
     * @param  Collection<int, Pengiriman>  $resi
     * @param  Collection<int, int>  $kerdusPerResi
     * @param  Collection<int, mixed>  $tibaPerResi
     * @param  Collection<int, SerahTerimaKurir>  $diJalanPerResi
     * @return Collection<string, array{kunci: string, nama: string, penerima: ?string, alamat: ?string, no_hp: ?string, jumlah_resi: int, jumlah_mushaf: int, jumlah_kerdus: int, diserahkan_di_jalan: int, status: string, tiba_pukul: ?string}>
     */
    private function daftarTujuan(Collection $resi, Collection $kerdusPerResi, Collection $tibaPerResi, Collection $diJalanPerResi): Collection
    {
        $sudahAdaBerikutnya = false;

        return $resi
            ->groupBy(function (Pengiriman $p) use ($diJalanPerResi): string {
                $asal = $diJalanPerResi->get($p->id);

                return $asal
                    ? $this->kunciTujuan($asal->nama_lembaga_asal ?: $asal->nama_penerima_asal, $asal->alamat_asal)
                    : $this->kunciTujuan($p->nama_lembaga ?: $p->nama_penerima, $p->alamat_tujuan);
            })
            ->map(function (Collection $kelompok, string $kunci) use ($kerdusPerResi, $tibaPerResi, $diJalanPerResi, &$sudahAdaBerikutnya): array {
                [$diJalan, $diantar] = $kelompok->partition(fn (Pengiriman $p) => $diJalanPerResi->has($p->id));

                if ($diantar->isEmpty()) {
                    $status = 'di_jalan';
                } elseif ($diantar->every(fn (Pengiriman $p) => in_array($p->status?->slug, self::SLUG_SAMPAI, true))) {
                    $status = 'tiba';
                } elseif (! $sudahAdaBerikutnya) {
                    $status = 'berikutnya';
                    $sudahAdaBerikutnya = true;
                } else {
                    $status = 'menunggu';
                }

                $tibaTerakhir = $diantar->map(fn (Pengiriman $p) => $tibaPerResi->get($p->id))->filter()->max();

                return [
                    'kunci' => $kunci,
                    ...$this->identitasTujuan($diantar->first(), $diJalanPerResi->get($diJalan->first()?->id)),
                    'no_hp' => $diantar->pluck('no_hp_penerima')->filter()->first(),
                    'jumlah_resi' => $diantar->count(),
                    'jumlah_mushaf' => (int) $diantar->sum(fn (Pengiriman $p) => $this->jumlahMushaf($p)),
                    'jumlah_kerdus' => $diantar->map(fn (Pengiriman $p) => $kerdusPerResi->get($p->id))->filter()->unique()->count(),
                    'diserahkan_di_jalan' => (int) $diJalan->sum(fn (Pengiriman $p) => $this->jumlahMushaf($p)),
                    'status' => $status,
                    'tiba_pukul' => $status === 'tiba' && $tibaTerakhir ? $tibaTerakhir->format('H.i') : null,
                ];
            });
    }

    /**
     * Nama dan alamat tujuan. Bila semua resinya sudah dialihkan di jalan, resi tidak
     * lagi menyimpan tujuan aslinya — ambil dari catatan serah terima.
     *
     * @return array{nama: string, penerima: ?string, alamat: ?string}
     */
    private function identitasTujuan(?Pengiriman $diantar, ?SerahTerimaKurir $asal): array
    {
        if ($diantar) {
            return [
                'nama' => $diantar->nama_lembaga ?: ($diantar->nama_penerima ?: 'Tanpa nama'),
                'penerima' => $diantar->nama_lembaga ? $diantar->nama_penerima : null,
                'alamat' => $diantar->alamat_tujuan,
            ];
        }

        return [
            'nama' => $asal?->nama_lembaga_asal ?: ($asal?->nama_penerima_asal ?: 'Tanpa nama'),
            'penerima' => $asal?->nama_lembaga_asal ? $asal->nama_penerima_asal : null,
            'alamat' => $asal?->alamat_asal,
        ];
    }

    /**
     * Penyerahan di tengah jalan, satu baris per penerima baru di satu alamat.
     *
     * @param  Collection<int, Pengiriman>  $resi
     * @param  Collection<int, SerahTerimaKurir>  $diJalanPerResi
     * @return array<int, array{nama: string, alamat: ?string, jumlah_mushaf: int, dari: string, pukul: ?string}>
     */
    private function daftarDiJalan(Collection $resi, Collection $diJalanPerResi): array
    {
        $resiPerId = $resi->keyBy('id');

        return $diJalanPerResi
            ->groupBy(fn (SerahTerimaKurir $s) => $this->kunciTujuan($s->nama_penerima, $s->alamat_penerima))
            ->map(fn (Collection $baris) => [
                'nama' => $baris->first()->nama_penerima,
                'alamat' => $baris->first()->alamat_penerima,
                'jumlah_mushaf' => (int) $baris->sum(fn (SerahTerimaKurir $s) => $this->jumlahMushaf($resiPerId->get($s->pengiriman_id))),
                'dari' => $baris->map(fn (SerahTerimaKurir $s) => $s->nama_lembaga_asal ?: $s->nama_penerima_asal)->filter()->unique()->implode(', '),
                'pukul' => $baris->max('created_at')?->format('H.i'),
            ])
            ->sortBy('pukul')
            ->values()
            ->all();
    }

    private function jumlahMushaf(?Pengiriman $p): int
    {
        return $p ? ((int) $p->jumlah_quran ?: 1) : 0;
    }

    /** Satu tujuan = nama lembaga (atau penerima) di satu alamat, tanpa peduli huruf besar/spasi. */
    private function kunciTujuan(?string $nama, ?string $alamat): string
    {
        return mb_strtolower(trim((string) $nama)).'|'.mb_strtolower(trim((string) $alamat));
    }
}
