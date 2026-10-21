<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\StokMushaf;
use App\Models\StokMushafPergerakan;
use App\Models\TransferStok;
use App\Models\User;
use App\Services\StokMushafService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Stok mushaf tanpa muwakif + dokumen transfer antar gudang (Fase 3).
 *
 * Pembagian tugas sesuai keputusan pemilik sistem:
 * - Manager Repacking & SPV Gudang: entri stok, pengurangan, membuat dokumen
 *   transfer, dan menerima transfer;
 * - menerima transfer juga boleh oleh SPV yang gudangnya adalah TUJUAN (itu
 *   yang menandai barang fisik sudah tiba); SPV asal tidak boleh;
 * - role warehouse (freelancer): hanya melihat.
 */
class StokMushafController extends Controller
{
    public function __construct(private StokMushafService $stokMushaf) {}

    public function index()
    {
        return Inertia::render('Warehouse/StokMushaf', [
            'gudangList' => Gudang::orderBy('id')->get(['id', 'kode', 'nama']),
            'jenisList' => JenisQuran::active()->orderBy('id')->get(['id', 'kode_jenis', 'nama_jenis', 'default_capacity']),
            'stokList' => StokMushaf::with(['gudang', 'jenisQuran'])
                ->when(auth()->user()->hasRole(RoleEnum::SPV_GUDANG->value), fn ($q) => $q->where('gudang_id', auth()->user()->gudang_id))
                ->orderBy('gudang_id')->get()
                ->map(fn (StokMushaf $stok) => [
                    'id' => $stok->id,
                    'gudang' => $stok->gudang->nama,
                    'gudang_id' => $stok->gudang_id,
                    'jenis' => $stok->jenisQuran->nama_jenis,
                    // id + nilai varian ikut dikirim: formulir "Catat Pergerakan" menghitung
                    // stok sekarang untuk pasangan jenis × varian sebelum menyimpan.
                    'jenis_id' => $stok->jenis_quran_id,
                    'varian' => (bool) $stok->terjemahan,
                    'terjemahan' => $this->labelVarian((bool) $stok->terjemahan),
                    'eks' => $stok->jumlah_eks,
                    'dus' => $stok->jumlahDus(),
                    'sisaEks' => $stok->sisaEksDiLuarDus(),
                ]),
            'transferList' => TransferStok::with(['gudangAsal', 'gudangTujuan', 'jenisQuran', 'pembuat'])
                ->when(auth()->user()->hasRole(RoleEnum::SPV_GUDANG->value), fn ($q) => $q
                    ->where(function ($q2) {
                        $q2->where('gudang_asal_id', auth()->user()->gudang_id)
                            ->orWhere('gudang_tujuan_id', auth()->user()->gudang_id);
                    }))
                ->orderBy('id', 'desc')->limit(50)->get()
                ->map(fn (TransferStok $transfer) => [
                    'id' => $transfer->id,
                    'kode' => $transfer->kode,
                    'asal' => $transfer->gudangAsal->nama,
                    'tujuan' => $transfer->gudangTujuan->nama,
                    'jenis' => $transfer->jenisQuran->nama_jenis,
                    'terjemahan' => $this->labelVarian((bool) $transfer->terjemahan),
                    'jumlah_eks' => $transfer->jumlah_eks,
                    'status' => $transfer->status,
                    'catatan' => $transfer->catatan,
                    'dibuat_oleh' => $transfer->pembuat->name,
                ]),
            'pergerakanList' => StokMushafPergerakan::with(['gudang', 'jenisQuran', 'pelaku'])
                ->when(auth()->user()->hasRole(RoleEnum::SPV_GUDANG->value), fn ($q) => $q->where('gudang_id', auth()->user()->gudang_id))
                ->orderBy('id', 'desc')->limit(30)->get()
                ->map(fn (StokMushafPergerakan $baris) => [
                    'id' => $baris->id,
                    'tipe' => $baris->tipe,
                    'gudang' => $baris->gudang->nama,
                    'jenis' => $baris->jenisQuran->nama_jenis,
                    'terjemahan' => $this->labelVarian((bool) $baris->terjemahan),
                    'jumlah_eks' => $baris->jumlah_eks,
                    'catatan' => $baris->catatan,
                    'oleh' => $baris->pelaku->name,
                    'waktu' => $baris->created_at->format('d/m H:i'),
                ]),
            'bolehKelola' => auth()->user()->can(PermissionEnum::STOK_UPDATE->value),
            'bolehTransfer' => auth()->user()->can(PermissionEnum::STOK_TRANSFER->value),
            'gudangSaya' => auth()->user()->gudang_id,
            // Fase 3 revisi: SPV Gudang tercakup ke gudangnya saja; PJ (gudang@)
            // dan peran selain spv-gudang melihat kedua gudang.
            'tercakupGudang' => auth()->user()->hasRole(RoleEnum::SPV_GUDANG->value),
        ]);
    }

