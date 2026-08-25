<?php

namespace App\Services\Cache;

use App\Models\Donatur;
use App\Models\MushafRequest;
use App\Models\Pengiriman;
use App\Models\Sertifikat;
use Illuminate\Support\Facades\Log;

class QueryCacheService extends BaseCacheService
{
    protected string $prefix = 'query';

    protected int $defaultTtl = 900; // 15 minutes for query results

    protected array $tags = ['query', 'search'];

    /**
     * Cache paginated pengiriman results
     */
    public function getPengirimanPaginated(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $cacheKey = $this->buildPengirimanCacheKey($filters, $page, $perPage);

        return $this->remember($cacheKey, function () use ($filters, $page, $perPage) {
            $query = Pengiriman::with([
                'donatur:id,nama_donatur,email,no_telp',
                'jenisQuran:id,nama_jenis,kode_jenis',
                'status:id,nama,slug,warna,icon',
            ]);

            // Apply filters
            $this->applyPengirimanFilters($query, $filters);

            return $query->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page)
                ->toArray();
        }, 600, ['query', 'pengiriman', 'paginated']); // 10 minutes
    }

    /**
     * Cache search results for pengiriman
     */
    public function searchPengiriman(string $search, int $limit = 10): array
    {
        $cacheKey = 'search_pengiriman_'.md5($search)."_{$limit}";

        return $this->remember($cacheKey, function () use ($search, $limit) {
            return Pengiriman::with([
                'donatur:id,nama_donatur',
                'status:id,nama,slug,warna',
            ])
                ->where(function ($query) use ($search) {
                    $query->where('no_resi', 'like', "%{$search}%")
                        ->orWhere('nama_penerima', 'like', "%{$search}%")
                        ->orWhere('alamat_penerima', 'like', "%{$search}%")
                        ->orWhereHas('donatur', function ($q) use ($search) {
                            $q->where('nama_donatur', 'like', "%{$search}%");
                        });
                })
                ->limit($limit)
                ->get([
                    'id', 'no_resi', 'nama_penerima', 'alamat_penerima',
                    'jumlah_quran', 'donatur_id', 'status_id', 'created_at',
                ])
                ->toArray();
        }, 300, ['query', 'search', 'pengiriman']); // 5 minutes
    }

    /**
     * Cache search results for donatur
     */
    public function searchDonatur(string $search, int $limit = 10): array
    {
        $cacheKey = 'search_donatur_'.md5($search)."_{$limit}";

        return $this->remember($cacheKey, function () use ($search, $limit) {
            return Donatur::where(function ($query) use ($search) {
                $query->where('nama_donatur', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('no_telp', 'like', "%{$search}%")
                    ->orWhere('alamat', 'like', "%{$search}%");
            })
                ->limit($limit)
                ->get([
                    'id', 'nama_donatur', 'email', 'no_telp',
                    'alamat', 'created_at',
                ])
                ->toArray();
        }, 300, ['query', 'search', 'donatur']); // 5 minutes
    }

    /**
     * Cache mushaf request search results
     */
    public function searchMushafRequests(string $search, int $limit = 10): array
    {
        $cacheKey = 'search_mushaf_requests_'.md5($search)."_{$limit}";

        return $this->remember($cacheKey, function () use ($search, $limit) {
            return MushafRequest::where(function ($query) use ($search) {
                $query->where('no_request', 'like', "%{$search}%")
                    ->orWhere('nama_lembaga', 'like', "%{$search}%")
                    ->orWhere('nama_penanggung_jawab', 'like', "%{$search}%")
                    ->orWhere('alamat_lembaga', 'like', "%{$search}%");
            })
                ->limit($limit)
                ->get([
                    'id', 'no_request', 'nama_lembaga', 'nama_penanggung_jawab',
                    'jumlah_mushaf', 'jumlah_mushaf_approved', 'jumlah_iqra', 'jumlah_iqra_approved', 'status', 'created_at',
                ])
                ->toArray();
        }, 300, ['query', 'search', 'mushaf_requests']); // 5 minutes
    }

    /**
     * Cache recent pengiriman by status
     */
    public function getRecentPengirimanByStatus(string $statusSlug, int $limit = 20): array
    {
        $cacheKey = "recent_pengiriman_status_{$statusSlug}_{$limit}";

        return $this->remember($cacheKey, function () use ($statusSlug, $limit) {
            return Pengiriman::with([
                'donatur:id,nama_donatur',
                'status:id,nama,slug,warna,icon',
            ])
                ->whereHas('status', function ($query) use ($statusSlug) {
                    $query->where('slug', $statusSlug);
                })
                ->orderBy('updated_at', 'desc')
                ->limit($limit)
                ->get([
                    'id', 'no_resi', 'nama_penerima', 'jumlah_quran',
                    'donatur_id', 'status_id', 'created_at', 'updated_at',
                ])
                ->toArray();
        }, 300, ['query', 'recent', 'status', $statusSlug]); // 5 minutes
    }

    /**
     * Cache donatur with most donations
     */
    public function getTopDonatur(int $limit = 10, string $period = 'all'): array
    {
        $cacheKey = "top_donatur_{$limit}_{$period}";

        return $this->remember($cacheKey, function () use ($limit, $period) {
            $query = Donatur::withSum('pengiriman', 'jumlah_quran')
                ->withCount('pengiriman');

            // Apply period filter
            if ($period !== 'all') {
                $query->whereHas('pengiriman', function ($q) use ($period) {
                    switch ($period) {
                        case 'month':
                            $q->whereMonth('created_at', now()->month)
                                ->whereYear('created_at', now()->year);
                            break;
                        case 'year':
                            $q->whereYear('created_at', now()->year);
                            break;
                        case '6months':
                            $q->where('created_at', '>=', now()->subMonths(6));
                            break;
                    }
                });
            }

            return $query->orderBy('pengiriman_sum_jumlah_quran', 'desc')
                ->limit($limit)
                ->get([
                    'id', 'nama_donatur', 'email', 'no_telp', 'created_at',
                ])
                ->map(function ($donatur) {
                    return [
                        'id' => $donatur->id,
                        'nama_donatur' => $donatur->nama_donatur,
                        'email' => $donatur->email,
                        'no_telp' => $donatur->no_telp,
                        'total_mushaf' => $donatur->pengiriman_sum_jumlah_quran ?? 0,
                        'total_pengiriman' => $donatur->pengiriman_count ?? 0,
                        'created_at' => $donatur->created_at,
                    ];
                })
                ->toArray();
        }, 900, ['query', 'top', 'donatur', $period]); // 15 minutes
    }

    /**
     * Cache delivery statistics by area
     */
    public function getDeliveryStatsByArea(int $limit = 10): array
    {
        return $this->remember("delivery_stats_by_area_{$limit}", function () use ($limit) {
            return Pengiriman::selectRaw('
                    provinsi_penerima,
                    COUNT(*) as total_deliveries,
                    SUM(jumlah_quran) as total_mushaf,
                    AVG(jumlah_quran) as avg_mushaf_per_delivery
                ')
                ->whereNotNull('provinsi_penerima')
                ->where('provinsi_penerima', '!=', '')
                ->groupBy('provinsi_penerima')
                ->orderBy('total_deliveries', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($stat) {
                    return [
                        'provinsi' => $stat->provinsi_penerima,
                        'total_deliveries' => (int) $stat->total_deliveries,
                        'total_mushaf' => (int) $stat->total_mushaf,
                        'avg_mushaf_per_delivery' => round($stat->avg_mushaf_per_delivery, 1),
                    ];
                })
                ->toArray();
        }, 1800, ['query', 'stats', 'area']); // 30 minutes
    }

    /**
     * Cache certificate statistics
     */
    public function getCertificateStats(): array
    {
        return $this->remember('certificate_stats', function () {
            return [
                'total_certificates' => Sertifikat::count(),
                'certificates_this_month' => Sertifikat::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'by_template' => Sertifikat::join('certificate_templates', 'sertifikat.template_id', '=', 'certificate_templates.id')
                    ->selectRaw('certificate_templates.name as template_name, COUNT(*) as count')
                    ->groupBy('certificate_templates.id', 'certificate_templates.name')
                    ->orderBy('count', 'desc')
                    ->get()
                    ->toArray(),
                'recent_certificates' => Sertifikat::with('template:id,name')
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'template_id', 'created_at'])
                    ->toArray(),
            ];
        }, 900, ['query', 'stats', 'certificates']); // 15 minutes
    }

    /**
     * Cache pending mushaf requests count by priority
     */
    public function getPendingMushafRequestStats(): array
    {
        return $this->remember('pending_mushaf_requests_stats', function () {
            // ✅ FIX: Use APPROVED quantities with fallback to requested
            $stats = MushafRequest::where('status', 'pending')
                ->selectRaw('
                    COUNT(*) as total_pending,
                    SUM(COALESCE(jumlah_mushaf_approved, jumlah_mushaf)) as total_mushaf_requested,
                    SUM(COALESCE(jumlah_iqra_approved, jumlah_iqra)) as total_iqra_requested,
                    AVG(COALESCE(jumlah_mushaf_approved, jumlah_mushaf) + COALESCE(jumlah_iqra_approved, jumlah_iqra)) as avg_books_per_request
                ')
                ->first();

            $oldRequests = MushafRequest::where('status', 'pending')
                ->where('created_at', '<', now()->subDays(7))
                ->count();

            return [
                'total_pending' => (int) ($stats->total_pending ?? 0),
                'total_mushaf_requested' => (int) ($stats->total_mushaf_requested ?? 0),
                'total_iqra_requested' => (int) ($stats->total_iqra_requested ?? 0),
                'avg_books_per_request' => round($stats->avg_books_per_request ?? 0, 1),
                'old_requests_count' => $oldRequests, // Older than 7 days
                'recent_requests' => MushafRequest::where('status', 'pending')
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get([
                        'id', 'no_request', 'nama_lembaga', 'jumlah_mushaf', 'jumlah_mushaf_approved',
                        'jumlah_iqra', 'jumlah_iqra_approved', 'created_at',
                    ])
                    ->toArray(),
            ];
        }, 300, ['query', 'stats', 'mushaf_requests']); // 5 minutes
    }

    /**
     * Build cache key for pengiriman pagination
     */
    private function buildPengirimanCacheKey(array $filters, int $page, int $perPage): string
    {
        $filterHash = md5(serialize($filters));

        return "pengiriman_paginated_{$filterHash}_{$page}_{$perPage}";
    }

    /**
     * Apply filters to pengiriman query
     */
    private function applyPengirimanFilters($query, array $filters): void
    {
        if (! empty($filters['status_id'])) {
            $query->where('status_id', $filters['status_id']);
        }

        if (! empty($filters['donatur_id'])) {
            $query->where('donatur_id', $filters['donatur_id']);
        }

        if (! empty($filters['jenis_quran_id'])) {
            $query->where('jenis_quran_id', $filters['jenis_quran_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('no_resi', 'like', "%{$search}%")
                    ->orWhere('nama_penerima', 'like', "%{$search}%")
                    ->orWhere('alamat_penerima', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }

    /**
     * Invalidate query cache for specific entity
     */
    public function invalidateEntityCache(string $entity): bool
    {
        Log::info("Invalidating query cache for entity: {$entity}");

        return $this->flushByTags(['query', $entity]);
    }

    /**
     * Invalidate search cache
     */
    public function invalidateSearchCache(): bool
    {
        Log::info('Invalidating search cache');

        return $this->flushByTags(['query', 'search']);
    }

    /**
     * Invalidate statistics cache
     */
    public function invalidateStatsCache(): bool
    {
        Log::info('Invalidating statistics cache');

        return $this->flushByTags(['query', 'stats']);
    }

    /**
     * Warm query cache with common queries
     */
    public function warm(): bool
    {
        try {
            Log::info('Warming query cache...');

            // Warm recent pengiriman by common statuses
            $commonStatuses = ['pemesanan', 'packing', 'pengiriman', 'diterima'];
            foreach ($commonStatuses as $status) {
                $this->getRecentPengirimanByStatus($status, 10);
            }

            // Warm top donatur
            $this->getTopDonatur(10, 'all');
            $this->getTopDonatur(5, 'month');

            // Warm stats
            $this->getDeliveryStatsByArea(10);
            $this->getCertificateStats();
            $this->getPendingMushafRequestStats();

            // Warm common pagination (first page)
            $this->getPengirimanPaginated([], 1, 15);

            Log::info('Query cache warmed successfully');

            return true;
        } catch (\Exception $e) {
            Log::error('Query cache warming failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get detailed query cache statistics
     */
    public function getDetailedStats(): array
    {
        $baseStats = $this->getStats();

        try {
            // Get some sample query stats
            $pendingStats = $this->getPendingMushafRequestStats();

            return array_merge($baseStats, [
                'pending_requests' => $pendingStats['total_pending'] ?? 0,
                'total_mushaf_requested' => $pendingStats['total_mushaf_requested'] ?? 0,
                'cached_entity_types' => ['pengiriman', 'donatur', 'mushaf_requests', 'certificates'],
            ]);
        } catch (\Exception $e) {
            return array_merge($baseStats, [
                'error' => 'Could not get detailed stats: '.$e->getMessage(),
            ]);
        }
    }
}
