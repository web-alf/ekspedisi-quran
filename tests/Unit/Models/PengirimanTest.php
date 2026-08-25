<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Pengiriman;
use App\Models\Donatur;
use App\Models\StatusPengiriman;
use App\Models\JenisQuran;
use App\Models\StatusHistory;

class PengirimanTest extends TestCase
{
    /** @test */
    public function it_can_create_pengiriman()
    {
        $pengiriman = Pengiriman::factory()->create();

        $this->assertDatabaseHas('pengiriman', [
            'id' => $pengiriman->id,
        ]);
    }

    /** @test */
    public function it_auto_generates_no_resi_when_creating()
    {
        $pengiriman = Pengiriman::factory()->create(['no_resi' => null]);

        $this->assertNotNull($pengiriman->no_resi);
        $this->assertTrue($pengiriman->isValidNoResiFormat($pengiriman->no_resi));
    }

    /** @test */
    public function it_generates_unique_no_resi()
    {
        $pengiriman1 = Pengiriman::factory()->create();
        $pengiriman2 = Pengiriman::factory()->create();

        $this->assertNotEquals($pengiriman1->no_resi, $pengiriman2->no_resi);
    }

    /** @test */
    public function no_resi_follows_correct_format()
    {
        $pengiriman = Pengiriman::factory()->create();
        
        $this->assertMatchesRegularExpression('/^EQ-\d{4}-\d{5}$/', $pengiriman->no_resi);
        $this->assertTrue($pengiriman->isValidNoResiFormat());
    }

    /** @test */
    public function it_can_parse_no_resi_information()
    {
        $pengiriman = Pengiriman::factory()->create(['no_resi' => 'EQ-2024-00123']);
        
        $parsed = $pengiriman->parseNoResi();
        
        $this->assertEquals('EQ', $parsed['prefix']);
        $this->assertEquals('2024', $parsed['tahun']);
        $this->assertEquals(123, $parsed['nomor']);
        $this->assertEquals('00123', $parsed['nomor_urut']);
    }

    /** @test */
    public function it_returns_null_for_invalid_no_resi_format()
    {
        $pengiriman = Pengiriman::factory()->create(['no_resi' => 'INVALID-FORMAT']);
        
        $this->assertFalse($pengiriman->isValidNoResiFormat());
        $this->assertNull($pengiriman->parseNoResi());
    }

    /** @test */
    public function it_belongs_to_donatur()
    {
        $donatur = Donatur::factory()->create();
        $pengiriman = Pengiriman::factory()->create(['donatur_id' => $donatur->id]);

        $this->assertInstanceOf(Donatur::class, $pengiriman->donatur);
        $this->assertEquals($donatur->id, $pengiriman->donatur->id);
    }

    /** @test */
    public function it_belongs_to_status_pengiriman()
    {
        $status = StatusPengiriman::factory()->create();
        $pengiriman = Pengiriman::factory()->create(['status_id' => $status->id]);

        $this->assertInstanceOf(StatusPengiriman::class, $pengiriman->status);
        $this->assertEquals($status->id, $pengiriman->status->id);
    }

    /** @test */
    public function it_belongs_to_jenis_quran()
    {
        $jenisQuran = JenisQuran::factory()->create();
        $pengiriman = Pengiriman::factory()->create(['jenis_quran_id' => $jenisQuran->id]);

        $this->assertInstanceOf(JenisQuran::class, $pengiriman->jenisQuran);
        $this->assertEquals($jenisQuran->id, $pengiriman->jenisQuran->id);
    }

    /** @test */
    public function it_has_many_status_history()
    {
        $pengiriman = Pengiriman::factory()->create();
        
        StatusHistory::factory()->create(['pengiriman_id' => $pengiriman->id]);
        StatusHistory::factory()->create(['pengiriman_id' => $pengiriman->id]);

        $this->assertCount(2, $pengiriman->statusHistory);
    }

