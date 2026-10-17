<?php

namespace App\Services;

use App\Exceptions\SerahTerimaDitolak;
use App\Models\Muatan;
use App\Models\MuatanItem;
use App\Models\MushafRequest;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use App\Models\SerahTerimaKurir;
use App\Models\StatusPengiriman;
use App\Models\TrackingHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Serah terima oleh kurir — pindai TERAKHIR sebelum mushaf berpindah tangan.
 *
 * Keputusan pemilik sistem (16/10/2026):
 *
 *   1. Di lokasi lembaga, kurir memindai kerdus (seluruh isinya) atau per mushaf, mengisi
 *      nama penerima di lokasi (+ HP, foto, GPS). Resinya menjadi "Tiba di Tujuan".
 *      "Diterima" tetap dinyatakan manager/distribusi — pengantar tidak memeriksa
 *      pekerjaannya sendiri (lihat Pengiriman::boot()).
 *   2. Di tengah jalan, orang lain boleh meminta beberapa mushaf. Kurir memindai QR PER
 *      MUSHAF (bukan kerdus: satu salah pindai tidak boleh mengalihkan 40 mushaf), mengisi
 *      penerima baru, dan resinya DIALIHKAN: penerima/alamat ditimpa, dilepas dari
 *      permintaan lembaganya, dan langsung "Tiba di Tujuan". Lembaga menerima lebih
 *      sedikit; permintaannya tetap bisa selesai dengan catatan "N diserahkan di jalan".
 *      Kurir boleh langsung tanpa persetujuan; semuanya tercatat untuk manager.
 *
 * Alurnya dua langkah karena kurir harus melihat apa yang akan diserahkan sebelum mengisi
 * formulir: `periksa()` (tanpa menulis apa pun) lalu `simpan()`.
 */
class SerahTerimaKurirService
{
    public const MODE_TUJUAN = SerahTerimaKurir::JENIS_TUJUAN;

    public const MODE_TENGAH_JALAN = SerahTerimaKurir::JENIS_TENGAH_JALAN;

    /** Batas resi per serah terima — satu kerdus Iqra berisi sampai 160 eks. */
    public const MAKS_RESI = 200;

