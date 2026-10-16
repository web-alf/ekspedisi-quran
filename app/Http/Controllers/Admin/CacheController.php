<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Cache\CacheManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CacheController extends Controller
{
    protected CacheManager $cacheManager;

    public function __construct(CacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Show cache management dashboard
     */
    public function index()
    {
        try {
            $stats = $this->cacheManager->getStats();
            $health = $this->cacheManager->getHealth();
            $monitoring = $this->cacheManager->getMonitoringData();

            return Inertia::render('Admin/Cache/Index', [
                'stats' => $stats,
                'health' => $health,
                'monitoring' => $monitoring,
                'services' => array_keys($this->cacheManager->getServices()),
            ]);
        } catch (\Exception $e) {
            Log::error('Cache dashboard failed', [
                'error' => $e->getMessage(),
            ]);

            return Inertia::render('Admin/Cache/Index', [
                'error' => 'Failed to load cache dashboard: '.$e->getMessage(),
                'stats' => null,
                'health' => null,
                'monitoring' => null,
                'services' => [],
            ]);
        }
    }

    /**
     * Warm all caches
     */
    public function warm(Request $request)
    {
        try {
            $service = $request->input('service');

            if ($service) {
                $cacheService = $this->cacheManager->getService($service);
                if (! $cacheService) {
                    return response()->json([
                        'success' => false,
                        'message' => "Service not found: {$service}",
                    ], 404);
                }

                $startTime = microtime(true);
                $result = $cacheService->warm();
                $endTime = microtime(true);

                return response()->json([
                    'success' => $result,
                    'message' => $result ?
                        "Cache warmed successfully for {$service}" :
                        "Failed to warm cache for {$service}",
                    'time_taken' => round(($endTime - $startTime) * 1000, 2),
                    'service' => $service,
                ]);
            }

            // Warm all caches
            $result = $this->cacheManager->warmAll();

            Log::info('Cache warming via admin panel', [
                'success' => $result['success'],
                'user' => auth()->id(),
            ]);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ?
                    'All caches warmed successfully' :
                    'Some caches failed to warm',
                'results' => $result['results'],
                'summary' => $result['summary'],
            ]);

        } catch (\Exception $e) {
            Log::error('Cache warming failed via admin panel', [
                'error' => $e->getMessage(),
                'user' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to warm cache: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear cache
     */
    public function clear(Request $request)
    {
        try {
            $service = $request->input('service');

            if ($service) {
                $cacheService = $this->cacheManager->getService($service);
                if (! $cacheService) {
                    return response()->json([
                        'success' => false,
                        'message' => "Service not found: {$service}",
                    ], 404);
                }

                $result = $cacheService->flushAll();

                return response()->json([
                    'success' => $result,
                    'message' => $result ?
                        "Cache cleared successfully for {$service}" :
                        "Failed to clear cache for {$service}",
                    'service' => $service,
                ]);
            }

            // Clear all caches
            $result = $this->cacheManager->clearAll();

            Log::warning('All cache cleared via admin panel', [
                'success' => $result['success'],
                'user' => auth()->id(),
            ]);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ?
                    'All caches cleared successfully' :
                    'Some caches failed to clear',
                'results' => $result['results'],
            ]);

        } catch (\Exception $e) {
            Log::error('Cache clearing failed via admin panel', [
                'error' => $e->getMessage(),
                'user' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get cache statistics
     */
    public function stats()
    {
        try {
            $stats = $this->cacheManager->getStats();

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get cache statistics: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get cache health
     */
    public function health()
    {
        try {
            $health = $this->cacheManager->getHealth();

            return response()->json([
                'success' => true,
                'data' => $health,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get cache health: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test cache functionality
     */
    public function test()
    {
        try {
            $results = $this->cacheManager->testCache();

            Log::info('Cache test performed via admin panel', [
                'success' => $results['success'],
                'user' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to test cache: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get monitoring data
     */
    public function monitoring()
    {
        try {
            $monitoring = $this->cacheManager->getMonitoringData();

            return response()->json([
                'success' => true,
                'data' => $monitoring,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get monitoring data: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Invalidate cache for specific entity
     */
    public function invalidate(Request $request)
    {
        $request->validate([
            'entity' => 'required|string',
            'entity_id' => 'nullable|integer',
        ]);

        try {
            $entity = $request->input('entity');
            $entityId = $request->input('entity_id');

            $result = $this->cacheManager->invalidateForEntity($entity, $entityId);

            Log::info('Cache invalidated via admin panel', [
                'entity' => $entity,
                'entity_id' => $entityId,
                'success' => $result,
                'user' => auth()->id(),
            ]);

            return response()->json([
                'success' => $result,
                'message' => $result ?
                    "Cache invalidated for {$entity}" :
                    "Failed to invalidate cache for {$entity}",
            ]);

        } catch (\Exception $e) {
            Log::error('Cache invalidation failed via admin panel', [
                'error' => $e->getMessage(),
                'user' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to invalidate cache: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get service-specific statistics
     */
    public function serviceStats($service)
    {
        try {
            $cacheService = $this->cacheManager->getService($service);
            if (! $cacheService) {
                return response()->json([
                    'success' => false,
                    'message' => "Service not found: {$service}",
                ], 404);
            }

            $stats = $cacheService->getStats();
            $health = $cacheService->health();

            return response()->json([
                'success' => true,
                'data' => [
                    'service' => $service,
                    'stats' => $stats,
                    'health' => $health,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Failed to get stats for {$service}: ".$e->getMessage(),
            ], 500);
        }
    }
}
