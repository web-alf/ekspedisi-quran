<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\Pengiriman;
use App\Models\TransferMushaf;
use App\Models\User;
use App\Services\StokMushafService;
use App\Support\CakupanGudang;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Perpindahan mushaf ber-resi antar gudang (LOKASI MUSHAF, 2026-10-15).
 *
 * Menjawab pertanyaan "mushaf ini ada di gudang mana": lokasinya tersimpan di
 * `pengiriman.gudang_id`, dan sesudah ditentukan sekali, HANYA dokumen di sini yang boleh
 * mengubahnya. Karena itu dokumennya memakai pola yang sama dengan transfer stok:
 *
 *   - dibuat gudang ASAL selagi statusnya `diproses` — lokasi mushaf BELUM berubah;
 *   - DITERIMA gudang TUJUAN — di titik itulah `pengiriman.gudang_id` berpindah;
 *   - gudang asal boleh membatalkan selama masih `diproses`.
 *
 * Satu mushaf hanya boleh ada di satu dokumen berjalan (dijaga di sisi server DAN oleh
 * kunci unik `transfer_mushaf_items.pengiriman_id`), supaya tidak ada mushaf yang "diterima"
 * dua gudang sekaligus.
 */
class TransferMushafController extends Controller
{
    public function __construct(private StokMushafService $stokMushaf) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $tercakupGudang = $user->hasRole(RoleEnum::SPV_GUDANG->value);

        // Dua dokumen dalam satu halaman (keputusan pemilik 26/10/2026): mushaf BER-RESI
        // (tab pertama) dan STOK TANPA MUWAKIF (tab kedua). Dua-duanya "perpindahan antar
        // gudang" bagi pengguna, dan halaman stok dulu menyimpan form + daftar dokumen
        // stok sehingga dokumen yang menunggu diterima tidak pernah terlihat di sini.
        $bolehStok = $user->can(PermissionEnum::STOK_READ->value);