    /**
     * Uraikan hasil pindai menjadi daftar resi yang AKAN diserahkan. Tidak menulis apa pun.
     *
     * @return array{status: int, data: array<string, mixed>}
     */
    public function periksa(User $user, string $kode, string $mode): array
    {
        $kodeKerdus = PackingBox::kodeDariPindai($kode);
        $adalahKerdus = str_starts_with($kodeKerdus, 'KB-');

        if ($adalahKerdus && $mode === self::MODE_TENGAH_JALAN) {
            return $this->gagal(422, 'Penyerahan di tengah jalan dipindai PER MUSHAF, bukan per kerdus — supaya satu salah pindai tidak mengalihkan seluruh isi kerdus.');
        }

        if ($adalahKerdus) {
            $box = PackingBox::query()
                ->with(['packingItems.pengiriman' => fn ($q) => $q->with(['status:id,nama,slug', 'jenisQuran:id,nama_jenis'])])
                ->where('kode_kerdus', $kodeKerdus)
                ->first();

            if (! $box) {
                return $this->gagal(404, "Kerdus {$kodeKerdus} tidak ditemukan.");
            }

            $resi = $box->packingItems->pluck('pengiriman')->filter()->values();

            if ($resi->isEmpty()) {
                return $this->gagal(422, "Kerdus {$kodeKerdus} belum berisi resi.");
            }
        } else {
            $noResi = $this->bersihkanResi($kode);
            $satu = Pengiriman::with(['status:id,nama,slug', 'jenisQuran:id,nama_jenis'])->where('no_resi', $noResi)->first();

            if (! $satu) {
                return $this->gagal(404, "Resi {$noResi} tidak ditemukan.");
            }

            $resi = collect([$satu]);
        }

        $muatanPerResi = $this->muatanPerResi($resi->pluck('id')->all());

        $baris = $resi->map(function (Pengiriman $p) use ($user, $muatanPerResi) {
            $alasan = $this->alasanTidakBolehDiserahkan($user, $p, $muatanPerResi[$p->id] ?? null);

            return [
                'id' => $p->id,
                'no_resi' => $p->no_resi,
                'jenis' => $p->jenisQuran?->nama_jenis,
                'jumlah_quran' => (int) $p->jumlah_quran,
                'nama_lembaga' => $p->nama_lembaga,
                'nama_penerima' => $p->nama_penerima,
                'alamat_tujuan' => $p->alamat_tujuan,
                'no_hp_penerima' => $p->no_hp_penerima,
                'status' => $p->status?->nama,
                'tujuan' => $this->kunciTujuan($p),
                'boleh' => $alasan === null,
                'alasan' => $alasan,
            ];
        })->values();

        $boleh = $baris->where('boleh', true);

        // Kerdus bisa berisi resi untuk lebih dari satu tujuan (packing per jenis, bukan
        // per tujuan). Kurir harus memilih di tujuan mana ia berada — kalau tidak, resi
        // lembaga lain ikut tercatat "tiba" di sini.
        $tujuan = $boleh->groupBy('tujuan')->map(fn (Collection $grup, string $kunci) => [
            'kunci' => $kunci,
            'nama_lembaga' => $grup->first()['nama_lembaga'],
            'nama_penerima' => $grup->first()['nama_penerima'],
            'alamat_tujuan' => $grup->first()['alamat_tujuan'],
            'no_hp_penerima' => $grup->first()['no_hp_penerima'],
            'jumlah_resi' => $grup->count(),
            'id_resi' => $grup->pluck('id')->values()->all(),
        ])->values();

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'mode' => $mode,
                'kode_kerdus' => $adalahKerdus ? $kodeKerdus : null,
                'resi' => $baris->all(),
                'jumlah_boleh' => $boleh->count(),
                'tujuan' => $tujuan->all(),
                'message' => $boleh->isEmpty()
                    ? 'Tidak ada resi yang bisa diserahkan: '.($baris->first()['alasan'] ?? '-')
                    : $boleh->count().' resi siap diserahkan.',
            ],
        ];
    }

    /**
     * Catat serah terima. Semua aturan dicek ULANG di sini pada baris terkunci — hasil
     * `periksa()` bisa sudah basi (resi lain dialihkan kurir kedua di sela-selanya).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $foto
     * @return array{status: int, data: array<string, mixed>}
     */
    public function simpan(User $user, array $data, array $foto = []): array
    {
        $mode = $data['mode'];
        $idResi = array_values(array_unique(array_map('intval', $data['pengiriman_ids'])));

        $statusTiba = StatusPengiriman::where('slug', Pengiriman::SLUG_TIBA)->first();

        if (! $statusTiba) {
            return $this->gagal(500, 'Status "Tiba di Tujuan" belum ada di data status pengiriman.');
        }

        // Berkas disimpan sebelum transaksi (berkas tidak ikut rollback); bila transaksi
        // gagal, berkasnya dihapus lagi di blok catch.
        $pathFoto = $this->simpanFoto($foto);

        try {
            $hasil = DB::transaction(function () use ($user, $data, $mode, $idResi, $statusTiba, $pathFoto) {
                $resi = Pengiriman::query()
                    ->with('status:id,nama,slug')
                    ->whereIn('id', $idResi)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($resi->count() !== count($idResi)) {
                    throw new SerahTerimaDitolak('Sebagian resi tidak ditemukan. Pindai ulang.');
                }

                $muatanPerResi = $this->muatanPerResi($idResi);

                foreach ($resi as $p) {
                    if ($alasan = $this->alasanTidakBolehDiserahkan($user, $p, $muatanPerResi[$p->id] ?? null)) {
                        throw new SerahTerimaDitolak($alasan);
                    }
                }

                if ($mode === self::MODE_TUJUAN && $resi->map(fn ($p) => $this->kunciTujuan($p))->unique()->count() > 1) {
                    throw new SerahTerimaDitolak('Resi yang dipilih punya tujuan berbeda. Serahkan per tujuan.');
                }

                $permintaanTerdampak = [];

                foreach ($resi as $p) {
                    $permintaanTerdampak[] = $this->serahkanSatu($user, $p, $data, $mode, $statusTiba, $pathFoto, $muatanPerResi[$p->id]?->id);
                }

                return array_values(array_unique(array_filter($permintaanTerdampak)));
            });
        } catch (SerahTerimaDitolak $e) {
            $this->hapusFoto($pathFoto);

            return $this->gagal(422, $e->getMessage());
        } catch (\Throwable $e) {
            $this->hapusFoto($pathFoto);

            throw $e;
        }

        // Resi yang dialihkan dilepas dari permintaannya; kalau sisa kiriman lembaga itu
        // ternyata sudah diterima semua, permintaannya selesai SEKARANG — tidak ada lagi
        // resi miliknya yang akan berubah status dan memicu penyelesaian dari observer.
        foreach ($hasil as $idPermintaan) {
            MushafRequest::find($idPermintaan)?->selesaikanBilaSemuaDiterima();
        }

        Log::info('Serah terima kurir dicatat', [
            'user_id' => $user->id,
            'mode' => $mode,
            'jumlah_resi' => count($idResi),
            'kode_kerdus' => $data['kode_kerdus'] ?? null,
        ]);

        $jumlah = count($idResi);

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'mode' => $mode,
                'jumlah_resi' => $jumlah,
                'message' => $mode === self::MODE_TUJUAN
                    ? "{$jumlah} resi tercatat Tiba di Tujuan, diserahkan kepada {$data['nama_penerima']}. Menunggu konfirmasi Diterima dari distribusi."
                    : "{$jumlah} resi dialihkan dan diserahkan kepada {$data['nama_penerima']} di tengah perjalanan.",
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $pathFoto
     * @return int|null id permintaan asal yang ditinggalkan (hanya `tengah_jalan`)
     */
    private function serahkanSatu(User $user, Pengiriman $p, array $data, string $mode, StatusPengiriman $statusTiba, array $pathFoto, ?int $muatanId): ?int
    {
        $asal = [
            'mushaf_request_id_asal' => $p->mushaf_request_id,
            'nama_lembaga_asal' => $p->nama_lembaga,
            'nama_penerima_asal' => $p->nama_penerima,
            'alamat_asal' => $p->alamat_tujuan,
        ];

        $atribut = [
            'received_by' => $data['nama_penerima'],
            'receiver_contact' => $data['no_hp_penerima'] ?? null,
            'delivery_proof' => $pathFoto ?: null,
            'delivery_notes' => $data['catatan'] ?? null,
        ];

        if ($mode === self::MODE_TENGAH_JALAN) {
            $atribut += [
                'nama_penerima' => $data['nama_penerima'],
                'no_hp_penerima' => $data['no_hp_penerima'],
                'alamat_tujuan' => $data['alamat_penerima'],
                'nama_lembaga' => null,
                'mushaf_request_id' => null,
            ];
        }

        $p->update($atribut);

        $keterangan = $mode === self::MODE_TUJUAN
            ? "Diserahkan kurir {$user->name} kepada {$data['nama_penerima']}"
                .($p->nama_lembaga ? " di {$p->nama_lembaga}" : '').'.'
            : "Diserahkan di tengah perjalanan oleh kurir {$user->name} kepada {$data['nama_penerima']}"
                .' (tujuan awal: '.($asal['nama_lembaga_asal'] ?: ($asal['nama_penerima_asal'] ?: '-')).').';

        if (! empty($data['catatan'])) {
            $keterangan .= ' Catatan: '.$data['catatan'];
        }

        $p->updateStatus($statusTiba->id, $keterangan, $user->id);

        // Riwayat tracking inilah yang tampil di halaman lacak publik (lokasi, foto, GPS).
        TrackingHistory::create([
            'pengiriman_id' => $p->id,
            'status_id' => $statusTiba->id,
            'user_id' => $user->id,
            'tanggal_update' => now(),
            'lokasi' => $mode === self::MODE_TENGAH_JALAN ? $data['alamat_penerima'] : ($p->alamat_tujuan ?: null),
            'keterangan' => $keterangan,
            'foto_dokumentasi' => $pathFoto,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ]);

        SerahTerimaKurir::create([
            'pengiriman_id' => $p->id,
            'muatan_id' => $muatanId,
            'user_id' => $user->id,
            'jenis' => $mode,
            'kode_kerdus' => $data['kode_kerdus'] ?? null,
            'nama_penerima' => $data['nama_penerima'],
            'no_hp_penerima' => $data['no_hp_penerima'] ?? null,
            'alamat_penerima' => $mode === self::MODE_TENGAH_JALAN ? $data['alamat_penerima'] : $p->alamat_tujuan,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'foto' => $pathFoto,
            'catatan' => $data['catatan'] ?? null,
        ] + ($mode === self::MODE_TENGAH_JALAN ? $asal : []));

        if ($mode !== self::MODE_TENGAH_JALAN || ! $asal['mushaf_request_id_asal']) {
            return null;
        }

        // Penunjuk lama `mushaf_requests.pengiriman_id` jangan menunjuk resi yang sudah
        // bukan miliknya — pindahkan ke kiriman lain permintaan itu (bila masih ada).
        $permintaan = MushafRequest::whereKey($asal['mushaf_request_id_asal'])->lockForUpdate()->first();

        if ($permintaan && (int) $permintaan->pengiriman_id === (int) $p->id) {
            $pengganti = $permintaan->pengirimans()->orderBy('id')->value('id');

            if ($pengganti) {
                $permintaan->forceFill(['pengiriman_id' => $pengganti])->saveQuietly();
            }
        }

        return (int) $asal['mushaf_request_id_asal'];
    }

    /**
     * Alasan resi ini TIDAK boleh diserahterimakan oleh pengguna ini, atau null bila boleh.
     */
    public function alasanTidakBolehDiserahkan(User $user, Pengiriman $p, ?Muatan $muatan): ?string
    {
        $slug = $p->status?->slug ?? StatusPengiriman::query()->whereKey($p->status_id)->value('slug');

        if ($slug === Pengiriman::SLUG_TIBA) {
            return "Resi {$p->no_resi} sudah diserahkan (Tiba di Tujuan).";
        }

        if ($slug === 'diterima') {
            return "Resi {$p->no_resi} sudah Diterima.";
        }

        if (! in_array($slug, Pengiriman::SLUG_BOLEH_DISERAHKAN, true)) {
            return "Resi {$p->no_resi} belum siap diserahkan (status: ".($p->status?->nama ?? $slug ?? '-').').';
        }

        if (! $muatan) {
            return "Resi {$p->no_resi} tidak ada di muatan mana pun — hanya barang yang dibawa kurir yang bisa diserahkan.";
        }

        if (($user->adalahKurir() || $user->adalahManager())
            && ! in_array((int) $muatan->kurir_id, $user->idBawahan(), true)) {
            return "Resi {$p->no_resi} dibawa muatan {$muatan->kode_muatan}, bukan muatan Anda.";
        }

        return null;
    }

    /**
     * @param  array<int, int>  $idResi
     * @return array<int, Muatan> muatan per id resi
     */
    private function muatanPerResi(array $idResi): array
    {
        return MuatanItem::query()
            ->with('muatan:id,kode_muatan,kurir_id')
            ->whereIn('pengiriman_id', $idResi)
            ->get()
            ->mapWithKeys(fn (MuatanItem $item) => [$item->pengiriman_id => $item->muatan])
            ->all();
    }

    /** Dua resi bertujuan sama bila lembaga + alamatnya sama. */
    private function kunciTujuan(Pengiriman $p): string
    {
        return md5(Str::lower(trim((string) $p->nama_lembaga)).'|'.Str::lower(trim((string) $p->alamat_tujuan)));
    }

    /**
     * @param  array<int, UploadedFile>  $foto
     * @return array<int, string>
     */
    private function simpanFoto(array $foto): array
    {
        $folder = 'dokumentasi/serah-terima/'.now()->format('Y-m-d');

        return collect($foto)
            ->filter(fn ($f) => $f instanceof UploadedFile && $f->isValid())
            ->map(fn (UploadedFile $f) => $f->storeAs(
                $folder,
                now()->format('His').'_'.Str::random(8).'.'.strtolower($f->getClientOriginalExtension() ?: 'jpg'),
                'public'
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $path
     */
    private function hapusFoto(array $path): void
    {
        if ($path !== []) {
            Storage::disk('public')->delete($path);
        }
    }

    private function bersihkanResi(string $mentah): string
    {
        $teks = trim(strip_tags($mentah));
        $terurai = json_decode($teks, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($terurai)) {
            $teks = (string) ($terurai['no_resi'] ?? $terurai['resi'] ?? $teks);
        }

        return preg_match('/EQ-\d{4}-\d{5}/i', $teks, $m) ? strtoupper($m[0]) : $teks;
    }

    /**
     * @return array{status: int, data: array<string, mixed>}
     */
    private function gagal(int $status, string $pesan): array
    {
        return ['status' => $status, 'data' => ['success' => false, 'message' => $pesan]];
    }
}
