<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeographicCacheService extends BaseCacheService
{
    protected string $prefix = 'geographic';

    protected int $defaultTtl = 86400; // 24 hours for geographic data

    protected array $tags = ['geographic', 'wilayah'];

    private const API_BASE = 'https://www.emsifa.com/api-wilayah-indonesia/api';

    private const API_TIMEOUT = 30;

    /**
     * Get all provinces with caching
     */
    public function getProvinces(): ?array
    {
        return $this->remember('provinces', function () {
            Log::info('Fetching provinces from external API');

            try {
                $response = Http::timeout(self::API_TIMEOUT)->get(self::API_BASE.'/provinces.json');

                if ($response->failed()) {
                    throw new \Exception('Failed to fetch provinces: '.$response->status());
                }

                $data = $response->json();

                if (! is_array($data)) {
                    throw new \Exception('Invalid data format received');
                }

                Log::info('Successfully fetched '.count($data).' provinces');

                return $data;
            } catch (\Exception $e) {
                Log::error('Error fetching provinces: '.$e->getMessage());
                throw $e;
            }
        }, $this->defaultTtl, ['geographic', 'provinces']);
    }

    /**
     * Get regencies by province ID with caching
     */
    public function getRegencies(string $provinceId): ?array
    {
        // Validate province ID
        if (! preg_match('/^[0-9]{1,2}$/', $provinceId)) {
            Log::warning('Invalid province ID format', ['province_id' => $provinceId]);

            return null;
        }

        return $this->remember("regencies_{$provinceId}", function () use ($provinceId) {
            Log::info("Fetching regencies for province {$provinceId}");

            try {
                $response = Http::timeout(self::API_TIMEOUT)
                    ->get(self::API_BASE."/regencies/{$provinceId}.json");

                if ($response->failed()) {
                    throw new \Exception('Failed to fetch regencies: '.$response->status());
                }

                $data = $response->json();

                if (! is_array($data)) {
                    throw new \Exception('Invalid data format received');
                }

                Log::info('Successfully fetched '.count($data)." regencies for province {$provinceId}");

                return $data;
            } catch (\Exception $e) {
                Log::error("Error fetching regencies for province {$provinceId}: ".$e->getMessage());
                throw $e;
            }
        }, $this->defaultTtl, ['geographic', 'regencies', "province_{$provinceId}"]);
    }

    /**
     * Get districts by regency ID with caching
     */
    public function getDistricts(string $regencyId): ?array
    {
        // Validate regency ID
        if (! preg_match('/^[0-9]{2,10}$/', $regencyId)) {
            Log::warning('Invalid regency ID format', ['regency_id' => $regencyId]);

            return null;
        }

        return $this->remember("districts_{$regencyId}", function () use ($regencyId) {
            Log::info("Fetching districts for regency {$regencyId}");

            try {
                $response = Http::timeout(self::API_TIMEOUT)
                    ->get(self::API_BASE."/districts/{$regencyId}.json");

                if ($response->failed()) {
                    if ($response->status() === 404) {
                        throw new \Exception('Regency not found', 404);
                    }
                    throw new \Exception('Failed to fetch districts: '.$response->status());
                }

                $data = $response->json();

                if (! is_array($data)) {
                    throw new \Exception('Invalid data format received');
                }

                Log::info('Successfully fetched '.count($data)." districts for regency {$regencyId}");

                return $data;
            } catch (\Exception $e) {
                Log::error("Error fetching districts for regency {$regencyId}: ".$e->getMessage());
                throw $e;
            }
        }, $this->defaultTtl, ['geographic', 'districts', "regency_{$regencyId}"]);
    }

    /**
     * Get villages by district ID with caching
     */
    public function getVillages(string $districtId): ?array
    {
        // Validate district ID
        if (! preg_match('/^[0-9]{2,10}$/', $districtId)) {
            Log::warning('Invalid district ID format', ['district_id' => $districtId]);

            return null;
        }

        return $this->remember("villages_{$districtId}", function () use ($districtId) {
            Log::info("Fetching villages for district {$districtId}");

            try {
                $response = Http::timeout(self::API_TIMEOUT)
                    ->get(self::API_BASE."/villages/{$districtId}.json");

                if ($response->failed()) {
                    if ($response->status() === 404) {
                        throw new \Exception('District not found', 404);
                    }
                    throw new \Exception('Failed to fetch villages: '.$response->status());
                }

                $data = $response->json();

                if (! is_array($data)) {
                    throw new \Exception('Invalid data format received');
                }

                Log::info('Successfully fetched '.count($data)." villages for district {$districtId}");

                return $data;
            } catch (\Exception $e) {
                Log::error("Error fetching villages for district {$districtId}: ".$e->getMessage());
                throw $e;
            }
        }, $this->defaultTtl, ['geographic', 'villages', "district_{$districtId}"]);
    }

    /**
     * Get complete address hierarchy (optimized for forms)
     */
    public function getAddressHierarchy(string $provinceId, ?string $regencyId = null, ?string $districtId = null): array
    {
        $cacheKey = "hierarchy_{$provinceId}";
        if ($regencyId) {
            $cacheKey .= "_{$regencyId}";
        }
        if ($districtId) {
            $cacheKey .= "_{$districtId}";
        }

        return $this->remember($cacheKey, function () use ($provinceId, $regencyId, $districtId) {
            $hierarchy = [
                'province' => null,
                'regencies' => [],
                'districts' => [],
                'villages' => [],
            ];

            try {
                // Get provinces and find the selected one
                $provinces = $this->getProvinces();
                if ($provinces) {
                    foreach ($provinces as $province) {
                        if ($province['id'] == $provinceId) {
                            $hierarchy['province'] = $province;
                            break;
                        }
                    }
                }

                // Get regencies for the province
                $hierarchy['regencies'] = $this->getRegencies($provinceId) ?? [];

                // If regency is specified, get districts
                if ($regencyId) {
                    $hierarchy['districts'] = $this->getDistricts($regencyId) ?? [];
                }

                // If district is specified, get villages
                if ($districtId) {
                    $hierarchy['villages'] = $this->getVillages($districtId) ?? [];
                }

                return $hierarchy;
            } catch (\Exception $e) {
                Log::error('Error building address hierarchy', [
                    'province_id' => $provinceId,
                    'regency_id' => $regencyId,
                    'district_id' => $districtId,
                    'error' => $e->getMessage(),
                ]);

                return $hierarchy; // Return empty structure on error
            }
        }, 1800, ['geographic', 'hierarchy']); // 30 minutes for hierarchy
    }

    /**
     * Search locations by name (cached)
     */
    public function searchProvinces(string $query): array
    {
        $provinces = $this->getProvinces();
        if (! $provinces) {
            return [];
        }

        $query = strtolower($query);

        return array_filter($provinces, function ($province) use ($query) {
            return str_contains(strtolower($province['name']), $query);
        });
    }

    /**
     * Search regencies by name within a province
     */
    public function searchRegencies(string $provinceId, string $query): array
    {
        $regencies = $this->getRegencies($provinceId);
        if (! $regencies) {
            return [];
        }

        $query = strtolower($query);

        return array_filter($regencies, function ($regency) use ($query) {
            return str_contains(strtolower($regency['name']), $query);
        });
    }

    /**
     * Get popular provinces (most commonly used)
     */
    public function getPopularProvinces(int $limit = 10): array
    {
        return $this->remember("popular_provinces_{$limit}", function () use ($limit) {
            // Common provinces in Indonesia by population/activity
            $popularIds = ['11', '12', '13', '14', '15', '31', '32', '33', '34', '35'];

            $provinces = $this->getProvinces();
            if (! $provinces) {
                return [];
            }

            $popular = [];
            foreach ($popularIds as $id) {
                foreach ($provinces as $province) {
                    if ($province['id'] == $id) {
                        $popular[] = $province;
                        break;
                    }
                }
                if (count($popular) >= $limit) {
                    break;
                }
            }

            return $popular;
        }, 3600, ['geographic', 'popular']); // 1 hour
    }

    /**
     * Validate address data
     */
    public function validateAddress(array $address): array
    {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
        ];

        // Check required fields
        if (empty($address['province_id'])) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Province ID is required';
        }

        if (! empty($address['province_id']) && ! preg_match('/^[0-9]{1,2}$/', $address['province_id'])) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Invalid province ID format';
        }

        if (! empty($address['regency_id']) && ! preg_match('/^[0-9]{2,10}$/', $address['regency_id'])) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Invalid regency ID format';
        }

        if (! empty($address['district_id']) && ! preg_match('/^[0-9]{2,10}$/', $address['district_id'])) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Invalid district ID format';
        }

        if (! empty($address['village_id']) && ! preg_match('/^[0-9]{2,10}$/', $address['village_id'])) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Invalid village ID format';
        }

        return $validation;
    }

    /**
     * Get location name by ID with caching
     */
    public function getLocationName(string $type, string $id): ?string
    {
        return $this->remember("location_name_{$type}_{$id}", function () use ($type, $id) {
            try {
                switch ($type) {
                    case 'province':
                        $provinces = $this->getProvinces();
                        if ($provinces) {
                            foreach ($provinces as $province) {
                                if ($province['id'] == $id) {
                                    return $province['name'];
                                }
                            }
                        }
                        break;

                    case 'regency':
                        // We need province ID to get regencies, this is a limitation
                        // For now, return null and let the caller handle it
                        return null;

                        // Similar for district and village
                    default:
                        return null;
                }

                return null;
            } catch (\Exception $e) {
                Log::error('Error getting location name', [
                    'type' => $type,
                    'id' => $id,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }, 3600, ['geographic', 'names']);
    }

    /**
     * Clear geographic cache
     */
    public function clearGeographicCache(): bool
    {
        Log::info('Clearing geographic cache');

        return $this->flushByTags(['geographic']);
    }

    /**
     * Clear cache for specific province
     */
    public function clearProvinceCache(string $provinceId): bool
    {
        Log::info("Clearing cache for province {$provinceId}");

        return $this->flushByTags(["province_{$provinceId}"]);
    }

    /**
     * Warm geographic cache with essential data
     */
    public function warm(): bool
    {
        try {
            Log::info('Warming geographic cache...');

            // Warm provinces (most important)
            $this->getProvinces();

            // Warm popular provinces
            $this->getPopularProvinces();

            // Pre-warm some major provinces (Jakarta, Jawa Barat, Jawa Tengah)
            $majorProvinces = ['31', '32', '33']; // Jakarta, Jabar, Jateng
            foreach ($majorProvinces as $provinceId) {
                try {
                    $this->getRegencies($provinceId);
                } catch (\Exception $e) {
                    Log::warning("Could not warm regencies for province {$provinceId}: ".$e->getMessage());
                }
            }

            Log::info('Geographic cache warmed successfully');

            return true;
        } catch (\Exception $e) {
            Log::error('Geographic cache warming failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get detailed geographic cache statistics
     */
    public function getDetailedStats(): array
    {
        $baseStats = $this->getStats();

        try {
            $stats = [
                'api_base_url' => self::API_BASE,
                'api_timeout' => self::API_TIMEOUT,
            ];

            // Count cached provinces
            try {
                $provinces = $this->getProvinces();
                $stats['provinces_count'] = $provinces ? count($provinces) : 0;
            } catch (\Exception $e) {
                $stats['provinces_error'] = $e->getMessage();
            }

            return array_merge($baseStats, $stats);
        } catch (\Exception $e) {
            return array_merge($baseStats, [
                'error' => 'Could not get detailed stats: '.$e->getMessage(),
            ]);
        }
    }
}