        return Inertia::render('Admin/TransferMushaf/Index', [
            'gudangList' => Gudang::orderBy('id')->get(['id', 'kode', 'nama']),

            'transferList' => TransferMushaf::with(['gudangAsal:id,nama', 'gudangTujuan:id,nama', 'pembuat:id,name', 'penerima:id,name'])
                ->when($tercakupGudang, fn ($q) => $q->where(fn ($q2) => $q2
                    ->where('gudang_asal_id', $user->gudang_id)
                    ->orWhere('gudang_tujuan_id', $user->gudang_id)))
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn (TransferMushaf $t) => [
                    'id' => $t->id,
                    'kode' => $t->kode,
                    'asal_id' => $t->gudang_asal_id,
                    'asal' => $t->gudangAsal?->nama,
                    'tujuan_id' => $t->gudang_tujuan_id,
                    'tujuan' => $t->gudangTujuan?->nama,
                    'status' => $t->status,
                    'jumlah_resi' => $t->jumlah_resi,
                    'jumlah_eks' => $t->jumlah_eks,
                    'catatan' => $t->catatan,
                    'dibuat_oleh' => $t->pembuat?->name,
                    'diterima_oleh' => $t->penerima?->name,
                    'dibuat_pada' => $t->created_at?->format('d/m/Y H:i'),
                    'diterima_pada' => $t->received_at?->format('d/m/Y H:i'),
                ]),

            'bolehTransfer' => $user->can(PermissionEnum::MUSHAF_TRANSFER->value),
            'gudangSaya' => $user->gudang_id,
            'tercakupGudang' => $tercakupGudang,
            'peranSuperAdmin' => $user->hasRole(RoleEnum::SUPER_ADMIN->value),
            // PJ murni (manager-repacking tanpa spv-gudang) boleh menerima/membatalkan
            // dokumen transfer stok gudang mana pun — aturan yang sama dengan server.
            'peranPjGudang' => $user->hasRole(RoleEnum::MANAGER_REPACKING->value) && ! $tercakupGudang,

            // --- Tab "Stok tanpa muwakif" ---
            'penggunaId' => $user->id,
            'bolehTransferStok' => $user->can(PermissionEnum::STOK_TRANSFER->value),
            'jenisList' => $bolehStok
                ? JenisQuran::active()->orderBy('id')->get(['id', 'kode_jenis', 'nama_jenis', 'default_capacity'])
                : [],
            'stokList' => $bolehStok ? $this->stokMushaf->ringkasanStok($user) : [],
            'transferStokList' => $bolehStok ? $this->stokMushaf->daftarTransfer($user) : [],
        ]);
    }

    /**
     * Daftar mushaf yang bisa dipindahkan — dicari dari halaman, bukan dikirim sekaligus.
     *
     * Sengaja dibatasi 25 baris: kolam resi produksi 26 ribu baris, dan mengirim semuanya
     * ke layar hanya membuat halaman berat tanpa menambah kemampuan.
     */
    public function cari(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:40'],
        ]);

        $kata = $validated['q'];
        $user = $request->user();

        $daftar = Pengiriman::with(['jenisQuran:id,kode_jenis,nama_jenis', 'gudang:id,nama', 'status:id,slug,nama'])
            ->where(fn ($q) => $q->where('no_resi', 'like', "%{$kata}%")
                ->orWhere('nama_lembaga', 'like', "%{$kata}%")
                ->orWhere('nama_penerima', 'like', "%{$kata}%"))
            ->whereHas('status', fn ($q) => $q->whereNotIn('slug', ['diterima', 'batal']))
            ->orderBy('no_resi')
            ->limit(25)
            ->get()
            ->map(function (Pengiriman $p) use ($user) {
                $alasan = $this->alasanTidakBisaDipindah($p, $user);

                return [
                    'id' => $p->id,
                    'no_resi' => $p->no_resi,
                    'jenis' => $p->jenisQuran?->nama_jenis,
                    'eks' => (int) $p->jumlah_quran,
                    'status' => $p->status?->nama,
                    'gudang' => $p->gudang?->nama,
                    'gudang_id' => $p->gudang_id,
                    'boleh' => $alasan === null,
                    'alasan' => $alasan,
                ];
            });

        return response()->json(['success' => true, 'data' => $daftar]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'gudang_tujuan_id' => ['required', 'integer', 'exists:gudang,id'],
            'pengiriman_ids' => ['required', 'array', 'min:1', 'max:200'],
            'pengiriman_ids.*' => ['integer', 'exists:pengiriman,id'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $asalId = $user->gudang_id;

        // SPV tanpa penempatan tidak boleh memilih gudang asal sendiri — itu sama saja
        // dengan memindahkan mushaf dari gudang mana pun.
        if (CakupanGudang::belumDitempatkan($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Akun SPV Anda belum ditempatkan di gudang. Minta Manager Gudang menempatkan akun Anda.',
            ], 403);
        }

        // Gudang asal = gudang pelaku. Akun tanpa gudang (mis. super-admin) harus memilih
        // sendiri, kalau tidak dokumennya tidak punya titik awal yang bisa diterima tujuan.
        if (! $asalId) {
            $validated += $request->validate(['gudang_asal_id' => ['required', 'integer', 'exists:gudang,id']]);
            $asalId = (int) $validated['gudang_asal_id'];
        }

        if ((int) $asalId === (int) $validated['gudang_tujuan_id']) {
            return response()->json(['success' => false, 'message' => 'Gudang asal dan tujuan tidak boleh sama.'], 422);
        }

        try {
            $transfer = DB::transaction(function () use ($validated, $user, $asalId) {
                $resi = Pengiriman::with(['status:id,slug,nama', 'packingItem.packingBox'])
                    ->whereIn('id', $validated['pengiriman_ids'])
                    ->lockForUpdate()
                    ->get();

                if ($resi->count() !== count(array_unique($validated['pengiriman_ids']))) {
                    throw new \Exception('Ada resi yang tidak ditemukan.');
                }

                foreach ($resi as $p) {
                    $alasan = $this->alasanTidakBisaDipindah($p, $user);

                    if ($alasan !== null) {
                        throw new \Exception($alasan);
                    }
                }

                $transfer = TransferMushaf::create([
                    'kode' => TransferMushaf::generateKode(),
                    'gudang_asal_id' => $asalId,
                    'gudang_tujuan_id' => (int) $validated['gudang_tujuan_id'],
                    'status' => TransferMushaf::STATUS_DIPROSES,
                    'jumlah_resi' => $resi->count(),
                    'jumlah_eks' => (int) $resi->sum('jumlah_quran'),
                    'catatan' => $validated['catatan'] ?? null,
                    'created_by' => $user->id,
                ]);

                foreach ($resi as $p) {
                    $transfer->items()->create(['pengiriman_id' => $p->id]);
                }

                Log::info('Dokumen perpindahan mushaf dibuat', [
                    'kode' => $transfer->kode,
                    'dari' => $transfer->gudang_asal_id,
                    'ke' => $transfer->gudang_tujuan_id,
                    'jumlah_resi' => $resi->count(),
                ]);

                return $transfer;
            });
        } catch (QueryException $e) {
            // Galat basis data (mis. dua dokumen dibuat bersamaan) tidak ditampilkan mentah.
            Log::warning('Gagal membuat dokumen perpindahan mushaf', ['pesan' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Dokumen gagal dibuat karena data berubah pada saat yang sama. Muat ulang lalu coba lagi.'], 409);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => sprintf(
                'Dokumen %s dibuat: %d resi (%d eks) menunggu diterima %s. Lokasi mushaf baru berpindah setelah diterima.',
                $transfer->kode,
                $transfer->jumlah_resi,
                $transfer->jumlah_eks,
                $transfer->gudangTujuan?->nama ?? 'gudang tujuan',
            ),
            'data' => ['id' => $transfer->id, 'kode' => $transfer->kode],
        ]);
    }

    public function terima(Request $request, TransferMushaf $transfer)
    {
        $user = $request->user();

        if (! $this->bolehTerima($user, $transfer)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pengguna gudang TUJUAN, Manager Repacking, atau super-admin yang bisa menerima dokumen ini.',
            ], 403);
        }

        if ($transfer->status !== TransferMushaf::STATUS_DIPROSES) {
            return response()->json(['success' => false, 'message' => 'Dokumen ini sudah tidak menunggu penerimaan (status: '.$transfer->status.').'], 422);
        }

        try {
            DB::transaction(function () use ($transfer, $user) {
                $terkunci = TransferMushaf::whereKey($transfer->getKey())->lockForUpdate()->firstOrFail();

                if ($terkunci->status !== TransferMushaf::STATUS_DIPROSES) {
                    throw new \RuntimeException('Dokumen sudah diproses pihak lain.');
                }

                $idResi = $terkunci->items()->pluck('pengiriman_id')->all();

                // Resi dikunci, lalu syarat "tidak di dalam kerdus" dicek ULANG: syarat itu dulu
                // hanya diperiksa saat dokumen dibuat. Resi yang dikemas di gudang asal di antara
                // pembuatan dan penerimaan dokumen akan berbeda gudang dengan kerdusnya.
                Pengiriman::whereIn('id', $idResi)->lockForUpdate()->get(['id']);

                $dalamKerdus = DB::table('packing_items')
                    ->join('pengiriman', 'pengiriman.id', '=', 'packing_items.pengiriman_id')
                    ->whereIn('packing_items.pengiriman_id', $idResi)
                    ->lockForUpdate()
                    ->pluck('pengiriman.no_resi');

                if ($dalamKerdus->isNotEmpty()) {
                    throw new \RuntimeException('Resi '.$dalamKerdus->take(5)->implode(', ')
                        .' sudah dikemas ke kerdus setelah dokumen ini dibuat. Batalkan dokumen ini lalu pindahkan kerdusnya.');
                }

                // LOKASI MUSHAF berpindah DI SINI — bukan saat dokumen dibuat.
                Pengiriman::whereIn('id', $idResi)->update(['gudang_id' => $terkunci->gudang_tujuan_id]);

                $terkunci->update([
                    'status' => TransferMushaf::STATUS_DITERIMA,
                    'received_by' => $user->id,
                    'received_at' => now(),
                ]);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Dokumen '.$transfer->kode.' diterima: '.$transfer->jumlah_resi.' resi ('.$transfer->jumlah_eks.' eks) kini tercatat di '.($transfer->gudangTujuan?->nama ?? 'gudang tujuan').'.',
        ]);
    }

    public function batalkan(Request $request, TransferMushaf $transfer)
    {
        $user = $request->user();

        if (! $this->bolehBatalkan($user, $transfer)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pembuat dokumen, pengguna gudang ASAL, Manager Repacking, atau super-admin yang bisa membatalkan.',
            ], 403);
        }

        if ($transfer->status !== TransferMushaf::STATUS_DIPROSES) {
            return response()->json(['success' => false, 'message' => 'Dokumen ini sudah selesai — hanya yang masih menunggu penerimaan yang bisa dibatalkan.'], 422);
        }

        try {
            DB::transaction(function () use ($transfer) {
                $terkunci = TransferMushaf::whereKey($transfer->getKey())->lockForUpdate()->firstOrFail();

                if ($terkunci->status !== TransferMushaf::STATUS_DIPROSES) {
                    throw new \RuntimeException('Dokumen sudah diproses pihak lain.');
                }

                // Lokasi mushaf TIDAK disentuh: dokumen batal berarti barangnya tidak pindah.
                $terkunci->items()->delete();
                $terkunci->update(['status' => TransferMushaf::STATUS_DIBATALKAN, 'jumlah_resi' => 0, 'jumlah_eks' => 0]);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Dokumen '.$transfer->kode.' dibatalkan. Lokasi mushaf tidak berubah.']);
    }

    /**
     * Syarat sebuah mushaf boleh masuk dokumen perpindahan.
     *
     * Tiga penjaga, semuanya karena data akan berbohong tanpanya:
     *   1. Bukan mushaf yang sudah selesai/dibatalkan — mushaf yang sudah diterima penerima
     *      tidak ada lagi di gudang, jadi tidak ada yang bisa dipindah.
     *   2. Tidak sedang dalam dokumen perpindahan lain — kalau boleh, dua gudang bisa
     *      menerima mushaf yang sama dan lokasinya jadi tidak bisa ditentukan.
     *   3. Tidak berada di dalam kerdus — kerdus berisi banyak resi; memindahkan satu resinya
     *      saja membuat isi kerdus berpindah sebagian. Kerdus yang pindah gudang harus
     *      dipindahkan sebagai kerdus (fitur terpisah), bukan lewat resi satuannya.
     */
    private function alasanTidakBisaDipindah(Pengiriman $pengiriman, User $user): ?string
    {
        $slug = $pengiriman->status?->slug;

        if (in_array($slug, ['diterima', 'batal'], true)) {
            return "Resi {$pengiriman->no_resi} sudah selesai ({$slug}) — mushafnya tidak ada lagi di gudang.";
        }

        $terbuka = $pengiriman->transferMushafTerbuka();

        if ($terbuka) {
            return "Resi {$pengiriman->no_resi} sedang dalam dokumen perpindahan {$terbuka->kode} yang belum diterima.";
        }

        // `transfer_mushaf_items.pengiriman_id` unik: resi yang pernah tercatat di dokumen
        // yang sudah DITERIMA tidak bisa masuk dokumen lain. Dinyatakan di sini supaya
        // petugas mendapat alasan yang jelas, bukan galat duplicate key.
        $pernah = TransferMushaf::query()
            ->whereHas('items', fn ($q) => $q->where('pengiriman_id', $pengiriman->id))
            ->value('kode');

        if ($pernah) {
            return "Resi {$pengiriman->no_resi} sudah pernah dipindahkan lewat dokumen {$pernah}; satu resi hanya bisa dipindahkan sekali.";
        }

        $kerdus = $pengiriman->packingItem?->packingBox;

        if ($kerdus) {
            return "Resi {$pengiriman->no_resi} sudah di dalam kerdus {$kerdus->kode_kerdus} — pindahkan kerdusnya, bukan resinya satu-satu.";
        }

        // Akun ber-gudang hanya boleh memindahkan dari gudangnya sendiri; kalau mushafnya
        // tercatat di gudang lain, itu perpindahan yang belum selesai dibereskan.
        if ($user->gudang_id && $pengiriman->gudang_id && (int) $user->gudang_id !== (int) $pengiriman->gudang_id) {
            return "Resi {$pengiriman->no_resi} tercatat di {$pengiriman->gudang?->nama} — bukan gudang Anda.";
        }

        return null;
    }

    /** Boleh MENERIMA = pengguna gudang TUJUAN, Manager Repacking (PJ), atau super-admin. */
    private function bolehTerima(User $user, TransferMushaf $transfer): bool
    {
        if ($this->adalahPj($user)) {
            return true;
        }

        return $user->gudang_id && (int) $user->gudang_id === (int) $transfer->gudang_tujuan_id;
    }

    /** Boleh MEMBATALKAN = pembuat, pengguna gudang ASAL, Manager Repacking, super-admin. */
    private function bolehBatalkan(User $user, TransferMushaf $transfer): bool
    {
        if ($user->id === $transfer->created_by || $this->adalahPj($user)) {
            return true;
        }

        return $user->gudang_id && (int) $user->gudang_id === (int) $transfer->gudang_asal_id;
    }

    /**
     * PJ = super-admin, atau manager-repacking TANPA role spv-gudang. Di produksi SPV ikut
     * memegang role manager-repacking; tanpa pengecualian ini SPV Semarang bisa menerima
     * dokumen yang ditujukan ke Boyolali. Aturan yang sama dengan StokMushafController.
     */
    private function adalahPj(User $user): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value)
            || ($user->hasRole(RoleEnum::MANAGER_REPACKING->value)
                && ! $user->hasRole(RoleEnum::SPV_GUDANG->value));
    }
}
