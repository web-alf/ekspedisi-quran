<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DonaturController;
use App\Http\Controllers\Admin\LandingContent\ContactSettingsController;
use App\Http\Controllers\Admin\LandingContent\GeneralSettingsController;
use App\Http\Controllers\Admin\LandingContent\LandingSettingsController;
use App\Http\Controllers\Admin\LandingContent\LegalSettingsController;
use App\Http\Controllers\Admin\LandingContent\SeoSettingsController;
use App\Http\Controllers\Admin\LandingContent\SocialSettingsController;
use App\Http\Controllers\Admin\PengirimanController;
use App\Http\Controllers\Admin\PengirimanTrackingController;
use App\Http\Controllers\Admin\PermissionController;
// use App\Http\Controllers\Admin\SettingController; // Removed - All settings now under Landing Content
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VideoController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Public\LegalController;
use App\Http\Controllers\Public\TrackingController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Include public certificate routes
require __DIR__.'/public-certificate.php';

// Root route - Landing page (accessible untuk semua user)
Route::get('/', function () {
    // Get settings for landing page
    $settings = \App\Models\Setting::where('is_public', true)
        ->where('is_active', true)
        ->whereIn('group', ['landing', 'contact', 'social', 'general', 'gallery', 'faq', 'testimonial', 'video'])
        ->pluck('value', 'key');

    $mapData = collect();
    $stats = [
        'provinces' => 0,
        'cities' => 0,
        'institutions' => 0,
        // total mushaf tersalurkan sekarang diambil dari pengiriman dengan status 'diterima'
        'totalMushaf' => 0,
    ];

    // Only load map data if enabled in settings
    if ($settings->get('landing_map_enabled', true)) {
        // ✅ Show MushafRequest with status 'completed' using APPROVED quantities
        // This ensures consistency with admin page
        $mapData = \App\Models\MushafRequest::where('status', 'completed')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id', 'nama_lembaga', 'provinsi', 'kota_kabupaten', 'latitude', 'longitude', 'status',
                'jumlah_mushaf', 'jumlah_mushaf_a5', 'jumlah_mushaf_a6', 'jumlah_iqra',
                'jumlah_mushaf_approved', 'jumlah_mushaf_a5_approved', 'jumlah_mushaf_a6_approved', 'jumlah_iqra_approved',
                'kategori_lembaga')
            ->get()
            ->map(function ($item) {
                // Use APPROVED quantities (fallback to requested if not set)
                $approvedMushaf = $item->jumlah_mushaf_approved ?? $item->jumlah_mushaf;
                $approvedIqra = $item->jumlah_iqra_approved ?? $item->jumlah_iqra;
                $total = $approvedMushaf + $approvedIqra;

                return [
                    'id' => $item->id,
                    'nama_lembaga' => $item->nama_lembaga,
                    'provinsi' => $item->provinsi,
                    'kota_kabupaten' => $item->kota_kabupaten,
                    'lat' => (float) $item->latitude,
                    'lng' => (float) $item->longitude,
                    'status' => 'completed',
                    'jumlah_mushaf' => $total,
                    'kategori' => $item->kategori_lembaga,
                    'nama_penerima' => $item->nama_lembaga,
                ];
            });
    }

    // Only calculate statistics if enabled in settings
    if ($settings->get('landing_stats_enabled', true)) {
        // Stat provinsi/kota/lembaga tetap dari mushaf_requests completed agar representasi sebaran lembaga
        $requestsAgg = \App\Models\MushafRequest::where('status', 'completed')
            ->selectRaw('
                COUNT(DISTINCT provinsi) as provinces,
                COUNT(DISTINCT kota_kabupaten) as cities,
                COUNT(*) as institutions
            ')
            ->first();

        // Total mushaf tersalurkan dari pengiriman status diterima
        $distributed = \Illuminate\Support\Facades\DB::table('pengiriman')
            ->join('status_pengiriman', 'pengiriman.status_id', '=', 'status_pengiriman.id')
            ->where('status_pengiriman.slug', 'diterima')
            ->sum('pengiriman.jumlah_quran');

        $stats = [
            'provinces' => $requestsAgg->provinces ?? 0,
            'cities' => $requestsAgg->cities ?? 0,
            'institutions' => $requestsAgg->institutions ?? 0,
            'totalMushaf' => (int) $distributed,
        ];
    }

    // Get content from database
    $testimonials = \App\Models\Testimonial::active()->ordered()->get();
    $galleries = \App\Models\Gallery::active()->ordered()->get();
    $videos = \App\Models\Video::active()->ordered()->get();
    $faqs = \App\Models\Faq::active()->ordered()->get();

    // Build section order with fallback to defaults
    $sectionOrderValue = \App\Models\Setting::get('landing_section_order');
    $sectionOrder = [];
    if (! empty($sectionOrderValue)) {
        $decoded = json_decode($sectionOrderValue, true);
        if (is_array($decoded)) {
            $sectionOrder = \App\Services\LandingSectionRegistry::validate($decoded);
        }
    }
    if (empty($sectionOrder)) {
        $sectionOrder = \App\Services\LandingSectionRegistry::getDefaultOrder();
    }

    return Inertia::render('Landing', [
        'mapData' => $mapData,
        'stats' => $stats,
        'settings' => $settings->toArray(), // Convert Collection to plain array for JavaScript
        'testimonials' => $testimonials,
        'galleries' => $galleries,
        'videos' => $videos,
        'faqs' => $faqs,
        'sectionOrder' => $sectionOrder,
    ]);
})->name('home');

// SEO Routes
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// 🔍 Public Tracking Routes (enhanced rate limiting)
Route::middleware(['throttle:tracking-public', 'rate-limit-handler'])->group(function () {
    Route::get('/tracking', [TrackingController::class, 'index'])->name('public.tracking.index');
    Route::post('/tracking/search', [TrackingController::class, 'search'])->name('public.tracking.search');
    Route::get('/tracking/{no_resi}', [TrackingController::class, 'track'])->name('public.tracking');
});

// 📖 Public Mushaf Request Routes (enhanced rate limiting)
Route::middleware(['throttle:mushaf-request', 'rate-limit-handler'])->group(function () {
    Route::get('/mushaf-request', [App\Http\Controllers\Public\MushafRequestController::class, 'index'])->name('mushaf-request');
    Route::post('/mushaf-request', [App\Http\Controllers\Public\MushafRequestController::class, 'store'])->name('mushaf-request.store');
    Route::get('/mushaf-request/success/{no_request}', [App\Http\Controllers\Public\MushafRequestController::class, 'success'])->name('mushaf-request.success');
    Route::post('/mushaf-request/check-status', [App\Http\Controllers\Public\MushafRequestController::class, 'checkStatus'])->name('mushaf-request.check-status');
});

// 📋 Public Mushaf Tracking Routes (enhanced rate limiting)
Route::middleware(['throttle:tracking-public', 'rate-limit-handler'])->group(function () {
    Route::get('/mushaf-tracking', [App\Http\Controllers\Public\MushafTrackingController::class, 'index'])->name('mushaf-tracking');
    Route::get('/mushaf-tracking/{no_request}', [App\Http\Controllers\Public\MushafTrackingController::class, 'track'])->name('mushaf-tracking.show');
    Route::post('/mushaf-tracking/check', [App\Http\Controllers\Public\MushafTrackingController::class, 'apiCheck'])->name('mushaf-tracking.check');
});

