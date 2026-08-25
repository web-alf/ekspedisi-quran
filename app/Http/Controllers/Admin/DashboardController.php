<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Services\Cache\DashboardCacheService;
use App\Services\Cache\ReferenceDataCacheService;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class DashboardController extends Controller
{
    protected $analyticsService;

    protected $dashboardCache;

    protected $referenceCache;

    public function __construct(
        AnalyticsService $analyticsService,
        DashboardCacheService $dashboardCache,
        ReferenceDataCacheService $referenceCache
    ) {
        $this->analyticsService = $analyticsService;
        $this->dashboardCache = $dashboardCache;
        $this->referenceCache = $referenceCache;
    }

    public function index()
    {
        $startTime = microtime(true);

        $user = auth()->user();
        $role = $user->getRoleNames()->first() ?? 'super-admin';

        try {
            // Get cached dashboard data
            $stats = $this->dashboardCache->getStats($role);
            $activities = $this->dashboardCache->getActivities($role);
            $chartsData = $this->dashboardCache->getChartsData();

            // Get real-time metrics (not cached for freshness)
            $realTimeMetrics = $this->analyticsService->getRealTimeMetrics();

            // Get reference data (heavily cached)
            $referenceData = $this->referenceCache->getAllReferenceData();

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            Log::info('Dashboard loaded with caching', [
                'role' => $role,
                'response_time_ms' => $responseTime,
                'stats_count' => count($stats),
                'activities_count' => count($activities),
                'charts_data_keys' => array_keys($chartsData),
            ]);

            return Inertia::render('Admin/Dashboard', [
                'stats' => $stats,
                'activities' => $activities,
                'chartsData' => $chartsData,
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard caching failed, falling back to direct queries', [
                'error' => $e->getMessage(),
                'role' => $role,
            ]);

            // Fallback to original implementation
            return $this->fallbackDashboard($user, $role);
        }
    }

    /**
     * Fallback dashboard implementation without caching
     */
    private function fallbackDashboard($user, $role)
    {
        $startTime = microtime(true);

        // Get role-specific permissions (simplified)
        $permissions = $user ? $user->getAllPermissions()->pluck('name')->toArray() : [];

        // Basic stats without caching
        $stats = $this->getBasicStats($role);
        $activities = $this->getBasicActivities($role);
        $chartsData = $this->getBasicChartsData();
        $realTimeMetrics = $this->analyticsService->getRealTimeMetrics();

        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000, 2);

        Log::warning('Dashboard loaded without caching (fallback)', [
            'role' => $role,
            'response_time_ms' => $responseTime,
        ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'activities' => $activities,
            'chartsData' => $chartsData,
        ]);
    }

    /**
     * Basic stats for fallback
     */
    private function getBasicStats($role)
    {
        // Simplified stats without the complex optimizations
        return [
            'totalPengiriman' => \App\Models\Pengiriman::count(),
            'totalDonatur' => \App\Models\Donatur::count(),
            'totalMushafRequests' => \App\Models\MushafRequest::count(),
            'totalUsers' => \App\Models\User::count(),
        ];
    }

    /**
     * Basic activities for fallback
     */
    private function getBasicActivities($role)
    {
        // Simplified activities
        $activities = [];

        $recentShipments = \App\Models\Pengiriman::with(['donatur:id,nama_donatur', 'status:id,nama'])
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        foreach ($recentShipments as $shipment) {
            $activities[] = [
                'id' => 'shipment_'.$shipment->id,
                'type' => 'shipment',
                'title' => 'Pengiriman '.$shipment->no_resi,
                'description' => $shipment->jumlah_quran.' mushaf untuk '.($shipment->donatur->nama_donatur ?? 'Donatur'),
                'timestamp' => $shipment->created_at->diffForHumans(),
                'status' => strtolower($shipment->status->nama ?? 'pending'),
                'icon' => '📦',
            ];
        }

        return array_slice($activities, 0, 5);
    }

    /**
     * Basic charts data for fallback
     * Must match the same structure as DashboardCacheService::computeChartsData()
     */
    private function getBasicChartsData()
    {
        try {
            // Return the same structure as DashboardCacheService
            return [
                'monthlyShipments' => $this->getMonthlyShipments(),
                'monthlyMushafRequests' => $this->getMonthlyMushafRequests(),
                'statusDistribution' => $this->getStatusDistribution(),
                'topDonatur' => $this->getTopDonatur(),
                'dailyActivities' => $this->getDailyActivities(),
            ];
        } catch (\Exception $e) {
            Log::error('Charts data computation failed in fallback', [
                'error' => $e->getMessage(),
            ]);

            return [
                'monthlyShipments' => [],
                'monthlyMushafRequests' => [],
                'statusDistribution' => [],
                'topDonatur' => [],
                'dailyActivities' => [],
            ];
        }
    }

    /**
     * Get monthly shipments data for charts
     */
    private function getMonthlyShipments(int $months = 6): array
    {
        $monthlyData = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subMonths($i);
            $month = $date->format('M Y');

            $count = \App\Models\Pengiriman::whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();

            $monthlyData[] = [
                'month' => $month,
                'count' => $count,
            ];
        }

        return $monthlyData;
    }

    /**
     * Get monthly mushaf requests data for charts
     */
    private function getMonthlyMushafRequests(int $months = 6): array
    {
        $monthlyData = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subMonths($i);
            $month = $date->format('M Y');

            $count = \App\Models\MushafRequest::whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();

            $monthlyData[] = [
                'month' => $month,
                'count' => $count,
            ];
        }

        return $monthlyData;
    }

    /**
     * Get status distribution data for charts
     */
    private function getStatusDistribution(): array
    {
        return \App\Models\StatusPengiriman::withCount('pengiriman')
            ->where('is_active', true)
            ->get()
            ->map(function ($status) {
                return [
                    'name' => $status->nama,
                    'slug' => $status->slug,
                    'count' => $status->pengiriman_count,
                    'color' => $this->getStatusColor($status->slug),
                ];
            })
            ->filter(fn ($status) => $status['count'] > 0)
            ->values()
            ->toArray();
    }

    /**
     * Get top donatur data for charts
     */
    private function getTopDonatur(int $limit = 5): array
    {
        return \App\Models\Donatur::select('id', 'nama_donatur')
            ->withSum('pengiriman', 'jumlah_quran')
            ->orderBy('pengiriman_sum_jumlah_quran', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($donatur) {
                return [
                    'name' => $donatur->nama_donatur,
                    'total_mushaf' => $donatur->pengiriman_sum_jumlah_quran ?? 0,
                ];
            })
            ->toArray();
    }

    /**
     * Get daily activities for the last 7 days
     */
    private function getDailyActivities(): array
    {
        $dailyActivities = [];

        // Prepare date array for the last 7 days
        for ($i = 6; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subDays($i);
            $dateString = $date->toDateString();

            // Count shipments and mushaf sent for this date
            $shipments = \App\Models\Pengiriman::whereDate('updated_at', $dateString)
                ->whereHas('status', function ($query) {
                    $query->where('slug', 'pengiriman');
                })
                ->count();

            $completedShipments = \App\Models\Pengiriman::whereDate('updated_at', $dateString)
                ->whereHas('status', function ($query) {
                    $query->where('slug', 'diterima');
                })
                ->count();

            $mushafSent = \App\Models\Pengiriman::whereDate('updated_at', $dateString)
                ->whereHas('status', function ($query) {
                    $query->whereIn('slug', ['pengiriman', 'diterima']);
                })
                ->sum('jumlah_quran');

            $dailyActivities[] = [
                'day' => $date->format('D'),
                'shipments' => $shipments,
                'requests' => \App\Models\MushafRequest::whereDate('created_at', $dateString)->count(),
                'mushaf_sent' => (int) $mushafSent,
                'completed_shipments' => $completedShipments,
            ];
        }

        return $dailyActivities;
    }

    /**
     * Get status color mapping
     */
    private function getStatusColor(string $slug): string
    {
        return match ($slug) {
            'diterima' => '#10b981',
            'pengiriman', 'selesai-packing' => '#3b82f6',
            'pemesanan' => '#f59e0b',
            'produksi', 'kedatangan', 'packing' => '#8b5cf6',
            'batal' => '#ef4444',
            default => '#6b7280'
        };
    }

    /**
     * Get role-specific permissions
     */
    private function getRolePermissions($role)
    {
        $user = auth()->user();

        return $user ? $user->getAllPermissions()->pluck('name')->toArray() : [];
    }
}
