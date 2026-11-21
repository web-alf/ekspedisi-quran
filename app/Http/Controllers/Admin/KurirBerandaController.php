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
            ]);
        }

        $tujuan = $this->daftarTujuan($muatan);

        return Inertia::render('Admin/Kurir/Beranda', [
            'muatan' => [
                'id' => $muatan->id,
                'kode_muatan' => $muatan->kode_muatan,
                'nama_muatan' => $muatan->nama_muatan,
                'tanggal_muatan' => $muatan->tanggal_muatan?->format('Y-m-d'),
                'total_resi' => $tujuan->sum('jumlah_resi'),
                'total_mushaf' => $tujuan->sum('jumlah_mushaf'),
                'total_kerdus' => $tujuan->sum('jumlah_kerdus'),
                'jumlah_tujuan' => $tujuan->count(),
                'jumlah_tujuan_tiba' => $tujuan->where('status', 'tiba')->count(),
            ],
            'tujuan' => $tujuan->values(),
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
     * Tujuan dalam muatan, urut sesuai urutan resi dimuat.
     *
     * Status tujuan: `tiba` bila semua resinya sudah Tiba di Tujuan/Diterima;
     * tujuan pertama yang belum tiba menjadi `berikutnya`; sisanya `menunggu`.
     *
     * @return Collection<int, array{kunci: string, nama: string, penerima: ?string, alamat: ?string, no_hp: ?string, jumlah_resi: int, jumlah_mushaf: int, jumlah_kerdus: int, status: string, tiba_pukul: ?string}>
     */
    private function daftarTujuan(Muatan $muatan): Collection
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

        $tibaPerResi = SerahTerimaKurir::query()
            ->whereIn('pengiriman_id', $ids)
            ->where('jenis', SerahTerimaKurir::JENIS_TUJUAN)
            ->get(['pengiriman_id', 'created_at'])
            ->groupBy('pengiriman_id')
            ->map(fn (Collection $baris) => $baris->max('created_at'));

        $sudahAdaBerikutnya = false;

        return $resi
            ->groupBy(fn (Pengiriman $p) => $this->kunciTujuan($p))
            ->map(function (Collection $kelompok, string $kunci) use ($kerdusPerResi, $tibaPerResi, &$sudahAdaBerikutnya): array {
                $pertama = $kelompok->first();
                $semuaSampai = $kelompok->every(fn (Pengiriman $p) => in_array($p->status?->slug, self::SLUG_SAMPAI, true));

                if ($semuaSampai) {
                    $status = 'tiba';
                } elseif (! $sudahAdaBerikutnya) {
                    $status = 'berikutnya';
                    $sudahAdaBerikutnya = true;
                } else {
                    $status = 'menunggu';
                }

                $tibaTerakhir = $kelompok->map(fn (Pengiriman $p) => $tibaPerResi->get($p->id))->filter()->max();

                return [
                    'kunci' => $kunci,
                    'nama' => $pertama->nama_lembaga ?: ($pertama->nama_penerima ?: 'Tanpa nama'),
                    'penerima' => $pertama->nama_lembaga ? $pertama->nama_penerima : null,
                    'alamat' => $pertama->alamat_tujuan,
                    'no_hp' => $kelompok->pluck('no_hp_penerima')->filter()->first(),
                    'jumlah_resi' => $kelompok->count(),
                    'jumlah_mushaf' => (int) $kelompok->sum(fn (Pengiriman $p) => $p->jumlah_quran ?: 1),
                    'jumlah_kerdus' => $kelompok->map(fn (Pengiriman $p) => $kerdusPerResi->get($p->id))->filter()->unique()->count(),
                    'status' => $status,
                    'tiba_pukul' => $status === 'tiba' && $tibaTerakhir ? $tibaTerakhir->format('H.i') : null,
                ];
            });
    }

    /** Satu tujuan = nama lembaga (atau penerima) di satu alamat, tanpa peduli huruf besar/spasi. */
    private function kunciTujuan(Pengiriman $p): string
    {
        $nama = $p->nama_lembaga ?: $p->nama_penerima;

        return mb_strtolower(trim((string) $nama)).'|'.mb_strtolower(trim((string) $p->alamat_tujuan));
    }
}
