<?php

use App\Services\Cache\CacheManager;
use App\Services\QueryAnalyzer;

/**
 * Garansi super-admin bisa membuka SEMUA halaman tanpa 500 — termasuk dua
 * halaman diagnostik yang dulu meledak (crawl 105 halaman, 13/10/2026):
 * 1. /admin/query-optimization → rekursi silang getPerformanceMetrics ↔
 *    calculatePerformanceGrade → "Maximum call stack".
 * 2. /admin/cache/test → CacheManager memanggil store/retrieve/forget yang
 *    protected di BaseCacheService → "Call to protected method".
 */
it('grade performa tidak meledak meski log penuh query lambat', function () {
    $analyzer = app(QueryAnalyzer::class);
    $analyzer->resetAnalysis();

    $property = (new ReflectionClass($analyzer))->getProperty('queryLog');
    $property->setValue($analyzer, array_map(fn ($i) => [
        'sql' => 'select * from pengiriman_'.$i,
        'bindings' => [],
        'execution_time' => 500 + $i, // semua di atas ambang critical
        'connection' => 'testing',
        'file' => 'uji.php',
        'line' => $i,
    ], range(1, 4000)));

    // Rekursi lama meledak di sini (call stack penuh); versi baru selesai normal.
    $metrics = $analyzer->getPerformanceMetrics();

    expect($metrics['performance_grade'])->toBeString()->toBe('F')
        ->and($metrics['total_queries'])->toBe(4000);
});

it('CacheManager mengetes semua layanan cache tanpa salah akses protected', function () {
    $manager = app(CacheManager::class);

    $hasil = $manager->testCache();

    expect($hasil['results'])->toBeArray()->not()->toBeEmpty();

    // Hasil harus boolean dan sukses; kalau ada yang gagal pasti karena
    // masalah cache nyata, BUKAN lagi error akses protected.
    foreach ($hasil['results'] as $nama => $r) {
        expect($r['overall'])->toBeBool();
        if (! $r['overall']) {
            expect($r['error'])->not->toContain('protected');
        }
    }
});
