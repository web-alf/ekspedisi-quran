<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\OnDemandCertificateService;

class TrackingController extends Controller
{
    protected $onDemandCertificateService;

    public function __construct(OnDemandCertificateService $onDemandCertificateService)
    {
        $this->onDemandCertificateService = $onDemandCertificateService;
    }
    /**
     * Show tracking search page
     */
    public function index()
    {
        // Get settings for consistent layout
        $settings = \App\Models\Setting::where('is_public', true)
            ->where('is_active', true)
            ->whereIn('group', ['landing', 'contact', 'social', 'general'])
            ->pluck('value', 'key');

        return Inertia::render('Public/TrackingPage', [
            'pengiriman' => null,
            'statusHistory' => [],
            'searchQuery' => '',
            'error' => null,
            'settings' => $settings
        ]);
    }

    /**
     * Track specific resi number
     */
    public function track($noResi)
    {
        // Clean up the resi number
        $noResi = strtoupper(trim($noResi));
        
        // Find pengiriman with detailed relationships
        $pengiriman = Pengiriman::with([
            'donatur:id,nama_donatur,kode_donatur,no_hp',
            'donatur.wakafBatches.sertifikat',
            'wakafItem:id,pengiriman_id,wakif_name,doa_request',
            'jenisQuran:id,nama_jenis,kode_jenis',
            'status:id,nama,slug,warna,icon',
            'statusHistory' => function($query) {
                $query->with(['statusFrom:id,nama,slug', 'statusTo:id,nama,slug', 'creator:id,name'])
                      ->orderBy('created_at', 'desc');
            },
            'trackingHistory' => function($query) {
                $query->with(['status:id,nama,slug,warna,icon', 'user:id,name'])
                      ->orderBy('tanggal_update', 'desc');
            }
        ])->where('no_resi', $noResi)->first();

        if (!$pengiriman) {
            abort(404, 'Nomor resi tidak ditemukan');
        }

        // Get all statuses for the timeline (cached for performance)
        $allStatuses = \Cache::remember('status_pengiriman_active', 3600, function () {
            return StatusPengiriman::active()->orderBy('urutan')->get();
        });

        // Format data for display
        $pengirimanData = [
            'id' => $pengiriman->id,
            'no_resi' => $pengiriman->no_resi,
            'jumlah_quran' => $pengiriman->jumlah_quran,
            'tanggal_wakaf' => $pengiriman->tanggal_wakaf,
            'formatted_tanggal_wakaf' => $pengiriman->tanggal_wakaf 
                ? $pengiriman->tanggal_wakaf->format('d/m/Y') 
                : null,
            'alamat_tujuan' => $pengiriman->alamat_tujuan,
            'nama_penerima' => $pengiriman->nama_penerima,
            'no_hp_penerima' => $pengiriman->no_hp_penerima,
            'catatan' => $pengiriman->catatan,
            'qr_code_url' => $pengiriman->qr_code_path ? \Storage::url($pengiriman->qr_code_path) : null,
            'donatur' => $pengiriman->donatur ? [
                'id' => $pengiriman->donatur->id,
                'kode_donatur' => $pengiriman->donatur->kode_donatur,
                'nama_donatur' => $pengiriman->donatur->nama_donatur,
                'no_hp' => $pengiriman->donatur->no_hp,
            ] : null,
            'wakaf_item' => $pengiriman->wakafItem ? [
                'wakif_name' => $pengiriman->wakafItem->wakif_name,
                'doa_request' => $pengiriman->wakafItem->doa_request,
            ] : null,
            'jenis_quran' => $pengiriman->jenisQuran ? [
                'id' => $pengiriman->jenisQuran->id,
                'nama_jenis' => $pengiriman->jenisQuran->nama_jenis,
                'deskripsi' => $pengiriman->jenisQuran->deskripsi ?? null,
            ] : null,
            'status' => $pengiriman->status ? [
                'id' => $pengiriman->status->id,
                'nama' => $pengiriman->status->nama,
                'slug' => $pengiriman->status->slug,
                'deskripsi' => $pengiriman->status->deskripsi ?? null,
                'warna' => $pengiriman->status->warna ?? 'gray',
                'urutan' => $pengiriman->status->urutan,
                'icon' => $pengiriman->status->icon,
            ] : null,
        ];

        // Generate certificate download URLs if available
        // Logic: Show certificates from all wakaf batches belonging to this donatur
        // because certificates are issued per wakaf batch, not per individual shipment
        $certificateUrls = [];
        if ($pengiriman->donatur && $pengiriman->donatur->wakafBatches) {
            foreach ($pengiriman->donatur->wakafBatches as $batch) {
                if ($batch->sertifikat) {
                    // Get wakif names from all pengiriman of this donatur
                    $wakifNames = \App\Models\Pengiriman::where('donatur_id', $batch->donatur_id)
                        ->with('wakafItem:id,pengiriman_id,wakif_name')
                        ->get()
                        ->map(function($p) {
                            return $p->wakafItem ? $p->wakafItem->wakif_name : null;
                        })
                        ->filter()
                        ->unique()
                        ->values()
                        ->toArray();
                    
                    $certificateUrls[] = [
                        'batch_code' => $batch->batch_code,
                        'download_url' => $this->onDemandCertificateService->getPublicDownloadUrl($batch->sertifikat),
                        'nomor_sertifikat' => $batch->sertifikat->nomor_sertifikat,
                        'generated_at' => $batch->sertifikat->generated_at,
                        'wakif_names' => $wakifNames, // Array of wakif names for this batch/donatur
                        'donatur_name' => $batch->donatur->nama_donatur
                    ];
                }
            }
        }

        // Format status history
        $statusHistoryData = $pengiriman->statusHistory->map(function($history) {
            // Manual decode dokumentasi to avoid accessor issues
            $photos = [];
            $rawDok = $history->getRawOriginal('dokumentasi');
            if ($rawDok) {
                $decoded = json_decode($rawDok, true);
                // Handle double encoding
                if (is_string($decoded)) {
                    $decoded = json_decode($decoded, true);
                }
                if (is_array($decoded)) {
                    foreach ($decoded as $item) {
                        if (is_string($item)) {
                            $photos[] = asset('storage/' . $item);
                        } elseif (is_array($item) && isset($item['path'])) {
                            $photos[] = isset($item['url']) ? $item['url'] : asset('storage/' . $item['path']);
                        }
                    }
                }
            }

            return [
                'id' => $history->id,
                'pengiriman_id' => $history->pengiriman_id,
                'status_from_id' => $history->status_from,
                'status_to_id' => $history->status_to,
                'catatan' => $history->catatan,
                'lokasi' => $history->lokasi,
                'created_at' => $history->created_at,
                'formatted_date' => $history->created_at->format('d/m/Y H:i'),
                'foto_dokumentasi' => $photos, // Manual decode
                'has_location' => $history->has_location,
                'status_from' => $history->statusFrom ? [
                    'id' => $history->statusFrom->id,
                    'nama' => $history->statusFrom->nama,
                    'slug' => $history->statusFrom->slug,
                ] : null,
                'status_to' => $history->statusTo ? [
                    'id' => $history->statusTo->id,
                    'nama' => $history->statusTo->nama,
                    'slug' => $history->statusTo->slug,
                ] : null,
                'creator' => $history->creator ? [
                    'id' => $history->creator->id,
                    'name' => $history->creator->name,
                ] : null,
            ];
        });
        
        // Format tracking history for documentation
        $trackingHistoryData = $pengiriman->trackingHistory->map(function($history) {
            return [
                'id' => $history->id,
                'status' => $history->status ? [
                    'id' => $history->status->id,
                    'nama' => $history->status->nama,
                    'slug' => $history->status->slug,
                    'warna' => $history->status->warna,
                    'icon' => $history->status->icon,
                ] : null,
                'tanggal_update' => $history->tanggal_update->format('d/m/Y H:i'),
                'lokasi' => $history->lokasi,
                'keterangan' => $history->keterangan,
                'foto_dokumentasi' => $history->photo_urls, // Uses the accessor method
                'foto_dokumentasi_raw' => $history->foto_dokumentasi, // Raw data for debugging
                'user' => $history->user ? [
                    'id' => $history->user->id,
                    'name' => $history->user->name,
                ] : null,
            ];
        });

        // Get settings for consistent layout
        $settings = \App\Models\Setting::where('is_public', true)
            ->where('is_active', true)
            ->whereIn('group', ['landing', 'contact', 'social', 'general'])
            ->pluck('value', 'key');

        return Inertia::render('Public/TrackingResult', [
            'pengiriman' => $pengirimanData,
            'statusHistory' => $statusHistoryData,
            'trackingHistory' => $trackingHistoryData,
            'certificateUrls' => $certificateUrls,
            'allStatuses' => $allStatuses->map(function ($status) {
                return [
                    'id' => $status->id,
                    'nama' => $status->nama,
                    'slug' => $status->slug,
                    'deskripsi' => $status->deskripsi,
                    'warna' => $status->warna,
                    'urutan' => $status->urutan,
                    'icon' => $status->icon,
                ];
            }),
            'no_resi' => $noResi,
            'searchQuery' => $noResi,
            'settings' => $settings
        ]);
    }

