<?php

namespace Tests\Feature\Public;

use Tests\TestCase;
use App\Models\Pengiriman;
use App\Models\Donatur;
use App\Models\StatusPengiriman;
use App\Models\StatusHistory;
use App\Models\JenisQuran;
use App\Models\Sertifikat;
use App\Models\WakafBatch;

class TrackingSystemTest extends TestCase
{
    protected $pengiriman;
    protected $donatur;
    protected $jenisQuran;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->donatur = Donatur::factory()->create([
            'nama_donatur' => 'Test Donatur for Tracking'
        ]);
        
        $this->jenisQuran = JenisQuran::where('kode_jenis', 'A5')->first() ?? 
                            JenisQuran::factory()->a5()->create();
        
        $this->pengiriman = Pengiriman::factory()->create([
            'no_resi' => 'EQ-2024-12345',
            'donatur_id' => $this->donatur->id,
            'jenis_quran_id' => $this->jenisQuran->id,
            'nama_penerima' => 'Test Penerima',
            'alamat_tujuan' => 'Jl. Test Tracking No. 123',
            'no_hp_penerima' => '08123456789',
            'jumlah_quran' => 5,
        ]);
    }

    /** @test */
    public function it_can_display_tracking_index_page()
    {
        $response = $this->get('/tracking');

        $response->assertStatus(200);
        $response->assertSee('Tracking'); // Should contain tracking form
    }

    /** @test */
    public function it_can_track_valid_shipment()
    {
        $response = $this->get("/tracking/{$this->pengiriman->no_resi}");

        $response->assertStatus(200);
        $response->assertSee($this->pengiriman->no_resi);
        $response->assertSee($this->donatur->nama_donatur);
        $response->assertSee($this->pengiriman->nama_penerima);
        $response->assertSee($this->pengiriman->alamat_tujuan);
    }

    /** @test */
    public function it_returns_404_for_invalid_tracking_number()
    {
        $response = $this->get('/tracking/INVALID-RESI-123');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_can_search_tracking_via_post()
    {
        $response = $this->post('/tracking/search', [
            'no_resi' => $this->pengiriman->no_resi
        ]);

        $response->assertRedirect("/tracking/{$this->pengiriman->no_resi}");
    }

    /** @test */
    public function it_validates_tracking_search_input()
    {
        $response = $this->post('/tracking/search', [
            'no_resi' => '' // Empty input
        ]);

        $response->assertSessionHasErrors(['no_resi']);
    }

    /** @test */
    public function it_redirects_to_tracking_url_for_search()
    {
        $response = $this->post('/tracking/search', [
            'no_resi' => 'NONEXISTENT-RESI'
        ]);

        $response->assertRedirect('/tracking/NONEXISTENT-RESI');
    }

    /** @test */
    public function it_displays_status_history_when_available()
    {
        $status1 = StatusPengiriman::factory()->create(['nama' => 'Pending']);
        $status2 = StatusPengiriman::factory()->create(['nama' => 'Processing']);
        
        // Create status history
        StatusHistory::factory()->create([
            'pengiriman_id' => $this->pengiriman->id,
            'status_from' => null,
            'status_to' => $status1->id,
            'catatan' => 'Pengiriman dibuat',
            'created_at' => now()->subDays(2),
        ]);
        
        StatusHistory::factory()->create([
            'pengiriman_id' => $this->pengiriman->id,
            'status_from' => $status1->id,
            'status_to' => $status2->id,
            'catatan' => 'Status diupdate ke processing',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->get("/tracking/{$this->pengiriman->no_resi}");

        $response->assertStatus(200);
        $response->assertSee('Pengiriman dibuat');
        $response->assertSee('Status diupdate ke processing');
    }

    /** @test */
    public function it_shows_certificate_download_link_when_available()
    {
        $wakafBatch = WakafBatch::factory()->create([
            'donatur_id' => $this->donatur->id
        ]);
        
        $this->pengiriman->update(['wakaf_batch_id' => $wakafBatch->id]);
        
        $sertifikat = Sertifikat::factory()->create([
            'wakaf_batch_id' => $wakafBatch->id,
            'nomor_sertifikat' => 'CERT-EQ-2024-00001'
        ]);

        $response = $this->get("/tracking/{$this->pengiriman->no_resi}");

        $response->assertStatus(200);
        $response->assertSee('Sertifikat');
        $response->assertSee('Download');
    }

    /** @test */
    public function it_does_not_show_certificate_link_when_not_available()
    {
        $response = $this->get("/tracking/{$this->pengiriman->no_resi}");

        $response->assertStatus(200);
        $response->assertDontSee('Download Sertifikat');
    }

    /** @test */
    public function it_displays_shipment_details_correctly()
    {
        $response = $this->get("/tracking/{$this->pengiriman->no_resi}");

        $response->assertStatus(200);
        
        // Should display shipment information
        $response->assertSee($this->pengiriman->no_resi);
        $response->assertSee($this->donatur->nama_donatur);
        $response->assertSee($this->jenisQuran->nama_jenis);
        $response->assertSee($this->pengiriman->jumlah_quran);
        $response->assertSee($this->pengiriman->nama_penerima);
        $response->assertSee($this->pengiriman->no_hp_penerima);
        $response->assertSee($this->pengiriman->alamat_tujuan);
    }

    /** @test */
    public function it_respects_rate_limiting()
    {
        // Make multiple requests quickly
        for ($i = 0; $i < 5; $i++) {
            $response = $this->get("/tracking/{$this->pengiriman->no_resi}");
            $response->assertStatus(200);
        }
        
        // This should still work as we're within limit (60 per minute)
        $response = $this->get("/tracking/{$this->pengiriman->no_resi}");
        $response->assertStatus(200);
    }

    /** @test */
    public function api_endpoint_returns_json_data()
    {
        $response = $this->get("/api/tracking/{$this->pengiriman->no_resi}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'no_resi' => $this->pengiriman->no_resi,
                'donatur' => $this->donatur->nama_donatur,
                'penerima' => $this->pengiriman->nama_penerima,
                'alamat' => $this->pengiriman->alamat_tujuan,
            ]
        ]);
    }

    /** @test */
    public function api_endpoint_returns_404_for_invalid_resi()
    {
        $response = $this->get('/api/tracking/INVALID-RESI-123');

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Nomor resi tidak ditemukan'
        ]);
    }

    /** @test */
    public function qr_code_endpoint_generates_qr_code()
    {
        $response = $this->get("/api/tracking/{$this->pengiriman->no_resi}/qr");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'no_resi' => $this->pengiriman->no_resi
        ]);
    }

    /** @test */
    public function legacy_track_route_redirects_correctly()
    {
        $response = $this->get("/track/{$this->pengiriman->no_resi}");

        $response->assertRedirect("/tracking/{$this->pengiriman->no_resi}");
    }

    /** @test */
    public function it_handles_case_insensitive_tracking_numbers()
    {
        $lowerCaseResi = strtolower($this->pengiriman->no_resi);
        
        $response = $this->get("/tracking/{$lowerCaseResi}");

        // Should either work or redirect to uppercase version
        $this->assertTrue(
            $response->isSuccessful() || 
            $response->isRedirection()
        );
    }

    /** @test */
    public function it_displays_delivery_photos_when_available()
    {
        $this->pengiriman->update([
            'delivery_proof' => [
                'photos/delivery1.jpg',
                'photos/delivery2.jpg'
            ]
        ]);

        $response = $this->get("/tracking/{$this->pengiriman->no_resi}");

        $response->assertStatus(200);
        $response->assertSee('Foto Dokumentasi');
    }

    /** @test */
    public function it_shows_received_information_when_delivered()
    {
        $deliveredStatus = StatusPengiriman::factory()->create([
            'nama' => 'Delivered',
            'is_final' => true
        ]);
        
        $this->pengiriman->update([
            'status_id' => $deliveredStatus->id,
            'received_at' => now()->subDays(1),
            'received_by' => 'John Recipient',
            'receiver_contact' => '08987654321',
            'delivery_notes' => 'Delivered successfully'
        ]);

        $response = $this->get("/tracking/{$this->pengiriman->no_resi}");

        $response->assertStatus(200);
        $response->assertSee('John Recipient');
        $response->assertSee('08987654321');
        $response->assertSee('Delivered successfully');
    }

    /** @test */
    public function it_validates_no_resi_format()
    {
        $invalidFormats = [
            'INVALID-FORMAT',
            'EQ-INVALID',
            '123456789',
            'EQ-2024-INVALID',
        ];

        foreach ($invalidFormats as $invalidResi) {
            $response = $this->get("/tracking/{$invalidResi}");
            
            // Should return 404 or handle gracefully
            $this->assertTrue(
                $response->status() === 404 || 
                $response->isRedirection()
            );
        }
    }

    /** @test */
    public function it_displays_formatted_dates_correctly()
    {
        $this->pengiriman->update([
            'tanggal_wakaf' => '2024-01-15',
            'received_at' => '2024-02-01 14:30:00'
        ]);

        $response = $this->get("/tracking/{$this->pengiriman->no_resi}");

        $response->assertStatus(200);
        // Should show formatted dates (15/01/2024, etc.)
        $response->assertSee('15/01/2024');
    }
}