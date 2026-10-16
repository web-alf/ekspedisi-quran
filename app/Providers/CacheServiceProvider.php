<?php

namespace App\Providers;

use App\Services\Cache\CacheManager;
use App\Services\Cache\DashboardCacheService;
use App\Services\Cache\GeographicCacheService;
use App\Services\Cache\QueryCacheService;
use App\Services\Cache\ReferenceDataCacheService;
use App\Services\Cache\UserCacheService;
use Illuminate\Support\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    /**
     * Register cache services
     */
    public function register(): void
    {
        // Register individual cache services
        $this->app->singleton(DashboardCacheService::class);
        $this->app->singleton(ReferenceDataCacheService::class);
        $this->app->singleton(GeographicCacheService::class);
        $this->app->singleton(UserCacheService::class);
        $this->app->singleton(QueryCacheService::class);

        // Register cache manager
        $this->app->singleton(CacheManager::class, function ($app) {
            return new CacheManager(
                $app->make(DashboardCacheService::class),
                $app->make(ReferenceDataCacheService::class),
                $app->make(GeographicCacheService::class),
                $app->make(UserCacheService::class),
                $app->make(QueryCacheService::class)
            );
        });

        // Alias for easier access
        $this->app->alias(CacheManager::class, 'cache.manager');
    }

    /**
     * Bootstrap cache services
     */
    public function boot(): void
    {
        // Auto-warm cache in production if enabled
        if ($this->app->environment('production') && config('cache.auto_warm', false)) {
            $this->app->booted(function () {
                try {
                    $cacheManager = $this->app->make(CacheManager::class);
                    $cacheManager->warmAll();
                } catch (\Exception $e) {
                    logger()->error('Auto cache warming failed on boot', [
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        }
    }

    /**
     * Get the services provided by the provider
     */
    public function provides(): array
    {
        return [
            CacheManager::class,
            DashboardCacheService::class,
            ReferenceDataCacheService::class,
            GeographicCacheService::class,
            UserCacheService::class,
            QueryCacheService::class,
            'cache.manager',
        ];
    }
}
