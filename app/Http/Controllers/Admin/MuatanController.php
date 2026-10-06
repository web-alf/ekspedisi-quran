<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Muatan;
use App\Models\MuatanItem;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Support\PengirimanStageVisibility;
use App\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Management Muatan & Distribusi.
 *
 * Muatan = sekumpulan resi yang diantar satu kurir dalam satu perjalanan.
 *
 * Aturan wewenang yang dijaga di sini:
 *
 *  - Kurir hanya boleh mengantar dan memindahkan status PERJALANAN
 *    (mis. "Proses Pengiriman"). Ia TIDAK boleh menyelesaikan distribusi.
 *  - Menyelesaikan distribusi — mengubah status resi menjadi "Diterima" —
 *    hanya boleh role `distribusi` (dan super-admin).
 *
 * Pembatasan itu ditegakkan di server, bukan hanya disembunyikan di tampilan:
 * kurir yang mengirim permintaan langsung ke endpoint "selesaikan" tetap ditolak.
 * Kalau hanya tombolnya yang disembunyikan, satu panggilan manual sudah cukup
 * untuk menembusnya.
 */
class MuatanController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:muatan.read')->only(['index', 'show']);
        $this->middleware('permission:muatan.create')->only(['create', 'store']);
        $this->middleware('permission:muatan.update')->only(['edit', 'update', 'syncItems']);
        $this->middleware('permission:muatan.delete')->only(['destroy', 'removeItem']);
        $this->middleware('permission:muatan.scan')->only(['scanItem']);
    }

    /**
     * Daftar muatan.
     *
     * Kurir hanya melihat muatan miliknya. Role lain (distribusi, supervisor,
     * manager, super-admin) melihat semua — mereka yang memantau.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Muatan::query()
            ->with([
                'kurir:id,name,email',
                'pembuat:id,name',
            ])
            ->withCount('items');

        if ($this->hanyaMiliknya($user)) {
            $query->forKurir($user->id);
        }

        if ($request->filled('kurir_id')) {
            $query->where('kurir_id', $request->integer('kurir_id'));
        }

        if ($request->filled('tanggal')) {
            $query->tanggal($request->string('tanggal')->toString());
        }

        if ($request->filled('search')) {
            $cari = $request->string('search')->toString();
            $query->where(function ($q) use ($cari): void {
                $q->where('kode_muatan', 'like', "%{$cari}%")
                    ->orWhere('nama_muatan', 'like', "%{$cari}%");
            });
        }

        $muatan = $query->orderByDesc('tanggal_muatan')
            ->orderByDesc('id')
            ->paginate(PerPage::resolve($request))
            ->withQueryString();

        // Sebaran status per muatan di halaman ini. Dihitung sekaligus (bukan per
        // baris) supaya tidak ada N+1 pada daftar.
        $sebaran = $this->sebaranUntuk($muatan->pluck('id')->all());

        $muatan->through(fn (Muatan $m) => [
            'id' => $m->id,
            'kode_muatan' => $m->kode_muatan,
            'nama_muatan' => $m->nama_muatan,
            'tanggal_muatan' => $m->tanggal_muatan?->format('Y-m-d'),
            'catatan' => $m->catatan,
            'jumlah_lembaga' => $m->jumlah_lembaga,
            'kurir' => $m->kurir ? ['id' => $m->kurir->id, 'name' => $m->kurir->name] : null,
            'pembuat' => $m->pembuat?->name,
            'total_resi' => $m->total_resi,
            'total_mushaf' => $m->total_mushaf,
            'selesai' => $this->hitungSelesai($m, $sebaran[$m->id] ?? []),
            'sebaran_status' => $sebaran[$m->id] ?? [],
        ]);

        return Inertia::render('Admin/Muatan/Index', PerPage::props($request) + [
            'muatan' => $muatan,
            'kurirList' => $this->kurirList(),
            'filters' => $request->only(['search', 'kurir_id', 'tanggal']),
            'dapatMembuat' => $user->can(PermissionEnum::MUATAN_CREATE->value),
            'hanyaMiliknya' => $this->hanyaMiliknya($user),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Muatan/Create', [
            'kurirList' => $this->kurirList(),
            // Resi yang sudah siap diantar dan belum masuk muatan mana pun.
            'resiSiap' => $this->resiBelumDimuat(),
        ]);
    }

    /**
     * Simpan muatan baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kurir_id' => ['required', 'integer', 'exists:users,id'],
            'tanggal_muatan' => ['required', 'date'],
            'nama_muatan' => ['nullable', 'string', 'max:255'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'jumlah_lembaga' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'pengiriman_ids' => ['nullable', 'array'],
            'pengiriman_ids.*' => ['integer', 'exists:pengiriman,id'],
        ], [
            'kurir_id.required' => 'Kurir wajib dipilih',
            'tanggal_muatan.required' => 'Tanggal muatan wajib diisi',
            'jumlah_lembaga.integer' => 'Jumlah lembaga harus berupa angka',
            'jumlah_lembaga.min' => 'Jumlah lembaga minimal 1',
        ]);

        // Kurir yang dipilih harus benar-benar berperan sebagai kurir; tanpa
        // pemeriksaan ini, resi bisa "diantar" oleh akun gudang yang tidak pernah
        // berangkat.
        $kurir = User::find($validated['kurir_id']);
        if (! $kurir || ! $kurir->hasRole(RoleEnum::COURIER->value)) {
            return back()->withErrors(['kurir_id' => 'Pengguna yang dipilih bukan kurir.']);
        }

        $muatan = DB::transaction(function () use ($validated, $request) {
            $muatan = Muatan::create([
                'kurir_id' => $validated['kurir_id'],
                'created_by' => $request->user()->id,
                'tanggal_muatan' => $validated['tanggal_muatan'],
                'nama_muatan' => $validated['nama_muatan'] ?? null,
                'catatan' => $validated['catatan'] ?? null,
                'jumlah_lembaga' => $validated['jumlah_lembaga'] ?? null,
            ]);

            foreach ($validated['pengiriman_ids'] ?? [] as $urutan => $pengirimanId) {
                $this->lampirkanResi($muatan, (int) $pengirimanId, $request->user()->id, $urutan + 1);
            }

            $muatan->syncTotals();

            return $muatan;
        });

        return redirect()
            ->route('admin.muatan.show', $muatan->id)
            ->with('success', "Muatan {$muatan->kode_muatan} berhasil dibuat.");
    }

    public function show(Request $request, Muatan $muatan): Response
    {
        $this->pastikanBolehLihat($request, $muatan);

        $muatan->load(['kurir:id,name,email', 'pembuat:id,name']);

        $items = MuatanItem::where('muatan_id', $muatan->id)
            ->with([
                'pengiriman:id,no_resi,nama_penerima,nama_lembaga,alamat_tujuan,no_hp_penerima,jumlah_quran,status_id,jenis_quran_id',
                'pengiriman.status:id,nama,slug,warna,urutan',
                'pengiriman.jenisQuran:id,nama_jenis,kode_jenis',
                'pemuat:id,name',
            ])
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();

        $user = $request->user();

        return Inertia::render('Admin/Muatan/Show', [
            'muatan' => [
                'id' => $muatan->id,
                'kode_muatan' => $muatan->kode_muatan,
                'nama_muatan' => $muatan->nama_muatan,
                'tanggal_muatan' => $muatan->tanggal_muatan?->format('Y-m-d'),
                'catatan' => $muatan->catatan,
                'jumlah_lembaga' => $muatan->jumlah_lembaga,
                'kurir' => $muatan->kurir ? ['id' => $muatan->kurir->id, 'name' => $muatan->kurir->name] : null,
                'pembuat' => $muatan->pembuat?->name,
                'total_resi' => $muatan->total_resi,
                'total_mushaf' => $muatan->total_mushaf,
                'sebaran_status' => $muatan->sebaranStatus(),
                'selesai' => $muatan->selesai,
            ],
            'items' => $items->map(fn (MuatanItem $item) => [
                'id' => $item->id,
                'urutan' => $item->urutan,
                'dimuat_at' => $item->dimuat_at?->toIso8601String(),
                'pemuat' => $item->pemuat?->name,
                'catatan' => $item->catatan,
                'pengiriman' => $item->pengiriman ? [
                    'id' => $item->pengiriman->id,
                    'no_resi' => $item->pengiriman->no_resi,
                    'nama_penerima' => $item->pengiriman->nama_penerima,
                    'nama_lembaga' => $item->pengiriman->nama_lembaga,
                    'alamat_tujuan' => $item->pengiriman->alamat_tujuan,
                    'no_hp_penerima' => $item->pengiriman->no_hp_penerima,
                    'jumlah_quran' => $item->pengiriman->jumlah_quran,
                    'jenis' => $item->pengiriman->jenisQuran?->nama_jenis,
                    'status' => $item->pengiriman->status ? [
                        'id' => $item->pengiriman->status->id,
                        'nama' => $item->pengiriman->status->nama,
                        'slug' => $item->pengiriman->status->slug,
                        'warna' => $item->pengiriman->status->warna,
                    ] : null,
                ] : null,
            ]),
            'statusPerjalanan' => $this->statusPerjalanan(),
            'statusSelesai' => $this->statusDiterima(),
            'dapatMengubah' => $user->can(PermissionEnum::MUATAN_UPDATE->value),
            'dapatMenghapus' => $user->can(PermissionEnum::MUATAN_DELETE->value),
            'dapatMemindai' => $user->can(PermissionEnum::MUATAN_SCAN->value),
            // Inilah pembeda kurir vs role distribusi, dikirim ke tampilan supaya
            // tombolnya tepat — tetapi penegakannya ada di server (lihat
            // selesaikanDistribusi()).
            'dapatMenyelesaikan' => $this->bolehMenyelesaikan($user),
            'alasanTidakBolehSelesai' => $this->alasanTidakBolehSelesai($user),
        ]);
    }

    public function edit(Request $request, Muatan $muatan): Response
    {
        $this->pastikanBolehKelola($request, $muatan);

        return Inertia::render('Admin/Muatan/Edit', [
            'muatan' => [
                'id' => $muatan->id,
                'kode_muatan' => $muatan->kode_muatan,
                'nama_muatan' => $muatan->nama_muatan,
                'tanggal_muatan' => $muatan->tanggal_muatan?->format('Y-m-d'),
                'catatan' => $muatan->catatan,
                'jumlah_lembaga' => $muatan->jumlah_lembaga,
                'kurir_id' => $muatan->kurir_id,
            ],
            'kurirList' => $this->kurirList(),
        ]);
    }

    public function update(Request $request, Muatan $muatan)
    {
        $this->pastikanBolehKelola($request, $muatan);

        $validated = $request->validate([
            'kurir_id' => ['required', 'integer', 'exists:users,id'],
            'tanggal_muatan' => ['required', 'date'],
            'nama_muatan' => ['nullable', 'string', 'max:255'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'jumlah_lembaga' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ], [
            'jumlah_lembaga.integer' => 'Jumlah lembaga harus berupa angka',
            'jumlah_lembaga.min' => 'Jumlah lembaga minimal 1',
        ]);

        $kurir = User::find($validated['kurir_id']);
        if (! $kurir || ! $kurir->hasRole(RoleEnum::COURIER->value)) {
            return back()->withErrors(['kurir_id' => 'Pengguna yang dipilih bukan kurir.']);
        }

        $muatan->update($validated);

        return redirect()
            ->route('admin.muatan.show', $muatan->id)
            ->with('success', 'Muatan berhasil diperbarui.');
    }

    public function destroy(Request $request, Muatan $muatan)
    {
        $this->pastikanBolehKelola($request, $muatan);

        $kode = $muatan->kode_muatan;

        // Isi muatan dilepas, bukan dihapus resinya: resi adalah barang milik
        // pemohon, bukan milik muatan. Menghapus muatan tidak boleh menghapus
        // pengiriman.
        DB::transaction(function () use ($muatan): void {
            MuatanItem::where('muatan_id', $muatan->id)->delete();
            $muatan->delete();
        });

        return redirect()
            ->route('admin.muatan.index')
            ->with('success', "Muatan {$kode} dihapus. Resinya tidak terhapus, hanya dikeluarkan dari muatan.");
    }

    /**
     * Pindai resi untuk memasukkannya ke muatan.
     *
     * Dipakai kurir saat memuat barang ke kendaraan maupun role distribusi yang
     * menyiapkan muatan.
     */
    public function scanItem(Request $request, Muatan $muatan): JsonResponse
    {
        $this->pastikanBolehKelola($request, $muatan);

        $validated = $request->validate([
            'no_resi' => ['required', 'string'],
        ]);

        $noResi = $this->bersihkanResi($validated['no_resi']);

        $pengiriman = Pengiriman::where('no_resi', $noResi)->first();

        if (! $pengiriman) {
            return response()->json([
                'success' => false,
                'message' => "Resi {$noResi} tidak ditemukan.",
            ], 404);
        }

        // Sudah ada di muatan lain?
        $sudahAda = MuatanItem::where('pengiriman_id', $pengiriman->id)->first();

        if ($sudahAda && (int) $sudahAda->muatan_id === (int) $muatan->id) {
            return response()->json([
                'success' => false,
                'message' => "Resi {$noResi} sudah ada di muatan ini.",
            ], 409);
        }

        if ($sudahAda) {
            $lain = Muatan::find($sudahAda->muatan_id);

            return response()->json([
                'success' => false,
                'message' => "Resi {$noResi} sudah dimuat di muatan ".($lain->kode_muatan ?? '-').'.',
            ], 409);
        }

        if (! $this->siapDimuat($pengiriman)) {
            return response()->json([
                'success' => false,
                'message' => "Resi {$noResi} belum siap diantar (status: ".($pengiriman->status->nama ?? '-').').',
            ], 422);
        }

        DB::transaction(function () use ($muatan, $pengiriman, $request): void {
            $this->lampirkanResi($muatan, $pengiriman->id, $request->user()->id);
            $muatan->syncTotals();
        });

        return response()->json([
            'success' => true,
            'message' => "Resi {$noResi} dimuat ke muatan {$muatan->kode_muatan}.",
            'pengiriman' => [
                'id' => $pengiriman->id,
                'no_resi' => $pengiriman->no_resi,
                'nama_penerima' => $pengiriman->nama_penerima,
                'nama_lembaga' => $pengiriman->nama_lembaga,
                'alamat_tujuan' => $pengiriman->alamat_tujuan,
                'jumlah_quran' => $pengiriman->jumlah_quran,
            ],
            'total_resi' => $muatan->fresh()->total_resi,
            'total_mushaf' => $muatan->fresh()->total_mushaf,
        ]);
    }

    /**
     * Keluarkan satu resi dari muatan.
     */
    public function removeItem(Request $request, Muatan $muatan, MuatanItem $item)
    {
        $this->pastikanBolehKelola($request, $muatan);

        if ((int) $item->muatan_id !== (int) $muatan->id) {
            abort(404);
        }

        DB::transaction(function () use ($muatan, $item): void {
            $item->delete();
            $muatan->syncTotals();
        });

        return back()->with('success', 'Resi dikeluarkan dari muatan.');
    }

    /**
     * Pindahkan status SELURUH resi dalam muatan ke satu tahap perjalanan.
     *
     * Kurir boleh memakai ini untuk menandai barang sedang dalam perjalanan.
     * SENGAJA menolak status "diterima": menyelesaikan distribusi adalah
     * wewenang role distribusi, jadi aturannya sama dengan selesaikanDistribusi().
     */
    public function ubahStatusPerjalanan(Request $request, Muatan $muatan): JsonResponse
    {
        $this->pastikanBolehKelola($request, $muatan);

        $validated = $request->validate([
            'status_id' => ['required', 'integer', 'exists:status_pengiriman,id'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $status = StatusPengiriman::findOrFail($validated['status_id']);

        if ($status->slug === 'diterima') {
            return response()->json([
                'success' => false,
                'message' => 'Menyelesaikan distribusi (status Diterima) hanya boleh dilakukan role distribusi.',
            ], 403);
        }

        $hasil = $this->terapkanStatusKeSemuaResi(
            $muatan,
            $status,
            $validated['catatan'] ?? null,
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => "Status {$hasil['berhasil']} resi diubah menjadi {$status->nama}."
                .($hasil['gagal'] > 0 ? " {$hasil['gagal']} resi dilewati (status tidak bisa mundur)." : ''),
            'berhasil' => $hasil['berhasil'],
            'gagal' => $hasil['gagal'],
        ]);
    }

    /**
     * Selesaikan distribusi: ubah SELURUH resi dalam muatan menjadi "Diterima".
     *
     * HANYA role distribusi (dan super-admin). Ini pembatasan yang diminta:
     * "yang berhak menyelesaikan distribusi adalah role distribusi, bukan kurir".
     *
     * Ditegakkan di server — kurir yang memanggil endpoint ini langsung tetap
     * ditolak, bukan sekadar tombolnya disembunyikan.
     */
    public function selesaikanDistribusi(Request $request, Muatan $muatan): JsonResponse
    {
        if (! $this->bolehMenyelesaikan($request->user())) {
            return response()->json([
                'success' => false,
                'message' => $this->alasanTidakBolehSelesai($request->user()),
            ], 403);
        }

        $validated = $request->validate([
            'catatan' => ['nullable', 'string', 'max:500'],
            'receiver_contact' => ['nullable', 'string', 'max:50'],
        ]);

        $statusDiterima = $this->modelStatusDiterima();

        if (! $statusDiterima) {
            return response()->json([
                'success' => false,
                'message' => 'Status "Diterima" tidak ditemukan pada data status pengiriman.',
            ], 500);
        }

        $hasil = DB::transaction(function () use ($muatan, $statusDiterima, $validated, $request) {
            $hasil = $this->terapkanStatusKeSemuaResi(
                $muatan,
                $statusDiterima,
                $validated['catatan'] ?? 'Distribusi diselesaikan',
                $request->user()->id
            );

            // Catat siapa & kapan menerima, di resinya masing-masing.
            $idResi = MuatanItem::where('muatan_id', $muatan->id)->pluck('pengiriman_id')->all();

            Pengiriman::whereIn('id', $idResi)->update([
                'received_at' => now(),
                'received_by' => $request->user()->name,
                'receiver_contact' => $validated['receiver_contact'] ?? null,
            ]);

            return $hasil;
        });

        Log::info('Distribusi diselesaikan', [
            'muatan' => $muatan->kode_muatan,
            'oleh' => $request->user()->id,
            'berhasil' => $hasil['berhasil'],
            'gagal' => $hasil['gagal'],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Distribusi muatan {$muatan->kode_muatan} diselesaikan: {$hasil['berhasil']} resi diterima."
                .($hasil['gagal'] > 0 ? " {$hasil['gagal']} resi dilewati." : ''),
            'berhasil' => $hasil['berhasil'],
            'gagal' => $hasil['gagal'],
        ]);
    }

    /**
     * Pindai per-box: isi kotak pindai adalah kode kerdus, bukan nomor resi.
     *
     * Memindai satu kerdus memuat SELURUH resi di dalamnya sekaligus — itulah
     * gunanya memindai per-box: satu kerdus A5 bisa berisi 20 mushaf, dan
     * memindainya satu per satu berarti 20 kali pindai untuk satu kerdus.
     *
     * Dipisah dari scanItem (bukan dideteksi otomatis dari isi kotak pindai)
     * karena keduanya melakukan hal yang berbeda: yang satu menambah satu resi,
     * yang satu lagi menambah seluruh isi kerdus. Menggabungkannya berarti satu
     * salah baca menghasilkan puluhan resi termuat tanpa disadari.
     */
    public function scanBox(Request $request, Muatan $muatan): JsonResponse
    {
        $this->pastikanBolehKelola($request, $muatan);

        $validated = $request->validate([
            'kode' => ['required', 'string'],
        ]);

        $kodeKerdus = $this->bersihkanKerdus($validated['kode']);

        $box = PackingBox::with(['packingItems.pengiriman.status'])
            ->where('kode_kerdus', $kodeKerdus)
            ->first();

        if (! $box) {
            return response()->json([
                'success' => false,
                'message' => "Kerdus {$kodeKerdus} tidak ditemukan.",
            ], 404);
        }

        $resinya = $box->packingItems
            ->map(fn ($item) => $item->pengiriman)
            ->filter()
            ->values();

        if ($resinya->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Kerdus {$kodeKerdus} belum berisi resi.",
            ], 422);
        }

        $dimuat = 0;
        $sudahDiMuatanIni = 0;
        $diMuatanLain = [];
        $belumSiap = [];

        DB::transaction(function () use ($muatan, $resinya, $request, &$dimuat, &$sudahDiMuatanIni, &$diMuatanLain, &$belumSiap): void {
            $urutan = (int) MuatanItem::where('muatan_id', $muatan->id)->max('urutan');

            foreach ($resinya as $pengiriman) {
                $sudahAda = MuatanItem::where('pengiriman_id', $pengiriman->id)->first();

                if ($sudahAda) {
                    if ((int) $sudahAda->muatan_id === (int) $muatan->id) {
                        $sudahDiMuatanIni++;

                        continue;
                    }

                    $diMuatanLain[] = $pengiriman->no_resi;

                    continue;
                }

                // Satu kerdus bisa memuat resi dari beberapa tahap sekaligus.
                // Resi yang belum siap DILEWATI dan dilaporkan, bukan menggagalkan
                // seluruh kerdus — sisanya tetap harus bisa dimuat.
                if (! $this->siapDimuat($pengiriman)) {
                    $belumSiap[] = $pengiriman->no_resi.'.'.($pengiriman->status->slug ?? '-');

                    continue;
                }

                $this->lampirkanResi($muatan, $pengiriman->id, $request->user()->id, ++$urutan);
                $dimuat++;
            }
        });

        $muatan->syncTotals();
        $muatan->refresh();

        $catatan = [];
        if ($sudahDiMuatanIni > 0) {
            $catatan[] = "{$sudahDiMuatanIni} sudah ada di muatan ini";
        }
        if ($diMuatanLain !== []) {
            $catatan[] = count($diMuatanLain).' sudah dimuat di muatan lain ('.implode(', ', array_slice($diMuatanLain, 0, 3)).(count($diMuatanLain) > 3 ? ', ...' : '').')';
        }
        if ($belumSiap !== []) {
            $catatan[] = count($belumSiap).' belum siap diantar ('.implode(', ', array_slice($belumSiap, 0, 3)).(count($belumSiap) > 3 ? ', ...' : '').')';
        }

        return response()->json([
            'success' => true,
            'message' => $dimuat > 0
                ? "Kerdus {$kodeKerdus}: {$dimuat} resi dimuat".($catatan !== [] ? '. Dilewati: '.implode('; ', $catatan).'.' : '.')
                : "Tidak ada resi dari kerdus {$kodeKerdus} yang bisa dimuat".($catatan !== [] ? ': '.implode('; ', $catatan).'.' : '.'),
            'dimuat' => $dimuat,
            'total_resi' => $muatan->total_resi,
            'total_mushaf' => $muatan->total_mushaf,
            'jumlah_lembaga' => $muatan->jumlah_lembaga,
        ]);
    }

    public function syncItems(Request $request, Muatan $muatan)
    {
        $this->pastikanBolehKelola($request, $muatan);
        $validated = $request->validate([
            'pengiriman_ids' => ['required', 'array'],
            'pengiriman_ids.*' => ['integer', 'exists:pengiriman,id'],
        ]);

        $dilewati = [];

        DB::transaction(function () use ($muatan, $validated, $request, &$dilewati): void {
            foreach ($validated['pengiriman_ids'] as $pengirimanId) {
                if (MuatanItem::where('pengiriman_id', $pengirimanId)->exists()) {
                    $dilewati[] = $pengirimanId;

                    continue;
                }

                $this->lampirkanResi($muatan, (int) $pengirimanId, $request->user()->id);
            }

            $muatan->syncTotals();
        });

        $pesan = 'Resi ditambahkan ke muatan.';
        if ($dilewati !== []) {
            $pesan .= ' '.count($dilewati).' resi dilewati karena sudah ada di muatan lain.';
        }

        return back()->with('success', $pesan);
    }

    // --- Helper ---

    /**
     * Resi boleh dimuat bila sudah selesai packing atau sedang dalam pengiriman —
     * dan belum diterima. Memuat resi yang belum selesai packing berarti
     * mengantar barang yang belum siap.
     */
    private function siapDimuat(Pengiriman $pengiriman): bool
    {
        $slug = $pengiriman->status?->slug ?? '';

        return in_array($slug, ['selesai-packing', 'pengiriman'], true);
    }

    private function lampirkanResi(Muatan $muatan, int $pengirimanId, int $userId, ?int $urutan = null): void
    {
        $urutan ??= (int) MuatanItem::where('muatan_id', $muatan->id)->max('urutan') + 1;

        MuatanItem::create([
            'muatan_id' => $muatan->id,
            'pengiriman_id' => $pengirimanId,
            'urutan' => $urutan,
            'dimuat_at' => now(),
            'dimuat_by' => $userId,
        ]);
    }

    /**
     * Ubah status semua resi dalam muatan.
     *
     * Status yang tidak boleh mundur dilewati, bukan dipaksakan: memindahkan
     * resi yang sudah "Diterima" kembali ke "Proses Pengiriman" akan mengubah
     * riwayat yang sudah terjadi.
     *
     * @return array{berhasil: int, gagal: int}
     */
    private function terapkanStatusKeSemuaResi(Muatan $muatan, StatusPengiriman $status, ?string $catatan, int $userId): array
    {
        $berhasil = 0;
        $gagal = 0;

        $idResi = MuatanItem::where('muatan_id', $muatan->id)->pluck('pengiriman_id')->all();

        foreach (Pengiriman::whereIn('id', $idResi)->get() as $pengiriman) {
            $statusSekarang = $pengiriman->status;

            // Sudah di status ini — hitung sebagai berhasil, jangan diubah ulang.
            if ((int) $pengiriman->status_id === (int) $status->id) {
                $berhasil++;

                continue;
            }

            // Diterima bersifat final: tidak boleh dipindah lagi.
            if ($statusSekarang && $statusSekarang->is_final) {
                $gagal++;

                continue;
            }

            // Status mundur tidak diizinkan, kecuali ke "batal".
            if ($status->slug !== 'batal' && $statusSekarang && $status->urutan < $statusSekarang->urutan) {
                $gagal++;

                continue;
            }

            $pengiriman->updateStatus($status->id, $catatan, $userId);
            $berhasil++;
        }

        return ['berhasil' => $berhasil, 'gagal' => $gagal];
    }

    /**
     * Boleh menyelesaikan distribusi?
     *
     * Role distribusi, manager distribusi (yang memverifikasi), dan super-admin.
     * Kurir — walau punya izin muatan.scan dan shipments.update-status — TIDAK.
     */
    private function bolehMenyelesaikan(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (! $user->can(PermissionEnum::MUATAN_COMPLETE->value)) {
            return false;
        }

        // Sabuk pengaman kedua: kalau izin muatan.complete suatu saat ikut
        // diberikan ke kurir, perannya tetap menghalangi. Kurir mengantar barang;
        // yang menyatakan barang sudah diterima adalah pemeriksa, bukan pengantar.
        return ! $user->hasRole(RoleEnum::COURIER->value);
    }

    private function alasanTidakBolehSelesai(?User $user): ?string
    {
        if ($user === null || $this->bolehMenyelesaikan($user)) {
            return null;
        }

        if ($user->hasRole(RoleEnum::COURIER->value)) {
            return 'Kurir hanya boleh memindahkan status perjalanan. Menyelesaikan distribusi (status Diterima) adalah wewenang Distribusi dan Manager Distribusi.';
        }

        return 'Menyelesaikan distribusi adalah wewenang Distribusi dan Manager Distribusi.';
    }

    /**
     * Kurir hanya melihat muatannya sendiri.
     */
    private function hanyaMiliknya(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        // Pengawas tetap melihat semua; kurir tidak.
        return $user->hasRole(RoleEnum::COURIER->value);
    }

    private function pastikanBolehLihat(Request $request, Muatan $muatan): void
    {
        if ($this->hanyaMiliknya($request->user()) && (int) $muatan->kurir_id !== (int) $request->user()->id) {
            abort(403, 'Anda hanya boleh melihat muatan milik Anda sendiri.');
        }
    }

    /**
     * Kurir hanya boleh MENGUBAH muatannya sendiri.
     *
     * Wajib dipanggil di setiap aksi yang menyentuh isi muatan. Tanpa ini, kurir
     * yang menebak ID muatan kurir lain bisa memuat barang ke muatan orang lain
     * atau memindahkan statusnya lewat /admin/muatan/{id}/pindai — jalur yang
     * berbeda dari halaman scan khusus kurir.
     */
    private function pastikanBolehKelola(Request $request, Muatan $muatan): void
    {
        if ($this->hanyaMiliknya($request->user()) && (int) $muatan->kurir_id !== (int) $request->user()->id) {
            abort(403, 'Anda hanya boleh mengubah muatan milik Anda sendiri.');
        }
    }

    /**
     * Sebaran status untuk banyak muatan sekaligus.
     *
     * @param  array<int, int>  $idMuatan
     * @return array<int, array<string, int>>
     */
    private function sebaranUntuk(array $idMuatan): array
    {
        if ($idMuatan === []) {
            return [];
        }

        $baris = DB::table('muatan_items')
            ->join('pengiriman', 'pengiriman.id', '=', 'muatan_items.pengiriman_id')
            ->join('status_pengiriman', 'status_pengiriman.id', '=', 'pengiriman.status_id')
            ->whereIn('muatan_items.muatan_id', $idMuatan)
            ->groupBy('muatan_items.muatan_id', 'status_pengiriman.slug')
            ->selectRaw('muatan_items.muatan_id, status_pengiriman.slug, COUNT(*) as jumlah')
            ->get();

        $keluar = [];

        foreach ($baris as $b) {
            $keluar[(int) $b->muatan_id][$b->slug] = (int) $b->jumlah;
        }

        return $keluar;
    }

    /**
     * @param  array<string, int>  $sebaran
     */
    private function hitungSelesai(Muatan $muatan, array $sebaran): bool
    {
        if ($muatan->total_resi === 0) {
            return false;
        }

        return ($sebaran['diterima'] ?? 0) === $muatan->total_resi;
    }

    /**
     * Status yang boleh dipakai kurir untuk memindahkan barang (bukan final).
     *
     * @return array<int, array<string, mixed>>
     */
    private function statusPerjalanan(): array
    {
        $user = auth()->user();

        return PengirimanStageVisibility::visibleStatuses($user)
            ->reject(fn ($s) => $s->slug === 'diterima')
            ->map(fn ($s) => [
                'id' => $s->id,
                'nama' => $s->nama,
                'slug' => $s->slug,
                'warna' => $s->warna,
            ])
            ->values()
            ->all();
    }

    /**
     * Model status "Diterima" — dipakai untuk MENGUBAH status, bukan sekadar
     * ditampilkan. Pisahkan dari statusDiterima() yang mengembalikan array untuk
     * frontend; mencampur keduanya membuat array diserahkan ke kode yang
     * mengharapkan model.
     */
    private function modelStatusDiterima(): ?StatusPengiriman
    {
        return StatusPengiriman::where('slug', 'diterima')->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function statusDiterima(): ?array
    {
        $status = $this->modelStatusDiterima();

        if (! $status) {
            return null;
        }

        return [
            'id' => $status->id,
            'nama' => $status->nama,
            'slug' => $status->slug,
            'warna' => $status->warna,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function kurirList(): array
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', RoleEnum::COURIER->value))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
            ->all();
    }

    /**
     * Resi yang siap diantar dan belum masuk muatan mana pun.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resiBelumDimuat(): array
    {
        $idTerpakai = MuatanItem::pluck('pengiriman_id')->all();

        $slugSiap = ['selesai-packing', 'pengiriman'];

        return Pengiriman::query()
            ->whereNotIn('id', $idTerpakai)
            ->whereHas('status', fn ($q) => $q->whereIn('slug', $slugSiap))
            ->with(['status:id,nama,slug,warna', 'jenisQuran:id,nama_jenis'])
            ->orderByDesc('id')
            ->limit(300)
            ->get(['id', 'no_resi', 'nama_penerima', 'nama_lembaga', 'alamat_tujuan', 'jumlah_quran', 'status_id', 'jenis_quran_id'])
            ->map(fn (Pengiriman $p) => [
                'id' => $p->id,
                'no_resi' => $p->no_resi,
                'nama_penerima' => $p->nama_penerima,
                'nama_lembaga' => $p->nama_lembaga,
                'alamat_tujuan' => $p->alamat_tujuan,
                'jumlah_quran' => $p->jumlah_quran,
                'jenis' => $p->jenisQuran?->nama_jenis,
                'status' => $p->status?->nama,
            ])
            ->all();
    }

    /**
     * Bersihkan hasil pindai: QR berisi no_resi, tapi pemindai kadang
     * mengembalikan JSON atau teks berlebih.
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
     * Ambil kode kerdus dari isi kotak pindai.
     *
     * Kode kerdus bisa datang sebagai teks polos maupun JSON dari pemindai lama,
     * jadi keduanya diterima. Bentuknya KB-YYYYMMDD-USER-JENIS-NN.
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
