<?php

namespace App\Services;

use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\PindaiKerdusLog;
use App\Models\User;
use App\Support\BoxUnits;
use App\Support\PerPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Pindai kerdus oleh sisi distribusi (manager / distribusi).
 *
 * Menjawab satu pertanyaan yang sebelumnya tidak terjawab di sistem: "kerdus mana yang
 * sudah berpindah tangan, siapa yang memindainya, jam berapa, dan berapa eks mushaf di
 * dalamnya (A5/A6/Iqra)".
 *
 * Sifatnya VERIFIKASI ISI:
 *   - Petugas menghitung isi fisik kerdus dan mengetik jumlahnya (hitungan buta — angka
 *     data tidak ditunjukkan dulu). Sama = 'cocok', beda = 'selisih'.
 *   - Kerdus hanya boleh dimuat ke kurir bila verifikasi terakhirnya cocok; kerdus selisih
 *     dibereskan gudang lalu dipindai ulang (tidak ada jalur override).
 *   - Status pengiriman TIDAK berubah karena pindai.
 *   - Kerdus boleh dipindai berkali-kali (diperiksa ulang saat serah terima). Semua
 *     jejaknya disimpan; ringkasannya menghitung tiap kerdus SEKALI.
 *
 * Dua jalur masuk yang keduanya sah:
 *   - `qr`     : kode dibaca kamera.
 *   - `manual` : kode diketik petugas — label rusak, kaca kamera kotor, atau baterai HP
 *                habis. Jalur ini WAJIB ada; tanpa itu operator yang gagal memindai tidak
 *                akan mencatat apa pun, dan justru data itulah yang dibutuhkan.
 *
 * Kerdus yang BELUM TERDAFTAR pun bisa dicatat, asalkan petugas mengisi jenis dan jumlah
 * eksnya. Kalau tidak, permintaannya ditolak dengan `perlu_isian` supaya layar bisa
 * menampilkan isian itu alih-alih pesan buntu.
 *
 * Logikanya ada di layanan ini, bukan di controller, karena dipakai DUA tempat: endpoint
 * pencatatannya (`PindaiKerdusController`) dan halaman kerja muatan yang menampilkan
 * panelnya (`MuatanController`). Satu implementasi, satu aturan angka.
 */
