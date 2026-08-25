<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BulkOperationLog;
use App\Models\User;
use App\Models\StatusPengiriman;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class BulkOperationsAnalyticsController extends Controller
{
    /**
     * Display bulk operations dashboard
     */
    public function dashboard()
    {
        // Today's stats
        $todayStats = BulkOperationLog::getTodayStats();
        
        // Weekly trend
        $weeklyTrend = BulkOperationLog::getWeeklyTrend();
        
        // Top performers this month
        $topPerformers = BulkOperationLog::getTopPerformers(5);
        
        // Recent operations
        $recentOperations = BulkOperationLog::with(['user', 'newStatus', 'box'])
            ->latest('operation_timestamp')
            ->limit(10)
            ->get()
            ->map(function($log) {
                return [
                    'id' => $log->id,
                    'operation_type' => $log->operation_type_display,
                    'box_code' => $log->box_code,
                    'user_name' => $log->user?->name ?? 'Unknown',
                    'items_count' => $log->items_count,
                    'operation_summary' => $log->operation_summary,
                    'timestamp' => $log->operation_timestamp->format('d/m/Y H:i'),
                    'processing_time' => $log->processing_time,
                    'notes' => $log->notes
                ];
            });
        
        // Box operations summary
        $boxOperationsSummary = BulkOperationLog::selectRaw('box_code, COUNT(*) as operations, SUM(items_count) as total_items, MAX(operation_timestamp) as last_operation')
            ->thisWeek()
            ->groupBy('box_code')
            ->orderBy('operations', 'desc')
            ->limit(10)
            ->get()
            ->map(function($item) {
                return [
                    'box_code' => $item->box_code,
                    'operations' => $item->operations,
                    'total_items' => $item->total_items,
                    'last_operation' => Carbon::parse($item->last_operation)->format('d/m H:i')
                ];
            });
        
        return Inertia::render('Admin/BulkOperations/Dashboard', [
            'todayStats' => $todayStats,
            'weeklyTrend' => $weeklyTrend,
            'topPerformers' => $topPerformers,
            'recentOperations' => $recentOperations,
            'boxOperationsSummary' => $boxOperationsSummary
        ]);
    }
    
    /**
     * Display detailed analytics
     */
    public function analytics(Request $request)
    {
        $period = $request->get('period', '7'); // days
        $startDate = Carbon::now()->subDays($period);
        
        // Operation type distribution
        $operationTypes = BulkOperationLog::selectRaw('operation_type, COUNT(*) as count, SUM(items_count) as items')
            ->where('operation_timestamp', '>=', $startDate)
            ->groupBy('operation_type')
            ->get();
        
        // User performance analysis
        $userPerformance = BulkOperationLog::with('user')
            ->selectRaw('user_id, COUNT(*) as operations, SUM(items_count) as items, AVG(processing_time_ms) as avg_time')
            ->where('operation_timestamp', '>=', $startDate)
            ->groupBy('user_id')
            ->orderBy('operations', 'desc')
            ->get();
        
        // Processing time analysis
        $processingTimeStats = BulkOperationLog::whereNotNull('processing_time_ms')
            ->where('operation_timestamp', '>=', $startDate)
            ->selectRaw('
                AVG(processing_time_ms) as avg_time,
                MIN(processing_time_ms) as min_time,
                MAX(processing_time_ms) as max_time,
                PERCENTILE_CONT(0.5) WITHIN GROUP(ORDER BY processing_time_ms) as median_time,
                PERCENTILE_CONT(0.95) WITHIN GROUP(ORDER BY processing_time_ms) as p95_time
            ')
            ->first();
        
        // Status change patterns
        $statusChanges = BulkOperationLog::with(['oldStatus', 'newStatus'])
            ->whereNotNull('new_status_id')
            ->where('operation_timestamp', '>=', $startDate)
            ->get()
            ->groupBy(function($item) {
                $from = $item->oldStatus?->nama_status ?? 'Unknown';
                $to = $item->newStatus?->nama_status ?? 'Unknown';
                return "{$from} → {$to}";
            })
            ->map(function($group, $transition) {
                return [
                    'transition' => $transition,
                    'count' => $group->count(),
                    'items' => $group->sum('items_count')
                ];
            })
            ->sortByDesc('count')
            ->values();
        
        // Hourly distribution
        $hourlyDistribution = BulkOperationLog::selectRaw('HOUR(operation_timestamp) as hour, COUNT(*) as operations, SUM(items_count) as items')
            ->where('operation_timestamp', '>=', $startDate)
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();
        
        return response()->json([
            'operation_types' => $operationTypes,
            'user_performance' => $userPerformance,
            'processing_time_stats' => $processingTimeStats,
            'status_changes' => $statusChanges,
            'hourly_distribution' => $hourlyDistribution
        ]);
    }
    
    /**
     * Get operation history for specific box
     */
    public function boxHistory(Request $request)
    {
        $request->validate([
            'box_code' => 'required|string'
        ]);
        
        $history = BulkOperationLog::with(['user', 'oldStatus', 'newStatus'])
            ->where('box_code', $request->box_code)
            ->orderBy('operation_timestamp', 'desc')
            ->get()
            ->map(function($log) {
                return [
                    'id' => $log->id,
                    'operation_type' => $log->operation_type_display,
                    'user_name' => $log->user?->name ?? 'Unknown',
                    'items_count' => $log->items_count,
                    'old_status' => $log->oldStatus?->nama_status,
                    'new_status' => $log->newStatus?->nama_status,
                    'old_address' => $log->old_address ? substr($log->old_address, 0, 50) . '...' : null,
                    'new_address' => $log->new_address ? substr($log->new_address, 0, 50) . '...' : null,
                    'notes' => $log->notes,
                    'timestamp' => $log->operation_timestamp->format('d/m/Y H:i:s'),
                    'processing_time' => $log->processing_time,
                    'ip_address' => $log->ip_address
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }
    
    /**
     * Export bulk operations report
     */
    public function exportReport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'required|in:csv,excel'
        ]);
        
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        
        $operations = BulkOperationLog::with(['user', 'oldStatus', 'newStatus'])
            ->whereBetween('operation_timestamp', [$startDate, $endDate])
            ->orderBy('operation_timestamp', 'desc')
            ->get();
        
        if ($request->format === 'csv') {
            return $this->generateCSVReport($operations, $startDate, $endDate);
        } else {
            return $this->generateExcelReport($operations, $startDate, $endDate);
        }
    }
    
    /**
     * Generate CSV report
     */
    private function generateCSVReport($operations, $startDate, $endDate)
    {
        $filename = 'bulk_operations_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function() use ($operations) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fwrite($file, "\xEF\xBB\xBF");
            
            // Header
            fputcsv($file, [
                'Timestamp',
                'Box Code',
                'Operation Type',
                'User',
                'Items Count',
                'Old Status',
                'New Status', 
                'Old Address',
                'New Address',
                'Processing Time',
                'Notes',
                'IP Address'
            ]);
            
            // Data
            foreach ($operations as $op) {
                fputcsv($file, [
                    $op->operation_timestamp->format('Y-m-d H:i:s'),
                    $op->box_code,
                    $op->operation_type_display,
                    $op->user?->name ?? 'Unknown',
                    $op->items_count,
                    $op->oldStatus?->nama_status ?? '',
                    $op->newStatus?->nama_status ?? '',
                    $op->old_address ?? '',
                    $op->new_address ?? '',
                    $op->processing_time ?? '',
                    $op->notes ?? '',
                    $op->ip_address ?? ''
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
    
    /**
     * Generate Excel report (simplified XML format)
     */
    private function generateExcelReport($operations, $startDate, $endDate)
    {
        $filename = 'bulk_operations_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.xlsx';
        
        return response()->streamDownload(function() use ($operations) {
            echo "<?xml version=\"1.0\"?>\n";
            echo "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\">\n";
            echo "<Worksheet ss:Name=\"Bulk Operations\">\n";
            echo "<Table>\n";
            
            // Header
            echo "<Row>\n";
            $headers = ['Timestamp', 'Box Code', 'Operation Type', 'User', 'Items Count', 'Old Status', 'New Status', 'Processing Time', 'Notes'];
            foreach ($headers as $header) {
                echo "<Cell><Data ss:Type=\"String\">" . htmlspecialchars($header) . "</Data></Cell>\n";
            }
            echo "</Row>\n";
            
            // Data
            foreach ($operations as $op) {
                echo "<Row>\n";
                echo "<Cell><Data ss:Type=\"String\">" . $op->operation_timestamp->format('Y-m-d H:i:s') . "</Data></Cell>\n";
                echo "<Cell><Data ss:Type=\"String\">" . htmlspecialchars($op->box_code) . "</Data></Cell>\n";
                echo "<Cell><Data ss:Type=\"String\">" . htmlspecialchars($op->operation_type_display) . "</Data></Cell>\n";
                echo "<Cell><Data ss:Type=\"String\">" . htmlspecialchars($op->user?->name ?? 'Unknown') . "</Data></Cell>\n";
                echo "<Cell><Data ss:Type=\"Number\">{$op->items_count}</Data></Cell>\n";
                echo "<Cell><Data ss:Type=\"String\">" . htmlspecialchars($op->oldStatus?->nama_status ?? '') . "</Data></Cell>\n";
                echo "<Cell><Data ss:Type=\"String\">" . htmlspecialchars($op->newStatus?->nama_status ?? '') . "</Data></Cell>\n";
                echo "<Cell><Data ss:Type=\"String\">" . htmlspecialchars($op->processing_time ?? '') . "</Data></Cell>\n";
                echo "<Cell><Data ss:Type=\"String\">" . htmlspecialchars($op->notes ?? '') . "</Data></Cell>\n";
                echo "</Row>\n";
            }
            
            echo "</Table>\n";
            echo "</Worksheet>\n";
            echo "</Workbook>\n";
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
    
    /**
     * Get real-time stats (for dashboard auto-refresh)
     */
    public function realtimeStats()
    {
        $stats = [
            'today_operations' => BulkOperationLog::today()->count(),
            'today_items' => BulkOperationLog::today()->sum('items_count'),
            'active_users' => BulkOperationLog::today()
                ->distinct('user_id')
                ->count('user_id'),
            'avg_processing_time' => BulkOperationLog::today()
                ->whereNotNull('processing_time_ms')
                ->avg('processing_time_ms'),
            'last_operation' => BulkOperationLog::with('user')
                ->latest('operation_timestamp')
                ->first()
        ];
        
        if ($stats['last_operation']) {
            $stats['last_operation'] = [
                'user_name' => $stats['last_operation']->user?->name ?? 'Unknown',
                'box_code' => $stats['last_operation']->box_code,
                'items_count' => $stats['last_operation']->items_count,
                'timestamp' => $stats['last_operation']->operation_timestamp->format('H:i:s')
            ];
        }
        
        return response()->json($stats);
    }
}