<?php

namespace App\Services\Cache;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CacheManager
{
    protected DashboardCacheService $dashboardCache;

    protected ReferenceDataCacheService $referenceCache;

    protected GeographicCacheService $geographicCache;

    protected UserCacheService $userCache;

    protected QueryCacheService $queryCache;

    public function __construct(
        DashboardCacheService $dashboardCache,
        ReferenceDataCacheService $referenceCache,
        GeographicCacheService $geographicCache,
        UserCacheService $userCache,
        QueryCacheService $queryCache
    ) {
        $this->dashboardCache = $dashboardCache;
        $this->referenceCache = $referenceCache;
        $this->geographicCache = $geographicCache;
        $this->userCache = $userCache;
        $this->queryCache = $queryCache;
    }

    /**
     * Get all cache services
     */
    public function getServices(): array
    {
        return [
            'dashboard' => $this->dashboardCache,
            'reference' => $this->referenceCache,
            'geographic' => $this->geographicCache,
            'user' => $this->userCache,
            'query' => $this->queryCache,
        ];
    }

    /**
     * Get specific cache service
     */
    public function getService(string $name): ?BaseCacheService
    {
        return $this->getServices()[$name] ?? null;
    }

    /**
     * Warm all caches
     */
    public function warmAll(): array
    {
        $results = [];
        $startTime = microtime(true);

        Log::info('Starting cache warming for all services...');

        foreach ($this->getServices() as $name => $service) {
            try {
                $serviceStartTime = microtime(true);
                $result = $service->warm();
                $serviceEndTime = microtime(true);

                $results[$name] = [
                    'success' => $result,
                    'time_taken' => round($serviceEndTime - $serviceStartTime, 3),
                    'error' => null,
                ];

                Log::info("Cache warming completed for {$name}", [
                    'success' => $result,
                    'time_taken' => $results[$name]['time_taken'],
                ]);
            } catch (\Exception $e) {
                $results[$name] = [
                    'success' => false,
                    'time_taken' => 0,
                    'error' => $e->getMessage(),
                ];

                Log::error("Cache warming failed for {$name}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $totalTime = round(microtime(true) - $startTime, 3);
        $successCount = count(array_filter($results, fn ($r) => $r['success']));
        $totalServices = count($results);

        Log::info('Cache warming completed', [
            'total_time' => $totalTime,
            'successful_services' => $successCount,
            'total_services' => $totalServices,
            'success_rate' => round(($successCount / $totalServices) * 100, 1).'%',
        ]);

        return [
            'success' => $successCount === $totalServices,
            'results' => $results,
            'summary' => [
                'total_time' => $totalTime,
                'successful_services' => $successCount,
                'total_services' => $totalServices,
                'success_rate' => round(($successCount / $totalServices) * 100, 1),
            ],
        ];
    }

    /**
     * Get health status of all cache services
     */
    public function getHealth(): array
    {
        $health = [];
        $overallHealthy = true;

        foreach ($this->getServices() as $name => $service) {
            try {
                $serviceHealth = $service->health();
                $health[$name] = $serviceHealth;

                if (! $serviceHealth['healthy']) {
                    $overallHealthy = false;
                }
            } catch (\Exception $e) {
                $health[$name] = [
                    'healthy' => false,
                    'error' => $e->getMessage(),
                    'tested_at' => Carbon::now()->toISOString(),
                ];
                $overallHealthy = false;
            }
        }

        return [
            'overall_healthy' => $overallHealthy,
            'services' => $health,
            'cache_driver' => config('cache.default'),
            'tested_at' => Carbon::now()->toISOString(),
        ];
    }

    /**
     * Get comprehensive cache statistics
     */
    public function getStats(): array
    {
        $stats = [
            'cache_driver' => config('cache.default'),
            'generated_at' => Carbon::now()->toISOString(),
            'services' => [],
        ];

        $totalKeys = 0;
        $totalSize = 0;

        foreach ($this->getServices() as $name => $service) {
            try {
                $serviceStats = $service->getStats();
                $stats['services'][$name] = $serviceStats;

                if (isset($serviceStats['total_keys'])) {
                    $totalKeys += $serviceStats['total_keys'];
                }

                if (isset($serviceStats['average_size_bytes'])) {
                    $totalSize += $serviceStats['average_size_bytes'];
                }
            } catch (\Exception $e) {
                $stats['services'][$name] = [
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Add Redis-specific stats if available
        try {
            if (config('cache.default') === 'redis') {
                $redisStats = $this->getRedisStats();
                $stats['redis'] = $redisStats;
            }
        } catch (\Exception $e) {
            $stats['redis_error'] = $e->getMessage();
        }

        $stats['summary'] = [
            'total_cache_keys' => $totalKeys,
            'estimated_total_size_bytes' => $totalSize,
            'estimated_total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'services_count' => count($this->getServices()),
        ];

        return $stats;
    }

    /**
     * Get Redis-specific statistics
     */
    protected function getRedisStats(): array
    {
        try {
            $redis = Redis::connection();
            $info = $redis->info('memory');

            return [
                'used_memory' => $info['used_memory'] ?? 'unknown',
                'used_memory_human' => $info['used_memory_human'] ?? 'unknown',
                'used_memory_peak' => $info['used_memory_peak'] ?? 'unknown',
                'used_memory_peak_human' => $info['used_memory_peak_human'] ?? 'unknown',
                'connected_clients' => $redis->info('clients')['connected_clients'] ?? 'unknown',
                'total_commands_processed' => $redis->info('stats')['total_commands_processed'] ?? 'unknown',
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Could not retrieve Redis stats: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Clear all caches
     */
    public function clearAll(): array
    {
        $results = [];

        Log::info('Clearing all caches...');

        foreach ($this->getServices() as $name => $service) {
            try {
                $result = $service->flushAll();
                $results[$name] = [
                    'success' => $result,
                    'error' => null,
                ];
            } catch (\Exception $e) {
                $results[$name] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];

                Log::error("Failed to clear cache for {$name}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $successCount = count(array_filter($results, fn ($r) => $r['success']));
        $totalServices = count($results);

        Log::info('Cache clearing completed', [
            'successful_services' => $successCount,
            'total_services' => $totalServices,
        ]);

        return [
            'success' => $successCount === $totalServices,
            'results' => $results,
            'summary' => [
                'successful_services' => $successCount,
                'total_services' => $totalServices,
            ],
        ];
    }

    /**
     * Invalidate caches based on entity changes
     */
    public function invalidateForEntity(string $entity, ?int $entityId = null): bool
    {
        Log::info("Invalidating caches for entity: {$entity}", [
            'entity_id' => $entityId,
        ]);

        $success = true;

        try {
            switch ($entity) {
                case 'pengiriman':
                    $this->dashboardCache->invalidate(['dashboard', 'stats']);
                    $this->queryCache->invalidateEntityCache('pengiriman');
                    break;

                case 'donatur':
                    $this->dashboardCache->invalidate(['dashboard', 'stats']);
                    $this->queryCache->invalidateEntityCache('donatur');
                    break;

                case 'mushaf_request':
                    $this->dashboardCache->invalidate(['dashboard', 'stats']);
                    $this->queryCache->invalidateEntityCache('mushaf_requests');
                    break;

                case 'status_pengiriman':
                    $this->referenceCache->invalidateStatus();
                    $this->dashboardCache->invalidate(['dashboard']);
                    break;

                case 'jenis_quran':
                    $this->referenceCache->invalidateJenisQuran();
                    break;

                case 'certificate_template':
                    $this->referenceCache->invalidateTemplates();
                    break;

                case 'user':
                    if ($entityId) {
                        $this->userCache->invalidateUser($entityId);
                    }
                    $this->dashboardCache->invalidate(['dashboard', 'stats']);
                    break;

                case 'permission':
                    $this->userCache->invalidatePermissions();
                    break;

                case 'role':
                    $this->userCache->invalidateRoles();
                    break;

                default:
                    Log::warning("Unknown entity for cache invalidation: {$entity}");
                    $success = false;
            }
        } catch (\Exception $e) {
            Log::error("Cache invalidation failed for entity: {$entity}", [
                'error' => $e->getMessage(),
                'entity_id' => $entityId,
            ]);
            $success = false;
        }

        return $success;
    }

    /**
     * Get cache monitoring data for debugging
     */
    public function getMonitoringData(): array
    {
        return [
            'timestamp' => Carbon::now()->toISOString(),
            'cache_driver' => config('cache.default'),
            'health' => $this->getHealth(),
            'stats' => $this->getStats(),
            'services' => array_keys($this->getServices()),
            'config' => [
                'redis_host' => config('database.redis.default.host'),
                'redis_port' => config('database.redis.default.port'),
                'cache_prefix' => config('cache.prefix'),
            ],
        ];
    }

    /**
     * Test cache functionality
     */
    public function testCache(): array
    {
        $testResults = [];
        $testKey = 'cache_test_'.time();
        $testValue = 'test_value_'.uniqid();

        foreach ($this->getServices() as $name => $service) {
            try {
                // Test write
                $service->store($testKey, $testValue, 60);

                // Test read
                $retrieved = $service->retrieve($testKey);

                // Test delete
                $service->forget($testKey);

                $testResults[$name] = [
                    'write' => true,
                    'read' => $retrieved === $testValue,
                    'delete' => true,
                    'overall' => $retrieved === $testValue,
                    'error' => null,
                ];
            } catch (\Exception $e) {
                $testResults[$name] = [
                    'write' => false,
                    'read' => false,
                    'delete' => false,
                    'overall' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $overallSuccess = ! in_array(false, array_column($testResults, 'overall'));

        return [
            'success' => $overallSuccess,
            'results' => $testResults,
            'tested_at' => Carbon::now()->toISOString(),
        ];
    }
}
