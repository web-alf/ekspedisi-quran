<?php

namespace App\Services\Cache;

use App\Models\CertificateTemplate;
use App\Models\Donatur;
use App\Models\MushafRequest;
use App\Models\Pengiriman;
use App\Models\Sertifikat;
use App\Models\StatusHistory;
use App\Models\StatusPengiriman;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardCacheService extends BaseCacheService
{
    protected string $prefix = 'dashboard';

    protected int $defaultTtl = 300; // 5 minutes for dashboard stats

    protected array $tags = ['dashboard', 'stats'];

    /**
     * Get dashboard statistics with caching
     */
    public function getStats(string $role = 'super-admin'): array
    {
        $cacheKey = "stats_{$role}_".$this->getStatsCacheKey();

        return $this->remember($cacheKey, function () use ($role) {
            return $this->computeStats($role);
        }, $this->defaultTtl, ['dashboard', 'stats', 'role_'.$role]);
    }

    /**
     * Get dashboard activities with caching
     */
    public function getActivities(string $role = 'super-admin'): array
    {
        $cacheKey = "activities_{$role}_".$this->getActivitiesCacheKey();

        return $this->remember($cacheKey, function () use ($role) {
            return $this->computeActivities($role);
        }, 180, ['dashboard', 'activities', 'role_'.$role]); // 3 minutes
    }

    /**
     * Get dashboard charts data with caching
     */
    public function getChartsData(): array
    {
        $cacheKey = 'charts_'.$this->getChartsCacheKey();

        return $this->remember($cacheKey, function () {
            return $this->computeChartsData();
        }, 600, ['dashboard', 'charts']); // 10 minutes
    }

    /**
     * Get status distribution with caching
     */
    public function getStatusDistribution(): array
    {
        return $this->remember('status_distribution', function () {
            return StatusPengiriman::withCount('pengiriman')
                ->where('is_active', true)
                ->get()
                ->map(function ($status) {
                    return [
                        'name' => $status->nama,
                        'slug' => $status->slug,
                        'count' => $status->pengiriman_count,
                        'color' => $this->getStatusColor($status->slug),
                        'category' => $this->getStatusCategory($status->slug),
                    ];
                })
                ->filter(fn ($status) => $status['count'] > 0)
                ->values()
                ->toArray();
        }, 600, ['dashboard', 'charts', 'status']);
    }

    /**
     * Get top donatur with caching
     */
    public function getTopDonatur(int $limit = 5): array
    {
        return $this->remember("top_donatur_{$limit}", function () use ($limit) {
            return Donatur::select('id', 'nama_donatur')
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
        }, 900, ['dashboard', 'charts', 'donatur']); // 15 minutes
    }

    /**
     * Get monthly statistics
     */
    public function getMonthlyStats(int $months = 6): array
    {
        return $this->remember("monthly_stats_{$months}", function () use ($months) {
            $monthlyData = [
                'shipments' => [],
                'requests' => [],
            ];

            for ($i = $months - 1; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $month = $date->format('M Y');

                $shipmentsCount = Pengiriman::whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->count();

                $requestsCount = MushafRequest::whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->count();

                $monthlyData['shipments'][] = [
                    'month' => $month,
                    'count' => $shipmentsCount,
                ];

                $monthlyData['requests'][] = [
                    'month' => $month,
                    'count' => $requestsCount,
                ];
            }

            return $monthlyData;
        }, 1800, ['dashboard', 'charts', 'monthly']); // 30 minutes
    }

    /**
     * Get daily activities for the last week
     */
    public function getDailyActivities(): array
    {
        return $this->remember('daily_activities', function () {
            return $this->computeDailyActivities();
        }, 600, ['dashboard', 'charts', 'daily']); // 10 minutes
    }

    /**
     * Compute dashboard statistics
     */
    private function computeStats(string $role): array
    {
        // Base counts
        $stats = [
            'totalPengiriman' => Pengiriman::count(),
            'totalDonatur' => Donatur::count(),
            'totalMushafRequests' => MushafRequest::count(),
            'totalUsers' => User::count(),
            'totalCertificates' => Sertifikat::count(),
            'totalTemplates' => CertificateTemplate::count(),
        ];

        // Optimized shipment stats with single query
        try {
            $shipmentStats = DB::table('pengiriman')
                ->join('status_pengiriman', 'pengiriman.status_id', '=', 'status_pengiriman.id')
                ->selectRaw('
                    COUNT(CASE WHEN status_pengiriman.slug IN ("pemesanan", "produksi") THEN 1 END) as pending_shipments,
                    COUNT(CASE WHEN status_pengiriman.slug IN ("kedatangan", "packing", "selesai-packing", "pengiriman") THEN 1 END) as in_transit_shipments,
                    COUNT(CASE WHEN status_pengiriman.slug = "diterima" THEN 1 END) as completed_shipments,
                    COUNT(CASE WHEN status_pengiriman.slug = "batal" THEN 1 END) as cancelled_shipments,
                    SUM(CASE WHEN status_pengiriman.slug = "diterima" THEN pengiriman.jumlah_quran ELSE 0 END) as total_mushaf_distributed
                ')
                ->first();

            $stats = array_merge($stats, [
                'pendingShipments' => (int) $shipmentStats->pending_shipments,
                'inTransitShipments' => (int) $shipmentStats->in_transit_shipments,
                'completedShipments' => (int) $shipmentStats->completed_shipments,
                'cancelledShipments' => (int) $shipmentStats->cancelled_shipments,
                'totalMushafDistributed' => (int) $shipmentStats->total_mushaf_distributed,
            ]);
        } catch (\Exception $e) {
            Log::warning('Dashboard stats computation failed, using fallback', [
                'error' => $e->getMessage(),
            ]);

            // Fallback to individual queries
            $stats = array_merge($stats, $this->getFallbackShipmentStats());
        }

        // Mushaf request statistics
        $stats['pendingMushafRequests'] = MushafRequest::where('status', 'pending')->count();
        $stats['approvedMushafRequests'] = MushafRequest::where('status', 'approved')->count();
        $stats['rejectedMushafRequests'] = MushafRequest::where('status', 'rejected')->count();

        // Monthly statistics
        $currentMonth = Carbon::now();
        $stats['monthlyPengiriman'] = Pengiriman::whereMonth('created_at', $currentMonth->month)
            ->whereYear('created_at', $currentMonth->year)
            ->count();

        $stats['monthlyMushafRequests'] = MushafRequest::whereMonth('created_at', $currentMonth->month)
            ->whereYear('created_at', $currentMonth->year)
            ->count();

        $stats['totalWakif'] = Donatur::whereMonth('created_at', $currentMonth->month)
            ->whereYear('created_at', $currentMonth->year)
            ->count();

        // Average delivery days
        $avgDeliveryDays = Pengiriman::whereNotNull('received_at')
            ->whereNotNull('created_at')
            ->selectRaw('AVG(DATEDIFF(received_at, created_at)) as avg_days')
            ->value('avg_days');
        $stats['avgDeliveryDays'] = $avgDeliveryDays ? round($avgDeliveryDays, 1) : 0;

        return $this->filterStatsByRole($stats, $role);
    }

    /**
     * Compute dashboard activities
     */
    private function computeActivities(string $role): array
    {
        $activities = [];

        // Recent shipments
        $recentShipments = Pengiriman::with([
            'donatur:id,nama_donatur',
            'status:id,nama',
        ])
            ->select('id', 'no_resi', 'jumlah_quran', 'donatur_id', 'status_id', 'created_at')
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

        // Recent mushaf requests - ✅ FIX: Use approved quantities with fallback
        $recentRequests = MushafRequest::select('id', 'no_request', 'nama_lembaga', 'jumlah_mushaf', 'jumlah_mushaf_approved', 'jumlah_iqra', 'jumlah_iqra_approved', 'status', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        foreach ($recentRequests as $request) {
            // Use approved quantities with fallback to requested
            $totalMushaf = ($request->jumlah_mushaf_approved ?? $request->jumlah_mushaf) +
                          ($request->jumlah_iqra_approved ?? $request->jumlah_iqra);

            $activities[] = [
                'id' => 'request_'.$request->id,
                'type' => 'mushaf_request',
                'title' => 'Permintaan Mushaf '.$request->no_request,
                'description' => 'Dari '.$request->nama_lembaga.' - '.$totalMushaf.' buah',
                'timestamp' => $request->created_at->diffForHumans(),
                'status' => $request->status,
                'icon' => '📖',
            ];
        }

        // Recent status changes
        $recentStatusChanges = StatusHistory::with([
            'pengiriman:id,no_resi',
            'statusTo:id,nama',
        ])
            ->select('id', 'pengiriman_id', 'status_to', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        foreach ($recentStatusChanges as $history) {
            $activities[] = [
                'id' => 'status_'.$history->id,
                'type' => 'status_change',
                'title' => 'Status Diupdate',
                'description' => $history->pengiriman->no_resi.' → '.($history->statusTo->nama ?? 'Status Baru'),
                'timestamp' => $history->created_at->diffForHumans(),
                'status' => 'completed',
                'icon' => '🔄',
            ];
        }

        // Sort by timestamp and return latest 8
        usort($activities, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return array_slice($activities, 0, 8);
    }

    /**
     * Compute charts data
     */
    private function computeChartsData(): array
    {
        return [
            'monthlyShipments' => $this->getMonthlyStats()['shipments'],
            'monthlyMushafRequests' => $this->getMonthlyStats()['requests'],
            'statusDistribution' => $this->getStatusDistribution(),
            'topDonatur' => $this->getTopDonatur(),
            'dailyActivities' => $this->getDailyActivities(),
        ];
    }

    /**
     * Compute daily activities for the last week
     */
    private function computeDailyActivities(): array
    {
        $dates = [];
        $dailyActivities = [];

        // Prepare date array
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dates[] = $date->toDateString();
            $dailyActivities[$date->toDateString()] = [
                'day' => $date->format('D'),
                'shipments' => 0,
                'requests' => 0,
                'mushaf_sent' => 0,
                'completed_shipments' => 0,
            ];
        }

        // Single query for pengiriman statistics
        $pengirimanStats = DB::table('pengiriman')
            ->join('status_pengiriman', 'pengiriman.status_id', '=', 'status_pengiriman.id')
            ->whereIn(DB::raw('DATE(pengiriman.updated_at)'), $dates)
            ->selectRaw('
                DATE(pengiriman.updated_at) as date,
                COUNT(CASE WHEN status_pengiriman.slug = "pengiriman" THEN 1 END) as shipments_count,
                SUM(CASE WHEN status_pengiriman.slug IN ("pengiriman", "diterima") THEN pengiriman.jumlah_quran ELSE 0 END) as mushaf_sent_count,
                COUNT(CASE WHEN status_pengiriman.slug = "diterima" THEN 1 END) as completed_count
            ')
            ->groupBy(DB::raw('DATE(pengiriman.updated_at)'))
            ->get()
            ->keyBy('date');

        // Single query for mushaf requests
        $requestStats = MushafRequest::whereIn(DB::raw('DATE(created_at)'), $dates)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as requests_count')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get()
            ->keyBy('date');

        // Merge data
        foreach ($dailyActivities as $date => $activity) {
            if (isset($pengirimanStats[$date])) {
                $stats = $pengirimanStats[$date];
                $dailyActivities[$date]['shipments'] = (int) $stats->shipments_count;
                $dailyActivities[$date]['mushaf_sent'] = (int) $stats->mushaf_sent_count;
                $dailyActivities[$date]['completed_shipments'] = (int) $stats->completed_count;
            }

            if (isset($requestStats[$date])) {
                $dailyActivities[$date]['requests'] = (int) $requestStats[$date]->requests_count;
            }
        }

        return array_values($dailyActivities);
    }

    /**
     * Generate cache key for stats based on time interval
     */
    private function getStatsCacheKey(): string
    {
        // Update cache every 5 minutes
        return Carbon::now()->format('Y-m-d-H').'_'.floor(Carbon::now()->minute / 5);
    }

    /**
     * Generate cache key for activities based on time interval
     */
    private function getActivitiesCacheKey(): string
    {
        // Update cache every 3 minutes
        return Carbon::now()->format('Y-m-d-H').'_'.floor(Carbon::now()->minute / 3);
    }

    /**
     * Generate cache key for charts based on time interval
     */
    private function getChartsCacheKey(): string
    {
        // Update cache every 10 minutes
        return Carbon::now()->format('Y-m-d-H').'_'.floor(Carbon::now()->minute / 10);
    }

    /**
     * Fallback shipment stats calculation
     */
    private function getFallbackShipmentStats(): array
    {
        return [
            'pendingShipments' => Pengiriman::whereHas('status', function ($query) {
                $query->whereIn('slug', ['pemesanan', 'produksi']);
            })->count(),
            'inTransitShipments' => Pengiriman::whereHas('status', function ($query) {
                $query->whereIn('slug', ['kedatangan', 'packing', 'selesai-packing', 'pengiriman']);
            })->count(),
            'completedShipments' => Pengiriman::whereHas('status', function ($query) {
                $query->where('slug', 'diterima');
            })->count(),
            'cancelledShipments' => Pengiriman::whereHas('status', function ($query) {
                $query->where('slug', 'batal');
            })->count(),
            'totalMushafDistributed' => Pengiriman::whereHas('status', function ($query) {
                $query->where('slug', 'diterima');
            })->sum('jumlah_quran'),
        ];
    }

    /**
     * Filter stats based on user role
     */
    private function filterStatsByRole(array $stats, string $role): array
    {
        return match ($role) {
            'customer-service' => array_intersect_key($stats, array_flip([
                'totalMushafRequests', 'pendingMushafRequests', 'approvedMushafRequests',
                'rejectedMushafRequests', 'totalDonatur', 'monthlyMushafRequests',
            ])),
            'warehouse' => array_intersect_key($stats, array_flip([
                'totalPengiriman', 'pendingShipments', 'inTransitShipments',
                'completedShipments', 'totalMushafDistributed', 'monthlyPengiriman',
            ])),
            'courier' => array_intersect_key($stats, array_flip([
                'pendingShipments', 'inTransitShipments', 'completedShipments', 'totalMushafDistributed',
            ])),
            default => $stats
        };
    }

    /**
     * Get status color
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
     * Get status category
     */
    private function getStatusCategory(string $slug): string
    {
        return match ($slug) {
            'pemesanan', 'produksi' => 'pending',
            'kedatangan', 'packing', 'selesai-packing', 'pengiriman' => 'in_transit',
            'diterima' => 'completed',
            'batal' => 'cancelled',
            default => 'unknown'
        };
    }

    /**
     * Invalidate dashboard cache when data changes
     */
    public function invalidate(array $specificTags = []): bool
    {
        $tagsToInvalidate = empty($specificTags) ? $this->tags : $specificTags;

        Log::info('Invalidating dashboard cache', ['tags' => $tagsToInvalidate]);

        return $this->flushByTags($tagsToInvalidate);
    }

    /**
     * Warm dashboard cache
     */
    public function warm(): bool
    {
        try {
            Log::info('Warming dashboard cache...');

            // Warm basic stats for all roles
            $roles = ['super-admin', 'warehouse', 'customer-service', 'courier'];
            foreach ($roles as $role) {
                $this->getStats($role);
                $this->getActivities($role);
            }

            // Warm charts data
            $this->getChartsData();
            $this->getStatusDistribution();
            $this->getTopDonatur();
            $this->getDailyActivities();
            $this->getMonthlyStats();

            Log::info('Dashboard cache warmed successfully');

            return true;
        } catch (\Exception $e) {
            Log::error('Dashboard cache warming failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
