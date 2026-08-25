<?php

namespace App\Services;

use App\Models\Pengiriman;
use App\Models\Donatur;
use App\Models\MushafRequest;
use App\Models\StatusPengiriman;
use App\Models\DailyPackingTask;
use App\Models\UserPerformance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AnalyticsService
{
    /**
     * Get shipment analytics data
     */
    public function getShipmentAnalytics($period = '30d')
    {
        $cacheKey = "shipment_analytics_{$period}";
        
        return Cache::remember($cacheKey, 300, function() use ($period) {
            $startDate = $this->getStartDate($period);
            
            return [
                'daily_shipments' => $this->getDailyShipments($startDate),
                'status_distribution' => $this->getStatusDistribution(),
                'delivery_performance' => $this->getDeliveryPerformance($startDate),
                'regional_distribution' => $this->getRegionalDistribution($startDate),
                'monthly_trends' => $this->getMonthlyTrends(),
                'completion_rate' => $this->getCompletionRate($startDate)
            ];
        });
    }

    /**
     * Get donatur analytics
     */
    public function getDonaturAnalytics($period = '30d')
    {
        $cacheKey = "donatur_analytics_{$period}";
        
        return Cache::remember($cacheKey, 300, function() use ($period) {
            $startDate = $this->getStartDate($period);
            
            return [
                'new_donatur_trend' => $this->getNewDonaturTrend($startDate),
                'top_donatur' => $this->getTopDonatur($startDate),
                'donation_distribution' => $this->getDonationDistribution($startDate),
                'retention_rate' => $this->getDonaturRetentionRate(),
                'average_donation' => $this->getAverageDonation($startDate)
            ];
        });
    }

    /**
     * Get warehouse performance analytics
     */
    public function getWarehouseAnalytics($period = '30d')
    {
        $cacheKey = "warehouse_analytics_{$period}";
        
        return Cache::remember($cacheKey, 300, function() use ($period) {
            $startDate = $this->getStartDate($period);
            
            return [
                'daily_productivity' => $this->getDailyProductivity($startDate),
                'user_performance' => $this->getUserPerformanceStats($startDate),
                'packing_efficiency' => $this->getPackingEfficiency($startDate),
                'completion_trends' => $this->getCompletionTrends($startDate),
                'workload_distribution' => $this->getWorkloadDistribution($startDate)
            ];
        });
    }

    /**
     * Get comprehensive dashboard charts data
     */
    public function getDashboardCharts()
    {
        return [
            'overview_stats' => $this->getOverviewStats(),
            'shipment_timeline' => $this->getShipmentTimeline(),
            'status_flow' => $this->getStatusFlow(),
            'performance_metrics' => $this->getPerformanceMetrics(),
            'geographic_data' => $this->getGeographicData(),
            'trend_analysis' => $this->getTrendAnalysis()
        ];
    }

    /**
     * Get real-time metrics
     */
    public function getRealTimeMetrics()
    {
        return [
            'active_shipments' => $this->getActiveShipmentsCount(),
            'pending_tasks' => $this->getPendingTasksCount(),
            'today_completed' => $this->getTodayCompletedCount(),
            'avg_processing_time' => $this->getAverageProcessingTime(),
            'efficiency_score' => $this->getEfficiencyScore()
        ];
    }

    // Private helper methods

    private function getStartDate($period)
    {
        return match($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            '1y' => now()->subYear(),
            default => now()->subDays(30)
        };
    }

    private function getDailyShipments($startDate)
    {
        return Pengiriman::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function($item) {
                return [
                    'date' => Carbon::parse($item->date)->format('Y-m-d'),
                    'count' => $item->count,
                    'formatted_date' => Carbon::parse($item->date)->format('d M')
                ];
            });
    }

    private function getStatusDistribution()
    {
        $statusData = Pengiriman::join('status_pengiriman', 'pengiriman.status_id', '=', 'status_pengiriman.id')
            ->selectRaw('status_pengiriman.nama as status_name, status_pengiriman.warna as color, COUNT(*) as count')
            ->groupBy('status_pengiriman.id', 'status_pengiriman.nama', 'status_pengiriman.warna')
            ->get();

        return $statusData->map(function($item) {
            return [
                'label' => $item->status_name,
                'value' => $item->count,
                'color' => $this->getChartColor($item->color),
                'percentage' => 0 // Will be calculated in frontend
            ];
        });
    }

    private function getDeliveryPerformance($startDate)
    {
        $onTimeDeliveries = Pengiriman::where('created_at', '>=', $startDate)
            ->whereHas('status', function($query) {
                $query->where('slug', 'diterima');
            })
            ->where('updated_at', '<=', DB::raw('DATE_ADD(created_at, INTERVAL 14 DAY)'))
            ->count();

        $totalCompleted = Pengiriman::where('created_at', '>=', $startDate)
            ->whereHas('status', function($query) {
                $query->where('slug', 'diterima');
            })
            ->count();

        return [
            'on_time' => $onTimeDeliveries,
            'total_completed' => $totalCompleted,
            'on_time_percentage' => $totalCompleted > 0 ? round(($onTimeDeliveries / $totalCompleted) * 100, 2) : 0,
            'average_days' => $this->getAverageDeliveryDays($startDate)
        ];
    }

    private function getRegionalDistribution($startDate)
    {
        return MushafRequest::where('created_at', '>=', $startDate)
            ->selectRaw('provinsi, COUNT(*) as count')
            ->whereNotNull('provinsi')
            ->groupBy('provinsi')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function($item) {
                return [
                    'region' => $item->provinsi,
                    'count' => $item->count
                ];
            });
    }

    private function getMonthlyTrends()
    {
        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $shipments = Pengiriman::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
            
            $months->push([
                'month' => $date->format('M Y'),
                'shipments' => $shipments,
                'date' => $date->format('Y-m')
            ]);
        }
        
        return $months;
    }

    private function getCompletionRate($startDate)
    {
        $total = Pengiriman::where('created_at', '>=', $startDate)->count();
        $completed = Pengiriman::where('created_at', '>=', $startDate)
            ->whereHas('status', function($query) {
                $query->where('slug', 'diterima');
            })
            ->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0
        ];
    }

    private function getNewDonaturTrend($startDate)
    {
        return Donatur::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count
                ];
            });
    }

    private function getTopDonatur($startDate)
    {
        return Donatur::withCount(['pengiriman' => function($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate);
            }])
            ->having('pengiriman_count', '>', 0)
            ->orderByDesc('pengiriman_count')
            ->limit(10)
            ->get()
            ->map(function($donatur) {
                return [
                    'name' => $donatur->nama_donatur,
                    'shipments' => $donatur->pengiriman_count,
                    'code' => $donatur->kode_donatur
                ];
            });
    }

    private function getDonationDistribution($startDate)
    {
        return Pengiriman::where('created_at', '>=', $startDate)
            ->join('jenis_quran', 'pengiriman.jenis_quran_id', '=', 'jenis_quran.id')
            ->selectRaw('jenis_quran.nama_jenis as type, COUNT(*) as count')
            ->groupBy('jenis_quran.id', 'jenis_quran.nama_jenis')
            ->get()
            ->map(function($item) {
                return [
                    'type' => $item->type,
                    'count' => $item->count
                ];
            });
    }

    private function getDonaturRetentionRate()
    {
        $thirtyDaysAgo = now()->subDays(30);
        $sixtyDaysAgo = now()->subDays(60);
        
        $oldDonatur = Donatur::where('created_at', '<=', $sixtyDaysAgo)->pluck('id');
        $returningDonatur = Pengiriman::where('created_at', '>=', $thirtyDaysAgo)
            ->whereIn('donatur_id', $oldDonatur)
            ->distinct('donatur_id')
            ->count();

        return [
            'total_old_donatur' => $oldDonatur->count(),
            'returning_donatur' => $returningDonatur,
            'retention_rate' => $oldDonatur->count() > 0 ? round(($returningDonatur / $oldDonatur->count()) * 100, 2) : 0
        ];
    }

    private function getAverageDonation($startDate)
    {
        return Pengiriman::where('created_at', '>=', $startDate)
            ->selectRaw('AVG(jumlah_quran) as avg_quran')
            ->first()
            ->avg_quran ?? 0;
    }

    private function getDailyProductivity($startDate)
    {
        return DailyPackingTask::where('tanggal_tugas', '>=', $startDate)
            ->selectRaw('DATE(tanggal_tugas) as date, SUM(total_selesai) as completed, SUM(total_target) as target')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->date,
                    'completed' => $item->completed,
                    'target' => $item->target,
                    'efficiency' => $item->target > 0 ? round(($item->completed / $item->target) * 100, 2) : 0
                ];
            });
    }

    private function getUserPerformanceStats($startDate)
    {
        return DailyPackingTask::join('users', 'daily_packing_tasks.user_id', '=', 'users.id')
            ->where('daily_packing_tasks.tanggal_tugas', '>=', $startDate)
            ->selectRaw('users.name, AVG((total_selesai/total_target)*100) as avg_efficiency, SUM(total_selesai) as total_completed')
            ->groupBy('users.id', 'users.name')
            ->having('total_completed', '>', 0)
            ->orderByDesc('avg_efficiency')
            ->get()
            ->map(function($item) {
                return [
                    'user' => $item->name,
                    'efficiency' => round($item->avg_efficiency, 2),
                    'total_completed' => $item->total_completed
                ];
            });
    }

    private function getPackingEfficiency($startDate)
    {
        $tasks = DailyPackingTask::where('tanggal_tugas', '>=', $startDate)->get();
        
        $totalTarget = $tasks->sum('total_target');
        $totalCompleted = $tasks->sum('total_selesai');
        
        return [
            'overall_efficiency' => $totalTarget > 0 ? round(($totalCompleted / $totalTarget) * 100, 2) : 0,
            'total_target' => $totalTarget,
            'total_completed' => $totalCompleted,
            'tasks_count' => $tasks->count()
        ];
    }

    private function getCompletionTrends($startDate)
    {
        return DailyPackingTask::where('tanggal_tugas', '>=', $startDate)
            ->selectRaw('DATE(tanggal_tugas) as date, 
                        COUNT(*) as total_tasks,
                        SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_tasks')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->date,
                    'completion_rate' => $item->total_tasks > 0 ? round(($item->completed_tasks / $item->total_tasks) * 100, 2) : 0
                ];
            });
    }

    private function getWorkloadDistribution($startDate)
    {
        return DailyPackingTask::join('users', 'daily_packing_tasks.user_id', '=', 'users.id')
            ->where('daily_packing_tasks.tanggal_tugas', '>=', $startDate)
            ->selectRaw('users.name, SUM(total_target) as workload')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('workload')
            ->get()
            ->map(function($item) {
                return [
                    'user' => $item->name,
                    'workload' => $item->workload
                ];
            });
    }

    // Dashboard-specific methods
    private function getOverviewStats()
    {
        return [
            'total_shipments' => Pengiriman::count(),
            'completed_today' => Pengiriman::whereDate('updated_at', today())
                ->whereHas('status', fn($q) => $q->where('slug', 'diterima'))
                ->count(),
            'pending_tasks' => DailyPackingTask::where('status', 'in_progress')->count(),
            'active_donatur' => Donatur::whereHas('pengiriman', fn($q) => $q->where('created_at', '>=', now()->subDays(30)))->count()
        ];
    }

    private function getShipmentTimeline()
    {
        return Pengiriman::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->date,
                    'shipments' => $item->count
                ];
            });
    }

    private function getStatusFlow()
    {
        $statuses = StatusPengiriman::withCount('pengiriman')
            ->orderBy('urutan')
            ->get();

        return $statuses->map(function($status) {
            return [
                'status' => $status->nama,
                'count' => $status->pengiriman_count,
                'color' => $this->getChartColor($status->warna)
            ];
        });
    }

    private function getPerformanceMetrics()
    {
        $lastWeek = now()->subDays(7);
        
        return [
            'avg_processing_time' => $this->getAverageProcessingTime(),
            'completion_rate' => $this->getWeeklyCompletionRate(),
            'efficiency_score' => $this->getEfficiencyScore(),
            'error_rate' => $this->getErrorRate()
        ];
    }

    private function getGeographicData()
    {
        return MushafRequest::selectRaw('provinsi, COUNT(*) as count')
            ->whereNotNull('provinsi')
            ->where('created_at', '>=', now()->subDays(90))
            ->groupBy('provinsi')
            ->orderByDesc('count')
            ->limit(15)
            ->get()
            ->map(function($item) {
                return [
                    'province' => $item->provinsi,
                    'requests' => $item->count
                ];
            });
    }

    private function getTrendAnalysis()
    {
        $thisMonth = Pengiriman::whereMonth('created_at', now()->month)->count();
        $lastMonth = Pengiriman::whereMonth('created_at', now()->subMonth()->month)->count();
        
        $growth = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 2) : 0;
        
        return [
            'monthly_growth' => $growth,
            'trend_direction' => $growth > 0 ? 'up' : ($growth < 0 ? 'down' : 'stable'),
            'this_month' => $thisMonth,
            'last_month' => $lastMonth
        ];
    }

    // Utility methods
    private function getChartColor($colorName)
    {
        return match($colorName) {
            'blue' => '#3B82F6',
            'green' => '#10B981',
            'yellow' => '#F59E0B',
            'red' => '#EF4444',
            'purple' => '#8B5CF6',
            'indigo' => '#6366F1',
            'gray' => '#6B7280',
            default => '#6B7280'
        };
    }

    private function getActiveShipmentsCount()
    {
        return Pengiriman::whereHas('status', function($query) {
            $query->where('is_final', false);
        })->count();
    }

    private function getPendingTasksCount()
    {
        return DailyPackingTask::where('status', 'in_progress')->count();
    }

    private function getTodayCompletedCount()
    {
        return DailyPackingTask::whereDate('tanggal_tugas', today())
            ->where('status', 'completed')
            ->count();
    }

    private function getAverageProcessingTime()
    {
        // Calculate average time from creation to completion
        $completed = Pengiriman::whereHas('status', fn($q) => $q->where('slug', 'diterima'))
            ->selectRaw('AVG(DATEDIFF(updated_at, created_at)) as avg_days')
            ->first();
            
        return round($completed->avg_days ?? 0, 1);
    }

    private function getEfficiencyScore()
    {
        $recentTasks = DailyPackingTask::where('tanggal_tugas', '>=', now()->subDays(7))->get();
        
        if ($recentTasks->isEmpty()) return 0;
        
        $totalTarget = $recentTasks->sum('total_target');
        $totalCompleted = $recentTasks->sum('total_selesai');
        
        return $totalTarget > 0 ? round(($totalCompleted / $totalTarget) * 100, 1) : 0;
    }

    private function getWeeklyCompletionRate()
    {
        $total = Pengiriman::where('created_at', '>=', now()->subDays(7))->count();
        $completed = Pengiriman::where('created_at', '>=', now()->subDays(7))
            ->whereHas('status', fn($q) => $q->where('slug', 'diterima'))
            ->count();
            
        return $total > 0 ? round(($completed / $total) * 100, 1) : 0;
    }

    private function getErrorRate()
    {
        // This would be calculated from error logs or monitoring data
        return Cache::get('system_error_rate', 2.1);
    }

    private function getAverageDeliveryDays($startDate)
    {
        $avgDays = Pengiriman::where('created_at', '>=', $startDate)
            ->whereHas('status', fn($q) => $q->where('slug', 'diterima'))
            ->selectRaw('AVG(DATEDIFF(updated_at, created_at)) as avg_days')
            ->first();
            
        return round($avgDays->avg_days ?? 14, 1);
    }
}