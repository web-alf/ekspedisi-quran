<?php

namespace Tests\Feature\Public;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Pengiriman;
use App\Models\StatusHistory;
use App\Models\TrackingHistory;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Models\Donatur;
use App\Models\JenisQuran;

class TrackingStatusHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'StatusPengirimanSeeder']);
    }

    /**
     * Test tracking page shows StatusHistory documentation photos
     */
    public function test_tracking_page_shows_status_history_photos()
    {
        $pengiriman = Pengiriman::factory()->create([
            'no_resi' => 'EQ-2025-TEST01'
        ]);

        $user = User::factory()->create();

        // Create StatusHistory with dokumentasi
        StatusHistory::create([
            'pengiriman_id' => $pengiriman->id,
            'status_from' => StatusPengiriman::first()->id,
            'status_to' => StatusPengiriman::skip(1)->first()->id,
            'catatan' => 'Update dari Box Scanner',
            'lokasi' => 'Warehouse Jakarta',
            'created_by' => $user->id,
            'dokumentasi' => [
                'status_updates/test1.jpg',
                'status_updates/test2.jpg'
            ]
        ]);

        $response = $this->get('/tracking/EQ-2025-TEST01');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/TrackingResult')
            ->has('statusHistory', 1)
            ->where('statusHistory.0.catatan', 'Update dari Box Scanner')
            ->where('statusHistory.0.lokasi', 'Warehouse Jakarta')
            ->has('statusHistory.0.foto_dokumentasi', 2)
        );
    }

    /**
     * Test tracking page handles double-encoded documentation
     */
    public function test_tracking_page_handles_double_encoded_documentation()
    {
        $pengiriman = Pengiriman::factory()->create([
            'no_resi' => 'EQ-2025-TEST02'
        ]);

        $user = User::factory()->create();

        // Insert double-encoded data
        $doubleEncoded = json_encode(json_encode([
            'dokumentasi/test1.jpg',
            'dokumentasi/test2.jpg'
        ]));

        \DB::table('status_histories')->insert([
            'pengiriman_id' => $pengiriman->id,
            'status_from' => StatusPengiriman::first()->id,
            'status_to' => StatusPengiriman::skip(1)->first()->id,
            'catatan' => 'Legacy double-encoded data',
            'created_by' => $user->id,
            'dokumentasi' => $doubleEncoded,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $response = $this->get('/tracking/EQ-2025-TEST02');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/TrackingResult')
            ->has('statusHistory', 1)
            ->has('statusHistory.0.foto_dokumentasi', 2)
        );
    }

    /**
     * Test tracking page shows both StatusHistory and TrackingHistory
     */
    public function test_tracking_page_shows_combined_histories()
    {
        $pengiriman = Pengiriman::factory()->create([
            'no_resi' => 'EQ-2025-TEST03'
        ]);

        $user = User::factory()->create();
        $status = StatusPengiriman::first();

        // Create StatusHistory
        StatusHistory::create([
            'pengiriman_id' => $pengiriman->id,
            'status_from' => $status->id,
            'status_to' => StatusPengiriman::skip(1)->first()->id,
            'catatan' => 'From BoxScanner',
            'created_by' => $user->id,
            'dokumentasi' => ['status_updates/test1.jpg']
        ]);

        // Create TrackingHistory
        TrackingHistory::create([
            'pengiriman_id' => $pengiriman->id,
            'status_id' => $status->id,
            'user_id' => $user->id,
            'tanggal_update' => now(),
            'keterangan' => 'Regular tracking update',
            'foto_dokumentasi' => ['dokumentasi/test2.jpg']
        ]);

        $response = $this->get('/tracking/EQ-2025-TEST03');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/TrackingResult')
            ->has('statusHistory', 1)
            ->has('trackingHistory', 1)
        );
    }

    /**
     * Test tracking page handles StatusHistory with no documentation
     */
    public function test_tracking_page_handles_no_documentation()
    {
        $pengiriman = Pengiriman::factory()->create([
            'no_resi' => 'EQ-2025-TEST04'
        ]);

        $user = User::factory()->create();

        StatusHistory::create([
            'pengiriman_id' => $pengiriman->id,
            'status_from' => StatusPengiriman::first()->id,
            'status_to' => StatusPengiriman::skip(1)->first()->id,
            'catatan' => 'No photos',
            'created_by' => $user->id,
            'dokumentasi' => null
        ]);

        $response = $this->get('/tracking/EQ-2025-TEST04');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/TrackingResult')
            ->has('statusHistory', 1)
            ->where('statusHistory.0.foto_dokumentasi', [])
        );
    }

    /**
     * Test tracking page handles object format documentation
     */
    public function test_tracking_page_handles_object_format_documentation()
    {
        $pengiriman = Pengiriman::factory()->create([
            'no_resi' => 'EQ-2025-TEST05'
        ]);

        $user = User::factory()->create();

        StatusHistory::create([
            'pengiriman_id' => $pengiriman->id,
            'status_from' => StatusPengiriman::first()->id,
            'status_to' => StatusPengiriman::skip(1)->first()->id,
            'catatan' => 'BoxScanner object format',
            'created_by' => $user->id,
            'dokumentasi' => [
                [
                    'path' => 'status_updates/test.jpg',
                    'original_name' => 'photo.jpg',
                    'url' => asset('storage/status_updates/test.jpg')
                ]
            ]
        ]);

        $response = $this->get('/tracking/EQ-2025-TEST05');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/TrackingResult')
            ->has('statusHistory.0.foto_dokumentasi', 1)
        );

        // Verify URL format
        $props = $response->viewData('page')['props'];
        $photo = $props['statusHistory'][0]['foto_dokumentasi'][0];
        $this->assertStringContainsString('storage/status_updates/test.jpg', $photo);
    }

    /**
     * Test API endpoint returns StatusHistory documentation
     */
    public function test_api_returns_status_history_documentation()
    {
        $pengiriman = Pengiriman::factory()->create([
            'no_resi' => 'EQ-2025-TEST06'
        ]);

        $user = User::factory()->create();

        StatusHistory::create([
            'pengiriman_id' => $pengiriman->id,
            'status_from' => StatusPengiriman::first()->id,
            'status_to' => StatusPengiriman::skip(1)->first()->id,
            'catatan' => 'API test',
            'created_by' => $user->id,
            'dokumentasi' => ['status_updates/api-test.jpg']
        ]);

        $response = $this->getJson('/api/tracking/EQ-2025-TEST06');

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'status_history' => [
                    '*' => ['catatan', 'tanggal']
                ]
            ]
        ]);
    }

    /**
     * Test tracking page doesn't break with malformed documentation data
     */
    public function test_tracking_page_handles_malformed_documentation()
    {
        $pengiriman = Pengiriman::factory()->create([
            'no_resi' => 'EQ-2025-TEST07'
        ]);

        $user = User::factory()->create();

        // Insert malformed data
        \DB::table('status_histories')->insert([
            'pengiriman_id' => $pengiriman->id,
            'status_from' => StatusPengiriman::first()->id,
            'status_to' => StatusPengiriman::skip(1)->first()->id,
            'catatan' => 'Malformed data',
            'created_by' => $user->id,
            'dokumentasi' => '{invalid json}',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $response = $this->get('/tracking/EQ-2025-TEST07');

        // Should still render without error
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/TrackingResult')
            ->has('statusHistory', 1)
        );
    }

    /**
     * Test tracking page shows location from StatusHistory
     */
    public function test_tracking_page_shows_location_from_status_history()
    {
        $pengiriman = Pengiriman::factory()->create([
            'no_resi' => 'EQ-2025-TEST08'
        ]);

        $user = User::factory()->create();

        StatusHistory::create([
            'pengiriman_id' => $pengiriman->id,
            'status_from' => StatusPengiriman::first()->id,
            'status_to' => StatusPengiriman::skip(1)->first()->id,
            'catatan' => 'With location',
            'lokasi' => 'Jakarta Distribution Center',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'created_by' => $user->id
        ]);

        $response = $this->get('/tracking/EQ-2025-TEST08');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/TrackingResult')
            ->where('statusHistory.0.lokasi', 'Jakarta Distribution Center')
            ->where('statusHistory.0.has_location', true)
        );
    }

    /**
     * Test tracking page performance with many history entries
     */
    public function test_tracking_page_handles_many_history_entries()
    {
        $pengiriman = Pengiriman::factory()->create([
            'no_resi' => 'EQ-2025-TEST09'
        ]);

        $user = User::factory()->create();
        $statuses = StatusPengiriman::take(5)->get();

        // Create multiple StatusHistory entries
        for ($i = 0; $i < 10; $i++) {
            StatusHistory::create([
                'pengiriman_id' => $pengiriman->id,
                'status_from' => $statuses[$i % 4]->id,
                'status_to' => $statuses[($i % 4) + 1]->id,
                'catatan' => "Update #{$i}",
                'created_by' => $user->id,
                'dokumentasi' => ["status_updates/test{$i}.jpg"],
                'created_at' => now()->subHours(10 - $i)
            ]);
        }

        $start = microtime(true);
        $response = $this->get('/tracking/EQ-2025-TEST09');
        $duration = microtime(true) - $start;

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('statusHistory', 10)
        );

        // Performance check: should load in less than 1 second
        $this->assertLessThan(1.0, $duration);
    }

    /**
     * Test tracking page shows creator information
     */
    public function test_tracking_page_shows_creator_information()
    {
        $pengiriman = Pengiriman::factory()->create([
            'no_resi' => 'EQ-2025-TEST10'
        ]);

        $user = User::factory()->create(['name' => 'John Warehouse']);

        StatusHistory::create([
            'pengiriman_id' => $pengiriman->id,
            'status_from' => StatusPengiriman::first()->id,
            'status_to' => StatusPengiriman::skip(1)->first()->id,
            'catatan' => 'With creator',
            'created_by' => $user->id
        ]);

        $response = $this->get('/tracking/EQ-2025-TEST10');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/TrackingResult')
            ->where('statusHistory.0.creator.name', 'John Warehouse')
        );
    }
}
