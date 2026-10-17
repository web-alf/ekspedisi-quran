<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Pengiriman;
use App\Models\Setting;
use App\Models\StatusPengiriman;
use App\Services\OnDemandCertificateService;
use App\Services\RiwayatPublikService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrackingController extends Controller
{
    protected $onDemandCertificateService;

    public function __construct(
        OnDemandCertificateService $onDemandCertificateService,
        protected RiwayatPublikService $riwayatPublik,
    ) {
        $this->onDemandCertificateService = $onDemandCertificateService;
    }

    /**
     * Show tracking search page
     */
    public function index()
    {
        // Get settings for consistent layout
        $settings = Setting::where('is_public', true)
            ->where('is_active', true)
            ->whereIn('group', ['landing', 'contact', 'social', 'general'])
            ->pluck('value', 'key');

        return Inertia::render('Public/TrackingPage', [
            'pengiriman' => null,
            'searchQuery' => '',
            'error' => null,
            'settings' => $settings,
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
            'donatur:id,nama_donatur,kode_donatur',
            'donatur.wakafBatches.sertifikat',
            'wakafItem:id,pengiriman_id,wakif_name',
            'jenisQuran:id,nama_jenis,kode_jenis',
            'status:id,nama,slug,warna,icon',
            'statusHistory',
            'trackingHistory',
            'serahTerimaKurir',
        ])->where('no_resi', $noResi)->first();

        if (! $pengiriman) {
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
            // `catatan` dan doa wakaf sengaja TIDAK dikirim (keputusan pemilik sistem,
            // 16/10/2026): catatan resi berisi "Donatur: … | Doa: …" dari impor, dan doa hanya
            // untuk lembar cetak resi — bukan untuk siapa pun yang tahu nomor resinya.
            'qr_code_url' => $pengiriman->qr_code_path ? \Storage::url($pengiriman->qr_code_path) : null,
            'donatur' => $pengiriman->donatur ? [
                'id' => $pengiriman->donatur->id,
                'kode_donatur' => $pengiriman->donatur->kode_donatur,
                'nama_donatur' => $pengiriman->donatur->nama_donatur,
                // no_hp donatur sengaja TIDAK dikirim: halaman ini publik (cukup tahu no
                // resi) dan nomor itu tidak pernah ditampilkan — dulu tetap ikut di JSON halaman.
            ] : null,
            'wakaf_item' => $pengiriman->wakafItem ? [
                'wakif_name' => $pengiriman->wakafItem->wakif_name,
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
        $nomorSudahAda = [];

        if ($pengiriman->donatur) {
            // Nama wakif sama untuk semua sertifikat donatur ini, jadi dihitung
            // sekali — bukan di dalam perulangan batch seperti sebelumnya.
            $wakifNames = Pengiriman::where('donatur_id', $pengiriman->donatur_id)
                ->with('wakafItem:id,pengiriman_id,wakif_name')
                ->get()
                ->map(function ($p) {
                    return $p->wakafItem ? $p->wakafItem->wakif_name : null;
                })
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            foreach ($pengiriman->donatur->wakafBatches as $batch) {
                if (! $batch->sertifikat) {
                    continue;
                }

                $nomor = $batch->sertifikat->nomor_sertifikat;
                $nomorSudahAda[] = $nomor;

                $certificateUrls[] = [
                    'batch_code' => $batch->batch_code,
                    'download_url' => $this->onDemandCertificateService->getPublicDownloadUrl($batch->sertifikat),
                    'nomor_sertifikat' => $nomor,
                    'generated_at' => $batch->sertifikat->generated_at,
                    'wakif_names' => $wakifNames, // Array of wakif names for this batch/donatur
                    'donatur_name' => $batch->donatur->nama_donatur,
                ];
            }

            // Sertifikat konsolidasi (per donatur) TIDAK tertaut ke batch mana
            // pun — `wakaf_batch_id`-nya NULL — sehingga tidak akan pernah
            // terlihat lewat perulangan batch di atas. Padahal justru inilah yang
            // dibuat saat sebuah batch ditutup (lihat
            // TutupPengirimanSelesaiCommand), jadi tanpa blok ini sertifikatnya
            // ada di database tapi tidak bisa ditemukan donatur di mana pun.
            $sertifikatKonsolidasi = $pengiriman->donatur->sertifikat()
                ->where('is_consolidated', true)
                ->get();

            foreach ($sertifikatKonsolidasi as $sertifikat) {
                if (in_array($sertifikat->nomor_sertifikat, $nomorSudahAda, true)) {
                    continue;
                }

                $certificateUrls[] = [
                    'batch_code' => null,
                    'download_url' => $this->onDemandCertificateService->getPublicDownloadUrl($sertifikat),
                    'nomor_sertifikat' => $sertifikat->nomor_sertifikat,
                    'generated_at' => $sertifikat->generated_at,
                    'wakif_names' => $wakifNames,
                    'donatur_name' => $pengiriman->donatur->nama_donatur,
                ];
            }
        }

        // Riwayat publik: hanya tahap penting, tanpa catatan & nama petugas
        // (lihat RiwayatPublikService). Riwayat mentah tidak pernah dikirim ke sini.
        $riwayat = $this->riwayatPublik->susun($pengiriman);

        // Get settings for consistent layout
        $settings = Setting::where('is_public', true)
            ->where('is_active', true)
            ->whereIn('group', ['landing', 'contact', 'social', 'general'])
            ->pluck('value', 'key');

        return Inertia::render('Public/TrackingResult', [
            'pengiriman' => $pengirimanData,
            'riwayat' => $riwayat,
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
            'settings' => $settings,
        ]);
    }

    /**
     * Search for resi (POST method from form)
     */
    public function search(Request $request)
    {
        $request->validate([
            'no_resi' => 'required|string|max:20',
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
            'donatur:id,kode_donatur,nama_donatur',
            'wakafItem:id,pengiriman_id,wakif_name',
            'jenisQuran:id,nama_jenis,kode_jenis',
            'status:id,nama,slug,warna,icon',
            'statusHistory',
            'trackingHistory',
            'serahTerimaKurir',
        ])->where('no_resi', $noResi)->first();

        if (! $pengiriman) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor resi tidak ditemukan',
                'data' => null,
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
                'riwayat' => $this->riwayatPublik->susun($pengiriman),
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
                }),
            ],
        ]);
    }

    /**
     * Generate QR Code URL for tracking
     */
    public function qrCode($noResi)
    {
        $pengiriman = Pengiriman::where('no_resi', $noResi)->first();

        if (! $pengiriman) {
            abort(404, 'Nomor resi tidak ditemukan');
        }

        $trackingUrl = route('public.tracking', $noResi);

        // Simple QR code generation using Google Charts API
        $qrCodeUrl = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl='.urlencode($trackingUrl);

        return response()->json([
            'success' => true,
            'qr_code_url' => $qrCodeUrl,
            'tracking_url' => $trackingUrl,
            'no_resi' => $noResi,
        ]);
    }
}
