<?php

use App\Enums\RoleEnum;
use App\Models\BulkOperationLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Endpoint analitik operasi massal (`/admin/bulk-operations/analytics`) menjawab 500
 * di dash.
 *
 * Penyebabnya `PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY processing_time_ms)`:
 * agregat berurut itu tidak ada di MariaDB 10.11 yang dipakai produksi, sehingga
 * basis data menolak seluruh kueri (SQLSTATE[42000] 1064) — bukan hanya kolom
 * mediannya yang kosong. Karena itu ujinya BUKAN "median terisi", melainkan
 * "endpoint menjawab 200 dan angkanya benar": kueri yang salah sintaksis tetap
 * gagal walau tabelnya kosong.
 *
 * Median dan p95 sekarang dihitung di PHP dengan interpolasi linier, sama seperti
 * rumus PERCENTILE_CONT, supaya angkanya tidak bergeser dari yang dulu dimaksud.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function analitikBulkOps(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::SUPER_ADMIN->value);

    return $user->fresh();
}

/**
 * @param  list<int>  $waktuMs
 */
function catatOperasi(array $waktuMs): void
{
    foreach ($waktuMs as $ms) {
        BulkOperationLog::factory()->create([
            'processing_time_ms' => $ms,
            'operation_timestamp' => now()->subDay(),
        ]);
    }
}

it('menjawab 200 dan menghitung median serta p95 dari waktu proses', function () {
    catatOperasi([100, 200, 300, 400, 1000]);

    $response = $this->actingAs(analitikBulkOps())->get('/admin/bulk-operations/analytics');

    $response->assertOk();

    expect($response->json('processing_time_stats'))->toMatchArray([
        'avg_time' => 400,
        'min_time' => 100,
        'max_time' => 1000,
        // (5-1)*0.5 = 2 -> tepat di nilai ke-3
        'median_time' => 300,
        // (5-1)*0.95 = 3.8 -> 400 + 0,8 x (1000-400)
        'p95_time' => 880,
    ]);
});

it('tetap menjawab 200 walau belum ada operasi sama sekali', function () {
    // Kueri lama gagal di sini juga: yang salah sintaksisnya, bukan datanya.
    $response = $this->actingAs(analitikBulkOps())->get('/admin/bulk-operations/analytics');

    $response->assertOk();

    expect($response->json('processing_time_stats'))->toMatchArray([
        'avg_time' => null,
        'min_time' => null,
        'max_time' => null,
        'median_time' => null,
        'p95_time' => null,
    ]);
});

it('mengabaikan operasi di luar periode yang diminta', function () {
    catatOperasi([300, 300]);

    BulkOperationLog::factory()->create([
        'processing_time_ms' => 9000,
        'operation_timestamp' => now()->subDays(30),
    ]);

    $response = $this->actingAs(analitikBulkOps())->get('/admin/bulk-operations/analytics?period=7');

    $response->assertOk();

    expect($response->json('processing_time_stats'))->toMatchArray([
        'max_time' => 300,
        'median_time' => 300,
    ]);
});