    public function entri(Request $request)
    {
        $validated = $request->validate([
            'gudang_id' => ['required', 'integer', 'exists:gudang,id'],
            'jenis_quran_id' => ['required', 'integer', 'exists:jenis_quran,id'],
            // Temuan lapangan 19/10/2026: A5/A6 ada varian ber-terjemahan dan tanpa.
            // WAJIB dipilih — tanpa ini dua tumpukan tercampur dan "stok tinggal 4 eks"
            // tak bisa dijawab.
            'terjemahan' => ['required', 'boolean'],
            'tipe' => ['required', 'in:masuk,keluar'],
            'jumlah_eks' => ['required', 'integer', 'min:1', 'max:500'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $gudang = Gudang::findOrFail($validated['gudang_id']);
        $jenis = JenisQuran::findOrFail($validated['jenis_quran_id']);
        $terjemahan = (bool) $validated['terjemahan'];

        // SPV tercakup hanya boleh menyentuh stok gudangnya sendiri (server-side,
        // bukan sekadar disembunyikan di form).
        $tercakup = auth()->user()->hasRole(RoleEnum::SPV_GUDANG->value);
        if ($tercakup && (int) $validated['gudang_id'] !== (int) auth()->user()->gudang_id) {
            return response()->json(['success' => false, 'message' => 'Anda hanya boleh mengelola stok gudang Anda sendiri.'], 403);
        }

        try {
            $stok = $validated['tipe'] === 'masuk'
                ? $this->stokMushaf->catatMasuk($gudang, $jenis, (int) $validated['jumlah_eks'], $terjemahan, $validated['catatan'] ?? null, auth()->user())
                : $this->stokMushaf->keluarkan($gudang, $jenis, (int) $validated['jumlah_eks'], $terjemahan, $validated['catatan'] ?? null, auth()->user());
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => sprintf('Stok %s%s di %s: %d eks (%d dus %s) terekam.', $jenis->nama_jenis, $this->sebutanVarian($terjemahan), $gudang->nama, $stok->jumlah_eks, $stok->jumlahDus(), $stok->sisaEksDiLuarDus() > 0 ? '+ '.$stok->sisaEksDiLuarDus().' eks' : 'utuh'),
            'data' => ['eks' => $stok->jumlah_eks, 'dus' => $stok->jumlahDus()],
        ]);
    }

    public function buatTransfer(Request $request)
    {
        $validated = $request->validate([
            'gudang_asal_id' => ['required', 'integer', 'exists:gudang,id', 'different:gudang_tujuan_id'],
            'gudang_tujuan_id' => ['required', 'integer', 'exists:gudang,id'],
            'jenis_quran_id' => ['required', 'integer', 'exists:jenis_quran,id'],
            'terjemahan' => ['required', 'boolean'],
            'jumlah_eks' => ['required', 'integer', 'min:1', 'max:500'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $asal = Gudang::findOrFail($validated['gudang_asal_id']);
        $tujuan = Gudang::findOrFail($validated['gudang_tujuan_id']);

        // SPV tercakup: dokumen dibuat DARI gudangnya sendiri saja.
        $tercakup = auth()->user()->hasRole(RoleEnum::SPV_GUDANG->value);
        if ($tercakup && (int) $validated['gudang_asal_id'] !== (int) auth()->user()->gudang_id) {
            return response()->json(['success' => false, 'message' => 'Dokumen transfer hanya bisa dibuat dari gudang Anda sendiri.'], 403);
        }

        try {
            $transfer = $this->stokMushaf->buatTransfer(
                $asal,
                $tujuan,
                JenisQuran::findOrFail($validated['jenis_quran_id']),
                (int) $validated['jumlah_eks'],
                (bool) $validated['terjemahan'],
                $validated['catatan'] ?? null,
                auth()->user(),
            );
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => sprintf('Dokumen %s dibuat: %d eks %s%s dari %s ke %s. Stok berpindah saat SPV tujuan menerima.', $transfer->kode, $transfer->jumlah_eks, $transfer->jenisQuran->nama_jenis, $this->sebutanVarian((bool) $transfer->terjemahan), $transfer->gudangAsal->nama, $transfer->gudangTujuan->nama),
            'data' => ['id' => $transfer->id, 'kode' => $transfer->kode],
        ]);
    }

    public function terimaTransfer(TransferStok $transfer)
    {
        $pelaku = auth()->user();

        if (! $this->bolehTerima($pelaku, $transfer)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya SPV gudang TUJUAN, Manager Repacking, atau super-admin yang bisa menerima transfer ini.',
            ], 403);
        }

        try {
            $this->stokMushaf->terimaTransfer($transfer, $pelaku);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => sprintf('Dokumen %s diterima: stok %s berkurang, %s bertambah.', $transfer->kode, $transfer->gudangAsal->nama, $transfer->gudangTujuan->nama),
        ]);
    }

    public function batalkanTransfer(TransferStok $transfer)
    {
        $pelaku = auth()->user();

        if (! $this->bolehBatalkan($pelaku, $transfer)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pembuat dokumen, SPV gudang ASAL, Manager Repacking, atau super-admin yang bisa membatalkan.',
            ], 403);
        }

