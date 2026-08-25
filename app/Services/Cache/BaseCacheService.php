<?php

namespace App\Services\Cache;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

abstract class BaseCacheService
{
    protected string $prefix = '';

    protected int $defaultTtl = 3600; // 1 hour

    protected array $tags = [];

    protected bool $useTagging = true;

    /**
     * Generate cache key with prefix
     */
    protected function key(string $key): string
    {
        return $this->prefix.':'.$key;
    }

    /**
     * Store data in cache with tags
     */
    protected function store(string $key, mixed $data, ?int $ttl = null, array $tags = []): mixed
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $key = $this->key($key);
        $allTags = array_merge($this->tags, $tags);

        try {
            if ($this->useTagging && ! empty($allTags) && $this->supportsTagging()) {
                Cache::tags($allTags)->put($key, $data, $ttl);
            } else {
                Cache::put($key, $data, $ttl);
            }

            Log::info("Cache stored: {$key}", [
                'ttl' => $ttl,
                'tags' => $allTags,
                'size' => is_string($data) ? strlen($data) : strlen(serialize($data)),
            ]);

            return $data;
        } catch (\Exception $e) {
            Log::error("Cache store failed: {$key}", [
                'error' => $e->getMessage(),
                'data_type' => gettype($data),
            ]);

            return $data;
        }
    }

    /**
     * Retrieve data from cache with fallback
     */
    protected function retrieve(string $key, ?callable $fallback = null, ?int $ttl = null, array $tags = []): mixed
    {
        $key = $this->key($key);
        $allTags = array_merge($this->tags, $tags);

        try {
            if ($this->useTagging && ! empty($allTags) && $this->supportsTagging()) {
                $cached = Cache::tags($allTags)->get($key);
            } else {
                $cached = Cache::get($key);
            }

            if ($cached !== null) {
                Log::debug("Cache hit: {$key}");

                return $cached;
            }

            if ($fallback !== null) {
                Log::debug("Cache miss: {$key}, executing fallback");
                $data = $fallback();

                if ($data !== null) {
                    $this->store(str_replace($this->prefix.':', '', $key), $data, $ttl, $tags);
                }

                return $data;
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Cache retrieve failed: {$key}", [
                'error' => $e->getMessage(),
            ]);

            return $fallback ? $fallback() : null;
        }
    }

    /**
     * Remember data in cache
     */
    protected function remember(string $key, callable $callback, ?int $ttl = null, array $tags = []): mixed
    {
        return $this->retrieve($key, $callback, $ttl, $tags);
    }

    /**
     * Forget cache key
     */
    protected function forget(string $key): bool
    {
        $key = $this->key($key);

        try {
            Cache::forget($key);
            Log::info("Cache forgotten: {$key}");

            return true;
        } catch (\Exception $e) {
            Log::error("Cache forget failed: {$key}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Flush cache by tags
     */
    protected function flushByTags(array $tags): bool
    {
        if (! $this->supportsTagging()) {
            Log::warning('Cache tagging not supported, cannot flush by tags');

            return false;
        }

        try {
            Cache::tags($tags)->flush();
            Log::info('Cache flushed by tags', ['tags' => $tags]);

            return true;
        } catch (\Exception $e) {
            Log::error('Cache flush by tags failed', [
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Flush all cache for this service
     */
    public function flushAll(): bool
    {
        if (! empty($this->tags)) {
            return $this->flushByTags($this->tags);
        }

        try {
            // Pattern-based flush for prefixed keys
            $pattern = $this->prefix.':*';

            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $redis = Redis::connection();
                $keys = $redis->keys($pattern);

                if (! empty($keys)) {
                    $redis->del($keys);
                    Log::info("Cache pattern flush: {$pattern}", ['keys_deleted' => count($keys)]);
                }

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Cache flush all failed: {$this->prefix}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if cache store supports tagging
     */
    protected function supportsTagging(): bool
    {
        return Cache::getStore() instanceof \Illuminate\Cache\RedisStore;
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        try {
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $redis = Redis::connection();
                $pattern = $this->prefix.':*';
                $keys = $redis->keys($pattern);

                $stats = [
                    'prefix' => $this->prefix,
                    'total_keys' => count($keys),
                    'tags' => $this->tags,
                    'default_ttl' => $this->defaultTtl,
                    'supports_tagging' => $this->supportsTagging(),
                ];

                // Sample key sizes (first 10 keys)
                $sampleKeys = array_slice($keys, 0, 10);
                $totalSize = 0;

                foreach ($sampleKeys as $key) {
                    $value = $redis->get($key);
                    $totalSize += strlen($value ?? '');
                }

                $stats['average_size_bytes'] = count($sampleKeys) > 0 ?
                    intval($totalSize / count($sampleKeys)) : 0;

                return $stats;
            }

            return [
                'prefix' => $this->prefix,
                'message' => 'Cache statistics only available for Redis store',
            ];
        } catch (\Exception $e) {
            return [
                'prefix' => $this->prefix,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Warm cache (to be implemented by child classes)
     */
    abstract public function warm(): bool;

    /**
     * Get cache health status
     */
    public function health(): array
    {
        $testKey = 'health_check_'.time();
        $testValue = 'test_'.uniqid();

        try {
            // Test write
            $this->store($testKey, $testValue, 60);

            // Test read
            $retrieved = $this->retrieve($testKey);

            // Cleanup
            $this->forget($testKey);

            $isHealthy = $retrieved === $testValue;

            return [
                'healthy' => $isHealthy,
                'prefix' => $this->prefix,
                'supports_tagging' => $this->supportsTagging(),
                'tested_at' => Carbon::now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'prefix' => $this->prefix,
                'error' => $e->getMessage(),
                'tested_at' => Carbon::now()->toISOString(),
            ];
        }
    }
}
