<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ThermalPrintController extends Controller
{
    /**
     * Generate thermal label untuk single pengiriman
     * Ukuran: 100×150 mm (portrait)
     */
    public function printSingle(Pengiriman $pengiriman, Request $request)
    {
        // Get logo and contact settings
        $appLogo = Setting::where('key', 'app_logo')->first();
        $contactAddress = Setting::where('key', 'contact_address')->first();

        $data = [
            'pengiriman' => $pengiriman->load(['wakafItem', 'jenisQuran', 'donatur', 'status']),
            'printDate' => Carbon::now()->format('d/m/Y'),
            'printTime' => Carbon::now()->format('H:i'),
            'qrUrl' => $pengiriman->qr_code_path ? asset('storage/'.str_replace('public/', '', $pengiriman->qr_code_path)) : null,
            'appLogo' => $appLogo ? $appLogo->value : null,
            'contactAddress' => $contactAddress ? $contactAddress->value : 'Alamat kontak tidak tersedia',
        ];

        return view('admin.thermal-print.label-100x150', $data);
    }

    /**
     * Show bulk thermal print form
     * Halaman untuk memilih pengiriman yang akan di-print bulk
     */
    public function bulkIndex(Request $request)
    {
        // Get available pengiriman for bulk printing
        $pengirimanList = Pengiriman::with(['wakafItem', 'jenisQuran', 'donatur', 'status'])
            ->whereHas('status', function($query) {
                // Only show pengiriman yang sudah bisa di-print (proses packing atau selesai)
                $query->whereIn('nama', ['Proses Packing', 'Sedang Dikirim', 'Selesai']);
            })
            ->where('qr_code_path', '!=', null) // Harus sudah ada QR code
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Get logo and contact settings
        $appLogo = Setting::where('key', 'app_logo')->first();
        $contactAddress = Setting::where('key', 'contact_address')->first();

        // Check if request expects JSON (AJAX/API call) or HTML (browser)
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'pengiriman_list' => $pengirimanList,
                'total_available' => $pengirimanList->total(),
                'app_logo' => $appLogo ? $appLogo->value : null,
                'contact_address' => $contactAddress ? $contactAddress->value : 'Alamat kontak tidak tersedia',
                'message' => 'Thermal print bulk data loaded successfully'
            ]);
        }

        // Return HTML view for browser access
        return view('admin.thermal-print.bulk-index', [
            'pengirimanList' => $pengirimanList,
            'totalAvailable' => $pengirimanList->total(),
            'appLogo' => $appLogo ? $appLogo->value : null,
            'contactAddress' => $contactAddress ? $contactAddress->value : 'Alamat kontak tidak tersedia',
        ]);
    }

    /**
     * Generate thermal labels untuk multiple pengiriman
     * Ukuran: 100×150 mm (portrait)
     */
    public function printBulk(Request $request)
    {
        $request->validate([
            'pengiriman_ids' => 'required|array',
            'pengiriman_ids.*' => 'exists:pengiriman,id',
        ]);

        $pengirimanList = Pengiriman::with(['wakafItem', 'jenisQuran', 'donatur', 'status'])
            ->whereIn('id', $request->pengiriman_ids)
            ->get();

        // Get logo and contact settings
        $appLogo = Setting::where('key', 'app_logo')->first();
        $contactAddress = Setting::where('key', 'contact_address')->first();

        $data = [
            'pengirimanList' => $pengirimanList,
            'printDate' => Carbon::now()->format('d/m/Y'),
            'printTime' => Carbon::now()->format('H:i'),
            'totalLabels' => count($pengirimanList),
            'appLogo' => $appLogo ? $appLogo->value : null,
            'contactAddress' => $contactAddress ? $contactAddress->value : 'Alamat kontak tidak tersedia',
        ];

        return view('admin.thermal-print.bulk-100x150', $data);
    }

    /**
     * Generate thermal label preview
     * Ukuran: 100×150 mm (portrait)
     */
    public function preview(Request $request)
    {
        // Get logo and contact settings
        $appLogo = Setting::where('key', 'app_logo')->first();
        $contactAddress = Setting::where('key', 'contact_address')->first();

        // Sample data untuk preview
        $samplePengiriman = (object) [
            'no_resi' => 'EQ-2025-00987',
            'nama_penerima' => 'Muhammad Faried',
            'nama_lembaga' => 'LAZ Rabbani',
            'no_hp_penerima' => '085604686544',
            'alamat_tujuan' => 'Blok Karakurung 009/004 Situraja, Gantar, CIKEASI UDIK, GUNUNG PUTRI, KABUPATEN BOGOR, JAWA BARAT, 45264',
            'donatur' => (object) [
                'nama_donatur' => 'Ahmad Suryadi',
                'kode_donatur' => 'DON003',
            ],
            'wakafItem' => (object) [
                'wakif_name' => 'Rayhan',
                'relationship_to_donatur' => 'Anak',
                'doa_request' => 'Semoga Allah memberikan keberkahan dan kemudahan',
            ],
            'jenisQuran' => (object) [
                'nama_jenis' => 'Al-Quran Ukuran A5',
            ],
            'status' => (object) [
                'nama' => 'Proses Packing',
            ],
            'jumlah_quran' => 1,
            'created_at' => Carbon::now(),
            'tanggal_wakaf' => Carbon::now(),
            'qr_code_path' => null,
        ];

        $data = [
            'pengiriman' => $samplePengiriman,
            'printDate' => Carbon::now()->format('d/m/Y'),
            'printTime' => Carbon::now()->format('H:i'),
            'isPreview' => true,
            'qrUrl' => null, // Will show placeholder QR
            'appLogo' => $appLogo ? $appLogo->value : null,
            'contactAddress' => $contactAddress ? $contactAddress->value : 'Alamat kontak tidak tersedia',
        ];

        return view('admin.thermal-print.label-100x150', $data);
    }

    /**
     * Generate thermal label untuk packing box
     * Ukuran: 100×150 mm (portrait)
     */
    public function printBox(PackingBox $packingBox)
    {
        // Load all relations
        $packingBox->load([
            'dailyPackingTask.user',
            'jenisQuran',
            'packingItems.pengiriman.donatur',
            'packingItems.pengiriman.wakafItem',
            'packingItems.packedByUser',
        ]);

        // Get logo and contact settings
        $appLogo = Setting::where('key', 'app_logo')->first();
        $contactAddress = Setting::where('key', 'contact_address')->first();

        $data = [
            'box' => $packingBox,
            'contentSummary' => $packingBox->getContentSummary(),
            'qrCodeBase64' => $packingBox->getBoxQRBase64(),
            'printDate' => Carbon::now()->format('d/m/Y'),
            'printTime' => Carbon::now()->format('H:i'),
            'statusInfo' => $packingBox->getStatusInfo(),
            'appLogo' => $appLogo ? $appLogo->value : null,
            'contactAddress' => $contactAddress ? $contactAddress->value : 'Alamat kontak tidak tersedia',
        ];

        return view('admin.thermal-print.box-label-100x150', $data);
    }

    /**
     * Generate thermal label preview untuk box
     * Ukuran: 100×150 mm (portrait)
     */
    public function previewBox(Request $request)
    {
        // Sample data untuk preview box
        $sampleBox = (object) [
            'id' => 1,
            'kode_kerdus' => 'KB-20250731-003-A5-01',
            'status' => 'sealed',
            'seal_code' => '379373A4',
            'sealed_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'terisi' => 18,
            'kapasitas' => 20,
            'dailyPackingTask' => (object) [
                'user' => (object) [
                    'name' => 'Staff Gudang',
                ],
                // Konsisten dengan model: gunakan tanggal_tugas sebagai tanggal harian task
                'tanggal_tugas' => Carbon::now(),
            ],
            'jenisQuran' => (object) [
                'nama_jenis' => 'Al-Quran Ukuran A5',
            ],
        ];

        // Sample content summary
        $sampleContentSummary = [
            'jenis_quran' => 'Al-Quran Ukuran A5',
            'total_items' => 18,
            'box_info' => [
                'terisi' => 18,
                'kapasitas' => 20,
                'progress_percentage' => 90,
                'sealed_at' => Carbon::now()->format('d/m/Y H:i'),
            ],
            'items' => [
                [
                    'urutan_dalam_box' => 1,
                    'no_resi' => 'EQ-2025-00987',
                    'wakif' => 'Ahmad Suryadi',
                ],
                [
                    'urutan_dalam_box' => 2,
                    'no_resi' => 'EQ-2025-00988',
                    'wakif' => 'Siti Aminah',
                ],
                [
                    'urutan_dalam_box' => 3,
                    'no_resi' => 'EQ-2025-00989',
                    'wakif' => 'Muhammad Faried',
                ],
                [
                    'urutan_dalam_box' => 4,
                    'no_resi' => 'EQ-2025-00990',
                    'wakif' => 'Rayhan',
                ],
                [
                    'urutan_dalam_box' => 5,
                    'no_resi' => 'EQ-2025-00991',
                    'wakif' => 'Dewi Sartika',
                ],
            ],
        ];

        // Sample status info
        $sampleStatusInfo = [
            'label' => 'Tersegel',
            'color' => 'sealed',
        ];

        // Get logo and contact settings
        $appLogo = Setting::where('key', 'app_logo')->first();
        $contactAddress = Setting::where('key', 'contact_address')->first();

        $data = [
            'box' => $sampleBox,
            'contentSummary' => $sampleContentSummary,
            'qrCodeBase64' => 'data:image/svg+xml;base64,'.base64_encode('<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" fill="#000"/><text x="50" y="50" fill="#fff" text-anchor="middle" dy=".3em">QR</text></svg>'),
            'printDate' => Carbon::now()->format('d/m/Y'),
            'printTime' => Carbon::now()->format('H:i'),
            'statusInfo' => $sampleStatusInfo,
            'isPreview' => true,
            'appLogo' => $appLogo ? $appLogo->value : null,
            'contactAddress' => $contactAddress ? $contactAddress->value : 'Alamat kontak tidak tersedia',
        ];

        return view('admin.thermal-print.box-label-100x150', $data);
    }

    /**
     * Generate thermal labels untuk multiple boxes
     * Ukuran: 100×150 mm (portrait)
     */
    public function printBoxBulk(Request $request)
    {
        $request->validate([
            'box_ids' => 'required|array',
            'box_ids.*' => 'exists:packing_boxes,id',
        ]);

        $boxes = PackingBox::with([
            'dailyPackingTask.user',
            'jenisQuran',
            'packingItems',
        ])
            ->whereIn('id', $request->box_ids)
            ->get();

        // Get logo and contact settings
        $appLogo = Setting::where('key', 'app_logo')->first();
        $contactAddress = Setting::where('key', 'contact_address')->first();

        $data = [
            'boxes' => $boxes->map(function ($box) {
                return [
                    'box' => $box,
                    'contentSummary' => $box->getContentSummary(),
                    'qrCodeBase64' => $box->getBoxQRBase64(),
                    'statusInfo' => $box->getStatusInfo(),
                ];
            }),
            'printDate' => Carbon::now()->format('d/m/Y'),
            'printTime' => Carbon::now()->format('H:i'),
            'totalBoxes' => $boxes->count(),
            'appLogo' => $appLogo ? $appLogo->value : null,
            'contactAddress' => $contactAddress ? $contactAddress->value : 'Alamat kontak tidak tersedia',
        ];

        return view('admin.thermal-print.bulk-box-100x150', $data);
    }
}
