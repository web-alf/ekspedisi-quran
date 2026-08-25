<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\StatusHistory;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;

class StatusHistoryDokumentasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'StatusPengirimanSeeder']);
    }

    /**
     * Test getDocumentationPhotos handles simple string array format
     */
    public function test_handles_simple_string_array_format()
    {
        $pengiriman = Pengiriman::factory()->create();
        $user = User::factory()->create();

        $statusHistory = StatusHistory::create([
            'pengiriman_id' => $pengiriman->id,
            'status_from' => StatusPengiriman::first()->id,
            'status_to' => StatusPengiriman::skip(1)->first()->id,
            'catatan' => 'Test simple format',
            'created_by' => $user->id,
            'dokumentasi' => [
                'dokumentasi/test1.jpg',
                'dokumentasi/test2.jpg'
            ]
        ]);

        $photos = $statusHistory->getDocumentationPhotos();

        $this->assertIsArray($photos);
        $this->assertCount(2, $photos);
        $this->assertStringContainsString('storage/dokumentasi/test1.jpg', $photos[0]);
        $this->assertStringContainsString('storage/dokumentasi/test2.jpg', $photos[1]);
    }

    /**
     * Test getDocumentationPhotos handles object array format (BoxScanner)
     */
    public function test_handles_object_array_format()
    {
        $pengiriman = Pengiriman::factory()->create();
        $user = User::factory()->create();

        $statusHistory = StatusHistory::create([
            'pengiriman_id' => $pengiriman->id,
            'status_from' => StatusPengiriman::first()->id,
            'status_to' => StatusPengiriman::skip(1)->first()->id,
            'catatan' => 'Test object format',
            'created_by' => $user->id,
            'dokumentasi' => [
                [
                    'path' => 'status_updates/test1.jpg',
                    'original_name' => 'photo1.jpg',
                    'url' => asset('storage/status_updates/test1.jpg')
                ]
            ]
        ]);

        $photos = $statusHistory->getDocumentationPhotos();

        $this->assertIsArray($photos);
        $this->assertCount(1, $photos);
        $this->assertStringContainsString('storage/status_updates/test1.jpg', $photos[0]);
    }

    /**
     * Test handles double-encoded JSON (legacy data)
     */
    public function test_handles_double_encoded_json()
    {
        $pengiriman = Pengiriman::factory()->create();
        $user = User::factory()->create();

        // Simulate double-encoded data by inserting raw SQL
        $doubleEncoded = json_encode(json_encode([
            'dokumentasi/EQ-2025-00019/1760359450_0.jpg',
            'dokumentasi/EQ-2025-00019/1760359450_1.jpg'
        ]));

        \DB::table('status_histories')->insert([
            'pengiriman_id' => $pengiriman->id,
            'status_from' => StatusPengiriman::first()->id,
            'status_to' => StatusPengiriman::skip(1)->first()->id,
            'catatan' => 'Test double encoding',
            'created_by' => $user->id,
            'dokumentasi' => $doubleEncoded,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $statusHistory = StatusHistory::find(\DB::getPdo()->lastInsertId());
        $photos = $statusHistory->getDocumentationPhotos();

        $this->assertIsArray($photos);
        $this->assertCount(2, $photos);
        $this->assertStringContainsString('storage/dokumentasi', $photos[0]);
    }

    /**
     * Test getDokumentasiUrlsAttribute accessor works
     */
    public function test_dokumentasi_urls_accessor_works()
    {
        $pengiriman = Pengiriman::factory()->create();
        $user = User::factory()->create();

        $statusHistory = StatusHistory::create([
            'pengiriman_id' => $pengiriman->id,
            'status_from' => StatusPengiriman::first()->id,
            'status_to' => StatusPengiriman::skip(1)->first()->id,
            'catatan' => 'Test accessor',
            'created_by' => $user->id,
            'dokumentasi' => [
                'dokumentasi/test1.jpg',
                'dokumentasi/test2.jpg'
            ]
        ]);

        $urls = $statusHistory->dokumentasi_urls;

        $this->assertIsArray($urls);
        $this->assertCount(2, $urls);
    }

    /**
     * Test returns empty array when no dokumentasi
     */
    public function test_returns_empty_array_when_no_dokumentasi()
    {
        $pengiriman = Pengiriman::factory()->create();
        $user = User::factory()->create();

        $statusHistory = StatusHistory::create([
            'pengiriman_id' => $pengiriman->id,
            'status_from' => StatusPengiriman::first()->id,
            'status_to' => StatusPengiriman::skip(1)->first()->id,
            'catatan' => 'No photos',
            'created_by' => $user->id,
            'dokumentasi' => null
        ]);

        $photos = $statusHistory->getDocumentationPhotos();

        $this->assertIsArray($photos);
        $this->assertEmpty($photos);
    }

    /**
     * Test handles empty dokumentasi array
     */
    public function test_handles_empty_dokumentasi_array()
    {
        $pengiriman = Pengiriman::factory()->create();
        $user = User::factory()->create();

        $statusHistory = StatusHistory::create([
            'pengiriman_id' => $pengiriman->id,
            'status_from' => StatusPengiriman::first()->id,
            'status_to' => StatusPengiriman::skip(1)->first()->id,
            'catatan' => 'Empty array',
            'created_by' => $user->id,
            'dokumentasi' => []
        ]);

        $photos = $statusHistory->getDocumentationPhotos();

        $this->assertIsArray($photos);
        $this->assertEmpty($photos);
    }

    /**
     * Test handles mixed format (legacy + new)
     */
    public function test_handles_mixed_format()
    {
        $pengiriman = Pengiriman::factory()->create();
        $user = User::factory()->create();

        $statusHistory = StatusHistory::create([
            'pengiriman_id' => $pengiriman->id,
            'status_from' => StatusPengiriman::first()->id,
            'status_to' => StatusPengiriman::skip(1)->first()->id,
            'catatan' => 'Mixed format',
            'created_by' => $user->id,
            'dokumentasi' => [
                'dokumentasi/test1.jpg', // String format
                [                        // Object format
                    'path' => 'status_updates/test2.jpg',
                    'url' => asset('storage/status_updates/test2.jpg')
                ]
            ]
        ]);

        $photos = $statusHistory->getDocumentationPhotos();

        $this->assertIsArray($photos);
        $this->assertCount(2, $photos);
        $this->assertStringContainsString('storage', $photos[0]);
        $this->assertStringContainsString('storage', $photos[1]);
    }

    /**
     * Test handles malformed data gracefully
     */
    public function test_handles_malformed_data_gracefully()
    {
        $pengiriman = Pengiriman::factory()->create();
        $user = User::factory()->create();

        // Insert malformed JSON directly
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

        $statusHistory = StatusHistory::find(\DB::getPdo()->lastInsertId());
        $photos = $statusHistory->getDocumentationPhotos();

        // Should return empty array instead of throwing error
        $this->assertIsArray($photos);
        $this->assertEmpty($photos);
    }

    /**
     * Test URLs are properly formatted with asset helper
     */
    public function test_urls_properly_formatted()
    {
        $pengiriman = Pengiriman::factory()->create();
        $user = User::factory()->create();

        $statusHistory = StatusHistory::create([
            'pengiriman_id' => $pengiriman->id,
            'status_from' => StatusPengiriman::first()->id,
            'status_to' => StatusPengiriman::skip(1)->first()->id,
            'created_by' => $user->id,
            'dokumentasi' => ['dokumentasi/test.jpg']
        ]);

        $photos = $statusHistory->getDocumentationPhotos();

        $this->assertStringStartsWith('http', $photos[0]);
        $this->assertStringContainsString('/storage/', $photos[0]);
    }

    /**
     * Test accessor doesn't break relationships
     */
    public function test_accessor_doesnt_break_relationships()
    {
        $pengiriman = Pengiriman::factory()->create();
        $user = User::factory()->create();
        $statusFrom = StatusPengiriman::first();
        $statusTo = StatusPengiriman::skip(1)->first();

        $statusHistory = StatusHistory::create([
            'pengiriman_id' => $pengiriman->id,
            'status_from' => $statusFrom->id,
            'status_to' => $statusTo->id,
            'created_by' => $user->id,
            'dokumentasi' => ['dokumentasi/test.jpg']
        ]);

        // Access dokumentasi_urls
        $photos = $statusHistory->dokumentasi_urls;

        // Check relationships still work
        $this->assertNotNull($statusHistory->pengiriman);
        $this->assertEquals($pengiriman->id, $statusHistory->pengiriman->id);

        $this->assertNotNull($statusHistory->statusFrom);
        $this->assertEquals($statusFrom->id, $statusHistory->statusFrom->id);

        $this->assertNotNull($statusHistory->statusTo);
        $this->assertEquals($statusTo->id, $statusHistory->statusTo->id);

        $this->assertNotNull($statusHistory->creator);
        $this->assertEquals($user->id, $statusHistory->creator->id);
    }
}
