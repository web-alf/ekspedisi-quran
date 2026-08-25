<?php

namespace App\Services\Cache;

use App\Models\CertificateTemplate;
use App\Models\JenisQuran;
use App\Models\StatusPengiriman;
use Illuminate\Support\Facades\Log;

class ReferenceDataCacheService extends BaseCacheService
{
    protected string $prefix = 'reference';

    protected int $defaultTtl = 3600; // 1 hour for reference data

    protected array $tags = ['reference', 'static'];

    /**
     * Get status pengiriman data with caching
     */
    public function getStatusPengiriman(bool $activeOnly = true): array
    {
        $cacheKey = $activeOnly ? 'status_pengiriman_active' : 'status_pengiriman_all';

        return $this->remember($cacheKey, function () use ($activeOnly) {
            $query = StatusPengiriman::query();

            if ($activeOnly) {
                $query->where('is_active', true);
            }

            return $query->orderBy('urutan')
                ->get([
                    'id', 'nama', 'slug', 'deskripsi', 'warna',
                    'icon', 'urutan', 'is_active', 'is_final',
                ])
                ->map(function ($status) {
                    return [
                        'id' => $status->id,
                        'nama' => $status->nama,
                        'slug' => $status->slug,
                        'deskripsi' => $status->deskripsi,
                        'warna' => $status->warna,
                        'icon' => $status->icon,
                        'urutan' => $status->urutan,
                        'is_active' => $status->is_active,
                        'is_final' => $status->is_final,
                        'badge_class' => $status->badge_class,
                        'category' => $status->category,
                        'dashboard_label' => $status->dashboard_label,
                    ];
                })
                ->toArray();
        }, $this->defaultTtl, ['reference', 'status']);
    }

    /**
     * Get jenis quran data with caching
     */
    public function getJenisQuran(bool $activeOnly = true): array
    {
        $cacheKey = $activeOnly ? 'jenis_quran_active' : 'jenis_quran_all';

        return $this->remember($cacheKey, function () use ($activeOnly) {
            $query = JenisQuran::query();

            if ($activeOnly) {
                $query->where('is_active', true);
            }

            return $query->orderBy('kode_jenis')
                ->get(['id', 'kode_jenis', 'nama_jenis', 'deskripsi', 'is_active'])
                ->map(function ($jenis) {
                    return [
                        'id' => $jenis->id,
                        'kode_jenis' => $jenis->kode_jenis,
                        'nama_jenis' => $jenis->nama_jenis,
                        'deskripsi' => $jenis->deskripsi,
                        'is_active' => $jenis->is_active,
                        'badge_class' => $jenis->badge_class,
                        'default_capacity' => $jenis->getDefaultCapacity(),
                        'capacity_info' => $jenis->capacity_info,
                    ];
                })
                ->toArray();
        }, $this->defaultTtl, ['reference', 'jenis_quran']);
    }

    /**
     * Get certificate templates with caching
     */
    public function getCertificateTemplates(bool $activeOnly = true): array
    {
        $cacheKey = $activeOnly ? 'certificate_templates_active' : 'certificate_templates_all';

        return $this->remember($cacheKey, function () use ($activeOnly) {
            $query = CertificateTemplate::query();

            if ($activeOnly) {
                $query->where('is_active', true);
            }

            return $query->orderBy('name')
                ->get([
                    'id', 'name', 'description', 'template_file_path',
                    'is_active', 'is_default', 'created_at', 'updated_at',
                ])
                ->toArray();
        }, $this->defaultTtl, ['reference', 'templates']);
    }

    /**
     * Get status by slug with caching
     */
    public function getStatusBySlug(string $slug): ?array
    {
        $allStatuses = $this->getStatusPengiriman();

        foreach ($allStatuses as $status) {
            if ($status['slug'] === $slug) {
                return $status;
            }
        }

        return null;
    }

    /**
     * Get status by ID with caching
     */
    public function getStatusById(int $id): ?array
    {
        $allStatuses = $this->getStatusPengiriman(false); // Include inactive

        foreach ($allStatuses as $status) {
            if ($status['id'] === $id) {
                return $status;
            }
        }

        return null;
    }

    /**
     * Get jenis quran by code with caching
     */
    public function getJenisQuranByCode(string $code): ?array
    {
        $allJenis = $this->getJenisQuran();

        foreach ($allJenis as $jenis) {
            if ($jenis['kode_jenis'] === $code) {
                return $jenis;
            }
        }

        return null;
    }

    /**
     * Get default status ID
     */
    public function getDefaultStatusId(): int
    {
        return $this->remember('default_status_id', function () {
            $status = $this->getStatusBySlug('pemesanan');

            return $status ? $status['id'] : 1;
        }, 7200, ['reference', 'status', 'default']); // 2 hours
    }

    /**
     * Get status options for forms
     */
    public function getStatusOptions(): array
    {
        return $this->remember('status_options', function () {
            return array_map(function ($status) {
                return [
                    'value' => $status['id'],
                    'label' => $status['nama'],
                    'slug' => $status['slug'],
                    'color' => $status['warna'],
                    'icon' => $status['icon'],
                    'is_final' => $status['is_final'],
                ];
            }, $this->getStatusPengiriman());
        }, $this->defaultTtl, ['reference', 'status', 'options']);
    }