// 📄 Legal Pages Routes (dengan rate limiting)
Route::middleware('throttle:100,1')->group(function () {
    Route::get('/privacy-policy', [LegalController::class, 'privacyPolicy'])->name('legal.privacy-policy');
    Route::get('/terms-of-service', [LegalController::class, 'termsOfService'])->name('legal.terms-of-service');
});

// 📱 API Routes for tracking (enhanced rate limiting)
Route::prefix('api/tracking')->name('api.tracking.')->middleware(['throttle:tracking-public', 'rate-limit-handler'])->group(function () {
    Route::get('/{no_resi}', [TrackingController::class, 'api'])->name('show');
    Route::get('/{no_resi}/qr', [TrackingController::class, 'qrCode'])->name('qr');
});

// 🗺️ Wilayah Indonesia API Routes (enhanced rate limiting)
Route::prefix('api/wilayah')->name('api.wilayah.')->middleware(['throttle:wilayah-public', 'rate-limit-handler'])->group(function () {
    Route::get('/provinces', [App\Http\Controllers\Api\WilayahController::class, 'provinces'])->name('provinces');
    Route::get('/regencies/{provinceId}', [App\Http\Controllers\Api\WilayahController::class, 'regencies'])->name('regencies');
    Route::get('/districts/{regencyId}', [App\Http\Controllers\Api\WilayahController::class, 'districts'])->name('districts');
    Route::get('/villages/{districtId}', [App\Http\Controllers\Api\WilayahController::class, 'villages'])->name('villages');
});

// Admin only cache management (outside public rate limit group)
Route::post('api/wilayah/clear-cache', [App\Http\Controllers\Api\WilayahController::class, 'clearCache'])
    ->middleware(['auth', 'permission:system.monitor'])
    ->name('api.wilayah.clear-cache');

// 🌐 Legacy tracking route (untuk backward compatibility)
Route::get('/track/{no_resi}', function ($noResi) {
    return redirect()->route('public.tracking', $noResi);
})->name('public.track.legacy');

