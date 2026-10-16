<?php

namespace App\Observers;

use App\Services\Cache\CacheManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class CacheInvalidationObserver
{
    protected CacheManager $cacheManager;

    protected array $entityMap = [
        'App\Models\Pengiriman' => 'pengiriman',
        'App\Models\Donatur' => 'donatur',
        'App\Models\MushafRequest' => 'mushaf_request',
        'App\Models\StatusPengiriman' => 'status_pengiriman',
        'App\Models\JenisQuran' => 'jenis_quran',
        'App\Models\CertificateTemplate' => 'certificate_template',
        'App\Models\User' => 'user',
        'App\Models\Sertifikat' => 'certificate',
        'App\Models\StatusHistory' => 'status_history',
    ];

    public function __construct(CacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Handle model created event
     */
    public function created(Model $model): void
    {
        $this->invalidateCache($model, 'created');
    }

    /**
     * Handle model updated event
     */
    public function updated(Model $model): void
    {
        $this->invalidateCache($model, 'updated');
    }

    /**
     * Handle model deleted event
     */
    public function deleted(Model $model): void
    {
        $this->invalidateCache($model, 'deleted');
    }

    /**
     * Invalidate cache based on model changes
     */
    protected function invalidateCache(Model $model, string $action): void
    {
        $modelClass = get_class($model);
        $entity = $this->entityMap[$modelClass] ?? null;

        if (! $entity) {
            return; // No cache invalidation needed for this model
        }

        try {
            $entityId = $model->getKey();

            Log::info('Cache invalidation triggered', [
                'entity' => $entity,
                'entity_id' => $entityId,
                'action' => $action,
                'model' => $modelClass,
            ]);

            $this->cacheManager->invalidateForEntity($entity, $entityId);

            // Special handling for certain models
            $this->handleSpecialInvalidations($entity, $model, $action);

        } catch (\Exception $e) {
            Log::error('Cache invalidation failed', [
                'entity' => $entity,
                'action' => $action,
                'error' => $e->getMessage(),
                'model' => $modelClass,
            ]);
        }
    }

    /**
     * Handle special cache invalidations
     */
    protected function handleSpecialInvalidations(string $entity, Model $model, string $action): void
    {
        switch ($entity) {
            case 'pengiriman':
                // Also invalidate query cache for status-specific queries
                if (isset($model->status_id)) {
                    Log::debug('Invalidating status-specific cache for pengiriman');
                }
                break;

            case 'user':
                // Invalidate user-specific caches
                if ($model->getKey()) {
                    $userCache = $this->cacheManager->getService('user');
                    if ($userCache) {
                        $userCache->invalidateUser($model->getKey());
                    }
                }
                break;

            case 'status_pengiriman':
                // Invalidate all reference data when status changes
                $referenceCache = $this->cacheManager->getService('reference');
                if ($referenceCache) {
                    $referenceCache->invalidateAll();
                }
                break;

            case 'status_history':
                // When status history changes, invalidate dashboard activities
                $dashboardCache = $this->cacheManager->getService('dashboard');
                if ($dashboardCache) {
                    $dashboardCache->invalidate(['dashboard', 'activities']);
                }
                break;
        }
    }
}
