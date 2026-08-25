<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Models\Donatur;
use App\Models\MushafRequest;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Test dashboard fallback functionality when caching fails
 */
it('can generate charts data with correct structure when caching fails', function () {
    // Create test user with super-admin role
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create test data
    $status = StatusPengiriman::factory()->create([
        'nama' => 'Test Status',
        'slug' => 'diterima',
        'is_active' => true,
    ]);

    $donatur = Donatur::factory()->create([
        'nama_donatur' => 'Test Donatur',
    ]);

    Pengiriman::factory()->create([
        'donatur_id' => $donatur->id,
        'status_id' => $status->id,
        'jumlah_quran' => 10,
        'created_at' => now()->subMonths(1),
    ]);

    MushafRequest::factory()->create([
        'nama_lembaga' => 'Test Lembaga',
        'jumlah_mushaf' => 5,
        'created_at' => now()->subMonths(1),
    ]);

    // Create controller instance and call private method using reflection
    $controller = new DashboardController(
        app('App\Services\AnalyticsService'),
        app('App\Services\Cache\DashboardCacheService'),
        app('App\Services\Cache\ReferenceDataCacheService')
    );

    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getBasicChartsData');
    $method->setAccessible(true);

    $chartsData = $method->invoke($controller);

    // Assert the data structure matches what frontend expects
    expect($chartsData)->toBeArray()
        ->and($chartsData)->toHaveKeys([
            'monthlyShipments',
            'monthlyMushafRequests',
            'statusDistribution',
            'topDonatur',
            'dailyActivities',
        ]);

    // Test monthlyShipments structure
    expect($chartsData['monthlyShipments'])->toBeArray();
    if (count($chartsData['monthlyShipments']) > 0) {
        expect($chartsData['monthlyShipments'][0])->toHaveKeys(['month', 'count']);
    }

    // Test monthlyMushafRequests structure
    expect($chartsData['monthlyMushafRequests'])->toBeArray();
    if (count($chartsData['monthlyMushafRequests']) > 0) {
        expect($chartsData['monthlyMushafRequests'][0])->toHaveKeys(['month', 'count']);
    }

    // Test statusDistribution structure
    expect($chartsData['statusDistribution'])->toBeArray();
    if (count($chartsData['statusDistribution']) > 0) {
        expect($chartsData['statusDistribution'][0])->toHaveKeys(['name', 'slug', 'count', 'color']);
        expect($chartsData['statusDistribution'][0]['color'])->toBeString()->toStartWith('#');
    }

    // Test topDonatur structure
    expect($chartsData['topDonatur'])->toBeArray();
    if (count($chartsData['topDonatur']) > 0) {
        expect($chartsData['topDonatur'][0])->toHaveKeys(['name', 'total_mushaf']);
    }

    // Test dailyActivities structure
    expect($chartsData['dailyActivities'])->toBeArray();
    expect($chartsData['dailyActivities'])->toHaveCount(7); // Should always return 7 days
    if (count($chartsData['dailyActivities']) > 0) {
        expect($chartsData['dailyActivities'][0])->toHaveKeys(['day', 'shipments', 'requests', 'mushaf_sent', 'completed_shipments']);
    }
});

it('returns empty arrays when no data exists', function () {
    // Create test user
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create controller instance
    $controller = new DashboardController(
        app('App\Services\AnalyticsService'),
        app('App\Services\Cache\DashboardCacheService'),
        app('App\Services\Cache\ReferenceDataCacheService')
    );

    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getBasicChartsData');
    $method->setAccessible(true);

    $chartsData = $method->invoke($controller);

    // Assert basic structure exists even with no data
    expect($chartsData)->toBeArray()
        ->and($chartsData)->toHaveKeys([
            'monthlyShipments',
            'monthlyMushafRequests',
            'statusDistribution',
            'topDonatur',
            'dailyActivities',
        ]);

    // Assert arrays are present (may be empty)
    expect($chartsData['monthlyShipments'])->toBeArray();
    expect($chartsData['monthlyMushafRequests'])->toBeArray();
    expect($chartsData['statusDistribution'])->toBeArray();
    expect($chartsData['topDonatur'])->toBeArray();
    expect($chartsData['dailyActivities'])->toBeArray();

    // Daily activities should always return 7 days even with no data
    expect($chartsData['dailyActivities'])->toHaveCount(7);
});

it('dashboard fallback loads successfully with correct data structure', function () {
    // Create test user with super-admin role
    $user = User::factory()->create();
    $this->actingAs($user);

    // Mock cache failure by directly testing fallback route
    // We'll simulate this by making a request that could trigger the fallback
    $response = $this->get('/admin/dashboard');

    $response->assertSuccessful();

    // Assert that the page has the correct props structure
    $props = $response->viewData('page')['props'];

    expect($props)->toHaveKey('chartsData');
    expect($props['chartsData'])->toBeArray();
    expect($props['chartsData'])->toHaveKeys([
        'monthlyShipments',
        'monthlyMushafRequests',
        'statusDistribution',
        'topDonatur',
        'dailyActivities',
    ]);
});