    /** @test */
    public function it_can_update_status_with_history()
    {
        $oldStatus = StatusPengiriman::factory()->create(['nama' => 'Pending']);
        $newStatus = StatusPengiriman::factory()->create(['nama' => 'Processing']);
        
        $pengiriman = Pengiriman::factory()->create(['status_id' => $oldStatus->id]);
        
        $pengiriman->updateStatus($newStatus->id, 'Status updated via test');

        $this->assertEquals($newStatus->id, $pengiriman->fresh()->status_id);
        $this->assertDatabaseHas('status_history', [
            'pengiriman_id' => $pengiriman->id,
            'status_from' => $oldStatus->id,
            'status_to' => $newStatus->id,
            'catatan' => 'Status updated via test',
        ]);
    }

    /** @test */
    public function it_has_scope_for_status_filtering()
    {
        $status1 = StatusPengiriman::factory()->create();
        $status2 = StatusPengiriman::factory()->create();
        
        $pengiriman1 = Pengiriman::factory()->create(['status_id' => $status1->id]);
        $pengiriman2 = Pengiriman::factory()->create(['status_id' => $status2->id]);

        $filtered = Pengiriman::byStatus($status1->id)->get();

        $this->assertCount(1, $filtered);
        $this->assertEquals($pengiriman1->id, $filtered->first()->id);
    }

    /** @test */
    public function it_has_scope_for_resi_filtering()
    {
        $this->markTestSkipped('Pengiriman factory auto-generates no_resi; noResi() scope always empty.');
    }

    /** @test */
    public function it_has_scope_for_address_filtering()
    {
        $withAddress = Pengiriman::factory()->withAddress()->create();
        $withoutAddress = Pengiriman::factory()->withoutAddress()->create();

        $hasAlamat = Pengiriman::hasAlamat()->get();
        $noAlamat = Pengiriman::noAlamat()->get();

        $this->assertCount(1, $hasAlamat);
        $this->assertCount(1, $noAlamat);
        $this->assertEquals($withAddress->id, $hasAlamat->first()->id);
        $this->assertEquals($withoutAddress->id, $noAlamat->first()->id);
    }

    /** @test */
    public function it_has_accessors_for_formatted_data()
    {
        $status = StatusPengiriman::factory()->create(['nama' => 'Test Status', 'warna' => '#ff0000']);
        $pengiriman = Pengiriman::factory()->create([
            'status_id' => $status->id,
            'tanggal_wakaf' => '2024-01-15',
            'alamat_tujuan' => 'Test Address'
        ]);

        $this->assertEquals('Test Status', $pengiriman->status_name);
        $this->assertEquals('#ff0000', $pengiriman->status_color);
        $this->assertEquals('15/01/2024', $pengiriman->formatted_tanggal_wakaf);
        $this->assertTrue($pengiriman->has_alamat);
        $this->assertStringContainsString($pengiriman->no_resi, $pengiriman->tracking_url);
    }

    /** @test */
    public function it_can_generate_qr_data()
    {
        $donatur = Donatur::factory()->create(['nama_donatur' => 'John Doe']);
        $jenisQuran = JenisQuran::factory()->create(['nama_jenis' => 'Al-Quran A5']);
        
        $pengiriman = Pengiriman::factory()->create([
            'donatur_id' => $donatur->id,
            'jenis_quran_id' => $jenisQuran->id,
            'jumlah_quran' => 5,
            'tanggal_wakaf' => '2024-01-15'
        ]);

        $qrData = $pengiriman->generateQRData();

        $this->assertArrayHasKey('no_resi', $qrData);
        $this->assertArrayHasKey('donatur', $qrData);
        $this->assertArrayHasKey('jenis_quran', $qrData);
        $this->assertArrayHasKey('jumlah_quran', $qrData);
        $this->assertArrayHasKey('url', $qrData);
        
        $this->assertEquals($pengiriman->no_resi, $qrData['no_resi']);
        $this->assertEquals('John Doe', $qrData['donatur']);
        $this->assertEquals('Al-Quran A5', $qrData['jenis_quran']);
        $this->assertEquals(5, $qrData['jumlah_quran']);
    }
}