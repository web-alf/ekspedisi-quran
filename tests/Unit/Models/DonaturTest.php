<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Donatur;
use App\Models\Pengiriman;
use App\Models\WakafBatch;
use App\Models\WakafItem;

class DonaturTest extends TestCase
{
    /** @test */
    public function it_can_create_donatur()
    {
        $donatur = Donatur::factory()->create();

        $this->assertDatabaseHas('donatur', [
            'id' => $donatur->id,
            'nama_donatur' => $donatur->nama_donatur,
        ]);
    }

    /** @test */
    public function it_casts_jenis_wakaf_dipilih_to_array()
    {
        $donatur = Donatur::factory()->create([
            'jenis_wakaf_dipilih' => ['A5', 'A6', 'IQRA']
        ]);

        $this->assertIsArray($donatur->jenis_wakaf_dipilih);
        $this->assertContains('A5', $donatur->jenis_wakaf_dipilih);
        $this->assertContains('A6', $donatur->jenis_wakaf_dipilih);
        $this->assertContains('IQRA', $donatur->jenis_wakaf_dipilih);
    }

    /** @test */
    public function it_casts_dates_properly()
    {
        $donatur = Donatur::factory()->create([
            'donation_date' => '2024-01-15'
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $donatur->donation_date);
        $this->assertEquals('2024-01-15', $donatur->donation_date->format('Y-m-d'));
    }

    /** @test */
    public function it_casts_boolean_fields_properly()
    {
        $donatur = Donatur::factory()->create([
            'prayer_mode' => 'customize_individual',
        ]);

        $this->assertEquals('customize_individual', $donatur->prayer_mode);
        $this->assertNotEquals('semua_donatur', $donatur->prayer_mode);
    }

    /** @test */
    public function it_has_many_pengiriman()
    {
        $donatur = Donatur::factory()->create();
        $pengiriman1 = Pengiriman::factory()->create(['donatur_id' => $donatur->id]);
        $pengiriman2 = Pengiriman::factory()->create(['donatur_id' => $donatur->id]);

        $this->assertCount(2, $donatur->pengiriman);
        $this->assertTrue($donatur->pengiriman->contains($pengiriman1));
        $this->assertTrue($donatur->pengiriman->contains($pengiriman2));
    }

    /** @test */
    public function it_has_many_wakaf_batches()
    {
        $donatur = Donatur::factory()->create();
        $batch1 = WakafBatch::factory()->create(['donatur_id' => $donatur->id]);
        $batch2 = WakafBatch::factory()->create(['donatur_id' => $donatur->id]);

        $this->assertCount(2, $donatur->wakafBatches);
        $this->assertTrue($donatur->wakafBatches->contains($batch1));
        $this->assertTrue($donatur->wakafBatches->contains($batch2));
    }

    /** @test */
    public function it_calculates_total_quran_correctly()
    {
        $donatur = Donatur::factory()->create([
            'total_a5_count' => 5,
            'total_a6_count' => 3,
            'total_iqra_count' => 2,
        ]);

        $this->assertEquals(10, $donatur->total_quran);
    }

    /** @test */
    public function it_calculates_total_wakaf_from_batches()
    {
        $donatur = Donatur::factory()->create();
        WakafBatch::factory()->count(3)->create(['donatur_id' => $donatur->id]);

        $this->assertEquals(3, $donatur->total_wakaf);
    }

    /** @test */
    public function it_calculates_total_pengiriman_count()
    {
        $donatur = Donatur::factory()->create();
        Pengiriman::factory()->count(5)->create(['donatur_id' => $donatur->id]);

        $this->assertEquals(5, $donatur->total_pengiriman);
    }

    /** @test */
    public function it_can_search_by_donatur_name()
    {
        $donatur1 = Donatur::factory()->create(['nama_donatur' => 'John Doe']);
        $donatur2 = Donatur::factory()->create(['nama_donatur' => 'Jane Smith']);
        
        $results = Donatur::byDonatur('John')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($donatur1->id, $results->first()->id);
    }

    /** @test */
    public function it_can_search_by_phone_number()
    {
        $donatur1 = Donatur::factory()->create(['no_hp' => '08123456789']);
        $donatur2 = Donatur::factory()->create(['no_hp' => '08198765432']);
        
        $results = Donatur::byDonatur('081234')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($donatur1->id, $results->first()->id);
    }

    /** @test */
    public function it_can_search_by_email()
    {
        $donatur1 = Donatur::factory()->create(['email_donatur' => 'john@example.com']);
        $donatur2 = Donatur::factory()->create(['email_donatur' => 'jane@example.com']);
        
        $results = Donatur::byDonatur('john@example')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($donatur1->id, $results->first()->id);
    }

    /** @test */
    public function it_can_search_by_kode_donatur()
    {
        $donatur1 = Donatur::factory()->create(['kode_donatur' => 'DN-0001']);
        $donatur2 = Donatur::factory()->create(['kode_donatur' => 'DN-0002']);
        
        $results = Donatur::byDonatur('DN-0001')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($donatur1->id, $results->first()->id);
    }

    /** @test */
    public function it_can_filter_by_date_range()
    {
        $donatur1 = Donatur::factory()->create(['donation_date' => '2024-01-15']);
        $donatur2 = Donatur::factory()->create(['donation_date' => '2024-02-15']);
        $donatur3 = Donatur::factory()->create(['donation_date' => '2024-03-15']);

        $results = Donatur::byDateRange('2024-01-01', '2024-01-31')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($donatur1->id, $results->first()->id);
    }

    /** @test */
    public function it_can_filter_by_start_date_only()
    {
        $donatur1 = Donatur::factory()->create(['donation_date' => '2024-01-15']);
        $donatur2 = Donatur::factory()->create(['donation_date' => '2024-02-15']);

        $results = Donatur::byDateRange('2024-02-01')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($donatur2->id, $results->first()->id);
    }

    /** @test */
    public function it_can_filter_by_end_date_only()
    {
        $donatur1 = Donatur::factory()->create(['donation_date' => '2024-01-15']);
        $donatur2 = Donatur::factory()->create(['donation_date' => '2024-02-15']);

        $results = Donatur::byDateRange(null, '2024-01-31')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($donatur1->id, $results->first()->id);
    }

    /** @test */
    public function it_can_generate_wakaf_items_for_a5()
    {
        $donatur = Donatur::factory()->create([
            'total_a5_count' => 3,
            'total_a6_count' => 0,
            'total_iqra_count' => 0,
            'prayer_mode' => 'semua_donatur',
            'nama_donatur' => 'John Doe',
            'doa_untuk_semua' => 'Test prayer',
        ]);

        $items = $donatur->generateWakafItems();

        $this->assertCount(3, $items);
        
        foreach ($items as $index => $item) {
            $this->assertEquals($donatur->id, $item['donatur_id']);
            $this->assertEquals('A5', $item['wakaf_type']);
            $this->assertEquals($index + 1, $item['sequence_in_type']);
            $this->assertEquals($index + 1, $item['global_sequence']);
            $this->assertEquals('John Doe', $item['wakif_name']);
            $this->assertEquals('Test prayer', $item['doa_request']);
            $this->assertEquals('Diri sendiri', $item['relationship_to_donatur']);
            $this->assertEquals('pending', $item['status']);
        }
    }

    /** @test */
    public function it_can_generate_mixed_wakaf_items()
    {
        $donatur = Donatur::factory()->create([
            'total_a5_count' => 2,
            'total_a6_count' => 1,
            'total_iqra_count' => 1,
            'prayer_mode' => 'mixed',
        ]);

        $items = $donatur->generateWakafItems();

        $this->assertCount(4, $items);
        
        // Check A5 items
        $a5Items = array_filter($items, fn($item) => $item['wakaf_type'] === 'A5');
        $this->assertCount(2, $a5Items);
        
        // Check A6 items
        $a6Items = array_filter($items, fn($item) => $item['wakaf_type'] === 'A6');
        $this->assertCount(1, $a6Items);
        
        // Check IQRA items
        $iqraItems = array_filter($items, fn($item) => $item['wakaf_type'] === 'IQRA');
        $this->assertCount(1, $iqraItems);
        
        // Check global sequence
        $globalSequences = array_column($items, 'global_sequence');
        $this->assertEquals([1, 2, 3, 4], $globalSequences);
    }

    /** @test */
    public function it_generates_empty_wakif_name_when_not_semua_atas_nama_donatur()
    {
        $donatur = Donatur::factory()->create([
            'total_a5_count' => 1,
            'total_a6_count' => 0,
            'total_iqra_count' => 0,
            'prayer_mode' => 'customize_individual',
            'nama_donatur' => 'John Doe',
        ]);

        $items = $donatur->generateWakafItems();

        $this->assertCount(1, $items);
        $this->assertEquals('', $items[0]['wakif_name']);
        $this->assertEquals('', $items[0]['doa_request']);
    }
}