final class PindaiKerdusService
{
    /**
     * Prop untuk panel di halaman kerja (halaman Muatan).
     *
     * @return array<string, mixed>
     */
    public function props(Request $request): array
    {
        $hariIni = today()->toDateString();

        // Bawaan = hari ini (kerja harian). Dikirim KOSONG lewat filter berarti "seluruh
        // tanggal" — jadi `has()` dipakai, bukan `filled()`.
        $tanggal = $request->has('pindai_tanggal')
            ? $request->string('pindai_tanggal')->toString()
            : $hariIni;

        $cari = $request->string('pindai_cari')->toString();

        // Kunci permintaan diberi awalan `pindai_` supaya filter tabel PINDAI dan filter
        // tabel MUATAN di halaman yang sama tidak saling menimpa (`tanggal` di tabel muatan
        // berarti tanggal muatan, bukan tanggal pindai).
        $log = PindaiKerdusLog::query()
            ->with(['user:id,name', 'jenisQuran:id,kode_jenis,nama_jenis'])
            ->tanggal($tanggal)
            ->when($request->filled('pindai_jenis_quran_id'), fn ($q) => $q->where('jenis_quran_id', $request->integer('pindai_jenis_quran_id')))
            ->when($request->filled('pindai_user_id'), fn ($q) => $q->where('user_id', $request->integer('pindai_user_id')))
            ->when($cari !== '', fn ($q) => $q->where('kode_kerdus', 'like', '%'.$cari.'%'))
            ->orderByDesc('id')
            // Nama halaman sendiri: satu halaman memuat DUA tabel, dan keduanya tidak boleh
            // berebut nomor halaman yang sama.
            ->paginate(PerPage::resolve($request), ['*'], 'pindai_page')
            ->withQueryString();

        $log->through(fn (PindaiKerdusLog $baris) => [
            'id' => $baris->id,
            'kode_kerdus' => $baris->kode_kerdus,
            'jenis' => $baris->jenisQuran?->nama_jenis ?? 'Tanpa jenis',
            'jenis_kode' => $baris->jenisQuran?->kode_jenis,
            'jumlah_mushaf' => $baris->jumlah_mushaf,
            'jumlah_fisik' => $baris->jumlah_fisik,
            'hasil' => $baris->hasil,
            'metode' => $baris->metode,
            'asal' => $baris->asal,
            'ulang' => $baris->ulang,
            'catatan' => $baris->catatan,
            'oleh' => $baris->user?->name ?? 'Akun terhapus',
            'waktu' => $baris->created_at?->format('H:i:s'),
            'waktu_lengkap' => $baris->created_at?->format('d/m/Y H:i:s'),
            'tanggal' => $baris->created_at?->format('Y-m-d'),
        ]);

        return [
            'log' => $log,
            'filters' => [
                'tanggal' => $tanggal,
                'jenis_quran_id' => $request->string('pindai_jenis_quran_id')->toString(),
                'user_id' => $request->string('pindai_user_id')->toString(),
                'search' => $cari,
            ],
            'jenisList' => JenisQuran::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get(['id', 'kode_jenis', 'nama_jenis'])
                ->map(fn (JenisQuran $j) => [
                    'id' => $j->id,
                    'kode' => $j->kode_jenis,
                    'nama' => $j->nama_jenis,
                ]),
            // Hanya petugas yang pernah memindai: daftar ini untuk menyaring jejak, bukan
            // daftar pengguna sistem.
            'petugasList' => User::query()
                ->whereIn('id', PindaiKerdusLog::query()->whereNotNull('user_id')->distinct()->pluck('user_id'))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u) => ['id' => $u->id, 'nama' => $u->name]),
            'hariIni' => PindaiKerdusLog::ringkasan($hariIni),
            'total' => PindaiKerdusLog::ringkasan(null),
            'tanggalHariIni' => $hariIni,
            'siap' => $this->kerdusSiap($request),
        ];
    }

    /**
     * Kerdus PENUH / TERSEGEL yang belum dimuat ke kurir, untuk dipindai sisi distribusi.
     *
     * Sebelumnya sisi distribusi hanya tahu sebuah kerdus ada SETELAH memindainya — tidak ada
     * daftar "kerdus mana saja yang sudah selesai dikemas". Barisnya sengaja berbentuk sama
     * dengan Tracking Kerdus gudang (status, jenis, progress, isi, QR, petugas, segel, tanggal)
     * supaya gudang dan distribusi membaca kerdus yang sama dengan cara yang sama. Isi kerdus
     * sudah pasti dari data packing, jadi jumlahnya boleh tampil di sini.
     *
     * Kerdus yang seluruh isinya sudah masuk muatan disembunyikan: barangnya sudah berpindah
     * ke kurir, jadi tidak ada lagi yang bisa dipindai di gudang.
     *
     * @return array<string, mixed>
     */
    public function kerdusSiap(Request $request): array
    {
        $gudang = $request->string('siap_gudang')->toString();
        $cari = trim($request->string('siap_cari')->toString());

        $daftar = PackingBox::query()
            ->with(['jenisQuran', 'gudang:id,kode,nama', 'dailyPackingTask.user:id,name', 'pindaiKerdusTerakhir.user:id,name'])
            ->withCount('packingItems')
            ->whereIn('status', [PackingBox::STATUS_FULL, PackingBox::STATUS_SEALED])
            ->where(fn (Builder $q) => $q->doesntHave('packingItems')
                ->orWhereHas('packingItems', fn (Builder $isi) => $isi->whereNotExists(
                    fn ($muatan) => $muatan->selectRaw('1')
                        ->from('muatan_items')
                        ->whereColumn('muatan_items.pengiriman_id', 'packing_items.pengiriman_id')
                )))
            ->when($gudang !== '', fn (Builder $q) => $gudang === 'belum'
                ? $q->whereNull('gudang_id')
                : $q->where('gudang_id', (int) $gudang))
            ->when($cari !== '', fn (Builder $q) => $q->where('kode_kerdus', 'like', '%'.$cari.'%'))
            // Yang paling lama menunggu didahulukan: kerdus tersegel kemarin lebih mendesak
            // daripada yang baru disegel lima menit lalu.
            ->orderByRaw('sealed_at IS NULL, sealed_at ASC')
            ->orderBy('id')
            ->paginate(10, ['*'], 'siap_page')
            ->withQueryString();

        $daftar->through(function (PackingBox $box) {
            // Rumus satuan sama persis dengan BoxTrackingController::index.
            $pcsPerDoz = (int) ($box->kapasitas ?: ($box->jenisQuran?->getDefaultCapacity() ?? 0));

            return [
                'id' => $box->id,
                'kode_kerdus' => $box->kode_kerdus,
                'status' => $box->status,
                'status_info' => $box->getStatusInfo(),
                'jenis_quran' => $box->jenisQuran->nama_jenis ?? 'Belum ditentukan',
                'gudang' => $box->gudang?->nama,
                'kapasitas' => $box->kapasitas,
                'terisi' => $box->jumlah_terisi,
                'progress_percentage' => $box->progress_percentage,
                'item_count' => $box->packing_items_count,
                'satuan' => BoxUnits::breakdown((int) $box->jumlah_terisi, $pcsPerDoz),
                'satuan_kapasitas' => BoxUnits::breakdown((int) $box->kapasitas, $pcsPerDoz),
                'satuan_keterangan' => BoxUnits::explanation($pcsPerDoz, $box->jenisQuran->kode_jenis ?? null),
                'qr_code_base64' => $this->qrKerdus($box),
                'user_name' => $box->dailyPackingTask?->user?->name ?? 'N/A',
                'seal_code' => $box->seal_code,
                'sealed_at' => $box->sealed_at?->format('d/m/Y H:i'),
                'created_at' => $box->created_at?->format('d/m/Y H:i'),
                // Pindai terakhir sisi distribusi (menggantikan tabel riwayat terpisah yang
                // dulu menduplikasi kode & jenis kerdus yang sama).
                'dipindai_pada' => $box->pindaiKerdusTerakhir?->created_at?->format('d/m/Y H:i'),
                'dipindai_metode' => $box->pindaiKerdusTerakhir?->metode,
                'dipindai_oleh' => $box->pindaiKerdusTerakhir
                    ? ($box->pindaiKerdusTerakhir->user?->name ?? 'Akun terhapus')
                    : null,
            ];
        });

        return [
            'daftar' => $daftar,
            'filters' => ['gudang' => $gudang, 'cari' => $cari],
            'gudangList' => Gudang::query()->orderBy('id')->get(['id', 'kode', 'nama']),
        ];
    }

    /**
     * Isi satu kerdus untuk sisi distribusi: tiap mushaf (1 resi = 1 mushaf) beserta
     * wakif, donatur, status, dan siapa yang mengemasnya — plus seluruh riwayat pindai
     * kerdus itu, karena riwayat tidak lagi punya tabel sendiri di panel.
     *
     * @return array<string, mixed>
     */
    public function detailKerdus(PackingBox $box): array
    {
        $box->load([
            'jenisQuran',
            'gudang:id,kode,nama',
            'dailyPackingTask.user:id,name',
            'packingItems' => fn ($q) => $q->orderBy('urutan_dalam_box')->orderBy('id'),
            'packingItems.pengiriman.donatur:id,nama_donatur',
            'packingItems.pengiriman.wakafItem',
            'packingItems.pengiriman.jenisQuran:id,kode_jenis,nama_jenis',
            'packingItems.pengiriman.status:id,nama,slug',
            'packingItems.packedByUser:id,name',
        ]);

        $riwayat = PindaiKerdusLog::query()
            ->with('user:id,name')
            ->where('packing_box_id', $box->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return [
            'kerdus' => [
                'id' => $box->id,
                'kode_kerdus' => $box->kode_kerdus,
                'status_info' => $box->getStatusInfo(),
                'jenis_quran' => $box->jenisQuran->nama_jenis ?? 'Belum ditentukan',
                'gudang' => $box->gudang?->nama,
                'terisi' => $box->jumlah_terisi,
                'kapasitas' => $box->kapasitas,
                'jumlah_mushaf' => $box->jumlahEksIsi(),
                'user_name' => $box->dailyPackingTask?->user?->name ?? 'N/A',
                'seal_code' => $box->seal_code,
                'sealed_at' => $box->sealed_at?->format('d/m/Y H:i'),
            ],
            'mushaf' => $box->packingItems->map(function ($item) {
                $resi = $item->pengiriman;

                return [
                    'urutan' => $item->urutan_dalam_box,
                    'no_resi' => $resi?->no_resi,
                    'jenis' => $resi?->jenisQuran?->nama_jenis,
                    'jenis_kode' => $resi?->jenisQuran?->kode_jenis,
                    'jumlah' => (int) ($item->quantity ?: 1),
                    'wakif' => $resi?->wakafItem?->wakif_name ?? $resi?->donatur?->nama_donatur,
                    'donatur' => $resi?->donatur?->nama_donatur,
                    'status' => $resi?->status?->nama,
                    'dikemas_oleh' => $item->packedByUser?->name,
                    'dikemas_pada' => $item->packed_at?->format('d/m/Y H:i'),
                    'tracking_url' => $resi?->tracking_url,
                ];
            })->values(),
            'riwayat_pindai' => $riwayat->map(fn (PindaiKerdusLog $log) => [
                'waktu' => $log->created_at?->format('d/m/Y H:i'),
                'metode' => $log->metode,
                'oleh' => $log->user?->name ?? 'Akun terhapus',
                'jumlah_mushaf' => $log->jumlah_mushaf,
                'catatan' => $log->catatan,
            ])->values(),
        ];
    }

    /** QR kerdus untuk tampilan; gagal membuat QR tidak boleh menjatuhkan seluruh halaman. */
    private function qrKerdus(PackingBox $box): ?string
    {
        try {
            return $box->getBoxQRBase64();
        } catch (\Throwable $e) {
            Log::error('Gagal membuat QR kerdus', ['kode_kerdus' => $box->kode_kerdus, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Catat satu pindai kerdus.
     *
     * Jenis dan jumlah eks SELALU diambil dari data kerdus bila kerdusnya terdaftar — angka
     * kiriman klien diabaikan. Kalau tidak, siapa pun bisa menuliskan "500 eks A5" untuk
     * satu kerdus A6 berisi 40 eks, dan laporan tracking-nya jadi karangan.
     *
     * @param  array<string, mixed>  $validated
     * @return array{status: int, data: array<string, mixed>}
     */
    public function catat(User $user, array $validated): array
    {
        $metode = $validated['metode'] ?? PindaiKerdusLog::METODE_QR;
        $kode = PackingBox::kodeDariPindai($validated['kode']);

        $box = PackingBox::query()
            ->with(['packingItems:id,packing_box_id,quantity', 'jenisQuran:id,kode_jenis,nama_jenis'])
            ->where('kode_kerdus', $kode)
            ->first();

        if (! $box) {
            return $this->catatKerdusLuarSistem($user, $kode, $metode, $validated);
        }

        // Isi kerdus sudah pasti dari data packing (tiap mushaf dipindai satu per satu saat
        // masuk kerdus), jadi petugas distribusi TIDAK diminta mengetik ulang jumlahnya:
        // pindai = tercatat siapa, jam berapa, dan berapa eks per jenis. Jumlah fisik hanya
        // dibandingkan bila klien sengaja mengirimnya (jalur lama tetap dihormati).
        $jumlah = $box->jumlahEksIsi();
        $jumlahFisik = isset($validated['jumlah_fisik']) ? (int) $validated['jumlah_fisik'] : null;
        $hasil = $jumlahFisik === null || $jumlahFisik === $jumlah
            ? PindaiKerdusLog::HASIL_COCOK
            : PindaiKerdusLog::HASIL_SELISIH;

        $sebelumnya = PindaiKerdusLog::query()
            ->with('user:id,name')
            ->where('kode_kerdus', $kode)
            ->orderByDesc('id')
            ->first();

        $ulang = $sebelumnya !== null;

        $baris = PindaiKerdusLog::create([
            'user_id' => $user->id,
            'packing_box_id' => $box->id,
            'kode_kerdus' => $kode,
            // Kerdus kosong belum berjenis; bila petugas menyebutkannya, itu dipakai.
            'jenis_quran_id' => $box->jenis_quran_id ?? ($validated['jenis_quran_id'] ?? null),
            'jumlah_mushaf' => $jumlah,
            'jumlah_fisik' => $jumlahFisik,
            'hasil' => $hasil,
            'metode' => $metode,
            'asal' => PindaiKerdusLog::ASAL_KERDUS,
            'ulang' => $ulang,
            'catatan' => $validated['catatan'] ?? null,
        ]);

        Log::info('Pindai kerdus distribusi dicatat', [
            'user_id' => $user->id,
            'kode_kerdus' => $kode,
            'metode' => $metode,
            'jumlah_mushaf' => $jumlah,
            'jumlah_fisik' => $jumlahFisik,
            'hasil' => $hasil,
            'ulang' => $ulang,
        ]);

        $namaJenis = $box->jenisQuran->nama_jenis ?? '';
        $pesan = $hasil === PindaiKerdusLog::HASIL_COCOK
            ? trim("Kerdus {$kode} tercatat: {$jumlah} eks {$namaJenis}").'. Kerdus boleh dimuat.'
            : "Kerdus {$kode} SELISIH: dihitung {$jumlahFisik} eks, data {$jumlah} eks. "
                .'Kerdus diblokir dari muatan — kembalikan ke gudang untuk dibereskan, lalu pindai ulang.';

        if ($ulang) {
            $oleh = $sebelumnya->user?->name ?? 'akun yang sudah dihapus';
            $pesan .= " Kerdus ini sudah pernah dipindai oleh {$oleh} pukul "
                .$sebelumnya->created_at->format('d/m/Y H:i')
                .' — pindai ulang tetap tersimpan; hasil yang berlaku adalah pindai terakhir ini.';
        }

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'ulang' => $ulang,
                'kode' => $kode,
                'hasil' => $hasil,
                'message' => $pesan,
                'data' => [
                    'kode_kerdus' => $kode,
                    'jenis' => $box->jenisQuran->nama_jenis ?? null,
                    'jumlah_mushaf' => $jumlah,
                    'jumlah_fisik' => $jumlahFisik,
                    'hasil' => $hasil,
                    'jumlah_resi' => $box->packingItems->count(),
                    'status' => $box->status,
                    'tersegel' => $box->is_sealed,
                    'kapasitas' => $box->kapasitas,
                    'metode' => $metode,
                    'oleh' => $user->name,
                    'jam' => $baris->created_at->format('d/m/Y H:i:s'),
                    'pindai_sebelumnya' => $ulang ? [
                        'oleh' => $sebelumnya->user?->name ?? 'Akun terhapus',
                        'waktu' => $sebelumnya->created_at->format('d/m/Y H:i'),
                    ] : null,
                ],
                'hari_ini' => PindaiKerdusLog::ringkasan(today()->toDateString()),
                'total' => PindaiKerdusLog::ringkasan(null),
            ],
        ];
    }

    /**
     * Catat kerdus yang belum terdaftar di sistem (label rusak / belum tercatat).
     *
     * Tanpa jalur ini, kerdus seperti itu hanya jadi catatan lisan. Justru kerdus inilah yang
     * paling perlu jejak: kalau bukan karena dicatat manual, tidak ada satu pun bukti kerdus
     * itu pernah lewat.
     *
     * @param  array<string, mixed>  $validated
     * @return array{status: int, data: array<string, mixed>}
     */
    private function catatKerdusLuarSistem(User $user, string $kode, string $metode, array $validated): array
    {
        $lengkap = isset($validated['jenis_quran_id'], $validated['jumlah_mushaf']);

        if (! $lengkap) {
            return [
                'status' => 422,
                'data' => [
                    'success' => false,
                    'perlu_isian' => true,
                    'kode' => $kode,
                    'message' => "Kerdus {$kode} tidak terdaftar di sistem. Isi jenis dan jumlah eksnya supaya tetap tercatat.",
                ],
            ];
        }

        $jenis = JenisQuran::find($validated['jenis_quran_id']);

        $baris = PindaiKerdusLog::create([
            'user_id' => $user->id,
            'packing_box_id' => null,
            'kode_kerdus' => $kode,
            'jenis_quran_id' => $jenis?->id,
            'jumlah_mushaf' => (int) $validated['jumlah_mushaf'],
            'metode' => $metode,
            'asal' => PindaiKerdusLog::ASAL_MANDIRI,
            'ulang' => PindaiKerdusLog::where('kode_kerdus', $kode)->exists(),
            'catatan' => $validated['catatan'] ?? null,
        ]);

        Log::info('Pindai kerdus luar sistem dicatat manual', [
            'user_id' => $user->id,
            'kode_kerdus' => $kode,
            'jenis_quran_id' => $jenis?->id,
            'jumlah_mushaf' => $validated['jumlah_mushaf'],
        ]);

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'ulang' => (bool) $baris->ulang,
                'kode' => $kode,
                'message' => "Kerdus {$kode} belum terdaftar di sistem; dicatat manual sebagai "
                    .($jenis->nama_jenis ?? 'tanpa jenis').' '.$validated['jumlah_mushaf'].' eks.',
                'data' => [
                    'kode_kerdus' => $kode,
                    'jenis' => $jenis->nama_jenis ?? null,
                    'jumlah_mushaf' => (int) $validated['jumlah_mushaf'],
                    'jumlah_resi' => null,
                    'status' => null,
                    'tersegel' => false,
                    'kapasitas' => null,
                    'metode' => $metode,
                    'oleh' => $user->name,
                    'jam' => $baris->created_at->format('d/m/Y H:i:s'),
                    'pindai_sebelumnya' => null,
                ],
                'hari_ini' => PindaiKerdusLog::ringkasan(today()->toDateString()),
                'total' => PindaiKerdusLog::ringkasan(null),
            ],
        ];
    }
}
