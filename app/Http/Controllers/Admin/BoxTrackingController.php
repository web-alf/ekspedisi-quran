<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\User;
use App\Support\BoxUnits;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BoxTrackingController extends Controller
{
    /**
     * Display list of all boxes with tracking info
     */
    public function index(Request $request)
    {
        // OPTIMIZED: Use constrained eager loading to prevent N+1 queries
        $query = PackingBox::with([
            'dailyPackingTask:id,user_id,tanggal_tugas',
            'dailyPackingTask.user:id,name',
            'jenisQuran:id,nama_jenis,kode_jenis,default_capacity',
            'packingItems' => function ($query) {
                $query->select('id', 'packing_box_id', 'pengiriman_id', 'urutan_dalam_box')
                    ->with([
                        'pengiriman:id,no_resi,donatur_id,jenis_quran_id,wakaf_item_id',
                        'pengiriman.donatur:id,nama_donatur',
                        'pengiriman.jenisQuran:id,nama_jenis',
                        'pengiriman.wakafItem:id,wakif_name',
                    ]);
            },
        ]);

        // Filter by search (kode kerdus, user name, jenis quran)
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('kode_kerdus', 'like', '%'.$request->search.'%')
                    ->orWhereHas('dailyPackingTask.user', function ($uq) use ($request) {
                        $uq->where('name', 'like', '%'.$request->search.'%');
                    })
                    ->orWhereHas('jenisQuran', function ($jq) use ($request) {
                        $jq->where('nama_jenis', 'like', '%'.$request->search.'%');
                    });
            });
        }

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter khusus: hanya kerdus yang SUDAH SELESAI PACKING (tersegel).
        // Dipakai halaman "Daftar Quran Selesai Packing" — daftar mushaf yang
        // benar-benar sudah masuk kerdus dan siap distribusi.
        if ($request->boolean('selesai_packing')) {
            $query->where('status', PackingBox::STATUS_SEALED);
        }

        // Filter by jenis quran
        if ($request->jenis_quran_id) {
            $query->where('jenis_quran_id', $request->jenis_quran_id);
        }

        // Filter by user
        if ($request->user_id) {
            $query->whereHas('dailyPackingTask', function ($q) use ($request) {
                $q->where('user_id', $request->user_id);
            });
        }

        // Filter by date range
        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $boxes = $query->orderBy('created_at', 'desc')->paginate(20);

        // Transform data for frontend
        $boxesData = $boxes->through(function ($box) {
            $statusInfo = $box->getStatusInfo();

            // Isi 1 doz (= 1 kerdus penuh) untuk jenis ini. Kapasitas kerdus
            // adalah sumber pertamanya; kalau kosong, jatuh ke pengaturan jenis.
            $pcsPerDoz = (int) ($box->kapasitas ?: ($box->jenisQuran?->getDefaultCapacity() ?? 0));

            return [
                'id' => $box->id,
                'kode_kerdus' => $box->kode_kerdus,
                'status' => $box->status,
                'status_info' => $statusInfo,
                'jenis_quran' => $box->jenisQuran->nama_jenis ?? 'Belum ditentukan',
                'kapasitas' => $box->kapasitas,
                'terisi' => $box->jumlah_terisi,
                'progress_percentage' => $box->progress_percentage,
                'user_name' => $box->dailyPackingTask->user->name ?? 'N/A',
                'seal_code' => $box->seal_code,
                'sealed_at' => $box->sealed_at?->format('d/m/Y H:i'),
                'created_at' => $box->created_at->format('d/m/Y H:i'),
                'item_count' => $box->packingItems->count(),
                // Satuan isi kerdus (pcs / doz). 1 doz = 1 kerdus penuh, dan
                // isinya berbeda per jenis (A5=20, A6=40, IQRO=160), jadi
                // pembaginya diambil dari kapasitas kerdus itu sendiri.
                'satuan' => BoxUnits::breakdown((int) $box->jumlah_terisi, $pcsPerDoz),
                'satuan_kapasitas' => BoxUnits::breakdown((int) $box->kapasitas, $pcsPerDoz),
                'satuan_keterangan' => BoxUnits::explanation($pcsPerDoz, $box->jenisQuran->kode_jenis ?? null),
                // QR kerdus sudah dipakai gudang untuk operasi massal; ditampilkan
                // di daftar supaya bisa langsung dipindai tanpa membuka detail.
                'qr_code_base64' => $this->safeBoxQr($box),
            ];
        });

        // Get filter options
        $jenisQuranList = JenisQuran::orderBy('nama_jenis')->get(['id', 'nama_jenis']);
        $warehouseUsers = User::permission('warehouse.dashboard')
            ->where('is_active', true)
            ->get(['id', 'name']);

        // Stats
        $stats = [
            'total_boxes' => PackingBox::count(),
            'empty_boxes' => PackingBox::where('status', 'empty')->count(),
            'filling_boxes' => PackingBox::where('status', 'filling')->count(),
            'full_boxes' => PackingBox::where('status', 'full')->count(),
            'sealed_boxes' => PackingBox::where('status', 'sealed')->count(),
            'total_items_packed' => PackingBox::sum('jumlah_terisi'),
            // Ringkasan "selesai packing" dalam satuan: halaman ini dipakai gudang
            // untuk menghitung isi gudang, dan mereka menghitung dalam doz.
            'sealed_pcs' => (int) PackingBox::where('status', PackingBox::STATUS_SEALED)->sum('jumlah_terisi'),
        ];

        // Ringkasan "selesai packing" tidak bisa diringkas jadi satu angka doz:
        // isi 1 doz berbeda per jenis (A5=20, A6=40, IQRO=160), jadi 100 keping
        // A5 dan 100 keping A6 sama-sama "100 pcs" tapi jumlah doz-nya 5 vs 2,5.
        // Karena itu dirinci per jenis — itulah bentuk yang bisa dipakai gudang.
        $stats['sealed_per_jenis'] = PackingBox::where('status', PackingBox::STATUS_SEALED)
            ->selectRaw('jenis_quran_id, kapasitas, COUNT(*) as jumlah_kerdus, SUM(jumlah_terisi) as total_pcs')
            ->groupBy('jenis_quran_id', 'kapasitas')
            ->with('jenisQuran:id,nama_jenis,kode_jenis')
            ->get()
            ->map(function ($row) {
                $pcsPerDoz = (int) ($row->kapasitas ?: ($row->jenisQuran?->getDefaultCapacity() ?? 0));

                return [
                    'jenis' => $row->jenisQuran->nama_jenis ?? 'Belum ditentukan',
                    'kode_jenis' => $row->jenisQuran->kode_jenis ?? null,
                    'jumlah_kerdus' => (int) $row->jumlah_kerdus,
                    'satuan' => BoxUnits::breakdown((int) $row->total_pcs, $pcsPerDoz),
                    'keterangan' => BoxUnits::explanation($pcsPerDoz, $row->jenisQuran->kode_jenis ?? null),
                ];
            })
            ->values();

        return Inertia::render('Admin/BoxTracking/Index', [
            'boxes' => $boxesData,
            'filters' => $request->only(['search', 'status', 'jenis_quran_id', 'user_id', 'start_date', 'end_date', 'selesai_packing']),
            'jenisQuranList' => $jenisQuranList,
            'warehouseUsers' => $warehouseUsers,
            'stats' => $stats,
        ]);
    }

    /**
     * Ambil QR kerdus tanpa membuat halaman daftar ikut gagal.
     *
     * Pembuatan QR PNG bergantung pada ekstensi PHP; di server yang tidak punya
     * `imagick` (hanya `gd`) library-nya melempar RuntimeException. Sebelum ini
     * kegagalan itu hanya muncul saat mencetak label, tapi sejak kolom QR
     * ditampilkan di daftar, satu kerdus bisa membuat SELURUH halaman 500.
     * Karena itu kegagalan QR tidak boleh menjatuhkan halaman.
     */
    private function safeBoxQr(PackingBox $box): ?string
    {
        try {
            return $box->getBoxQRBase64();
        } catch (\Throwable $e) {
            logger()->error('Gagal membuat QR kerdus', [
                'kode_kerdus' => $box->kode_kerdus,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Show detailed box content
     */
    public function show(PackingBox $box)
    {
        $box->load([
            'dailyPackingTask.user',
            'jenisQuran',
            'packingItems.pengiriman.donatur',
            'packingItems.pengiriman.jenisQuran',
            'packingItems.pengiriman.wakafItem',
            'packingItems.packedByUser',
        ]);

        $contentSummary = $box->getContentSummary();
        $statusInfo = $box->getStatusInfo();

        return Inertia::render('Admin/BoxTracking/Show', [
            'box' => array_merge($contentSummary['box_info'], [
                'id' => $box->id,
                'status_info' => $statusInfo,
                'user_name' => $box->dailyPackingTask->user->name ?? 'N/A',
                'task_date' => $box->dailyPackingTask->tanggal_tugas->format('d/m/Y') ?? 'N/A',
                'qr_code_base64' => $this->safeBoxQr($box),
                'qr_data' => $box->getBoxQRData(),
                'item_count' => $box->packingItems->count(),
            ]),
            'items' => $contentSummary['items'],
            'jenis_quran' => $contentSummary['jenis_quran'],
        ]);
    }

    /**
     * Search box by code
     */
    public function searchByCode(Request $request)
    {
        $request->validate([
            'kode_kerdus' => 'required|string',
        ]);

        $box = PackingBox::where('kode_kerdus', $request->kode_kerdus)->first();

        if (! $box) {
            return response()->json([
                'success' => false,
                'message' => 'Kerdus dengan kode tersebut tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $box->id,
                'kode_kerdus' => $box->kode_kerdus,
                'redirect_url' => route('admin.box-tracking.show', $box->id),
            ],
        ]);
    }

    /**
     * Get box analytics
     */
    public function analytics(Request $request)
    {
        $period = $request->get('period', '7'); // days
        $startDate = Carbon::now()->subDays($period);

        // Daily box creation stats
        $dailyStats = PackingBox::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Box status distribution
        $statusStats = PackingBox::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        // Jenis Quran distribution
        $jenisStats = PackingBox::with('jenisQuran')
            ->whereNotNull('jenis_quran_id')
            ->get()
            ->groupBy('jenis_quran_id')
            ->map(function ($boxes, $jenisId) {
                return [
                    'jenis_quran' => $boxes->first()->jenisQuran->nama_jenis,
                    'count' => $boxes->count(),
                    'total_items' => $boxes->sum('jumlah_terisi'),
                ];
            })
            ->values();

        // Top performers (users with most sealed boxes)
        $topPerformers = User::permission('warehouse.dashboard')
            ->withCount(['dailyPackingTasks as sealed_boxes_count' => function ($q) use ($startDate) {
                $q->whereHas('packingBoxes', function ($boxQ) use ($startDate) {
                    $boxQ->where('status', 'sealed')
                        ->where('created_at', '>=', $startDate);
                });
            }])
            ->orderBy('sealed_boxes_count', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'sealed_boxes_count']);

        return response()->json([
            'daily_stats' => $dailyStats,
            'status_stats' => $statusStats,
            'jenis_stats' => $jenisStats,
            'top_performers' => $topPerformers,
        ]);
    }
}