        try {
            $this->stokMushaf->batalkanTransfer($transfer, $pelaku);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Dokumen '.$transfer->kode.' dibatalkan.']);
    }

    /**
     * Boleh MENERIMA = SPV yang gudangnya adalah tujuan, Manager Repacking,
     * atau super-admin. SPV asal tidak boleh — itu yang menunggu barangnya
     * ditandai tiba.
     */
    private function bolehTerima(User $pelaku, TransferStok $transfer): bool
    {
        if ($this->adalahPj($pelaku)) {
            return true;
        }

        return $pelaku->gudang_id !== null
            && $pelaku->gudang_id === $transfer->gudang_tujuan_id
            && $pelaku->can(PermissionEnum::STOK_TRANSFER->value);
    }

    /**
     * PJ = manager-repacking TANPA role spv-gudang. SPV yang juga PJ (keputusan
     * "PJ itu SPV") TETAP terikat gudangnya masing-masing — keputusan terbaru:
     * SPV dibatasi gudangnya sendiri; hanya gudang@ yang melihat kedua gudang.
     */
    private function adalahPj(User $pelaku): bool
    {
        return $pelaku->hasRole(RoleEnum::SUPER_ADMIN->value)
            || ($pelaku->hasRole(RoleEnum::MANAGER_REPACKING->value)
                && ! $pelaku->hasRole(RoleEnum::SPV_GUDANG->value));
    }

    /**
     * Sebutan varian untuk kalimat: " (ada terjemahan)" / " (tanpa terjemahan)".
     */
    private function sebutanVarian(bool $terjemahan): string
    {
        return $terjemahan ? ' (ada terjemahan)' : ' (tanpa terjemahan)';
    }

    /**
     * Label varian untuk kolom tabel: "Ada terjemahan" / "Tanpa terjemahan".
     */
    private function labelVarian(bool $terjemahan): string
    {
        return $terjemahan ? 'Ada terjemahan' : 'Tanpa terjemahan';
    }

    private function bolehBatalkan(User $pelaku, TransferStok $transfer): bool
    {
        if ($this->adalahPj($pelaku)) {
            return true;
        }

        return $pelaku->id === $transfer->created_by
            || ($pelaku->gudang_id !== null
                && $pelaku->gudang_id === $transfer->gudang_asal_id
                && $pelaku->can(PermissionEnum::STOK_TRANSFER->value));
    }
}
