<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

/**
 * `performance:benchmark` dijalankan penjadwal tiap tengah malam. Produksi memakai
 * `CACHE_STORE=database`, tetapi bagian "cache tags" di dalam perintah itu memanggil
 * `Cache::tags()` yang tidak didukung store database — akibatnya perintah melempar
 * `BadMethodCallException` setiap malam, dan 100 kunci cache tertulis tanpa pernah
 * dibersihkan.
 */
it('tetap berjalan pada store cache yang tidak mendukung tag (database)', function () {
    Config::set('cache.default', 'database');
    app()->forgetInstance('cache');

    $this->artisan('performance:benchmark', [
        '--iterations' => 1,
        '--warmup' => 0,
        '--output' => 'json',
    ])->assertSuccessful();
});

it('tetap berjalan pada store cache yang mendukung tag (array)', function () {
    $this->artisan('performance:benchmark', [
        '--iterations' => 1,
        '--warmup' => 0,
        '--output' => 'json',
    ])->assertSuccessful();
});
