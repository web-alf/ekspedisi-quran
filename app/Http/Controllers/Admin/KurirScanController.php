<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Muatan;
use App\Models\MuatanItem;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pindai barang untuk kurir — per-pcs (no_resi) DAN per-box (kode_kerdus).
 *
 * Latar belakang bentuk data: satu baris Pengiriman = satu mushaf, dan QR-nya
 * berisi no_resi. Jadi "pcs" di sini memang satu barcode = satu resi. Iqra juga
 * baris Pengiriman tersendiri (1 kerdus Iqra berisi sampai 160 eks), sehingga
 * memindai per-pcs tetap masuk akal untuknya.
 *
 * Kurir memindai barang yang dibawanya untuk MASUK ke muatan, dan boleh
 * memindahkan status PERJALANANNYA. Menyelesaikan distribusi (status "Diterima")
 * bukan wewenangnya — penegakannya ada di MuatanController.
 */
class KurirScanController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Muatan hari ini milik kurir ini, untuk dipilih sebagai tujuan pindai.
        $muatanHariIni = Muatan::query()
            ->forKurir($user->id)
            ->whereDate('tanggal_muatan', now()->toDateString())
            ->with('kurir:id,name')
            ->orderByDesc('id')
            ->get(['id', 'kode_muatan', 'nama_muatan', 'kurir_id', 'tanggal_muatan', 'total_resi', 'total_mushaf'])
            ->map(fn (Muatan $m) => [
                'id' => $m->id,
                'kode_muatan' => $m->kode_muatan,
                'nama_muatan' => $m->nama_muatan,
                'total_resi' => $m->total_resi,
                'total_mushaf' => $m->total_mushaf,
            ]);

        // Muatan lain yang masih berjalan (bukan hari ini), supaya kurir bisa
        // melanjutkan muatan yang belum selesai.
        $muatanLain = Muatan::query()
            ->forKurir($user->id)
            ->whereDate('tanggal_muatan', '!=', now()->toDateString())
            ->orderByDesc('tanggal_muatan')
            ->limit(10)
            ->get(['id', 'kode_muatan', 'nama_muatan', 'tanggal_muatan', 'total_resi', 'total_mushaf'])
            ->map(fn (Muatan $m) => [
                'id' => $m->id,
                'kode_muatan' => $m->kode_muatan,
                'nama_muatan' => $m->nama_muatan,
                'tanggal_muatan' => $m->tanggal_muatan?->format('Y-m-d'),
                'total_resi' => $m->total_resi,
                'total_mushaf' => $m->total_mushaf,
            ]);

        return Inertia::render('Admin/Kurir/Scan', [
            'muatanHariIni' => $muatanHariIni,
            'muatanLain' => $muatanLain,
        ]);
    }

    /**
     * Pindai per-pcs: QR berisi no_resi.
     */
    public function scanPcs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode' => ['required', 'string'],
            'muatan_id' => ['nullable', 'integer', 'exists:muatan,id'],
        ]);

        $noResi = $this->bersihkanResi($validated['kode']);

        $pengiriman = Pengiriman::with(['status:id,nama,slug,warna', 'jenisQuran:id,nama_jenis'])
            ->where('no_resi', $noResi)
            ->first();

        if (! $pengiriman) {
            return response()->json([
                'success' => false,
                'jenis' => 'pcs',
                'message' => "Resi {$noResi} tidak ditemukan.",
            ], 404);
        }

        $muatan = $this->muatanTujuan($request, $validated['muatan_id'] ?? null);

        if (! $muatan) {
            // Tanpa muatan tujuan, pindai tetap SAH sebagai pemeriksaan: kurir
            // perlu tahu barang ini apa dan statusnya. Yang tidak terjadi hanyalah
            // pencatatan ke muatan.
            return response()->json([
                'success' => true,
                'jenis' => 'pcs',
                'tercatat' => false,
                'message' => "Resi {$noResi} sah. Belum ada muatan aktif, jadi tidak dicatat.",
                'data' => $this->ringkasPengiriman($pengiriman),
            ]);
        }

        $sudahAda = MuatanItem::where('pengiriman_id', $pengiriman->id)->first();

        if ($sudahAda) {
            $sama = (int) $sudahAda->muatan_id === (int) $muatan->id;

            return response()->json([
                'success' => false,
                'jenis' => 'pcs',
                'tercatat' => $sama,
                'message' => $sama
                    ? "Resi {$noResi} sudah tercatat di muatan ini."
                    : "Resi {$noResi} sudah dimuat di muatan ".(Muatan::find($sudahAda->muatan_id)->kode_muatan ?? '-').'.',
                'data' => $this->ringkasPengiriman($pengiriman),
            ], 409);
        }

        MuatanItem::create([
            'muatan_id' => $muatan->id,
            'pengiriman_id' => $pengiriman->id,
            'urutan' => (int) MuatanItem::where('muatan_id', $muatan->id)->max('urutan') + 1,
            'dimuat_at' => now(),
            'dimuat_by' => $request->user()->id,
        ]);

        $muatan->syncTotals();

        return response()->json([
            'success' => true,
            'jenis' => 'pcs',
            'tercatat' => true,
            'message' => "Resi {$noResi} dimuat ke muatan {$muatan->kode_muatan}.",
            'data' => $this->ringkasPengiriman($pengiriman),
            'total_resi' => $muatan->fresh()->total_resi,
            'total_mushaf' => $muatan->fresh()->total_mushaf,
        ]);
    }

    /**
     * Pindai per-box: QR berisi kode_kerdus.
     *
     * Memindai satu kerdus berarti memuat SELURUH resi di dalamnya sekaligus —
     * itulah gunanya memindai per-box: satu kerdus A5 bisa berisi 20 mushaf.
     */
    public function scanBox(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode' => ['required', 'string'],
            'muatan_id' => ['nullable', 'integer', 'exists:muatan,id'],
        ]);

        $kodeKerdus = $this->bersihkanKerdus($validated['kode']);

        $box = PackingBox::with(['packingItems.pengiriman.status', 'jenisQuran:id,nama_jenis'])
            ->where('kode_kerdus', $kodeKerdus)
            ->first();

        if (! $box) {
            return response()->json([
                'success' => false,
                'jenis' => 'box',
                'message' => "Kerdus {$kodeKerdus} tidak ditemukan.",
            ], 404);
        }

        $resiDalamBox = $box->packingItems
            ->map(fn ($item) => $item->pengiriman)
            ->filter()
            ->values();

        if ($resiDalamBox->isEmpty()) {
            return response()->json([
                'success' => false,
                'jenis' => 'box',
                'message' => "Kerdus {$kodeKerdus} belum berisi resi.",
            ], 422);
        }

        $muatan = $this->muatanTujuan($request, $validated['muatan_id'] ?? null);

        if (! $muatan) {
            return response()->json([
                'success' => true,
                'jenis' => 'box',
                'tercatat' => false,
                'message' => "Kerdus {$kodeKerdus} sah berisi {$resiDalamBox->count()} resi. Belum ada muatan aktif, jadi tidak dicatat.",
                'data' => [
                    'kode_kerdus' => $box->kode_kerdus,
                    'jenis' => $box->jenisQuran?->nama_jenis,
                    'jumlah_resi' => $resiDalamBox->count(),
                    'kapasitas' => $box->kapasitas,
                    'jumlah_terisi' => $box->jumlah_terisi,
                ],
            ]);
        }

        $dimuat = 0;
        $dilewati = [];

        foreach ($resiDalamBox as $pengiriman) {
            $sudahAda = MuatanItem::where('pengiriman_id', $pengiriman->id)->first();

            if ($sudahAda) {
                $dilewati[] = $pengiriman->no_resi;

                continue;
            }

            MuatanItem::create([
                'muatan_id' => $muatan->id,
                'pengiriman_id' => $pengiriman->id,
                'urutan' => (int) MuatanItem::where('muatan_id', $muatan->id)->max('urutan') + 1,
                'dimuat_at' => now(),
                'dimuat_by' => $request->user()->id,
            ]);

            $dimuat++;
        }

        $muatan->syncTotals();

        return response()->json([
            'success' => true,
            'jenis' => 'box',
            'tercatat' => true,
            'message' => $dimuat > 0
                ? "Kerdus {$kodeKerdus}: {$dimuat} resi dimuat ke muatan {$muatan->kode_muatan}."
                    .($dilewati !== [] ? ' '.count($dilewati).' resi sudah ada di muatan lain.' : '')
                : "Semua resi kerdus {$kodeKerdus} sudah ada di muatan lain.",
            'data' => [
                'kode_kerdus' => $box->kode_kerdus,
                'jenis' => $box->jenisQuran?->nama_jenis,
                'jumlah_resi' => $resiDalamBox->count(),
                'dimuat' => $dimuat,
                'dilewati' => $dilewati,
                'kapasitas' => $box->kapasitas,
                'jumlah_terisi' => $box->jumlah_terisi,
            ],
            'total_resi' => $muatan->fresh()->total_resi,
            'total_mushaf' => $muatan->fresh()->total_mushaf,
        ]);
    }

    /**
     * Muatan tujuan pindai.
     *
     * Wajib milik kurir yang memindai — tanpa pemeriksaan ini, satu kurir bisa
     * menaruh barang ke muatan kurir lain, dan barang itu tidak akan pernah
     * tercatat sebagai dibawanya.
     */
    private function muatanTujuan(Request $request, ?int $muatanId): ?Muatan
    {
        $user = $request->user();

        if ($muatanId !== null) {
            $muatan = Muatan::find($muatanId);

            if (! $muatan) {
                return null;
            }

            // Role distribusi/super-admin boleh memuat ke muatan mana pun saat
            // menyiapkan; kurir hanya ke muatannya sendiri.
            if ($user->hasRole(RoleEnum::COURIER->value) && (int) $muatan->kurir_id !== (int) $user->id) {
                abort(403, 'Anda hanya boleh memuat barang ke muatan Anda sendiri.');
            }

            return $muatan;
        }

        // Tanpa muatan yang disebut: pakai muatan hari ini milik kurir ini.
        return Muatan::query()
            ->forKurir($user->id)
            ->whereDate('tanggal_muatan', now()->toDateString())
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkasPengiriman(Pengiriman $pengiriman): array
    {
        return [
            'id' => $pengiriman->id,
            'no_resi' => $pengiriman->no_resi,
            'nama_penerima' => $pengiriman->nama_penerima,
            'nama_lembaga' => $pengiriman->nama_lembaga,
            'alamat_tujuan' => $pengiriman->alamat_tujuan,
            'no_hp_penerima' => $pengiriman->no_hp_penerima,
            'jumlah_quran' => $pengiriman->jumlah_quran,
            'jenis' => $pengiriman->jenisQuran?->nama_jenis,
            'status' => $pengiriman->status?->nama,
            'status_slug' => $pengiriman->status?->slug,
        ];
    }

    /**
     * QR berisi no_resi; pemindai kadang mengembalikan JSON atau teks berlebih.
     */
    private function bersihkanResi(string $mentah): string
    {
        $teks = trim(strip_tags($mentah));

        $terurai = json_decode($teks, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($terurai)) {
            $teks = (string) ($terurai['no_resi'] ?? $terurai['resi'] ?? $teks);
        }

        if (preg_match('/EQ-\d{4}-\d{5}/', $teks, $m)) {
            return $m[0];
        }

        return $teks;
    }

    /**
     * QR kerdus: isinya bisa JSON {"kode_kerdus": "..."} atau kode mentah.
     */
    private function bersihkanKerdus(string $mentah): string
    {
        $teks = trim(strip_tags($mentah));

        $terurai = json_decode($teks, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($terurai)) {
            $teks = (string) ($terurai['kode_kerdus'] ?? $terurai['box_code'] ?? $teks);
        }

        if (preg_match('/KB-[A-Z0-9\-]+/i', $teks, $m)) {
            return strtoupper($m[0]);
        }

        return $teks;
    }
}
