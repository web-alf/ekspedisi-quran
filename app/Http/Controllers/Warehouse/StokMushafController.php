<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\StokMushaf;
use App\Models\StokMushafPergerakan;
use App\Models\TransferStok;
use App\Models\User;
use App\Services\RingkasanGudangService;
use App\Services\StokMushafService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Stok mushaf tanpa muwakif + dokumen transfer antar gudang (Fase 3).
 *
 * Pembagian tugas sesuai keputusan pemilik sistem:
 * - Manager Repacking & SPV Gudang: entri stok, pengurangan, membuat dokumen
 *   transfer, dan menerima transfer;
 * - menerima transfer juga boleh oleh SPV yang gudangnya adalah TUJUAN (itu
 *   yang menandai barang fisik sudah tiba); SPV asal tidak boleh;
 * - role warehouse (freelancer): hanya melihat, dan bila tugas packing hari ini berjenis
 *   (mis. A6) hanya stok jenis itu (02/11/2026).
 */
class StokMushafController extends Controller
{
    /**
     * Batas jumlah eks per sekali catat stok atau dokumen transfer (30/10/2026). Dulu 500 —
     * kemungkinan sisa saat stok masih dicatat dalam dus — padahal stok satu gudang sudah
     * ribuan eks, sehingga penerimaan 2.000 eks ditolak.
     */
    private const MAKS_EKS_SEKALI_CATAT = 100000;

    public function __construct(
        private StokMushafService $stokMushaf,
        private RingkasanGudangService $ringkasanGudang,
    ) {}

    public function index()
    {
        $user = auth()->user();
        // Satu cakupan untuk kartu, daftar, dan pergerakan: SPV dan freelancer hanya
        // gudangnya sendiri, akun tanpa gudang tidak melihat apa pun (User::cakupanGudangStok).
        $cakupan = $user->cakupanGudangStok();
        $tercakupGudang = $cakupan !== null;
        $jenisTugas = $this->jenisTugasFreelancer($user);
        $jenisTugasId = $jenisTugas?->id;

        // Kartu "Isi Stok" memakai SUMBER & KOMPONEN yang sama dengan dashboard
        // (RingkasanGudangService::stokKartu + KartuStok) supaya bentuk kartunya identik
        // di kedua halaman — bukan tabel atau kartu rancangan sendiri (27/10/2026).
        $gudangKartu = Gudang::query()
            ->when($tercakupGudang, fn ($q) => $q->whereKey($cakupan))
            ->orderBy('id')
            ->get(['id', 'kode', 'nama'])
            ->map(fn (Gudang $g) => [
                'gudang_id' => $g->id,
                'gudang' => $g->nama,
                'baris' => $this->ringkasanGudang->stokKartu($g->id, $jenisTugasId),
            ])
            ->filter(fn (array $g) => $g['baris'] !== [])
            ->values()
            ->all();

        return Inertia::render('Warehouse/StokMushaf', [
            'gudangList' => Gudang::orderBy('id')->get(['id', 'kode', 'nama']),
            'jenisList' => collect(JenisQuran::daftarUntukFormStok())
                ->when($jenisTugasId !== null, fn ($daftar) => $daftar->where('id', $jenisTugasId))
                ->values()->all(),
            'stokKartu' => $gudangKartu,
            'stokList' => StokMushaf::with(['gudang', 'jenisQuran'])
                ->when($tercakupGudang, fn ($q) => $q->where('gudang_id', $cakupan))
                ->when($jenisTugasId !== null, fn ($q) => $q->where('jenis_quran_id', $jenisTugasId))
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
            // transferList tidak lagi dikirim: form + daftar dokumen transfer pindah ke halaman
            // Perpindahan Mushaf (tab "Stok tanpa muwakif") supaya kedua jenis dokumen
            // perpindahan berada di satu tempat. Halaman ini menyisakan tautan ke sana.
            'pergerakanList' => StokMushafPergerakan::with(['gudang', 'jenisQuran', 'pelaku'])
                ->when($tercakupGudang, fn ($q) => $q->where('gudang_id', $cakupan))
                ->when($jenisTugasId !== null, fn ($q) => $q->where('jenis_quran_id', $jenisTugasId))
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
            'bolehKelola' => $user->can(PermissionEnum::STOK_UPDATE->value),
            'gudangSaya' => $user->gudang_id,
            // SPV Gudang dan freelancer tercakup ke gudangnya saja; hanya super-admin
            // dan PJ murni (gudang@) yang melihat kedua gudang.
            'tercakupGudang' => $tercakupGudang,
            // "A6" bila freelancer hanya melihat stok jenis tugasnya hari ini; null = semua jenis.
            'jenisTugas' => $jenisTugas?->labelSingkat(),
        ]);
    }

    /**
     * Jenis tugas packing hari ini milik freelancer murni (02/11/2026): freelancer bertugas A6
     * hanya melihat stok A6, seperti di Proses Packing. Hanya jenis yang dibatasi, bukan varian
     * terjemahan (sama dengan aturan scan). Null = tidak dibatasi: bukan freelancer murni,
     * belum ada tugas hari ini, atau tugas lama tanpa jenis.
     */
    private function jenisTugasFreelancer(User $user): ?JenisQuran
    {
        if (! $user->isFreelancerMurni()) {
            return null;
        }

        return DailyPackingTask::where('user_id', $user->id)
            ->whereDate('tanggal_tugas', today())
            ->whereNotNull('jenis_quran_id')
            ->with('jenisQuran:id,kode_jenis,nama_jenis')
            ->first()
            ?->jenisQuran;
    }