    /**
     * Get jenis quran options for forms
     */
    public function getJenisQuranOptions(): array
    {
        return $this->remember('jenis_quran_options', function () {
            return array_map(function ($jenis) {
                return [
                    'value' => $jenis['id'],
                    'label' => $jenis['nama_jenis'],
                    'code' => $jenis['kode_jenis'],
                    'capacity' => $jenis['default_capacity'],
                    'description' => $jenis['deskripsi'],
                ];
            }, $this->getJenisQuran());
        }, $this->defaultTtl, ['reference', 'jenis_quran', 'options']);
    }

    /**
     * Get certificate template options for forms
     */
    public function getCertificateTemplateOptions(): array
    {
        return $this->remember('certificate_template_options', function () {
            return array_map(function ($template) {
                return [
                    'value' => $template['id'],
                    'label' => $template['name'],
                    'description' => $template['description'],
                    'is_default' => $template['is_default'],
                ];
            }, $this->getCertificateTemplates());
        }, $this->defaultTtl, ['reference', 'templates', 'options']);
    }

    /**
     * Get status categories grouped
     */
    public function getStatusCategories(): array
    {
        return $this->remember('status_categories', function () {
            $statuses = $this->getStatusPengiriman();
            $categories = [];

            foreach ($statuses as $status) {
                $category = $status['category'];
                if (! isset($categories[$category])) {
                    $categories[$category] = [
                        'name' => ucfirst(str_replace('_', ' ', $category)),
                        'statuses' => [],
                    ];
                }
                $categories[$category]['statuses'][] = $status;
            }

            return $categories;
        }, $this->defaultTtl, ['reference', 'status', 'categories']);
    }

    /**
     * Get combined reference data for dashboard initialization
     */
    public function getAllReferenceData(): array
    {
        return $this->remember('all_reference_data', function () {
            return [
                'status_pengiriman' => $this->getStatusPengiriman(),
                'jenis_quran' => $this->getJenisQuran(),
                'certificate_templates' => $this->getCertificateTemplates(),
                'status_options' => $this->getStatusOptions(),
                'jenis_quran_options' => $this->getJenisQuranOptions(),
                'template_options' => $this->getCertificateTemplateOptions(),
                'status_categories' => $this->getStatusCategories(),
                'default_status_id' => $this->getDefaultStatusId(),
            ];
        }, $this->defaultTtl, ['reference', 'all']);
    }

    /**
     * Invalidate reference data cache when models are updated
     */
    public function invalidateStatus(): bool
    {
        Log::info('Invalidating status reference data cache');

        return $this->flushByTags(['reference', 'status']);
    }

    /**
     * Invalidate jenis quran cache
     */
    public function invalidateJenisQuran(): bool
    {
        Log::info('Invalidating jenis quran reference data cache');

        return $this->flushByTags(['reference', 'jenis_quran']);
    }

    /**
     * Invalidate certificate templates cache
     */
    public function invalidateTemplates(): bool
    {
        Log::info('Invalidating certificate templates reference data cache');

        return $this->flushByTags(['reference', 'templates']);
    }

    /**
     * Invalidate all reference data
     */
    public function invalidateAll(): bool
    {
        Log::info('Invalidating all reference data cache');

        return $this->flushByTags(['reference']);
    }

    /**
     * Warm reference data cache
     */
    public function warm(): bool
    {
        try {
            Log::info('Warming reference data cache...');

            // Warm all reference data
            $this->getStatusPengiriman();
            $this->getStatusPengiriman(false); // Including inactive
            $this->getJenisQuran();
            $this->getJenisQuran(false); // Including inactive
            $this->getCertificateTemplates();
            $this->getCertificateTemplates(false); // Including inactive

            // Warm derived data
            $this->getStatusOptions();
            $this->getJenisQuranOptions();
            $this->getCertificateTemplateOptions();
            $this->getStatusCategories();
            $this->getDefaultStatusId();
            $this->getAllReferenceData();

            Log::info('Reference data cache warmed successfully');

            return true;
        } catch (\Exception $e) {
            Log::error('Reference data cache warming failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get cache statistics for reference data
     */
    public function getDetailedStats(): array
    {
        $baseStats = $this->getStats();

        try {
            // Count specific reference data types
            $stats = [
                'status_pengiriman_count' => count($this->getStatusPengiriman(false)),
                'active_status_count' => count($this->getStatusPengiriman(true)),
                'jenis_quran_count' => count($this->getJenisQuran(false)),
                'active_jenis_count' => count($this->getJenisQuran(true)),
                'template_count' => count($this->getCertificateTemplates(false)),
                'active_template_count' => count($this->getCertificateTemplates(true)),
            ];

            return array_merge($baseStats, $stats);
        } catch (\Exception $e) {
            return array_merge($baseStats, [
                'error' => 'Could not get detailed stats: '.$e->getMessage(),
            ]);
        }
    }
}
