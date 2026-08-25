<?php

namespace Tests\Feature\Admin;

use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Models\WakafItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonaturDataConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required test data directly (avoid duplicates)
        JenisQuran::firstOrCreate(['kode_jenis' => 'A5'], ['nama_jenis' => 'Al-Quran Ukuran A5', 'is_active' => true, 'harga' => 50000, 'deskripsi' => 'Test A5']);
        JenisQuran::firstOrCreate(['kode_jenis' => 'A6'], ['nama_jenis' => 'Al-Quran Ukuran A6', 'is_active' => true, 'harga' => 40000, 'deskripsi' => 'Test A6']);
        JenisQuran::firstOrCreate(['kode_jenis' => 'IQRA'], ['nama_jenis' => 'Buku Iqro', 'is_active' => true, 'harga' => 30000, 'deskripsi' => 'Test IQRA']);

        StatusPengiriman::firstOrCreate(['kode' => 'PROSES'], ['nama' => 'Proses Pemesanan', 'slug' => 'proses-pemesanan', 'is_default' => true, 'warna' => 'blue', 'icon' => 'fas fa-clock']);

        // Create roles and permissions (avoid duplicates)
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'donatur.read']);
        $role->givePermissionTo($permission);

        // Create test user with super-admin role
        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('super-admin');
        $this->actingAs($this->user);
    }

    /** @test */
    public function donatur_counts_are_consistent_across_all_controller_methods()
    {
        // Create a donatur with known stored values
        $donatur = Donatur::factory()->create([
            'total_a5_count' => 5,  // Stored value
            'total_a6_count' => 3,  // Stored value
            'total_iqra_count' => 2, // Stored value
        ]);

        // Create wakaf items that represent the actual counts (different from stored)
        WakafItem::factory()->count(2)->create(['donatur_id' => $donatur->id, 'wakaf_type' => 'A5']);
        WakafItem::factory()->count(4)->create(['donatur_id' => $donatur->id, 'wakaf_type' => 'A6']);
        WakafItem::factory()->count(1)->create(['donatur_id' => $donatur->id, 'wakaf_type' => 'IQRA']);

        // Test index() method - should use actual counts
        $indexResponse = $this->get(route('admin.donatur.index'));
        $indexResponse->assertSuccessful();
        $indexResponse->assertInertia(fn ($page) => $page
            ->component('Admin/Donatur/Index')
            ->has('donatur.data')
            ->where('donatur.data', fn ($data) => collect($data)->contains(fn ($item) => $item['id'] === $donatur->id &&
                    $item['total_a5_count'] === 2 &&
                    $item['total_a6_count'] === 4 &&
                    $item['total_iqra_count'] === 1
            )
            )
        );

        // Test show() method - should use same actual counts
        $showResponse = $this->get(route('admin.donatur.show', $donatur));
        $showResponse->assertSuccessful();
        $showResponse->assertInertia(fn ($page) => $page
            ->component('Admin/Donatur/Show')
            ->has('donatur')
            ->where('donatur.total_a5_count', 2)
            ->where('donatur.total_a6_count', 4)
            ->where('donatur.total_iqra_count', 1)
        );

        // Test edit() method - should use same actual counts
        $editResponse = $this->get(route('admin.donatur.edit', $donatur));
        $editResponse->assertSuccessful();
        $editResponse->assertInertia(fn ($page) => $page
            ->component('Admin/Donatur/Edit')
            ->has('donatur')
            ->where('donatur.total_a5_count', 2)
            ->where('donatur.total_a6_count', 4)
            ->where('donatur.total_iqra_count', 1)
        );
    }

    /** @test */
    public function donatur_counts_update_consistently_when_wakaf_items_change()
    {
        $donatur = Donatur::factory()->create([
            'total_a5_count' => 1,
            'total_a6_count' => 1,
            'total_iqra_count' => 1,
        ]);

        WakafItem::factory()->create(['donatur_id' => $donatur->id, 'wakaf_type' => 'A5']);

        // Initially: stored = 1, actual = 1
        $initialResponse = $this->get(route('admin.donatur.show', $donatur));
        $initialResponse->assertInertia(fn ($page) => $page
            ->component('Admin/Donatur/Show')
            ->where('donatur.total_a5_count', 1)
        );

        // Add more wakaf items
        WakafItem::factory()->count(2)->create(['donatur_id' => $donatur->id, 'wakaf_type' => 'A5']);

        // Now: stored = 1, actual = 3 (should show 3 consistently)
        $updatedResponse = $this->get(route('admin.donatur.show', $donatur));
        $updatedResponse->assertInertia(fn ($page) => $page
            ->component('Admin/Donatur/Show')
            ->where('donatur.total_a5_count', 3)
        );

        // Verify edit page shows same count
        $editResponse = $this->get(route('admin.donatur.edit', $donatur));
        $editResponse->assertInertia(fn ($page) => $page
            ->component('Admin/Donatur/Edit')
            ->where('donatur.total_a5_count', 3)
        );
    }

    /** @test */
    public function consistency_validation_helper_detects_mismatches()
    {
        // Create donatur with mismatched data
        $donatur = Donatur::factory()->create([
            'total_a5_count' => 10,  // Stored
            'total_a6_count' => 5,   // Stored
            'total_iqra_count' => 3, // Stored
        ]);

        // Create different actual counts
        WakafItem::factory()->count(2)->create(['donatur_id' => $donatur->id, 'wakaf_type' => 'A5']); // Actual: 2
        WakafItem::factory()->count(5)->create(['donatur_id' => $donatur->id, 'wakaf_type' => 'A6']); // Actual: 5
        WakafItem::factory()->count(1)->create(['donatur_id' => $donatur->id, 'wakaf_type' => 'IQRA']); // Actual: 1

        // Use reflection to test the private helper method
        $controller = new \App\Http\Controllers\Admin\DonaturController(
            app(\App\Services\OnDemandCertificateService::class)
        );

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateCountConsistency');
        $method->setAccessible(true);

        $consistency = $method->invoke($controller, $donatur);

        // Verify inconsistency detection
        $this->assertEquals(10, $consistency['a5_stored']);
        $this->assertEquals(2, $consistency['a5_actual']);
        $this->assertFalse($consistency['a5_consistent']);

        $this->assertEquals(5, $consistency['a6_stored']);
        $this->assertEquals(5, $consistency['a6_actual']);
        $this->assertTrue($consistency['a6_consistent']);

        $this->assertEquals(3, $consistency['iqra_stored']);
        $this->assertEquals(1, $consistency['iqra_actual']);
        $this->assertFalse($consistency['iqra_consistent']);
    }
}
