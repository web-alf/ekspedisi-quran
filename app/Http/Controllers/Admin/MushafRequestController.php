<?php

namespace App\Http\Controllers\Admin;

use App\Exports\MushafRequestExport;
use App\Exports\MushafRequestTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\MushafRequestImport;
use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\MushafRequest;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Services\Cache\DashboardCacheService;
use App\Services\QrCodeService;
use App\Support\PerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class MushafRequestController extends Controller
{
    protected $dashboardCache;

    public function __construct(
        DashboardCacheService $dashboardCache,
        private QrCodeService $qr,
    ) {
        $this->dashboardCache = $dashboardCache;
        $this->authorizeResource(MushafRequest::class, 'mushafRequest');
    }

    /**
     * Display a listing of mushaf requests
     */
    public function index(Request $request)
    {
        // OPTIMIZED: Use constrained eager loading to prevent N+1 queries
        $query = MushafRequest::with([
            'reviewer:id,name',
            'pengiriman:id,mushaf_request_id,no_resi,status_id',
        ])
            ->orderBy('created_at', 'desc');

        // Search functionality
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('no_request', 'like', '%'.$request->search.'%')
                    ->orWhere('nama_lembaga', 'like', '%'.$request->search.'%')
                    ->orWhere('nama_pengurus_1', 'like', '%'.$request->search.'%')
                    ->orWhere('nama_pengurus_2', 'like', '%'.$request->search.'%')
                    ->orWhere('alamat_lengkap', 'like', '%'.$request->search.'%')
                    ->orWhere('provinsi', 'like', '%'.$request->search.'%')
                    ->orWhere('kota_kabupaten', 'like', '%'.$request->search.'%')
                    ->orWhere('kecamatan', 'like', '%'.$request->search.'%')
                    ->orWhere('kelurahan_desa', 'like', '%'.$request->search.'%');
            });
        }

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('created_at', [
                $request->start_date.' 00:00:00',
                $request->end_date.' 23:59:59',
            ]);
        } elseif ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        } elseif ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $mushafRequests = $query->paginate(PerPage::resolve($request))->withQueryString();

        // Get map data for distribution visualization
        // ✅ FIX: Use same data source as landing page - only show completed requests
        // This ensures admin and public maps are synchronized
        $mapData = MushafRequest::where('status', 'completed')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id', 'nama_lembaga', 'provinsi', 'kota_kabupaten', 'kecamatan', 'kelurahan_desa',
                'kode_pos', 'latitude', 'longitude', 'status',
                'jumlah_mushaf', 'jumlah_iqra', 'jumlah_mushaf_a5', 'jumlah_mushaf_a6',
                'jumlah_mushaf_approved', 'jumlah_mushaf_a5_approved', 'jumlah_mushaf_a6_approved', 'jumlah_iqra_approved', 'kategori_lembaga')
            ->get()
            ->map(function ($item) {
                // Pakai accessor model: satu rumus untuk seluruh aplikasi
                return [
                    'id' => $item->id,
                    'nama_lembaga' => $item->nama_lembaga,
                    'provinsi' => $item->provinsi,
                    'kota_kabupaten' => $item->kota_kabupaten,
                    // Kecamatan dan kelurahan ikut dikirim: pin peta menampilkan
                    // detail sampai tingkat desa, bukan hanya kabupaten.
                    'kecamatan' => $item->kecamatan,
                    'kelurahan_desa' => $item->kelurahan_desa,
                    'kode_pos' => $item->kode_pos,
                    'lat' => (float) $item->latitude,
                    'lng' => (float) $item->longitude,
                    'status' => $item->status,
                    'jumlah_mushaf' => $item->approved_breakdown['total'],
                    'jumlah_mushaf_a5' => $item->approved_breakdown['a5'],
                    'jumlah_mushaf_a6' => $item->approved_breakdown['a6'],
                    'jumlah_iqra' => $item->approved_breakdown['iqra'],
                    'kategori' => $item->kategori_lembaga,
                    'nama_penerima' => $item->nama_lembaga, // Add for marker grouping
                ];
            });

        return Inertia::render('Admin/MushafRequest/Index', [
            'perPage' => PerPage::resolve($request),
            'perPageOptions' => PerPage::OPTIONS,
            'mushafRequests' => $mushafRequests,
            'filters' => $request->only(['search', 'status', 'start_date', 'end_date']),
            'mapData' => $mapData,
            // Dihitung di server dari SELURUH permintaan selesai. Kartunya dulu
            // menjumlahkan halaman yang sedang dibuka, sehingga selalu nol.
            'kategoriStats' => MushafRequest::statistikGrupKategori(),
            'stats' => [
                'total' => MushafRequest::count(),
                'pending' => MushafRequest::pending()->count(),
                'reviewed' => MushafRequest::where('status', 'reviewed')->count(),
                'approved' => MushafRequest::approved()->count(),
                'rejected' => MushafRequest::rejected()->count(),
                'processed' => MushafRequest::processed()->count(),
                'completed' => MushafRequest::completed()->count(),
            ],
        ]);
    }

    /**
     * Show the specified mushaf request
     */
    public function show(MushafRequest $mushafRequest)
    {
        $mushafRequest->load(['reviewer', 'pengiriman.donatur', 'pengiriman.jenisQuran']);

        // Ensure file URLs are available
        $mushafRequest->foto_santri_url = $mushafRequest->foto_santri_path ? asset('storage/'.$mushafRequest->foto_santri_path) : null;
        $mushafRequest->foto_lembaga_url = $mushafRequest->foto_lembaga_path ? asset('storage/'.$mushafRequest->foto_lembaga_path) : null;
        $mushafRequest->file_nama_santri_url = $mushafRequest->file_nama_santri_path ? asset('storage/'.$mushafRequest->file_nama_santri_path) : null;

        // Get donatur list for processing modal
        $donaturList = Donatur::select('id', 'nama_donatur', 'kode_donatur')
            ->orderBy('nama_donatur')
            ->get();

        // Ringkasan SELURUH kiriman permintaan ini.
        //
        // Sejak satu mushaf = satu resi (15/10/2026), permintaan 80 eks dikirim sebagai 80
        // kiriman. Menampilkan hanya "kiriman pertama" membuat orang mengira jumlahnya salah —
        // inilah yang dulu membingungkan: satu resi berisi 80.
        $pengirimanRingkas = [
            'jumlah' => $mushafRequest->pengirimans()->count(),
            'mushaf' => (int) $mushafRequest->pengirimans()->sum('jumlah_quran'),
            'per_status' => $mushafRequest->pengirimans()
                ->join('status_pengiriman as sp', 'pengiriman.status_id', '=', 'sp.id')
                ->selectRaw('sp.slug as slug, sp.nama as nama, COUNT(*) as jumlah')
                ->groupBy('sp.slug', 'sp.nama')
                ->orderByDesc('jumlah')
                ->get()
                ->map(fn ($baris) => [
                    'slug' => $baris->slug,
                    'nama' => $baris->nama,
                    'jumlah' => (int) $baris->jumlah,
                ])
                ->all(),
        ];

        return Inertia::render('Admin/MushafRequest/Show', [
            'mushafRequest' => $mushafRequest,
            'donaturList' => $donaturList,
            'pengirimanRingkas' => $pengirimanRingkas,
        ]);
    }

    /**
     * Update the status of mushaf request
     */
    public function updateStatus(Request $request, MushafRequest $mushafRequest)
    {
        $this->authorize('update', $mushafRequest);

        $request->validate([
            'status' => ['required', 'in:pending,reviewed,approved,rejected,processed,completed'],
            'catatan_admin' => ['nullable', 'string', 'max:1000'],
        ]);

        // `processed` BUKAN status yang boleh dipilih langsung oleh admin: status
        // itu hanya boleh lahir dari `processToShipment`, yang sekalian membuat
        // kirimannya. Peta status di halaman detail dulu menawarkan "Sudah
        // Diproses" sebagai langkah lanjutan setelah "Disetujui"; backend hanya
        // menulis statusnya tanpa membuat kiriman, sementara `processToShipment`
        // menolak apa pun yang statusnya bukan `approved`. Sekali dipilih,
        // permintaan itu terperangkap: berlabel "Sudah Diproses" padahal tidak ada
        // kiriman, dan tidak bisa diproses lagi selamanya.
        if ($request->status === 'processed') {
            return back()->withErrors([
                'error' => 'Status "Sudah Diproses" tidak bisa dipilih langsung. Pakai tombol "Proses ke Pengiriman" agar kirimannya sekalian dibuat.',
            ]);
        }

        try {
            $oldStatus = $mushafRequest->status;

            switch ($request->status) {
                case 'approved':
                    $result = $mushafRequest->approve(auth()->id(), $request->catatan_admin);
                    $message = "Permintaan {$mushafRequest->no_request} telah disetujui";
                    $notificationMessage = "Permintaan mushaf Anda dengan nomor {$mushafRequest->no_request} telah disetujui.";
                    break;

                case 'rejected':
                    if (empty($request->catatan_admin)) {
                        throw new \Exception('Catatan wajib diisi untuk penolakan');
                    }
                    $result = $mushafRequest->reject(auth()->id(), $request->catatan_admin);
                    $message = "Permintaan {$mushafRequest->no_request} telah ditolak";
                    $notificationMessage = "Permintaan mushaf Anda dengan nomor {$mushafRequest->no_request} telah ditolak. Catatan: {$request->catatan_admin}";
                    break;

                case 'completed':
                    $result = $mushafRequest->update([
                        'status' => 'completed',
                        'catatan_admin' => $request->catatan_admin,
                        'reviewed_by' => auth()->id(),
                    ]);
                    $message = "Permintaan {$mushafRequest->no_request} telah diselesaikan";
                    $notificationMessage = "Permintaan mushaf Anda dengan nomor {$mushafRequest->no_request} telah selesai diproses dan mushaf telah sampai di tujuan.";
                    break;

                default:
                    $result = $mushafRequest->update([
                        'status' => $request->status,
                        'catatan_admin' => $request->catatan_admin,
                        'reviewed_by' => auth()->id(),
                    ]);
                    $message = "Status permintaan {$mushafRequest->no_request} telah diupdate";
                    $notificationMessage = "Status permintaan mushaf Anda dengan nomor {$mushafRequest->no_request} telah diupdate menjadi {$mushafRequest->status_label}.";
            }

            // Refresh model dari database dan reload relationships
            $mushafRequest->refresh();
            $mushafRequest->load(['reviewer', 'pengiriman.donatur', 'pengiriman.jenisQuran']);

            // Note: WhatsApp notification feature removed as per requirements

            // ✅ FIX: Invalidate dashboard cache after status update
            $this->dashboardCache->invalidate(['dashboard', 'stats', 'activities']);

            // Return dengan data yang fresh untuk halaman admin
            return redirect()->route('admin.mushaf-requests.show', $mushafRequest)
                ->with('success', $message);

        } catch (\Exception $e) {
            \Log::error('updateStatus error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'error' => 'Gagal update status: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Process approved mushaf request to pengiriman
     */
    public function processToShipment(Request $request, MushafRequest $mushafRequest)
    {
        $this->authorize('process', $mushafRequest);

        if ($mushafRequest->status !== 'approved') {
            return back()->withErrors(['error' => 'Hanya permintaan yang disetujui yang bisa diproses']);
        }

        // Permintaan yang SUDAH punya kiriman tidak boleh diproses lagi: jalur ini
        // membuat kiriman baru bila tidak menemukan kiriman kosong milik donatur
        // terpilih, lalu memindahkan tautannya ke kiriman baru itu — kiriman lama
        // kehilangan asal permintaannya. Keadaan "approved tetapi sudah punya
        // kiriman" nyata ada di produksi, jadi penjaganya dipasang di sini juga,
        // bukan hanya dengan menyembunyikan tombolnya.
        if ($mushafRequest->pengiriman_id) {
            return back()->withErrors([
                'error' => 'Permintaan ini sudah punya kiriman. Buka kirimannya untuk melanjutkan, jangan diproses ulang.',
            ]);
        }

        // Jumlah yang akan dikirim dibaca dari `approved_breakdown`, yang otomatis
        // memakai jumlah pengajuan selama jumlah disetujui belum pernah ditetapkan.
        // Penjaga lama berbunyi `has_quantity_change && is_null(jumlah_mushaf_approved)`
        // dan selalu bernilai benar — kolom pecahan `*_approved` NOT NULL default 0
        // membuat has_quantity_change selalu true — sehingga SELURUH permintaan
        // ditolak diproses ke pengiriman.
        if ($mushafRequest->approved_breakdown['total'] < 1) {
            return back()->withErrors([
                'error' => 'Jumlah mushaf yang disetujui masih 0, tidak ada yang bisa dikirim. Silakan edit jumlah mushaf terlebih dahulu.',
            ]);
        }

        $request->validate([
            'donatur_id' => ['required', 'exists:donatur,id'],
            'tanggal_wakaf' => ['required', 'date'],
        ]);

        try {
            \DB::beginTransaction();

            // Penjaga di atas membaca instance hasil route binding tanpa kunci; dua
            // klik "Proses" (atau dua CS bersamaan) sama-sama lolos dan masing-masing
            // membuat kiriman. Baris permintaan dikunci lalu dicek ulang di sini agar
            // proses kedua menunggu, melihat `pengiriman_id` yang sudah terisi, dan ditolak.
            $terkunci = MushafRequest::whereKey($mushafRequest->getKey())->lockForUpdate()->first();

            if (! $terkunci || $terkunci->status !== 'approved' || $terkunci->pengiriman_id) {
                \DB::rollBack();

                return back()->withErrors([
                    'error' => 'Permintaan ini baru saja diproses oleh pengguna lain. Muat ulang halaman untuk melihat kirimannya.',
                ]);
            }

            $mushafRequest = $terkunci;

            // ✅ FIX: Pakai satu sumber angka (approved_breakdown) agar jumlah yang
            // masuk ke pengiriman sama dengan yang tampil di halaman detail/daftar.
            $approved = $mushafRequest->approved_breakdown;
            $totalApproved = $approved['total'];

            // SATU MUSHAF = SATU RESI = SATU QR (keputusan pemilik sistem, 15/10/2026).
            //
            // Dulu satu permintaan melahirkan SATU resi berisi SELURUH jumlah yang disetujui
            // (80, 200, sampai 500 eks), dan resi kosong milik donatur ditimpa jumlahnya
            // menjadi angka itu. Akibatnya satu QR mewakili ratusan mushaf: label per mushaf
            // mustahil dibuat, alur gudang "pindai tiap mushaf" tidak bisa dipakai, dan
            // identitas tiap mushaf hilang.
            //
            // Sekarang permintaan dipecah per EKS dan per JENIS: 20 A5 + 60 A6 menjadi 20 resi
            // A5 dan 60 resi A6, masing-masing 1 eks, masing-masing ber-QR sendiri.
            $kebutuhan = array_filter([
                'A5' => (int) $approved['a5'],
                'A6' => (int) $approved['a6'],
                'IQRA' => (int) $approved['iqra'],
            ], fn (int $jumlah) => $jumlah > 0);

            // Kode jenis di master produksi: `A5`, `A6`, `IQRO`. Padanan `IQRA` ikut diterima
            // karena nama itu dipakai di beberapa dokumen lama — kalau tidak, permintaan Iqro
            // gagal diproses hanya karena beda satu huruf pada kode.
            $kodeJenisMaster = [
                'A5' => ['A5'],
                'A6' => ['A6'],
                'IQRA' => ['IQRO', 'IQRA'],
            ];

            if ($kebutuhan === []) {
                \DB::rollBack();

                return back()->withErrors(['error' => 'Tidak ada jumlah per jenis yang bisa dikirim.']);
            }

            // ✅ FIX: Build catatan with quantity adjustment info
            $originalTotal = $mushafRequest->jumlah_mushaf + $mushafRequest->jumlah_iqra;
            $catatan = "Dari permintaan mushaf: {$mushafRequest->no_request} - {$mushafRequest->nama_lembaga}";
            if ($mushafRequest->has_quantity_change) {
                $catatan .= " (Jumlah disesuaikan dari {$originalTotal} menjadi {$totalApproved})";
            }

            $isiKiriman = [
                // Arah balik harus ditulis. Dua jalur lain (set alamat massal dan pemindai
                // kerdus gudang) menulis `mushaf_request_id` pada kiriman; kalau jalur ini
                // tidak, kiriman hasil halaman permintaan tidak bisa ditelusuri balik.
                'mushaf_request_id' => $mushafRequest->id,
                'tanggal_wakaf' => $request->tanggal_wakaf,
                'alamat_tujuan' => $mushafRequest->alamat_lengkap,
                'nama_penerima' => $mushafRequest->nama_pengurus_1,
                'nama_lembaga' => $mushafRequest->nama_lembaga,
                'no_hp_penerima' => $mushafRequest->whatsapp_pengurus_1,
                'catatan' => $catatan,
            ];

            $resi = collect();
            $dipakai = 0;

            foreach ($kebutuhan as $kodeJenis => $jumlah) {
                // Jenis dibaca dari KODE, bukan id yang di-hardcode: nomor id bergantung isi
                // tabel jenis_quran, dan di database yang di-seed ulang ID-nya bisa berbeda.
                $jenisQuranId = null;

                foreach ($kodeJenisMaster[$kodeJenis] ?? [$kodeJenis] as $kode) {
                    $jenisQuranId = JenisQuran::where('kode_jenis', $kode)->value('id');

                    if ($jenisQuranId) {
                        break;
                    }
                }

                if (! $jenisQuranId) {
                    throw new \Exception("Jenis Quran {$kodeJenis} tidak ada di master jenis_quran.");
                }

                // Pakai DULU resi mushaf milik donatur yang belum punya alamat & penerima:
                // itu mushaf wakafnya yang memang sedang menunggu tujuan. Jumlah eksnya
                // TIDAK ditimpa — satu resi tetap satu eks, supaya identitas wakafnya utuh.
                // Dikunci (lockForUpdate) agar dua proses paralel tidak memakai resi yang sama.
                $kosong = Pengiriman::query()
                    ->where('donatur_id', $request->donatur_id)
                    ->where('jenis_quran_id', $jenisQuranId)
                    ->where('jumlah_quran', 1)
                    ->whereNull('alamat_tujuan')
                    ->whereNull('nama_penerima')
                    ->orderBy('id')
                    ->limit($jumlah)
                    ->lockForUpdate()
                    ->get();

                foreach ($kosong as $p) {
                    // Cek ulang setelah kunci diperoleh: proses lain mungkin sudah mengisi
                    // alamatnya di sela-sela pemilihan baris tadi.
                    if ($p->fresh()->alamat_tujuan) {
                        continue;
                    }

                    $p->update($isiKiriman);
                    $resi->push($p);
                    $dipakai++;
                }

                // Sisanya dibuat baru — tetap satu mushaf satu resi.
                $kurang = $jumlah - $kosong->count();

                for ($i = 0; $i < $kurang; $i++) {
                    $resi->push(Pengiriman::create($isiKiriman + [
                        'donatur_id' => $request->donatur_id,
                        'jenis_quran_id' => $jenisQuranId,
                        'jumlah_quran' => 1,
                        'status_id' => StatusPengiriman::getDefaultStatusId(),
                        'created_by' => auth()->id(),
                    ]));
                }
            }

            if ($resi->isEmpty()) {
                throw new \Exception('Tidak ada kiriman yang terbentuk dari permintaan ini.');
            }

            /** @var Pengiriman $utama Kiriman pertama — penunjuk lama `mushaf_requests.pengiriman_id`. */
            $utama = $resi->first();

            // Mark request as processed
            $mushafRequest->markAsProcessed($utama->id, auth()->id());

            \DB::commit();

            // QR dibuat SETELAH commit: berkas tidak bisa dibatalkan oleh rollback, jadi
            // pembuatannya tidak boleh berada di dalam transaksi. Kalau gagal, resinya tetap
            // ada dan QR-nya bisa dilengkapi lewat `php artisan qr:generate-missing`.
            $qrBaru = $this->siapkanQr($resi);

            // ✅ FIX: Invalidate dashboard cache after processing to shipment
            $this->dashboardCache->invalidate(['dashboard', 'stats', 'activities']);

            return redirect()->route('admin.mushaf-requests.show', $mushafRequest)
                ->with('success', sprintf(
                    'Permintaan %s diproses menjadi %d kiriman (%d mushaf — satu mushaf satu QR). QR dibuat: %d.%s',
                    $mushafRequest->no_request,
                    $resi->count(),
                    $totalApproved,
                    $qrBaru,
                    $dipakai > 0 ? " {$dipakai} kiriman memakai mushaf donatur yang sudah ada." : ''
                ));

        } catch (\Exception $e) {
            \DB::rollback();

            return back()->withErrors([
                'error' => 'Gagal memproses ke pengiriman: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Buat berkas QR untuk setiap kiriman yang belum punya.
     *
     * Satu resi = satu QR (isinya nomor resi), jadi satu permintaan 80 eks menghasilkan 80
     * berkas QR — masing-masing bisa dicetak menjadi label mushaf sendiri.
     *
     * Tidak pernah melempar: pembuatan berkas adalah langkah PELENGKAP. Kiriman yang gagal
     * dibuatkan QR-nya tetap sah, dan bisa dilengkapi kemudian lewat
     * `php artisan qr:generate-missing`.
     *
     * @param  Collection<int, Pengiriman>  $resi
     * @return int jumlah berkas QR yang BARU dibuat
     */
    private function siapkanQr(Collection $resi): int
    {
        $baru = 0;

        foreach ($resi as $kiriman) {
            try {
                if ($this->qr->sudahAda($kiriman)) {
                    continue;
                }

                $path = $this->qr->buatUntuk($kiriman);

                $kiriman->update([
                    'qr_code_path' => $path,
                    'qr_code_data' => $this->qr->dataUntuk($kiriman),
                ]);

                $baru++;
            } catch (\Throwable $e) {
                Log::warning('Gagal membuat QR kiriman hasil permintaan mushaf', [
                    'no_resi' => $kiriman->no_resi,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $baru;
    }

    /**
     * Get donatur list and approved mushaf requests for processing
     */
    public function getDonaturList()
    {
        $this->authorize('viewAny', MushafRequest::class);

        $donaturList = Donatur::select('id', 'nama_donatur', 'kode_donatur')
            ->orderBy('nama_donatur')
            ->get();

        // Get approved mushaf requests that haven't been processed yet
        $approvedMushafRequests = MushafRequest::approved()
            ->whereNull('pengiriman_id') // Only those not yet processed
            ->select('id', 'no_request', 'nama_lembaga', 'alamat_lengkap', 'nama_pengurus_1', 'whatsapp_pengurus_1')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'donatur_list' => $donaturList,
            'approved_mushaf_requests' => $approvedMushafRequests,
        ]);
    }

    /**
     * Bulk update status
     */
    public function bulkUpdateStatus(Request $request)
    {
        $this->authorize('update', MushafRequest::class);

        $request->validate([
            'request_ids' => ['required', 'array'],
            'request_ids.*' => ['exists:mushaf_requests,id'],
            'status' => ['required', 'in:pending,reviewed,approved,rejected,processed,completed'],
            'catatan_admin' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->status === 'rejected' && empty($request->catatan_admin)) {
            return back()->withErrors(['error' => 'Catatan wajib diisi untuk penolakan']);
        }

        try {
            \DB::beginTransaction();

            $updated = 0;
            $mushafRequests = MushafRequest::whereIn('id', $request->request_ids)->get();

            foreach ($mushafRequests as $mushafRequest) {
                if ($request->status === 'approved') {
                    $mushafRequest->approve(auth()->id(), $request->catatan_admin);
                } else {
                    $mushafRequest->reject(auth()->id(), $request->catatan_admin);
                }
                $updated++;
            }

            \DB::commit();

            // ✅ FIX: Invalidate dashboard cache after bulk update
            $this->dashboardCache->invalidate(['dashboard', 'stats', 'activities']);

            $statusLabel = $request->status === 'approved' ? 'disetujui' : 'ditolak';

            return back()->with('success', "{$updated} permintaan berhasil {$statusLabel}");

        } catch (\Exception $e) {
            \DB::rollback();

            return back()->withErrors([
                'error' => 'Gagal bulk update: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Export mushaf requests to Excel
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', MushafRequest::class);

        try {
            // Get filters from request
            $filters = $request->only(['search', 'status', 'start_date', 'end_date']);

            // Generate filename with timestamp and filters
            $filename = 'mushaf-requests-'.now()->format('Y-m-d-H-i-s');

            if (isset($filters['status']) && $filters['status']) {
                $filename .= '-'.$filters['status'];
            }

            if (isset($filters['start_date']) && $filters['start_date']) {
                $filename .= '-from-'.$filters['start_date'];
            }

            if (isset($filters['end_date']) && $filters['end_date']) {
                $filename .= '-to-'.$filters['end_date'];
            }

            $filename .= '.xlsx';

            // Export using MushafRequestExport class
            return Excel::download(new MushafRequestExport($filters), $filename);

        } catch (\Exception $e) {
            \Log::error('Export mushaf requests error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'error' => 'Gagal melakukan export: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Import mushaf requests from Excel
     */
    public function import(Request $request)
    {
        $this->authorize('create', MushafRequest::class);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // Max 10MB
        ]);

        try {
            $import = new MushafRequestImport;
            Excel::import($import, $request->file('file'));

            $results = $import->getResults();

            // Check if file was empty
            if ($results['success_count'] === 0 && $results['error_count'] === 0) {
                return back()->with('error', 'File Excel kosong atau tidak memiliki data. Pastikan file Excel berisi data sesuai template (minimal 1 baris data setelah header).');
            }

            if ($import->hasErrors()) {
                // Sertakan berapa yang berhasil, supaya pengguna tahu berkasnya
                // TIDAK terimport seluruhnya. Tanpa angka itu, pesan "selesai
                // dengan N gagal" masih menyisakan tebak-tebakan: berapa yang
                // sebenarnya masuk?
                $pesan = $results['success_count'] > 0
                    ? "Import selesai: {$results['success_count']} data berhasil, {$results['error_count']} data GAGAL dan tidak ikut masuk"
                    : "Tidak ada data yang masuk: {$results['error_count']} baris gagal";

                return back()->with([
                    'warning' => $pesan,
                    'import_errors' => $results['errors'],
                ]);
            }

            return back()->with('success', "Berhasil import {$results['success_count']} data mushaf request");

        } catch (\Exception $e) {
            return back()->with('error', 'Error saat import: '.$e->getMessage());
        }
    }

    /**
     * Download Excel import template
     */
    public function downloadTemplate()
    {
        $this->authorize('create', MushafRequest::class);

        try {
            $filename = 'mushaf-request-import-template-'.now()->format('Y-m-d').'.xlsx';

            return Excel::download(new MushafRequestTemplateExport, $filename);

        } catch (\Exception $e) {
            return back()->with('error', 'Error saat download template: '.$e->getMessage());
        }
    }

    /**
     * Update approved quantities for mushaf request
     */
    public function updateQuantities(Request $request, MushafRequest $mushafRequest)
    {
        $this->authorize('update', $mushafRequest);

        $request->validate([
            'jumlah_mushaf_a5_approved' => 'nullable|integer|min:0',
            'jumlah_mushaf_a6_approved' => 'nullable|integer|min:0',
            'jumlah_iqra_approved' => 'nullable|integer|min:0',
            'catatan_perubahan_jumlah' => 'nullable|string|max:1000',
        ]);

        try {
            $originalTotal = $mushafRequest->total_mushaf;

            // Kolom `jumlah_mushaf_approved` TIDAK diambil dari request: nilainya
            // dijaga model (hook `saving`) sebagai penjumlahan A5 + A6 + IQRA.
            // Sebelumnya kolom ini diisi apa adanya dari payload frontend yang
            // berisi total pengajuan, sehingga nilai yang tampil tidak pernah
            // berubah walau pecahannya diedit.
            $mushafRequest->update($request->only([
                'jumlah_mushaf_a5_approved',
                'jumlah_mushaf_a6_approved',
                'jumlah_iqra_approved',
                'catatan_perubahan_jumlah',
            ]));

            $newTotal = $mushafRequest->fresh()->total_mushaf_approved;
            $difference = $newTotal - $originalTotal;
            $changeText = $difference > 0 ? "ditambah $difference" : 'dikurangi '.abs($difference);

            // ✅ FIX: Invalidate dashboard cache after quantity update
            $this->dashboardCache->invalidate(['dashboard', 'stats', 'activities']);

            return back()->with('success', "Jumlah mushaf yang disetujui berhasil diperbarui (dari {$originalTotal} menjadi {$newTotal}, {$changeText})");

        } catch (\Exception $e) {
            return back()->with('error', 'Error saat update jumlah: '.$e->getMessage());
        }
    }

    /**
     * Update mushaf request lembaga information
     */
    public function updateLembaga(Request $request, MushafRequest $mushafRequest)
    {
        $this->authorize('update', $mushafRequest);

        $request->validate([
            'nama_lembaga' => 'required|string|max:255',
            'kategori_lembaga' => 'required|string|max:255',
            'alamat_lengkap' => 'required|string',
            'provinsi' => 'nullable|string|max:255',
            'provinsi_id' => 'nullable|string|max:20',
            'kota_kabupaten' => 'nullable|string|max:255',
            'kota_kabupaten_id' => 'nullable|string|max:20',
            'kecamatan' => 'nullable|string|max:255',
            'kecamatan_id' => 'nullable|string|max:20',
            'kelurahan_desa' => 'nullable|string|max:255',
            'kelurahan_desa_id' => 'nullable|string|max:20',
            'kode_pos' => 'nullable|string|max:10',
            'alamat_detail' => 'nullable|string|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'urgensi_request' => 'required|string',
            'sumber_info' => 'nullable|string|max:255',
        ]);

        try {
            $data = $request->only([
                'nama_lembaga',
                'kategori_lembaga',
                'alamat_lengkap',
                'provinsi',
                'provinsi_id',
                'kota_kabupaten',
                'kota_kabupaten_id',
                'kecamatan',
                'kecamatan_id',
                'kelurahan_desa',
                'kelurahan_desa_id',
                'kode_pos',
                'alamat_detail',
                'latitude',
                'longitude',
                'urgensi_request',
                'sumber_info',
            ]);

            $mushafRequest->update($data);

            return back()->with('success', 'Informasi lembaga berhasil diperbarui');

        } catch (\Exception $e) {
            return back()->with('error', 'Error saat update informasi lembaga: '.$e->getMessage());
        }
    }

    /**
     * Update mushaf request kontak information
     */
    public function updateKontak(Request $request, MushafRequest $mushafRequest)
    {
        $this->authorize('update', $mushafRequest);

        $request->validate([
            'nama_pengurus_1' => 'required|string|max:255',
            'jabatan_pengurus_1' => 'required|string|max:255',
            'whatsapp_pengurus_1' => 'required|string|max:20',
            'nama_pengurus_2' => 'nullable|string|max:255',
            'jabatan_pengurus_2' => 'nullable|string|max:255',
            'whatsapp_pengurus_2' => 'nullable|string|max:20',
        ]);

        try {
            $data = $request->only([
                'nama_pengurus_1',
                'jabatan_pengurus_1',
                'whatsapp_pengurus_1',
                'nama_pengurus_2',
                'jabatan_pengurus_2',
                'whatsapp_pengurus_2',
            ]);

            $mushafRequest->update($data);

            return back()->with('success', 'Informasi kontak berhasil diperbarui');

        } catch (\Exception $e) {
            return back()->with('error', 'Error saat update informasi kontak: '.$e->getMessage());
        }
    }

    /**
     * Update mushaf request file uploads
     */
    public function updateFiles(Request $request, MushafRequest $mushafRequest)
    {
        $this->authorize('update', $mushafRequest);

        $request->validate([
            'foto_santri' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'foto_lembaga' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'file_nama_santri' => 'nullable|file|mimes:pdf,xlsx,xls,doc,docx|max:5120',
            'delete_foto_santri' => 'nullable',
            'delete_foto_lembaga' => 'nullable',
            'delete_file_nama_santri' => 'nullable',
        ]);

        try {
            $data = [];

            // Handle file deletions
            if ($request->delete_foto_santri && $mushafRequest->foto_santri_path) {
                \Storage::disk('public')->delete($mushafRequest->foto_santri_path);
                $data['foto_santri_path'] = null;
            }

            if ($request->delete_foto_lembaga && $mushafRequest->foto_lembaga_path) {
                \Storage::disk('public')->delete($mushafRequest->foto_lembaga_path);
                $data['foto_lembaga_path'] = null;
            }

            if ($request->delete_file_nama_santri && $mushafRequest->file_nama_santri_path) {
                \Storage::disk('public')->delete($mushafRequest->file_nama_santri_path);
                $data['file_nama_santri_path'] = null;
            }

            // Handle file uploads
            if ($request->hasFile('foto_santri')) {
                // Delete old file if exists
                if ($mushafRequest->foto_santri_path) {
                    \Storage::disk('public')->delete($mushafRequest->foto_santri_path);
                }
                $path = $request->file('foto_santri')->store('mushaf-requests/foto-santri', 'public');
                $data['foto_santri_path'] = $path;
            }

            if ($request->hasFile('foto_lembaga')) {
                // Delete old file if exists
                if ($mushafRequest->foto_lembaga_path) {
                    \Storage::disk('public')->delete($mushafRequest->foto_lembaga_path);
                }
                $path = $request->file('foto_lembaga')->store('mushaf-requests/foto-lembaga', 'public');
                $data['foto_lembaga_path'] = $path;
            }

            if ($request->hasFile('file_nama_santri')) {
                // Delete old file if exists
                if ($mushafRequest->file_nama_santri_path) {
                    \Storage::disk('public')->delete($mushafRequest->file_nama_santri_path);
                }
                $path = $request->file('file_nama_santri')->store('mushaf-requests/file-nama-santri', 'public');
                $data['file_nama_santri_path'] = $path;
            }

            if (! empty($data)) {
                $mushafRequest->update($data);
            }

            return back()->with('success', 'File lampiran berhasil diperbarui');

        } catch (\Exception $e) {
            return back()->with('error', 'Error saat update file: '.$e->getMessage());
        }
    }

    /**
     * Delete mushaf request (allowed for all status except completed)
     */
    public function destroy(MushafRequest $mushafRequest)
    {
        // Prevent deletion of completed requests
        if ($mushafRequest->status === 'completed') {
            return back()->with('error', 'Tidak dapat menghapus request yang sudah selesai (completed). Data ini adalah historical record.');
        }

        try {
            // Delete associated files if exists
            if ($mushafRequest->foto_santri_path) {
                \Storage::disk('public')->delete($mushafRequest->foto_santri_path);
            }
            if ($mushafRequest->foto_lembaga_path) {
                \Storage::disk('public')->delete($mushafRequest->foto_lembaga_path);
            }
            if ($mushafRequest->file_nama_santri_path) {
                \Storage::disk('public')->delete($mushafRequest->file_nama_santri_path);
            }

            $no_request = $mushafRequest->no_request;
            $mushafRequest->delete();

            // ✅ FIX: Invalidate dashboard cache after deletion
            $this->dashboardCache->invalidate(['dashboard', 'stats', 'activities']);

            return back()->with('success', "Request {$no_request} berhasil dihapus");

        } catch (\Exception $e) {
            return back()->with('error', 'Error saat menghapus request: '.$e->getMessage());
        }
    }
}