    public function entri(Request $request)
    {
        $validated = $request->validate([
            'gudang_id' => ['required', 'integer', 'exists:gudang,id'],
            'jenis_quran_id' => ['required', 'integer', 'exists:jenis_quran,id'],
            // Temuan lapangan 19/10/2026: A5/A6 ada varian ber-terjemahan dan tanpa.
            // WAJIB dipilih — tanpa ini dua tumpukan tercampur dan "stok tinggal 4 eks"
            // tak bisa dijawab. IQRA tidak punya varian (lihat config stok_varian_jenis).
            'terjemahan' => $this->aturanVarian($request),
            'tipe' => ['required', 'in:masuk,keluar'],
            'jumlah_eks' => ['required', 'integer', 'min:1', 'max:'.self::MAKS_EKS_SEKALI_CATAT],
            'catatan' => ['nullable', 'string', 'max:255'],
        ], $this->pesanJumlahEks());

        $gudang = Gudang::findOrFail($validated['gudang_id']);
        $jenis = JenisQuran::findOrFail($validated['jenis_quran_id']);
        $terjemahan = $this->varianTersimpan($jenis, $validated);

        // SPV tercakup hanya boleh menyentuh stok gudangnya sendiri (server-side,
        // bukan sekadar disembunyikan di form).
        $cakupan = auth()->user()->cakupanGudangStok();
        if ($cakupan !== null && (int) $validated['gudang_id'] !== $cakupan) {
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
            'message' => sprintf('Stok %s%s di %s: %d eks (%d dus %s) terekam.', $jenis->nama_jenis, $this->sebutanVarian($jenis, $terjemahan), $gudang->nama, $stok->jumlah_eks, $stok->jumlahDus(), $stok->sisaEksDiLuarDus() > 0 ? '+ '.$stok->sisaEksDiLuarDus().' eks' : 'utuh'),
            'data' => ['eks' => $stok->jumlah_eks, 'dus' => $stok->jumlahDus()],
        ]);
    }

    public function buatTransfer(Request $request)
    {
        $validated = $request->validate([
            'gudang_asal_id' => ['required', 'integer', 'exists:gudang,id', 'different:gudang_tujuan_id'],
            'gudang_tujuan_id' => ['required', 'integer', 'exists:gudang,id'],
            'jenis_quran_id' => ['required', 'integer', 'exists:jenis_quran,id'],
            'terjemahan' => $this->aturanVarian($request),
            'jumlah_eks' => ['required', 'integer', 'min:1', 'max:'.self::MAKS_EKS_SEKALI_CATAT],
            'catatan' => ['nullable', 'string', 'max:255'],
        ], $this->pesanJumlahEks());

        $jenis = JenisQuran::findOrFail($validated['jenis_quran_id']);
        $asal = Gudang::findOrFail($validated['gudang_asal_id']);
        $tujuan = Gudang::findOrFail($validated['gudang_tujuan_id']);

        // SPV tercakup: dokumen dibuat DARI gudangnya sendiri saja.
        $cakupan = auth()->user()->cakupanGudangStok();
        if ($cakupan !== null && (int) $validated['gudang_asal_id'] !== $cakupan) {
            return response()->json(['success' => false, 'message' => 'Dokumen transfer hanya bisa dibuat dari gudang Anda sendiri.'], 403);
        }

        try {
            $transfer = $this->stokMushaf->buatTransfer(
                $asal,
                $tujuan,
                $jenis,
                (int) $validated['jumlah_eks'],
                $this->varianTersimpan($jenis, $validated),
                $validated['catatan'] ?? null,
                auth()->user(),
            );
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => sprintf('Dokumen %s dibuat: %d eks %s%s dari %s ke %s. Stok berpindah saat SPV tujuan menerima.', $transfer->kode, $transfer->jumlah_eks, $transfer->jenisQuran->nama_jenis, $this->sebutanVarian($transfer->jenisQuran, (bool) $transfer->terjemahan), $transfer->gudangAsal->nama, $transfer->gudangTujuan->nama),
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
     * Varian wajib hanya untuk jenis yang punya varian terjemahan (A5/A6). Jenis yang tidak
     * ditemukan diperlakukan wajib, supaya galatnya tetap muncul bersama galat jenis.
     *
     * @return array<int, mixed>
     */
    /**
     * @return array<string, string>
     */
    private function pesanJumlahEks(): array
    {
        return [
            'jumlah_eks.required' => 'Isi jumlah eks.',
            'jumlah_eks.integer' => 'Jumlah eks harus bilangan bulat.',
            'jumlah_eks.min' => 'Jumlah minimal 1 eks.',
            'jumlah_eks.max' => 'Jumlah maksimal 100.000 eks sekali catat.',
        ];
    }

    private function aturanVarian(Request $request): array
    {
        return [
            Rule::requiredIf(fn () => JenisQuran::find($request->integer('jenis_quran_id'))?->punyaVarianTerjemahan() ?? true),
            'nullable',
            'boolean',
        ];
    }

    /**
     * Varian yang disimpan: IQRA selalu "tanpa terjemahan", apa pun yang dikirim, supaya
     * stoknya tidak terpecah menjadi dua tumpukan.
     *
     * @param  array<string, mixed>  $validated
     */
    private function varianTersimpan(JenisQuran $jenis, array $validated): bool
    {
        return $jenis->punyaVarianTerjemahan() && (bool) ($validated['terjemahan'] ?? false);
    }

    /**
     * Sebutan varian untuk kalimat: " (ada terjemahan)" / " (tanpa terjemahan)"; kosong
     * untuk jenis tanpa varian (IQRA).
     */
    private function sebutanVarian(JenisQuran $jenis, bool $terjemahan): string
    {
        if (! $jenis->punyaVarianTerjemahan()) {
            return '';
        }

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
