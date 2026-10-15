<?php

namespace App\Services;

use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\PindaiKerdusLog;
use App\Models\User;
use App\Support\PerPage;
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
        ];
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

        // Hitungan BUTA: jumlah di data tidak dikirim ke layar sebelum petugas mengetik
        // hitungannya sendiri. Kalau angkanya terlihat duluan, "verifikasi" berubah jadi
        // menyalin angka — dan kerdus yang kurang isinya lolos.
        if (! isset($validated['jumlah_fisik'])) {
            return [
                'status' => 422,
                'data' => [
                    'success' => false,
                    'perlu_hitung' => true,
                    'kode' => $kode,
                    'jenis' => $box->jenisQuran->nama_jenis ?? null,
                    'message' => "Hitung isi fisik kerdus {$kode}, lalu ketik jumlah mushafnya.",
                ],
            ];
        }

        $jumlah = $box->jumlahEksIsi();
        $jumlahFisik = (int) $validated['jumlah_fisik'];
        $hasil = $jumlahFisik === $jumlah ? PindaiKerdusLog::HASIL_COCOK : PindaiKerdusLog::HASIL_SELISIH;

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
            ? trim("Kerdus {$kode} COCOK: {$jumlah} eks {$namaJenis}").'. Kerdus boleh dimuat.'
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
