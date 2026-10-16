<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Cache\GeographicCacheService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WilayahController extends Controller
{
    use ApiResponse;

    protected GeographicCacheService $geographicCache;

    public function __construct(GeographicCacheService $geographicCache)
    {
        $this->geographicCache = $geographicCache;
    }

    /**
     * Get all provinces
     */
    public function provinces()
    {
        try {
            $startTime = microtime(true);

            $data = $this->geographicCache->getProvinces();

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            if ($data === null) {
                Log::error('Failed to fetch provinces data');

                return $this->serverError('Gagal memuat data provinsi');
            }

            Log::info('Provinces loaded via cache', [
                'count' => count($data),
                'response_time_ms' => $responseTime,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data provinsi berhasil dimuat',
                'data' => $data,
                'meta' => [
                    'cached' => true,
                    'response_time_ms' => $responseTime,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching provinces: '.$e->getMessage());

            return $this->serverError('Gagal memuat data provinsi');
        }
    }

    /**
     * Get regencies by province ID
     */
    public function regencies($provinceId)
    {
        // Validate province ID
        if (! preg_match('/^[0-9]{1,2}$/', $provinceId)) {
            return $this->error('Invalid province ID format', 400, 'INVALID_PROVINCE_ID');
        }

        try {
            $startTime = microtime(true);

            $data = $this->geographicCache->getRegencies($provinceId);

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            if ($data === null) {
                Log::error("Failed to fetch regencies for province {$provinceId}");

                return $this->serverError('Gagal memuat data kota/kabupaten');
            }

            Log::info('Regencies loaded via cache', [
                'province_id' => $provinceId,
                'count' => count($data),
                'response_time_ms' => $responseTime,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data kota/kabupaten berhasil dimuat',
                'data' => $data,
                'meta' => [
                    'cached' => true,
                    'response_time_ms' => $responseTime,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching regencies for province {$provinceId}: ".$e->getMessage());

            return $this->serverError('Gagal memuat data kota/kabupaten');
        }
    }

    /**
     * Get districts by regency ID
     */
    public function districts($regencyId)
    {
        // Validate regency ID
        if (! preg_match('/^[0-9]{2,10}$/', $regencyId)) {
            return $this->error('Invalid regency ID format', 400, 'INVALID_REGENCY_ID');
        }

        try {
            $startTime = microtime(true);

            $data = $this->geographicCache->getDistricts($regencyId);

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            if ($data === null) {
                return $this->error('Regency not found', 404, 'REGENCY_NOT_FOUND');
            }

            Log::info('Districts loaded via cache', [
                'regency_id' => $regencyId,
                'count' => count($data),
                'response_time_ms' => $responseTime,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data kecamatan berhasil dimuat',
                'data' => $data,
                'meta' => [
                    'cached' => true,
                    'response_time_ms' => $responseTime,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching districts for regency {$regencyId}: ".$e->getMessage());

            if (str_contains($e->getMessage(), 'not found') || $e->getCode() === 404) {
                return $this->error('Regency not found', 404, 'REGENCY_NOT_FOUND');
            }

            return $this->serverError('Gagal memuat data kecamatan');
        }
    }

    /**
     * Get villages by district ID
     */
    public function villages($districtId)
    {
        // Validate district ID
        if (! preg_match('/^[0-9]{2,10}$/', $districtId)) {
            return $this->error('Invalid district ID format', 400, 'INVALID_DISTRICT_ID');
        }

        try {
            $startTime = microtime(true);

            $data = $this->geographicCache->getVillages($districtId);

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            if ($data === null) {
                return $this->error('District not found', 404, 'DISTRICT_NOT_FOUND');
            }

            Log::info('Villages loaded via cache', [
                'district_id' => $districtId,
                'count' => count($data),
                'response_time_ms' => $responseTime,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data kelurahan/desa berhasil dimuat',
                'data' => $data,
                'meta' => [
                    'cached' => true,
                    'response_time_ms' => $responseTime,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching villages for district {$districtId}: ".$e->getMessage());

            if (str_contains($e->getMessage(), 'not found') || $e->getCode() === 404) {
                return $this->error('District not found', 404, 'DISTRICT_NOT_FOUND');
            }

            return $this->serverError('Gagal memuat data kelurahan/desa');
        }
    }

    /**
     * Get address hierarchy (new optimized endpoint)
     */
    public function hierarchy($provinceId, Request $request)
    {
        // Validate province ID
        if (! preg_match('/^[0-9]{1,2}$/', $provinceId)) {
            return $this->error('Invalid province ID format', 400, 'INVALID_PROVINCE_ID');
        }

        $regencyId = $request->get('regency_id');
        $districtId = $request->get('district_id');

        // Validate optional IDs
        if ($regencyId && ! preg_match('/^[0-9]{2,10}$/', $regencyId)) {
            return $this->error('Invalid regency ID format', 400, 'INVALID_REGENCY_ID');
        }

        if ($districtId && ! preg_match('/^[0-9]{2,10}$/', $districtId)) {
            return $this->error('Invalid district ID format', 400, 'INVALID_DISTRICT_ID');
        }

        try {
            $startTime = microtime(true);

            $data = $this->geographicCache->getAddressHierarchy($provinceId, $regencyId, $districtId);

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            Log::info('Address hierarchy loaded via cache', [
                'province_id' => $provinceId,
                'regency_id' => $regencyId,
                'district_id' => $districtId,
                'response_time_ms' => $responseTime,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data hierarki alamat berhasil dimuat',
                'data' => $data,
                'meta' => [
                    'cached' => true,
                    'response_time_ms' => $responseTime,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching address hierarchy', [
                'province_id' => $provinceId,
                'regency_id' => $regencyId,
                'district_id' => $districtId,
                'error' => $e->getMessage(),
            ]);

            return $this->serverError('Gagal memuat data hierarki alamat');
        }
    }

    /**
     * Search provinces (new endpoint)
     */
    public function searchProvinces(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return $this->error('Query must be at least 2 characters', 400, 'QUERY_TOO_SHORT');
        }

        try {
            $startTime = microtime(true);

            $data = $this->geographicCache->searchProvinces($query);

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            Log::info('Province search via cache', [
                'query' => $query,
                'results_count' => count($data),
                'response_time_ms' => $responseTime,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Hasil pencarian provinsi',
                'data' => $data,
                'meta' => [
                    'cached' => true,
                    'response_time_ms' => $responseTime,
                    'query' => $query,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching provinces', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return $this->serverError('Gagal melakukan pencarian provinsi');
        }
    }

    /**
     * Get popular provinces (new endpoint)
     */
    public function popularProvinces()
    {
        try {
            $startTime = microtime(true);

            $data = $this->geographicCache->getPopularProvinces(10);

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            Log::info('Popular provinces loaded via cache', [
                'count' => count($data),
                'response_time_ms' => $responseTime,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data provinsi populer berhasil dimuat',
                'data' => $data,
                'meta' => [
                    'cached' => true,
                    'response_time_ms' => $responseTime,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching popular provinces: '.$e->getMessage());

            return $this->serverError('Gagal memuat data provinsi populer');
        }
    }

    /**
     * Clear cache for wilayah data (admin endpoint)
     */
    public function clearCache()
    {
        try {
            $cleared = $this->geographicCache->clearGeographicCache();

            Log::info('Geographic cache cleared successfully via API');

            return response()->json([
                'success' => $cleared,
                'message' => $cleared ? 'Cache cleared successfully' : 'Failed to clear cache',
            ]);

        } catch (\Exception $e) {
            Log::error('Error clearing wilayah cache: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Failed to clear cache',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get cache statistics (admin endpoint)
     */
    public function cacheStats()
    {
        try {
            $stats = $this->geographicCache->getDetailedStats();

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Cache statistics retrieved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting cache stats: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Failed to get cache statistics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
