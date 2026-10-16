<?php

namespace App\Services\Cache;

use App\Models\StatusPengiriman;
use Illuminate\Support\Facades\Cache;

/**
 * StatusPengirimanCache Service
 *
 * Centralized cache untuk status pengiriman IDs
 * ✅ Eliminates N+1 queries dari repeated status lookups
 * ✅ Loaded once per request dan cached di memory
 * ✅ Database cache fallback untuk performa
 *
 * Usage:
 *   $packingId = StatusPengirimanCache::getIdBySlug('packing');
 *   $allStatuses = StatusPengirimanCache::getAll();
 */
class StatusPengirimanCache
{
    /**
     * Cache key untuk status mapping
     */
    const CACHE_KEY = 'status_pengiriman_cache';

    /**
     * Cache duration (24 hours)
     */
    const CACHE_TTL = 86400;

    /**
     * Runtime cache untuk single request
     */
    protected static ?array $runtimeCache = null;

    /**
     * Get status ID by slug dengan caching
     *
     * @param string $slug Status slug (e.g., 'packing', 'diterima')
     * @return int|null Status ID atau null jika tidak ditemukan
     */
    public static function getIdBySlug(string $slug): ?int
    {
        $statuses = static::getAll();

        return $statuses[$slug]['id'] ?? null;
    }

    /**
     * Get status name by slug
     *
     * @param string $slug
     * @return string|null
     */
    public static function getNameBySlug(string $slug): ?string
    {
        $statuses = static::getAll();

        return $statuses[$slug]['nama_status'] ?? null;
    }

    /**
     * Get full status object by slug
     *
     * @param string $slug
     * @return array|null
     */
    public static function getBySlug(string $slug): ?array
    {
        $statuses = static::getAll();

        return $statuses[$slug] ?? null;
    }

    /**
     * Get all statuses dengan caching (keyed by slug)
     *
     * Returns:
     * [
     *   'packing' => ['id' => 4, 'slug' => 'packing', 'nama_status' => 'Packing'],
     *   'diterima' => ['id' => 7, 'slug' => 'diterima', 'nama_status' => 'Diterima'],
     *   ...
     * ]
     *
     * @return array
     */
    public static function getAll(): array
    {
        // Check runtime cache first (in-memory untuk request ini)
        if (static::$runtimeCache !== null) {
            return static::$runtimeCache;
        }

        // Check database cache (persistent)
        $cached = Cache::get(static::CACHE_KEY);

        if ($cached !== null) {
            static::$runtimeCache = $cached;
            return $cached;
        }

        // Load from database dan cache
        return static::refresh();
    }

    /**
     * Refresh cache dari database
     *
     * @return array
     */
    public static function refresh(): array
    {
        $statuses = StatusPengiriman::all()
            ->keyBy('slug')
            ->map(fn($status) => [
                'id' => $status->id,
                'slug' => $status->slug,
                'nama_status' => $status->nama_status,
                'deskripsi' => $status->deskripsi,
            ])
            ->toArray();

        // Store in both caches
        Cache::put(static::CACHE_KEY, $statuses, static::CACHE_TTL);
        static::$runtimeCache = $statuses;

        return $statuses;
    }

    /**
     * Clear cache (call after status changes)
     *
     * @return void
     */
    public static function clear(): void
    {
        Cache::forget(static::CACHE_KEY);
        static::$runtimeCache = null;
    }

    /**
     * Get multiple status IDs at once
     *
     * @param array $slugs Array of slugs
     * @return array Keyed by slug dengan value = ID
     */
    public static function getIdsBySlug(array $slugs): array
    {
        $statuses = static::getAll();
        $result = [];

        foreach ($slugs as $slug) {
            if (isset($statuses[$slug])) {
                $result[$slug] = $statuses[$slug]['id'];
            }
        }

        return $result;
    }

    /**
     * Check if status slug exists
     *
     * @param string $slug
     * @return bool
     */
    public static function exists(string $slug): bool
    {
        $statuses = static::getAll();

        return isset($statuses[$slug]);
    }

    /**
     * Warm up cache (call di AppServiceProvider)
     *
     * @return void
     */
    public static function warmUp(): void
    {
        static::refresh();
    }

    /**
     * Safe warm up cache - catches exceptions for cases like testing
     * where tables may not exist yet
     *
     * @return void
     */
    public static function warmUpSafe(): void
    {
        try {
            static::refresh();
        } catch (\Exception $e) {
            // Silently ignore if table doesn't exist (e.g., during testing)
            // Cache will be populated on first successful call
        }
    }
}
