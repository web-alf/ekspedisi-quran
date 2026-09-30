<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MushafRequest;
use App\Models\Setting;
use App\Models\StatusPengiriman;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MushafTrackingController extends Controller
{
    /**
     * Show the tracking search page
     */
    public function index()
    {
        // Get settings for consistent layout
        $settings = Setting::where('is_public', true)
            ->where('is_active', true)
            ->whereIn('group', ['landing', 'contact', 'social', 'general'])
            ->pluck('value', 'key');

        return Inertia::render('Public/MushafTracking/Index', [
            'pageTitle' => 'Cek Status Permintaan Mushaf',
            'pageDescription' => 'Lacak status permintaan mushaf Al-Qur\'an Anda dengan nomor permintaan',
            'settings' => $settings,
        ]);
    }

    /**
     * Track mushaf request by number
     */
    public function track($noRequest)
    {
        $mushafRequest = MushafRequest::where('no_request', $noRequest)
            ->with(['reviewer', 'pengiriman'])
            ->first();

        if (! $mushafRequest) {
            // Get settings for consistent layout
            $settings = Setting::where('is_public', true)
                ->where('is_active', true)
                ->whereIn('group', ['landing', 'contact', 'social', 'general'])
                ->pluck('value', 'key');

            return Inertia::render('Public/MushafTracking/Show', [
                'mushafRequest' => null,
                'no_request' => $noRequest,
                'statusHistory' => [],
                'settings' => $settings,
            ]);
        }

        // Create status history with consistent status labels
        $statusHistory = [
            [
                'status' => 'created',
                'status_label' => 'Permintaan Dibuat',
                'created_at' => $mushafRequest->created_at,
                'catatan' => 'Permintaan mushaf berhasil dibuat dan menunggu review.',
                'completed' => true,
            ],
        ];

        // Add current status to history with consistent labeling
        if ($mushafRequest->status !== 'pending') {
            $statusHistory[] = [
                'status' => $mushafRequest->status,
                'status_label' => $mushafRequest->status_label,
                'created_at' => $mushafRequest->updated_at,
                'catatan' => $mushafRequest->catatan_admin,
                'completed' => true,
                'current' => true,
            ];
        }

        // Hide sensitive file paths
        $mushafRequest->makeHidden(['foto_santri_path', 'foto_lembaga_path', 'file_nama_santri_path']);

        // Add quantity comparison data
        $quantityComparison = [
            'requested' => [
                'mushaf_a5' => $mushafRequest->jumlah_mushaf_a5,
                'mushaf_a6' => $mushafRequest->jumlah_mushaf_a6,
                'iqra' => $mushafRequest->jumlah_iqra,
                'total' => $mushafRequest->total_mushaf,
            ],
            'approved' => [
                'mushaf_a5' => $mushafRequest->approved_breakdown['a5'],
                'mushaf_a6' => $mushafRequest->approved_breakdown['a6'],
                'iqra' => $mushafRequest->approved_breakdown['iqra'],
                'total' => $mushafRequest->approved_breakdown['total'],
            ],
            'has_change' => $mushafRequest->has_quantity_change,
            'change_percentage' => $mushafRequest->quantity_change_percentage,
            'catatan_perubahan' => $mushafRequest->catatan_perubahan_jumlah,
        ];

        // Tahap pengiriman (packing → diterima). Data ini diambil dari pengiriman
        // yang tertaut, bukan dari kolom status permintaan: permintaan mushaf tidak
        // punya tahap packing/pengiriman sendiri, sehingga tanpa ini halaman publik
        // tidak pernah menampilkan kemajuan setelah "Sudah Diproses".
        $shippingStages = $this->buildShippingStages($mushafRequest);

        // Get settings for consistent layout
        $settings = Setting::where('is_public', true)
            ->where('is_active', true)
            ->whereIn('group', ['landing', 'contact', 'social', 'general'])
            ->pluck('value', 'key');

        return Inertia::render('Public/MushafTracking/Show', [
            'mushafRequest' => $mushafRequest,
            'no_request' => $noRequest,
            'statusHistory' => $statusHistory,
            'shippingStages' => $shippingStages,
            'quantityComparison' => $quantityComparison,
            'settings' => $settings,
        ]);
    }

    /**
     * Susun tahap pengiriman yang sudah dilalui permintaan mushaf.
     *
     * Permintaan mushaf tidak memiliki kolom status packing/pengiriman sendiri;
     * kemajuannya hanya terlihat dari status pengiriman yang tertaut. Urutan
     * tahap diambil dari kolom `urutan` pada master status, sehingga konsisten
     * dengan Status Pengiriman di panel admin.
     *
     * @return array<int, array{slug: string, label: string, reached: bool, is_current: bool, at: string|null}>
     */
    private function buildShippingStages(MushafRequest $mushafRequest): array
    {
        $pengiriman = $mushafRequest->pengiriman;

        if (! $pengiriman) {
            return [];
        }

        $steps = StatusPengiriman::query()
            ->whereIn('slug', ['packing', 'selesai-packing', 'pengiriman', 'diterima'])
            ->orderBy('urutan')
            ->get();

        $current = $pengiriman->status;

        if (! $current) {
            return [];
        }

        $isReached = fn (StatusPengiriman $step): bool => $step->urutan <= $current->urutan;

        return $steps->map(fn (StatusPengiriman $step): array => [
            'slug' => $step->slug,
            'label' => $step->nama,
            'reached' => $isReached($step),
            'is_current' => $step->slug === $current->slug,
            'at' => $step->slug === $current->slug
                ? optional($pengiriman->updated_at)->translatedFormat('d F Y')
                : null,
        ])->values()->all();
    }

    /**
     * API endpoint for checking status (for AJAX requests)
     */
    public function apiCheck(Request $request)
    {
        $request->validate([
            'no_request' => 'required|string',
        ]);

        $mushafRequest = MushafRequest::where('no_request', $request->no_request)->first();

        if (! $mushafRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor permintaan tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'no_request' => $mushafRequest->no_request,
                'nama_lembaga' => $mushafRequest->nama_lembaga,
                'status' => $mushafRequest->status,
                'status_label' => $mushafRequest->status_label,
                'total_mushaf' => ($mushafRequest->jumlah_mushaf ?? 0) + ($mushafRequest->jumlah_iqra ?? 0),
                'created_at' => $mushafRequest->created_at->format('d/m/Y H:i'),
                'catatan_admin' => $mushafRequest->catatan_admin,
            ],
        ]);
    }
}