// Guest routes - Protected with authentication rate limiting
Route::middleware(['guest', 'throttle:login'])->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout.get');

    // Main dashboard route - redirect to admin dashboard
    Route::get('/dashboard', function () {
        return redirect('/admin/dashboard');
    });

    // Admin specific routes - now using permission-based access
    Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
        // Dashboard - Available to all authenticated staff
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->middleware('permission:dashboard.view|dashboard.analytics')
            ->name('dashboard');

        // User Management - Super Admin Only
        Route::middleware('permission:users.read')->group(function () {
            Route::resource('users', UserController::class)
                ->middlewareFor(['create', 'store'], 'permission:users.create')
                ->middlewareFor(['edit', 'update'], 'permission:users.update')
                ->middlewareFor('destroy', 'permission:users.delete');

            Route::patch('users/{user}/toggle', [UserController::class, 'patch'])
                ->middleware('permission:users.toggle')
                ->name('users.toggle');
        });

        // Role Management - Super Admin Only
        Route::middleware('permission:roles.read')->group(function () {
            Route::resource('roles', RoleController::class)
                ->middlewareFor(['create', 'store'], 'permission:roles.create')
                ->middlewareFor(['edit', 'update'], 'permission:roles.update')
                ->middlewareFor('destroy', 'permission:roles.delete');
        });

        // Permission Management - Super Admin Only
        Route::middleware('permission:permissions.read')->group(function () {
            Route::resource('permissions', PermissionController::class)
                ->middlewareFor(['create', 'store'], 'permission:permissions.create')
                ->middlewareFor(['edit', 'update'], 'permission:permissions.update')
                ->middlewareFor('destroy', 'permission:permissions.delete');
        });

        // Donatur Management - CS and Super Admin
        Route::middleware('permission:donatur.read')->group(function () {
            Route::resource('donatur', DonaturController::class)
                ->middlewareFor(['create', 'store'], 'permission:donatur.create')
                ->middlewareFor(['edit', 'update'], 'permission:donatur.update')
                ->middlewareFor('destroy', 'permission:donatur.delete');

            Route::get('api/donatur/search-kode', [DonaturController::class, 'searchByKodeDonatur'])
                ->name('api.donatur.search-kode');

            Route::post('donatur/{donatur}/add-quran', [DonaturController::class, 'addQuran'])
                ->middleware('permission:donatur.update')
                ->name('donatur.add-quran');
            Route::patch('donatur/{donatur}/update-wakif-names', [DonaturController::class, 'updateWakifNames'])
                ->middleware('permission:donatur.update')
                ->name('donatur.update-wakif-names');

            Route::get('donatur-export', [DonaturController::class, 'export'])
                ->middleware('permission:donatur.export')
                ->name('donatur.export');
            Route::post('donatur-import', [DonaturController::class, 'import'])
                ->middleware('permission:donatur.import')
                ->name('donatur.import');
            Route::get('donatur-template', [DonaturController::class, 'downloadTemplate'])
                ->middleware('permission:donatur.read')
                ->name('donatur.template');

            // Wakaf Items Management - Nested under donatur
            Route::get('donatur/{donatur}/wakaf-items', [\App\Http\Controllers\Admin\WakafItemsController::class, 'index'])
                ->name('donatur.wakaf-items.index');
            Route::post('donatur/{donatur}/wakaf-items', [\App\Http\Controllers\Admin\WakafItemsController::class, 'store'])
                ->name('donatur.wakaf-items.store')
                ->middleware('permission:donatur.update');
            // Bulk operations must come BEFORE {wakafItem} routes to avoid conflicts
            Route::delete('donatur/{donatur}/wakaf-items/bulk-destroy', [\App\Http\Controllers\Admin\WakafItemsController::class, 'bulkDestroy'])
                ->name('donatur.wakaf-items.bulk-destroy')
                ->middleware(['permission:donatur.update']);
            Route::patch('donatur/{donatur}/wakaf-items/{wakafItem}', [\App\Http\Controllers\Admin\WakafItemsController::class, 'update'])
                ->name('donatur.wakaf-items.update')
                ->middleware('permission:donatur.update');
            Route::delete('donatur/{donatur}/wakaf-items/{wakafItem}', [\App\Http\Controllers\Admin\WakafItemsController::class, 'destroy'])
                ->name('donatur.wakaf-items.destroy')
                ->middleware('permission:donatur.update');
        });

        // 🚀 Pengiriman Management - Warehouse, Super Admin, and limited access for others
        Route::middleware('permission:shipments.read')->group(function () {
            // Pengiriman routes (excluding create and store - use donatur system instead)
            Route::get('pengiriman', [PengirimanController::class, 'index'])->name('pengiriman.index');
            Route::get('pengiriman/{pengiriman}', [PengirimanController::class, 'show'])->name('pengiriman.show');
            Route::get('pengiriman/{pengiriman}/edit', [PengirimanController::class, 'edit'])
                ->middleware('permission:shipments.update')
                ->name('pengiriman.edit');
            Route::put('pengiriman/{pengiriman}', [PengirimanController::class, 'update'])
                ->middleware('permission:shipments.update')
                ->name('pengiriman.update');
            Route::delete('pengiriman/{pengiriman}', [PengirimanController::class, 'destroy'])
                ->middleware('permission:shipments.delete')
                ->name('pengiriman.destroy');

            Route::post('pengiriman/bulk-status', [PengirimanController::class, 'bulkUpdateStatus'])
                ->middleware('permission:shipments.bulk-update')
                ->name('pengiriman.bulk-status');
            Route::post('pengiriman/bulk-alamat', [PengirimanController::class, 'bulkSetAlamat'])
                ->middleware('permission:shipments.bulk-update')
                ->name('pengiriman.bulk-alamat');
            Route::get('pengiriman-export', [PengirimanController::class, 'export'])
                ->middleware('permission:shipments.export')
                ->name('pengiriman.export');
        });

        // 🚚 Pengiriman Tracking Routes
        Route::get('pengiriman/{pengiriman}/update-status', [PengirimanTrackingController::class, 'showUpdateStatusForm'])
            ->middleware('permission:shipments.update-status')
            ->name('pengiriman.update-status.form');
        Route::post('pengiriman/{pengiriman}/update-status', [PengirimanTrackingController::class, 'updateStatusWithDocs'])
            ->middleware('permission:shipments.update-status')
            ->name('pengiriman.update-status');
        Route::post('pengiriman/batch-status', [PengirimanTrackingController::class, 'updateBatchStatus'])
            ->middleware('permission:shipments.update-status')
            ->name('pengiriman.batch-status');
        Route::post('pengiriman/{pengiriman}/process-status', [PengirimanTrackingController::class, 'updateProcessStatus'])
            ->middleware('permission:shipments.update-status')
            ->name('pengiriman.process-status');

        // 📱 Helper routes for clean URLs (redirect to main pengiriman with mode parameter)
        Route::get('qr-generator', function () {
            return redirect()->route('admin.pengiriman.index', ['mode' => 'generate-qr']);
        })->name('qr.generator');

        Route::get('qr-scanner', function () {
            return redirect()->route('admin.pengiriman.index', ['mode' => 'scan-status']);
        })->name('qr.scanner');

        // 📱 QR Code Management
        Route::post('qr/generate/{pengiriman}', [App\Http\Controllers\Admin\QRCodeController::class, 'generate'])
            ->middleware('permission:qr.generate|warehouse.qr.generate')
            ->name('qr.generate');
        Route::get('qr/display/{pengiriman}', [App\Http\Controllers\Admin\QRCodeController::class, 'display'])
            ->middleware('permission:qr.generate|warehouse.qr.generate')
            ->name('qr.display');
        Route::get('qr/download/{pengiriman}', [App\Http\Controllers\Admin\QRCodeController::class, 'download'])
            ->middleware('permission:qr.generate|warehouse.qr.generate')
            ->name('qr.download');
        Route::post('qr/bulk-generate', [App\Http\Controllers\Admin\QRCodeController::class, 'bulkGenerate'])
            ->middleware('permission:qr.bulk_operations|warehouse.qr.bulk_generate')
            ->name('qr.bulk-generate');
        Route::post('qr/verify', [App\Http\Controllers\Admin\QRCodeController::class, 'verifyQRDataAPI'])
            ->middleware('permission:qr.verify|warehouse.qr.verify')
            ->name('qr.verify');

        // 🖨️ Thermal Print Routes - Custom 78×100mm Labels
        Route::prefix('thermal-print')->name('thermal-print.')->middleware(['permission:qr.generate|warehouse.qr.generate'])->group(function () {
            Route::get('/single/{pengiriman}', [App\Http\Controllers\Admin\ThermalPrintController::class, 'printSingle'])
                ->middleware(['permission:qr.generate|warehouse.qr.generate|warehouse.qr.bulk_generate'])
                ->name('single');
            Route::get('/bulk', [App\Http\Controllers\Admin\ThermalPrintController::class, 'bulkIndex'])
                ->middleware(['permission:warehouse.qr.bulk_generate'])
                ->name('bulk.index');
            Route::post('/bulk', [App\Http\Controllers\Admin\ThermalPrintController::class, 'printBulk'])
                ->middleware('permission:qr.bulk_operations|warehouse.qr.bulk_generate')
                ->name('bulk');
            Route::get('/preview', [App\Http\Controllers\Admin\ThermalPrintController::class, 'preview'])
                ->middleware(['auth'])
                ->name('preview');

            // Box Label Routes
            Route::get('/box/{packingBox}', [App\Http\Controllers\Admin\ThermalPrintController::class, 'printBox'])
                ->name('box');
            Route::get('/box/preview', [App\Http\Controllers\Admin\ThermalPrintController::class, 'previewBox'])
                ->name('box.preview');
            Route::post('/box/bulk', [App\Http\Controllers\Admin\ThermalPrintController::class, 'printBoxBulk'])
                ->middleware('permission:qr.bulk_operations|warehouse.qr.bulk_generate')
                ->name('box.bulk');
        });

        // 📱 QR Scanner Routes - FIXED
        Route::get('pengiriman-status/{no_resi}', [PengirimanController::class, 'getPengirimanStatusInfo'])
            ->middleware('permission:shipments.track')
            ->name('pengiriman.status-info');
        Route::post('pengiriman/scan-status/update', [PengirimanController::class, 'updateStatusByScan'])
            ->middleware('permission:shipments.update-status|qr.scan|warehouse.qr.scan')
            ->name('pengiriman.scan-status.update');

        // 📦 Warehouse Packing System Routes (Permission-based access)
        Route::prefix('warehouse')->name('warehouse.')->middleware('permission:warehouse.dashboard')->group(function () {
            // Dashboard
            Route::get('/', [App\Http\Controllers\Warehouse\DashboardController::class, 'index'])
                ->middleware('permission:warehouse.dashboard')
                ->name('dashboard');
            Route::post('/start-scanning', [App\Http\Controllers\Warehouse\DashboardController::class, 'startScanning'])
                ->middleware('permission:warehouse.packing.scan')
                ->name('start-scanning');
            Route::post('/notification/{id}/read', [App\Http\Controllers\Warehouse\DashboardController::class, 'markNotificationRead'])
                ->middleware('permission:warehouse.dashboard')
                ->name('notification.read');

            // Packing Process
            Route::get('/packing', [App\Http\Controllers\Warehouse\PackingController::class, 'index'])
                ->middleware('permission:warehouse.packing.view')
                ->name('packing.index');
            Route::post('/packing/scan', [App\Http\Controllers\Warehouse\PackingController::class, 'scanItem'])
                ->middleware('permission:warehouse.packing.scan')
                ->name('packing.scan');
            Route::post('/packing/scan-confirm', [App\Http\Controllers\Warehouse\PackingController::class, 'processWithJenisConfirmation'])
                ->middleware('permission:warehouse.packing.scan')
                ->name('packing.scan-confirm');
            Route::post('/packing/box/{box}/seal', [App\Http\Controllers\Warehouse\PackingController::class, 'sealBox'])
                ->middleware('permission:warehouse.packing.seal|warehouse.boxes.seal')
                ->name('packing.seal-box');
            Route::get('/packing/history', [App\Http\Controllers\Warehouse\PackingController::class, 'history'])
                ->middleware('permission:warehouse.packing.view')
                ->name('packing.history');

            // Shared Box Collaboration
            Route::get('/shared-boxes-status', [App\Http\Controllers\Warehouse\DashboardController::class, 'getSharedBoxesStatus'])
                ->middleware('permission:warehouse.boxes.view|warehouse.tasks.view')
                ->name('shared-boxes-status');
            Route::get('/shared-collaboration', [App\Http\Controllers\Warehouse\DashboardController::class, 'sharedCollaboration'])
                ->middleware('permission:warehouse.boxes.view|warehouse.tasks.view')
                ->name('shared-collaboration');

            // Real-time updates
            Route::get('/dashboard-status', [App\Http\Controllers\Warehouse\DashboardController::class, 'getDashboardStatus'])
                ->middleware('permission:warehouse.dashboard')
                ->name('dashboard-status');
            Route::get('/box-progress/{boxId}', [App\Http\Controllers\Warehouse\DashboardController::class, 'getBoxProgress'])
                ->middleware('permission:warehouse.boxes.view')
                ->name('box-progress');

            // Shared box completion workflow
            Route::post('/box/{boxId}/trigger-completion', [App\Http\Controllers\Warehouse\DashboardController::class, 'triggerBoxCompletion'])
                ->middleware('permission:warehouse.box.update_any')
                ->name('trigger-box-completion');
            Route::post('/box/{boxId}/seal-request', [App\Http\Controllers\Warehouse\DashboardController::class, 'requestBoxSealing'])
                ->middleware('permission:warehouse.boxes.seal|warehouse.box.update_sealed')
                ->name('request-box-sealing');
            Route::get('/boxes-ready-for-seal', [App\Http\Controllers\Warehouse\DashboardController::class, 'getBoxesReadyForSeal'])
                ->middleware('permission:warehouse.boxes.seal')
                ->name('boxes-ready-for-seal');

            // Performance
            Route::get('/performance', [App\Http\Controllers\Warehouse\PerformanceController::class, 'index'])
                ->middleware('permission:warehouse.performance.view')
                ->name('performance');

            // Box Scanner Routes
            Route::get('/box-scanner', [App\Http\Controllers\Warehouse\BoxScannerController::class, 'index'])
                ->name('box-scanner')
                ->middleware('permission:warehouse.boxes.view');

            Route::post('/box-scanner/scan', [App\Http\Controllers\Warehouse\BoxScannerController::class, 'scanBox'])
                ->name('box-scanner.scan')
                ->middleware('permission:warehouse.qr.scan|warehouse.boxes.view');

            Route::post('/box-scanner/update-status', [App\Http\Controllers\Warehouse\BoxScannerController::class, 'updateStatus'])
                ->name('box-scanner.update-status')
                ->middleware('permission:warehouse.box.update_any');

            Route::post('/box-scanner/set-mushaf-address', [App\Http\Controllers\Warehouse\BoxScannerController::class, 'setMushafRequestAddress'])
                ->name('box-scanner.set-mushaf-address')
                ->middleware('permission:warehouse.box.update_any');

            // Bulk Operations Routes
            Route::post('/box-scanner/bulk-update-status', [App\Http\Controllers\Warehouse\BoxScannerController::class, 'bulkUpdateStatus'])
                ->name('box-scanner.bulk-update-status')
                ->middleware('permission:warehouse.box.update_any');

            Route::post('/box-scanner/bulk-box-operation', [App\Http\Controllers\Warehouse\BoxScannerController::class, 'bulkBoxOperation'])
                ->name('box-scanner.bulk-box-operation')
                ->middleware('permission:warehouse.box.update_any');

            // Job Progress Monitoring Routes
            Route::get('/job-progress/{job_id}', [App\Http\Controllers\Warehouse\BoxScannerController::class, 'getJobProgress'])
                ->name('job-progress')
                ->middleware('permission:warehouse.tasks.view');

            Route::get('/job-history', [App\Http\Controllers\Warehouse\BoxScannerController::class, 'getJobHistory'])
                ->name('job-history')
                ->middleware('permission:warehouse.tasks.view');

            // Job Monitor Dashboard
            Route::get('/job-monitor', [App\Http\Controllers\Warehouse\JobMonitorController::class, 'index'])
                ->name('job-monitor')
                ->middleware('permission:warehouse.tasks.view');

            Route::post('/job-monitor/progress', [App\Http\Controllers\Warehouse\JobMonitorController::class, 'getProgress'])
                ->name('job-monitor.progress')
                ->middleware('permission:warehouse.tasks.view');

            Route::post('/job-monitor/cancel', [App\Http\Controllers\Warehouse\JobMonitorController::class, 'cancelJob'])
                ->name('job-monitor.cancel')
                ->middleware('permission:warehouse.tasks.update');

            Route::post('/job-monitor/retry', [App\Http\Controllers\Warehouse\JobMonitorController::class, 'retryJob'])
                ->name('job-monitor.retry')
                ->middleware('permission:warehouse.tasks.update');

            Route::get('/job-monitor/details/{job_id}', [App\Http\Controllers\Warehouse\JobMonitorController::class, 'getJobDetails'])
                ->name('job-monitor.details')
                ->middleware('permission:warehouse.tasks.view');

            Route::get('/job-monitor/stats', [App\Http\Controllers\Warehouse\JobMonitorController::class, 'getStats'])
                ->name('job-monitor.stats')
                ->middleware('permission:warehouse.tasks.view');
        });

        // 👨‍💼 Supervisor Monitoring Routes (Permission-based access)
        Route::prefix('supervisor')->name('supervisor.')->middleware(['permission:supervisor.warehouse.monitor'])->group(function () {
            Route::get('/warehouse-monitor', [App\Http\Controllers\Supervisor\WarehouseMonitorController::class, 'index'])
                ->middleware('permission:supervisor.dashboard|supervisor.warehouse.monitor')
                ->name('warehouse-monitor');
            Route::get('/warehouse-monitor/data', [App\Http\Controllers\Supervisor\WarehouseMonitorController::class, 'getData'])
                ->middleware('permission:supervisor.dashboard|supervisor.warehouse.monitor')
                ->name('warehouse-monitor.data');
            Route::get('/warehouse-monitor/performance', [App\Http\Controllers\Supervisor\WarehouseMonitorController::class, 'getPerformanceData'])
                ->middleware('permission:supervisor.performance.view|supervisor.dashboard')
                ->name('warehouse-monitor.performance');
            Route::post('/warehouse-monitor/export', [App\Http\Controllers\Supervisor\WarehouseMonitorController::class, 'exportPerformanceData'])
                ->middleware('permission:supervisor.performance.reports')
                ->name('warehouse-monitor.export');
            Route::post('/redistribute/{task}', [App\Http\Controllers\Supervisor\WarehouseMonitorController::class, 'redistributeTask'])
                ->middleware('permission:supervisor.warehouse.redistribute')
                ->name('redistribute');
            Route::get('/performance-report', [App\Http\Controllers\Supervisor\WarehouseMonitorController::class, 'performanceReport'])
                ->middleware('permission:supervisor.performance.reports')
                ->name('performance-report');
            Route::post('/export-performance', [App\Http\Controllers\Supervisor\WarehouseMonitorController::class, 'exportPerformanceData'])
                ->middleware('permission:supervisor.performance.reports')
                ->name('export-performance');
            Route::delete('/warehouse-monitor/task/{task}/delete', [App\Http\Controllers\Supervisor\WarehouseMonitorController::class, 'deleteTask'])
                ->middleware('permission:supervisor.warehouse.assign')
                ->name('warehouse-monitor.delete-task');
            // NEW SYSTEM: Target-only assignment
            Route::post('/assign-target', [App\Http\Controllers\Supervisor\WarehouseMonitorController::class, 'assignTargetToUser'])
                ->middleware('permission:supervisor.warehouse.assign')
                ->name('assign-target');

            // DEPRECATED ROUTES - Kept for backward compatibility but return 410 Gone
            Route::get('/manual-assignment', function () {
                return redirect()->route('admin.supervisor.warehouse-monitor')
                    ->with('info', 'Manual assignment telah diganti dengan sistem target assignment.');
            })->name('manual-assignment');
            Route::post('/assign-items', [App\Http\Controllers\Supervisor\WarehouseMonitorController::class, 'assignItems'])
                ->middleware('permission:supervisor.warehouse.assign')
                ->name('assign-items');
            Route::post('/unassign-items', [App\Http\Controllers\Supervisor\WarehouseMonitorController::class, 'unassignItems'])
                ->middleware('permission:supervisor.warehouse.assign')
                ->name('unassign-items');
        });

        // Alternative route for QR status info
        Route::get('pengiriman/{no_resi}/status-info', [PengirimanController::class, 'getPengirimanStatusInfo'])
            ->middleware('permission:shipments.track')
            ->name('pengiriman.status-info-alt');

        // 📖 Mushaf Request Management
        Route::resource('mushaf-requests', App\Http\Controllers\Admin\MushafRequestController::class)
            ->only(['index', 'show'])
            ->middleware(['permission:mushaf-requests.read']);
        Route::patch('mushaf-requests/{mushafRequest}/status', [App\Http\Controllers\Admin\MushafRequestController::class, 'updateStatus'])
            ->middleware('permission:mushaf-requests.update|mushaf-requests.approve|mushaf-requests.reject')
            ->name('mushaf-requests.update-status');
        Route::post('mushaf-requests/{mushafRequest}/process', [App\Http\Controllers\Admin\MushafRequestController::class, 'processToShipment'])
            ->middleware('permission:mushaf-requests.process')
            ->name('mushaf-requests.process');
        Route::post('mushaf-requests/bulk-status', [App\Http\Controllers\Admin\MushafRequestController::class, 'bulkUpdateStatus'])
            ->middleware('permission:mushaf-requests.update|mushaf-requests.approve|mushaf-requests.reject')
            ->name('mushaf-requests.bulk-status');
        Route::get('mushaf-requests-export', [App\Http\Controllers\Admin\MushafRequestController::class, 'export'])
            ->middleware('permission:mushaf-requests.read')
            ->name('mushaf-requests.export');
        Route::post('mushaf-requests-import', [App\Http\Controllers\Admin\MushafRequestController::class, 'import'])
            ->middleware('permission:mushaf-requests.create')
            ->name('mushaf-requests.import');
        Route::get('mushaf-requests-template', [App\Http\Controllers\Admin\MushafRequestController::class, 'downloadTemplate'])
            ->middleware('permission:mushaf-requests.read')
            ->name('mushaf-requests.template');
        Route::patch('mushaf-requests/{mushafRequest}/quantities', [App\Http\Controllers\Admin\MushafRequestController::class, 'updateQuantities'])
            ->middleware('permission:mushaf-requests.update')
            ->name('mushaf-requests.update-quantities');
        Route::patch('mushaf-requests/{mushafRequest}/lembaga', [App\Http\Controllers\Admin\MushafRequestController::class, 'updateLembaga'])
            ->middleware('permission:mushaf-requests.update')
            ->name('mushaf-requests.update-lembaga');
        Route::patch('mushaf-requests/{mushafRequest}/kontak', [App\Http\Controllers\Admin\MushafRequestController::class, 'updateKontak'])
            ->middleware('permission:mushaf-requests.update')
            ->name('mushaf-requests.update-kontak');
        Route::post('mushaf-requests/{mushafRequest}/files', [App\Http\Controllers\Admin\MushafRequestController::class, 'updateFiles'])
            ->middleware('permission:mushaf-requests.update')
            ->name('mushaf-requests.update-files');
        Route::delete('mushaf-requests/{mushafRequest}', [App\Http\Controllers\Admin\MushafRequestController::class, 'destroy'])
            ->middleware('permission:mushaf-requests.delete')
            ->name('mushaf-requests.destroy');
        Route::get('api/donatur-list', [App\Http\Controllers\Admin\MushafRequestController::class, 'getDonaturList'])
            ->middleware('permission:mushaf-requests.read')
            ->name('api.donatur-list');

        // 📄 Certificate Management (On-Demand Token-Based)
        Route::prefix('certificates')->name('certificates.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\CertificateController::class, 'index'])
                ->middleware('permission:certificates.read')
                ->name('index');
            Route::post('/generate/batch/{wakafBatch}', [App\Http\Controllers\Admin\CertificateController::class, 'generateForBatch'])
                ->middleware('permission:certificates.create')
                ->name('generate-for-batch');
            Route::post('/regenerate/batch/{wakafBatch}', [App\Http\Controllers\Admin\CertificateController::class, 'regenerateForBatch'])
                ->middleware('permission:certificates.create')
                ->name('regenerate-for-batch');

            // New batch certificate with multiple wakif
            Route::post('/generate-batch-certificate/{wakafBatch}', [App\Http\Controllers\Admin\CertificateController::class, 'generateBatchCertificate'])
                ->middleware('permission:certificates.create')
                ->name('generate-batch-certificate');
            Route::get('/batches-ready-for-certificate', [App\Http\Controllers\Admin\CertificateController::class, 'getBatchesReadyForCertificate'])
                ->middleware('permission:certificates.read')
                ->name('batches-ready-for-certificate');

            // Bulk and consolidated certificate generation (background jobs)
            Route::post('/generate-bulk', [App\Http\Controllers\Admin\CertificateController::class, 'generateBulkCertificates'])
                ->middleware('permission:certificates.generate')
                ->name('generate-bulk');
            Route::post('/generate-consolidated', [App\Http\Controllers\Admin\CertificateController::class, 'generateConsolidatedCertificates'])
                ->middleware('permission:certificates.generate')
                ->name('generate-consolidated');

            // On-demand download and preview
            Route::get('/download/{id}', [App\Http\Controllers\Admin\CertificateController::class, 'download'])
                ->middleware('permission:certificates.download')
                ->name('download')
                ->where('id', '[0-9]+');
            Route::get('/preview/{id}', [App\Http\Controllers\Admin\CertificateController::class, 'preview'])
                ->middleware('permission:certificates.download')
                ->name('preview')
                ->where('id', '[0-9]+');
            Route::get('/view/{sertifikat}', [App\Http\Controllers\Admin\CertificateController::class, 'view'])
                ->middleware('permission:certificates.download')
                ->name('view');

            // Token generation for public sharing
            Route::post('/generate-token/{id}', [App\Http\Controllers\Admin\CertificateController::class, 'generateToken'])
                ->middleware('permission:certificates.generate')
                ->name('generate-token')
                ->where('id', '[0-9]+');

            // Management
            Route::get('/show/{id}', [App\Http\Controllers\Admin\CertificateController::class, 'show'])
                ->middleware('permission:certificates.read')
                ->name('show')
                ->where('id', '[0-9]+');
            Route::delete('/{id}', [App\Http\Controllers\Admin\CertificateController::class, 'destroy'])
                ->middleware('permission:certificates.delete')
                ->name('destroy')
                ->where('id', '[0-9]+');
            Route::patch('/{id}/mark-sent', [App\Http\Controllers\Admin\CertificateController::class, 'markAsSent'])
                ->middleware('permission:certificates.update')
                ->name('mark-sent')
                ->where('id', '[0-9]+');
        });

        // 🎨 Certificate Template Management
        Route::prefix('certificate-templates')->name('certificate-templates.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'index'])
                ->middleware('permission:templates.read')
                ->name('index');
            Route::get('/create', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'create'])
                ->middleware('permission:templates.create')
                ->name('create');
            Route::post('/', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'store'])
                ->middleware('permission:templates.create')
                ->name('store');
            Route::get('/{certificateTemplate}', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'show'])
                ->middleware('permission:templates.read')
                ->name('show');
            Route::get('/{certificateTemplate}/edit', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'edit'])
                ->middleware('permission:templates.update')
                ->name('edit');
            Route::put('/{certificateTemplate}', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'update'])
                ->middleware('permission:templates.update')
                ->name('update');
            Route::delete('/{certificateTemplate}', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'destroy'])
                ->middleware('permission:templates.delete')
                ->name('destroy');
            Route::get('/{certificateTemplate}/preview', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'preview'])
                ->middleware('permission:templates.read')
                ->name('preview');
            Route::get('/{certificateTemplate}/preview-sample', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'previewWithSampleData'])
                ->middleware('permission:templates.read')
                ->name('preview-sample');
            Route::get('/{certificateTemplate}/download', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'download'])
                ->middleware('permission:templates.read')
                ->name('download');
            Route::patch('/{certificateTemplate}/set-default', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'setDefault'])
                ->middleware('permission:templates.set-default')
                ->name('set-default');
            Route::patch('/{certificateTemplate}/toggle-status', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'toggleStatus'])
                ->middleware('permission:templates.toggle')
                ->name('toggle-status');
            Route::post('/{certificateTemplate}/update-field-position', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'updateFieldPosition'])
                ->middleware('permission:templates.update')
                ->name('update-field-position');
            Route::post('/{certificateTemplate}/update-all-positions', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'updateAllPositions'])
                ->middleware('permission:templates.update')
                ->name('update-all-positions');
            // TAMBAHAN: Route untuk generate certificate dari template
            Route::post('/{certificateTemplate}/generate-certificate', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'generateCertificate'])
                ->middleware('permission:templates.update')
                ->name('generate-certificate');
        });

        // 📦 Box Tracking Management
        Route::prefix('box-tracking')->name('box-tracking.')->middleware('permission:warehouse.boxes.view')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\BoxTrackingController::class, 'index'])->name('index');
            Route::get('/{box}', [App\Http\Controllers\Admin\BoxTrackingController::class, 'show'])->name('show');
            Route::post('/search', [App\Http\Controllers\Admin\BoxTrackingController::class, 'searchByCode'])->name('search');
            Route::get('/analytics/data', [App\Http\Controllers\Admin\BoxTrackingController::class, 'analytics'])->name('analytics');
        });

        // 📦 Box Bulk Update Operations (Phase 2)
        Route::prefix('box-bulk')->name('box-bulk.')->middleware('permission:shipments.update')->group(function () {
            Route::post('/preview', [App\Http\Controllers\Admin\BoxBulkUpdateController::class, 'preview'])->name('preview');
            Route::post('/update-status', [App\Http\Controllers\Admin\BoxBulkUpdateController::class, 'bulkUpdateStatus'])->name('update-status');
            Route::post('/update-address', [App\Http\Controllers\Admin\BoxBulkUpdateController::class, 'bulkUpdateAddress'])->name('update-address');
            Route::post('/update-both', [App\Http\Controllers\Admin\BoxBulkUpdateController::class, 'bulkUpdateBoth'])->name('update-both');
            Route::get('/statuses', [App\Http\Controllers\Admin\BoxBulkUpdateController::class, 'getAvailableStatuses'])->name('statuses');
            Route::get('/history', [App\Http\Controllers\Admin\BoxBulkUpdateController::class, 'getBulkUpdateHistory'])->name('history');
        });

        // 📊 Bulk Operations Analytics & Reporting (Phase 4)
        Route::prefix('bulk-operations')->name('bulk-operations.')->middleware('permission:system.monitor')->group(function () {
            Route::get('/dashboard', [App\Http\Controllers\Admin\BulkOperationsAnalyticsController::class, 'dashboard'])->name('dashboard');
            Route::get('/analytics', [App\Http\Controllers\Admin\BulkOperationsAnalyticsController::class, 'analytics'])->name('analytics');
            Route::get('/box-history', [App\Http\Controllers\Admin\BulkOperationsAnalyticsController::class, 'boxHistory'])->name('box-history');
            Route::get('/export-report', [App\Http\Controllers\Admin\BulkOperationsAnalyticsController::class, 'exportReport'])
                ->name('export-report');
            Route::get('/realtime-stats', [App\Http\Controllers\Admin\BulkOperationsAnalyticsController::class, 'realtimeStats'])->name('realtime-stats');
        });

        // 📊 Performance Monitoring (Super Admin Only)
        Route::prefix('performance')->name('performance.')->middleware('permission:system.monitor')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\PerformanceController::class, 'index'])->name('dashboard');
            Route::get('/api-metrics', [App\Http\Controllers\Admin\PerformanceController::class, 'apiMetrics'])->name('api-metrics');
            Route::get('/health-check', [App\Http\Controllers\Admin\PerformanceController::class, 'healthCheck'])->name('health-check');
            Route::post('/clear-cache', [App\Http\Controllers\Admin\PerformanceController::class, 'clearCache'])->name('clear-cache');
            Route::get('/export-report', [App\Http\Controllers\Admin\PerformanceController::class, 'exportReport'])
                ->name('export-report');
        });

        // 🔍 Query Optimization Dashboard (Super Admin Only)
        Route::prefix('query-optimization')->name('query-optimization.')->middleware('permission:system.monitor')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\QueryOptimizationController::class, 'dashboard'])->name('dashboard');
            Route::post('/real-time', [App\Http\Controllers\Admin\QueryOptimizationController::class, 'realTimeAnalysis'])->name('real-time');
            Route::get('/n-plus-one', [App\Http\Controllers\Admin\QueryOptimizationController::class, 'nPlusOneDetection'])->name('n-plus-one');
            Route::get('/index-recommendations', [App\Http\Controllers\Admin\QueryOptimizationController::class, 'indexRecommendations'])->name('index-recommendations');
            Route::post('/generate-migration', [App\Http\Controllers\Admin\QueryOptimizationController::class, 'generateIndexMigration'])->name('generate-migration');
            Route::post('/analyze-query', [App\Http\Controllers\Admin\QueryOptimizationController::class, 'analyzeQuery'])->name('analyze-query');
            Route::get('/performance-trends', [App\Http\Controllers\Admin\QueryOptimizationController::class, 'performanceTrends'])->name('performance-trends');
        });

        // 📊 Comprehensive Monitoring System (Super Admin Only)
        Route::prefix('monitoring')->name('monitoring.')
            ->middleware('permission:system.monitor')
            ->group(function () {
                // Main monitoring dashboard
                Route::get('/', [App\Http\Controllers\Admin\MonitoringDashboardController::class, 'index'])->name('dashboard');

                // Real-time metrics API
                Route::get('/api/metrics', [App\Http\Controllers\Admin\MonitoringDashboardController::class, 'realTimeMetrics'])->name('api.metrics');
                Route::get('/api/historical', [App\Http\Controllers\Admin\MonitoringDashboardController::class, 'historicalMetrics'])->name('api.historical');
                Route::get('/api/health', [App\Http\Controllers\Admin\MonitoringDashboardController::class, 'healthCheck'])->name('api.health');
                Route::post('/api/collect', [App\Http\Controllers\Admin\MonitoringDashboardController::class, 'collectMetrics'])->name('api.collect');

                // Performance benchmarks
                Route::get('/benchmarks', [App\Http\Controllers\Admin\MonitoringDashboardController::class, 'benchmarks'])->name('benchmarks');
                Route::post('/benchmarks/run', [App\Http\Controllers\Admin\MonitoringDashboardController::class, 'runBenchmarks'])->name('benchmarks.run');
                Route::post('/benchmarks/baselines', [App\Http\Controllers\Admin\MonitoringDashboardController::class, 'setBaselines'])->name('benchmarks.baselines');

                // Alert management
                Route::get('/alerts', [App\Http\Controllers\Admin\MonitoringDashboardController::class, 'alerts'])->name('alerts');
                Route::post('/alerts/clear', [App\Http\Controllers\Admin\MonitoringDashboardController::class, 'clearAlerts'])->name('alerts.clear');

                // Reports and recommendations
                Route::get('/reports', [App\Http\Controllers\Admin\MonitoringDashboardController::class, 'reports'])->name('reports');
                Route::get('/reports/generate', [App\Http\Controllers\Admin\MonitoringDashboardController::class, 'generateReport'])
                    ->name('reports.generate');
                Route::get('/recommendations', [App\Http\Controllers\Admin\MonitoringDashboardController::class, 'recommendations'])->name('recommendations');
            });

        // 🔧 Settings Management - REMOVED
        // All settings are now managed under Landing Content menu with dedicated controllers:
        // - General: /admin/settings/landing-content/general
        // - Landing: /admin/settings/landing-content/landing
        // - Contact: /admin/settings/landing-content/contact
        // - Social: /admin/settings/landing-content/social
        // - SEO: /admin/settings/landing-content/seo
        // - Legal: /admin/settings/landing-content/legal

        // Landing Content Settings Management
        Route::prefix('settings/landing-content')->name('settings.landing-content.')->group(function () {
            // General Settings
            Route::middleware(['permission:settings.read'])->group(function () {
                Route::get('/general', [GeneralSettingsController::class, 'index'])->name('general.index');
            });
            Route::middleware(['permission:settings.write'])->group(function () {
                Route::post('/general', [GeneralSettingsController::class, 'update'])->name('general.update');
            });

            // Landing Page Settings
            Route::middleware(['permission:settings.read'])->group(function () {
                Route::get('/landing', [LandingSettingsController::class, 'index'])->name('landing.index');
            });
            Route::middleware(['permission:settings.write'])->group(function () {
                Route::post('/landing', [LandingSettingsController::class, 'update'])->name('landing.update');
            });

            // Contact Settings
            Route::middleware(['permission:settings.read'])->group(function () {
                Route::get('/contact', [ContactSettingsController::class, 'index'])->name('contact.index');
            });
            Route::middleware(['permission:settings.write'])->group(function () {
                Route::post('/contact', [ContactSettingsController::class, 'update'])->name('contact.update');
            });

            // Social Media Settings
            Route::middleware(['permission:settings.read'])->group(function () {
                Route::get('/social', [SocialSettingsController::class, 'index'])->name('social.index');
            });
            Route::middleware(['permission:settings.write'])->group(function () {
                Route::post('/social', [SocialSettingsController::class, 'update'])->name('social.update');
            });

            // SEO Settings
            Route::middleware(['permission:settings.read'])->group(function () {
                Route::get('/seo', [SeoSettingsController::class, 'index'])->name('seo.index');
            });
            Route::middleware(['permission:settings.write'])->group(function () {
                Route::post('/seo', [SeoSettingsController::class, 'update'])->name('seo.update');
            });

            // Legal Settings
            Route::middleware(['permission:settings.read'])->group(function () {
                Route::get('/legal', [LegalSettingsController::class, 'index'])->name('legal.index');
            });
            Route::middleware(['permission:settings.write'])->group(function () {
                Route::post('/legal', [LegalSettingsController::class, 'update'])->name('legal.update');
            });
        });

        // Content Management - Testimonials, Gallery, FAQs
        // Galleries - Read access
        Route::middleware(['permission:settings.read'])->group(function () {
            Route::get('galleries', [\App\Http\Controllers\Admin\GalleryController::class, 'index'])->name('galleries.index');
        });

        // Galleries - Write access (must come before parameterized routes)
        Route::middleware(['permission:settings.write'])->group(function () {
            Route::get('galleries/create', [\App\Http\Controllers\Admin\GalleryController::class, 'create'])->name('galleries.create');
            Route::post('galleries', [\App\Http\Controllers\Admin\GalleryController::class, 'store'])->name('galleries.store');
            Route::post('galleries/update-order', [\App\Http\Controllers\Admin\GalleryController::class, 'updateOrder'])->name('galleries.update-order');
            Route::post('galleries/update-settings', [\App\Http\Controllers\Admin\GalleryController::class, 'updateSettings'])->name('galleries.update-settings');
        });

        // Galleries - Parameterized routes (must come after specific routes)
        Route::middleware(['permission:settings.read'])->group(function () {
            Route::get('galleries/{gallery}', [\App\Http\Controllers\Admin\GalleryController::class, 'show'])->name('galleries.show');
        });

        Route::middleware(['permission:settings.write'])->group(function () {
            Route::get('galleries/{gallery}/edit', [\App\Http\Controllers\Admin\GalleryController::class, 'edit'])->name('galleries.edit');
            Route::put('galleries/{gallery}', [\App\Http\Controllers\Admin\GalleryController::class, 'update'])->name('galleries.update');
            Route::post('galleries/{gallery}/toggle-status', [\App\Http\Controllers\Admin\GalleryController::class, 'toggleStatus'])->name('galleries.toggle-status');
        });
        Route::middleware(['permission:settings.delete'])->group(function () {
            Route::delete('galleries/{gallery}', [\App\Http\Controllers\Admin\GalleryController::class, 'destroy'])->name('galleries.destroy');
        });

        // Videos - Read access
        Route::middleware(['permission:settings.read'])->group(function () {
            Route::get('videos', [VideoController::class, 'index'])->name('videos.index');
        });

        // Videos - Write access (must come before parameterized routes)
        Route::middleware(['permission:settings.write'])->group(function () {
            Route::get('videos/create', [VideoController::class, 'create'])->name('videos.create');
            Route::post('videos', [VideoController::class, 'store'])->name('videos.store');
            Route::post('videos/update-order', [VideoController::class, 'updateOrder'])->name('videos.update-order');
            Route::post('videos/update-settings', [VideoController::class, 'updateSettings'])->name('videos.update-settings');
        });

        // Videos - Parameterized routes (must come after specific routes)
        Route::middleware(['permission:settings.read'])->group(function () {
            Route::get('videos/{video}', [VideoController::class, 'show'])->name('videos.show');
        });

        Route::middleware(['permission:settings.write'])->group(function () {
            Route::get('videos/{video}/edit', [VideoController::class, 'edit'])->name('videos.edit');
            Route::put('videos/{video}', [VideoController::class, 'update'])->name('videos.update');
            Route::post('videos/{video}/toggle-status', [VideoController::class, 'toggleStatus'])->name('videos.toggle-status');
        });
        Route::middleware(['permission:settings.delete'])->group(function () {
            Route::delete('videos/{video}', [VideoController::class, 'destroy'])->name('videos.destroy');
        });

        // Testimonials
        Route::middleware(['permission:settings.read'])->group(function () {
            Route::get('testimonials', [\App\Http\Controllers\Admin\TestimonialController::class, 'index'])->name('testimonials.index');
            Route::get('testimonials/{testimonial}', [\App\Http\Controllers\Admin\TestimonialController::class, 'show'])->name('testimonials.show');
        });
        Route::middleware(['permission:settings.write'])->group(function () {
            Route::get('testimonials/create', [\App\Http\Controllers\Admin\TestimonialController::class, 'create'])->name('testimonials.create');
            Route::post('testimonials', [\App\Http\Controllers\Admin\TestimonialController::class, 'store'])->name('testimonials.store');
            Route::post('testimonials/update-order', [\App\Http\Controllers\Admin\TestimonialController::class, 'updateOrder'])->name('testimonials.update-order');
            Route::post('testimonials/update-settings', [\App\Http\Controllers\Admin\TestimonialController::class, 'updateSettings'])->name('testimonials.update-settings');
        });
        Route::middleware(['permission:settings.write'])->group(function () {
            Route::get('testimonials/{testimonial}/edit', [\App\Http\Controllers\Admin\TestimonialController::class, 'edit'])->name('testimonials.edit');
            Route::match(['put', 'patch'], 'testimonials/{testimonial}', [\App\Http\Controllers\Admin\TestimonialController::class, 'update'])->name('testimonials.update');
            Route::post('testimonials/{testimonial}/toggle-status', [\App\Http\Controllers\Admin\TestimonialController::class, 'toggleStatus'])->name('testimonials.toggle-status');
        });
        Route::middleware(['permission:settings.delete'])->group(function () {
            Route::delete('testimonials/{testimonial}', [\App\Http\Controllers\Admin\TestimonialController::class, 'destroy'])->name('testimonials.destroy');
        });

        // FAQs
        Route::middleware(['permission:settings.read'])->group(function () {
            Route::get('faqs', [\App\Http\Controllers\Admin\FaqController::class, 'index'])->name('faqs.index');
            Route::get('faqs/{faq}', [\App\Http\Controllers\Admin\FaqController::class, 'show'])->name('faqs.show');
        });
        Route::middleware(['permission:settings.write'])->group(function () {
            Route::get('faqs/create', [\App\Http\Controllers\Admin\FaqController::class, 'create'])->name('faqs.create');
            Route::post('faqs', [\App\Http\Controllers\Admin\FaqController::class, 'store'])->name('faqs.store');
            Route::post('faqs/update-order', [\App\Http\Controllers\Admin\FaqController::class, 'updateOrder'])->name('faqs.update-order');
            Route::post('faqs/update-settings', [\App\Http\Controllers\Admin\FaqController::class, 'updateSettings'])->name('faqs.update-settings');
        });
        Route::middleware(['permission:settings.write'])->group(function () {
            Route::get('faqs/{faq}/edit', [\App\Http\Controllers\Admin\FaqController::class, 'edit'])->name('faqs.edit');
            Route::match(['put', 'patch'], 'faqs/{faq}', [\App\Http\Controllers\Admin\FaqController::class, 'update'])->name('faqs.update');
            Route::post('faqs/{faq}/toggle-status', [\App\Http\Controllers\Admin\FaqController::class, 'toggleStatus'])->name('faqs.toggle-status');
        });
        Route::middleware(['permission:settings.delete'])->group(function () {
            Route::delete('faqs/{faq}', [\App\Http\Controllers\Admin\FaqController::class, 'destroy'])->name('faqs.destroy');
        });

        // 🔧 Cache Management (Super Admin Only)
        Route::prefix('cache')->name('cache.')
            ->middleware('permission:system.monitor')->group(function () {
                Route::get('/', [App\Http\Controllers\Admin\CacheController::class, 'index'])->name('index');
                Route::post('/warm', [App\Http\Controllers\Admin\CacheController::class, 'warm'])->name('warm');
                Route::post('/clear', [App\Http\Controllers\Admin\CacheController::class, 'clear'])->name('clear');
                Route::get('/stats', [App\Http\Controllers\Admin\CacheController::class, 'stats'])->name('stats');
                Route::get('/health', [App\Http\Controllers\Admin\CacheController::class, 'health'])->name('health');
                Route::get('/test', [App\Http\Controllers\Admin\CacheController::class, 'test'])->name('test');
                Route::get('/monitoring', [App\Http\Controllers\Admin\CacheController::class, 'monitoring'])->name('monitoring');
                Route::post('/invalidate', [App\Http\Controllers\Admin\CacheController::class, 'invalidate'])->name('invalidate');
                Route::get('/service/{service}/stats', [App\Http\Controllers\Admin\CacheController::class, 'serviceStats'])->name('service.stats');
            });
    });

    // CS specific routes (if needed later)
    Route::middleware('permission:donatur.read')->prefix('cs')->name('cs.')->group(function () {
        // Add CS specific routes here if needed
    });

    // Courier specific routes (if needed later)
    Route::middleware('permission:shipments.read')->prefix('courier')->name('courier.')->group(function () {
        // Add courier specific routes here if needed
    });
});