    /**
     * Search for resi (POST method from form)
     */
    public function search(Request $request)
    {
        $request->validate([
            'no_resi' => 'required|string|max:20'
        ]);

        $noResi = strtoupper(trim($request->no_resi));
        
        // Redirect to tracking URL
        return redirect()->route('public.tracking', $noResi);
    }

    /**
     * API endpoint for AJAX tracking
     */
    public function api($noResi)
    {
        $noResi = strtoupper(trim($noResi));
        
        $pengiriman = Pengiriman::with([
            'donatur:id,kode_donatur,nama_donatur,no_hp',
            'wakafItem:id,pengiriman_id,wakif_name',
            'jenisQuran:id,nama_jenis,kode_jenis',
            'status:id,nama,slug,warna,icon',
            'statusHistory' => function($query) {
                $query->with(['statusTo:id,nama,slug', 'creator:id,name'])
                      ->orderBy('created_at', 'desc')
                      ->limit(10);
            },
            'trackingHistory' => function($query) {
                $query->with(['status:id,nama,slug,warna,icon', 'user:id,name'])
                      ->orderBy('tanggal_update', 'desc')
                      ->limit(10);
            }
        ])->where('no_resi', $noResi)->first();

        if (!$pengiriman) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor resi tidak ditemukan',
                'data' => null
            ], 404);
        }
        
        // Get all statuses for the timeline (cached for performance)
        $allStatuses = \Cache::remember('status_pengiriman_active', 3600, function () {
            return StatusPengiriman::active()->orderBy('urutan')->get();
        });

        return response()->json([
            'success' => true,
            'message' => 'Data pengiriman ditemukan',
            'data' => [
                'pengiriman' => [
                    'no_resi' => $pengiriman->no_resi,
                    'jumlah_quran' => $pengiriman->jumlah_quran,
                    'tanggal_wakaf' => $pengiriman->tanggal_wakaf?->format('d/m/Y'),
                    'alamat_tujuan' => $pengiriman->alamat_tujuan,
                    'status' => $pengiriman->status?->nama,
                    'status_slug' => $pengiriman->status?->slug,
                    'status_color' => $pengiriman->status?->warna,
                    'status_icon' => $pengiriman->status?->icon,
                    'wakif_nama' => $pengiriman->wakafItem?->wakif_name ?? $pengiriman->donatur?->nama_donatur,
                    'jenis_quran' => $pengiriman->jenisQuran?->nama_jenis,
                ],
                'status_history' => $pengiriman->statusHistory->map(function($history) {
                    return [
                        'status' => $history->statusTo?->nama,
                        'slug' => $history->statusTo?->slug,
                        'catatan' => $history->catatan,
                        'tanggal' => $history->created_at->format('d/m/Y H:i'),
                        'petugas' => $history->creator?->name,
                    ];
                }),
                'tracking_history' => $pengiriman->trackingHistory->map(function($history) {
                    return [
                        'status' => $history->status?->nama,
                        'slug' => $history->status?->slug,
                        'warna' => $history->status?->warna,
                        'icon' => $history->status?->icon,
                        'tanggal' => $history->tanggal_update->format('d/m/Y H:i'),
                        'lokasi' => $history->lokasi,
                        'keterangan' => $history->keterangan,
                        'foto_dokumentasi' => $history->photo_urls,
                        'petugas' => $history->user?->name,
                    ];
                }),
                'all_statuses' => $allStatuses->map(function ($status) {
                    return [
                        'id' => $status->id,
                        'nama' => $status->nama,
                        'slug' => $status->slug,
                        'deskripsi' => $status->deskripsi,
                        'warna' => $status->warna,
                        'urutan' => $status->urutan,
                        'icon' => $status->icon,
                    ];
                })
            ]
        ]);
    }

    /**
     * Generate QR Code URL for tracking
     */
    public function qrCode($noResi)
    {
        $pengiriman = Pengiriman::where('no_resi', $noResi)->first();
        
        if (!$pengiriman) {
            abort(404, 'Nomor resi tidak ditemukan');
        }

        $trackingUrl = route('public.tracking', $noResi);
        
        // Simple QR code generation using Google Charts API
        $qrCodeUrl = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . urlencode($trackingUrl);
        
        return response()->json([
            'success' => true,
            'qr_code_url' => $qrCodeUrl,
            'tracking_url' => $trackingUrl,
            'no_resi' => $noResi
        ]);
    }
}
