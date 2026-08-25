<?php

namespace Tests\Feature;

use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoxBulkUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Skip seeding for this test
    }

    public function test_box_bulk_update_system_integration()
    {
        // Create test user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create test data  
        $status = StatusPengiriman::create([
            'nama' => 'Test Status',
            'slug' => 'test-status',
            'deskripsi' => 'Test Status Description',
            'urutan' => 1
        ]);
        
        // Create required dependencies
        $task = \App\Models\DailyPackingTask::create([
            'user_id' => $user->id,
            'target_mushaf' => 80,
            'completed_mushaf' => 0,
            'carry_over' => 0,
            'tanggal_tugas' => now()->format('Y-m-d'),
            'status' => 'in_progress'
        ]);
        
        $box = PackingBox::create([
            'kode_kerdus' => 'KB-TEST-001',
            'jenis_quran_id' => 1,
            'kapasitas' => 20,
            'terisi' => 3,
            'status' => 'filling',
            'daily_packing_task_id' => $task->id
        ]);
        
        // Create test donatur
        $donatur = \App\Models\Donatur::create([
            'kode_donatur' => 'TEST-001',
            'nama_donatur' => 'Test Donatur',
            'nomor_hp' => '081234567890',
            'email' => 'test@example.com',
            'created_by' => $user->id
        ]);
        
        // Create test pengiriman and packing items
        for ($i = 1; $i <= 3; $i++) {
            $pengiriman = Pengiriman::create([
                'no_resi' => 'TEST-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'status_id' => $status->id,
                'donatur_id' => $donatur->id,
                'alamat_tujuan' => 'Test Address ' . $i,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            PackingItem::create([
                'packing_box_id' => $box->id,
                'pengiriman_id' => $pengiriman->id,
                'urutan_dalam_box' => $i,
                'packed_by' => $user->id,
                'packed_at' => now()
            ]);
        }

        // Ensure box has items
        $itemCount = $box->packingItems()->count();
        if ($itemCount === 0) {
            $this->fail('Test box has no items');
        }

        echo "Test box has {$itemCount} items\n";

        // Test 1: QR Preview
        $qrData = json_encode([
            'type' => 'box',
            'kode_kerdus' => $box->kode_kerdus,
            'seal_code' => $box->seal_code,
            'jenis_id' => $box->jenis_quran_id,
            'item_count' => $itemCount,
            'status' => $box->status,
            'task_id' => $box->daily_packing_task_id,
            'generated_at' => time()
        ]);

        $response = $this->postJson('/admin/box-bulk/preview', [
            'qr_data' => $qrData
        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $previewData = $response->json('data');
        $this->assertEquals($box->kode_kerdus, $previewData['box']['kode_kerdus']);
        $this->assertCount($itemCount, $previewData['items']);

        echo "Preview test: SUCCESS\n";

        // Create additional status for update test
        $newStatus = StatusPengiriman::create([
            'nama' => 'Proses Pengiriman',
            'slug' => 'pengiriman',
            'deskripsi' => 'Test status for update',
            'urutan' => 2
        ]);
        $response = $this->postJson('/admin/box-bulk/update-status', [
            'qr_data' => $qrData,
            'new_status_id' => $newStatus->id,
            'notes' => 'Integration test bulk update'
        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $statusData = $response->json('data');
        $this->assertEquals($itemCount, $statusData['updated_count']);
        $this->assertEquals($newStatus->nama, $statusData['new_status']);

        echo "Status update test: SUCCESS\n";
        echo "Updated {$statusData['updated_count']} items to status: {$statusData['new_status']}\n";

        // Test 3: Address Update
        $newAddress = 'Test Bulk Address Update via QR';
        $response = $this->postJson('/admin/box-bulk/update-address', [
            'qr_data' => $qrData,
            'new_address' => $newAddress,
            'notes' => 'Integration test address update'
        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $addressData = $response->json('data');
        $this->assertEquals($itemCount, $addressData['updated_count']);
        $this->assertEquals($newAddress, $addressData['new_address']);

        echo "Address update test: SUCCESS\n";
        echo "Updated {$addressData['updated_count']} items with new address\n";

        // Test 4: Combined Update  
        $finalStatus = StatusPengiriman::create([
            'nama' => 'Diterima Penerima',
            'slug' => 'diterima',
            'deskripsi' => 'Final status for combined test',
            'urutan' => 3
        ]);
        
        $response = $this->postJson('/admin/box-bulk/update-both', [
            'qr_data' => $qrData,
            'new_status_id' => $finalStatus->id,
            'new_address' => 'Combined Update Address',
            'notes' => 'Integration test combined update'
        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $combinedData = $response->json('data');
        $this->assertEquals($itemCount, $combinedData['updated_count']);

        echo "Combined update test: SUCCESS\n";
        echo "Updated {$combinedData['updated_count']} items with combined changes\n";

        echo "\n=== INTEGRATION TEST SUMMARY ===\n";
        echo "✅ QR Preview: Working\n";
        echo "✅ Status Update: Working\n";  
        echo "✅ Address Update: Working\n";
        echo "✅ Combined Update: Working\n";
        echo "✅ All tests passed successfully!\n";

        $this->assertTrue(true); // If we get here, all tests passed
    }
}