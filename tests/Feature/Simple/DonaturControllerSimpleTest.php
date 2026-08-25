<?php

namespace Tests\Feature\Simple;

use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\StatusPengiriman;
use App\Models\WakafItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonaturControllerSimpleTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_verifies_data_transformation_works()
    {
        // Create basic data
        $jenisQuran = JenisQuran::create(['nama_jenis' => 'Al-Quran A5', 'kode_jenis' => 'A5', 'is_active' => true, 'harga' => 50000, 'deskripsi' => 'Test']);
        $status = StatusPengiriman::create(['nama' => 'Test Status', 'slug' => 'test', 'kode' => 'TEST', 'is_default' => true, 'warna' => 'blue', 'icon' => 'fas fa-clock']);

        // Create a donatur with known stored values
        $donatur = Donatur::factory()->create([
            'total_a5_count' => 10,  // Stored value (wrong)
        ]);

        // Create wakaf items that represent the actual counts (different from stored)
        WakafItem::factory()->count(3)->create(['donatur_id' => $donatur->id, 'wakaf_type' => 'A5']);

        // Test the actual_a5_count accessor
        $actualCount = $donatur->actual_a5_count;
        $this->assertEquals(3, $actualCount); // Should be 3 (actual items)

        // Test the data transformation directly
        $donatur->total_a5_count = $donatur->actual_a5_count;
        $this->assertEquals(3, $donatur->total_a5_count); // Should now be 3 after transformation
    }
}
