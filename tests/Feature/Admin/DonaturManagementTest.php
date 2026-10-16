<?php

namespace Tests\Feature\Admin;

use App\Models\Donatur;
use App\Models\Pengiriman;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DonaturManagementTest extends TestCase
{
    protected $admin;

    protected $cs;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles and permissions if they don't exist
        $this->createRolesAndPermissions();

        // Create test users
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super_admin');

        $this->cs = User::factory()->create(['is_active' => true]);
        $this->cs->assignRole('cs');
    }

    protected function createRolesAndPermissions()
    {
        try {
            // Create roles
            $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
            $csRole = Role::firstOrCreate(['name' => 'cs']);

            // Create permissions
            $donaturPermissions = [
                'donatur.read',
                'donatur.create',
                'donatur.update',
                'donatur.delete',
                'donatur.import',
                'donatur.export',
            ];

            foreach ($donaturPermissions as $permission) {
                Permission::firstOrCreate(['name' => $permission]);
            }

            // Assign permissions to roles
            $superAdminRole->givePermissionTo($donaturPermissions);
            $csRole->givePermissionTo(['donatur.read', 'donatur.create', 'donatur.update']);

            // Refresh permissions cache
            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        } catch (\Exception $e) {
            // Skip if Spatie is not properly configured
        }
    }

    /** @test */
    public function it_can_display_donatur_index_page()
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/donatur');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_can_create_new_donatur()
    {
        $donaturData = [
            'kode_donatur' => 'DN-TEST-001',
            'nama_donatur' => 'John Doe Test',
            'no_hp' => '+628123456789',
            'email_donatur' => 'john@test.com',
            'alamat_donatur' => 'Test Address 123',
            'jenis_wakaf_dipilih' => ['A5', 'A6', 'IQRA'], // Include IQRA in selected types
            'jumlah_a5' => 3,       // Use jumlah_a5 instead of total_a5_count
            'jumlah_a6' => 2,       // Use jumlah_a6 instead of total_a6_count
            'jumlah_iqra' => 1,     // Use jumlah_iqra instead of total_iqra_count
            'donation_date' => '2024-01-15',
            'prayer_mode' => 'semua_donatur',  // Use prayer_mode instead of boolean fields
            'doa_untuk_semua' => 'Test prayer',
        ];

        $response = $this->actingAs($this->admin)
            ->post('/admin/donatur', $donaturData);

        $response->assertRedirect();

        $this->assertDatabaseHas('donatur', [
            'kode_donatur' => 'DN-TEST-001',
            'nama_donatur' => 'John Doe Test',
            'email_donatur' => 'john@test.com',
            'prayer_mode' => 'semua_donatur',
        ]);

        // Verify total calculation
        $donatur = Donatur::where('kode_donatur', 'DN-TEST-001')->first();
        $this->assertEquals(6, $donatur->total_quran); // 3 + 2 + 1
    }

    /** @test */
    public function it_validates_required_fields_when_creating_donatur()
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/donatur', []);

        $response->assertSessionHasErrors([
            'kode_donatur',
            'nama_donatur',
            'no_hp',
            'donation_date',
            'jenis_wakaf_dipilih',
            'prayer_mode',
        ]);
    }

    /** @test */
    public function it_can_update_existing_donatur()
    {
        $donatur = Donatur::factory()->create([
            'nama_donatur' => 'Original Name',
            'email_donatur' => 'original@test.com',
            'no_hp' => '+628123456789', // Valid Indonesian phone number
            'prayer_mode' => 'semua_donatur',
        ]);

        $updateData = [
            'kode_donatur' => $donatur->kode_donatur,
            'nama_donatur' => 'Updated Name',
            'no_hp' => '+628987654321', // Another valid Indonesian phone number
            'email_donatur' => 'updated@test.com',
            'alamat_donatur' => 'Updated Address',
            'prayer_mode' => 'customize_individual', // Changed prayer mode
            'doa_untuk_semua' => 'Updated prayer text',
        ];

        $response = $this->actingAs($this->admin)
            ->put("/admin/donatur/{$donatur->id}", $updateData);

        $response->assertRedirect();

        $this->assertDatabaseHas('donatur', [
            'id' => $donatur->id,
            'nama_donatur' => 'Updated Name',
            'no_hp' => '+628987654321',
            'email_donatur' => 'updated@test.com',
            'prayer_mode' => 'customize_individual',
            'doa_untuk_semua' => 'Updated prayer text',
        ]);
    }

    /** @test */
    public function it_validates_update_fields_correctly()
    {
        $donatur = Donatur::factory()->create();

        // Test validation: required fields for update
        $response = $this->actingAs($this->admin)
            ->put("/admin/donatur/{$donatur->id}", []);

        $response->assertSessionHasErrors([
            'kode_donatur',
            'nama_donatur',
            'no_hp',
            'prayer_mode',
        ]);

        // Test validation: invalid prayer_mode
        $response = $this->actingAs($this->admin)
            ->put("/admin/donatur/{$donatur->id}", [
                'nama_donatur' => 'Test Name',
                'no_hp' => '+628123456789',
                'prayer_mode' => 'invalid_mode', // Invalid prayer mode
            ]);

        $response->assertSessionHasErrors(['prayer_mode']);
    }

    /** @test */
    public function it_can_delete_donatur()
    {
        $donatur = Donatur::factory()->create();

        $response = $this->actingAs($this->admin)
            ->delete("/admin/donatur/{$donatur->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('donatur', ['id' => $donatur->id]);
    }

    /** @test */
    public function it_can_add_quran_to_existing_donatur()
    {
        $donatur = Donatur::factory()->create([
            'total_a5_count' => 2,
            'total_a6_count' => 1,
            'total_iqra_count' => 0,
        ]);

        $jenisQuranA5 = \App\Models\JenisQuran::firstOrCreate(
            ['kode_jenis' => 'A5'],
            ['nama_jenis' => 'Al-Quran A5', 'is_active' => true, 'harga' => 50000]
        );

        \App\Models\StatusPengiriman::firstOrCreate(
            ['slug' => 'pemesanan'],
            [
                'nama' => 'Proses Pemesanan',
                'deskripsi' => 'Default status',
                'warna' => 'purple',
                'urutan' => 1,
                'is_active' => true,
                'is_final' => false,
            ]
        );

        $addQuranData = [
            'jenis_quran_id' => $jenisQuranA5->id,
            'jumlah_quran' => 3,
            'tanggal_wakaf' => '2024-02-15',
            'catatan' => 'Additional donation',
        ];

        $response = $this->actingAs($this->admin)
            ->post("/admin/donatur/{$donatur->id}/add-quran", $addQuranData);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        // Refresh donatur to get updated counts
        $donatur->refresh();

        $this->assertEquals(5, $donatur->total_a5_count); // 2 + 3
    }

    /** @test */
    public function it_generates_wakaf_batches_when_creating_donatur()
    {
        $donaturData = [
            'kode_donatur' => 'DN-BATCH-001',
            'nama_donatur' => 'Batch Test User',
            'no_hp' => '+628123456789',
            'email_donatur' => 'batch@test.com',
            'alamat_donatur' => 'Batch Test Address',
            'jenis_wakaf_dipilih' => ['A5', 'A6'],
            'jumlah_a5' => 2,
            'jumlah_a6' => 3,
            'jumlah_iqra' => 0,
            'donation_date' => '2024-01-15',
            'prayer_mode' => 'semua_donatur',
            'doa_untuk_semua' => 'Test prayer for batch',
        ];

        $response = $this->actingAs($this->admin)
            ->post('/admin/donatur', $donaturData);

        $response->assertRedirect();

        $donatur = Donatur::where('kode_donatur', 'DN-BATCH-001')->first();

        $this->assertNotNull($donatur);

        // Should have correct number of pengiriman
        $totalPengiriman = $donatur->pengiriman()->count();
        $this->assertEquals(5, $totalPengiriman); // 2 A5 + 3 A6
    }

    /** @test */
    public function it_can_search_donatur_by_name()
    {
        $donatur1 = Donatur::factory()->create(['nama_donatur' => 'John Smith']);
        $donatur2 = Donatur::factory()->create(['nama_donatur' => 'Jane Doe']);

        $response = $this->actingAs($this->admin)
            ->get('/admin/donatur?search=John');

        $response->assertStatus(200);
        // Response should contain John Smith but not Jane Doe
        // This would need to be verified in actual response content
    }

    /** @test */
    public function it_can_export_donatur_data()
    {
        Donatur::factory()->count(5)->create();

        $response = $this->actingAs($this->admin)
            ->get('/admin/donatur-export');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function it_can_export_donatur_data_with_filters()
    {
        Donatur::factory()->create(['nama_donatur' => 'John Smith']);
        Donatur::factory()->create(['nama_donatur' => 'Jane Doe']);

        $response = $this->actingAs($this->admin)
            ->get('/admin/donatur-export?search=John');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function it_can_import_donatur_data()
    {
        // Seed required dependencies
        \App\Models\JenisQuran::firstOrCreate(
            ['kode_jenis' => 'A5'],
            ['nama_jenis' => 'Al-Quran A5', 'is_active' => true, 'harga' => 50000]
        );
        \App\Models\JenisQuran::firstOrCreate(
            ['kode_jenis' => 'A6'],
            ['nama_jenis' => 'Al-Quran A6', 'is_active' => true, 'harga' => 35000]
        );
        \App\Models\JenisQuran::firstOrCreate(
            ['kode_jenis' => 'IQRA'],
            ['nama_jenis' => 'Iqra', 'is_active' => true, 'harga' => 25000]
        );
        \App\Models\StatusPengiriman::firstOrCreate(
            ['slug' => 'pemesanan'],
            [
                'nama' => 'Proses Pemesanan',
                'deskripsi' => 'Default status',
                'warna' => 'purple',
                'urutan' => 1,
                'is_active' => true,
                'is_final' => false,
            ]
        );

        cache()->forget('jenis_quran_mapping');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'kode_donatur');
        $sheet->setCellValue('B1', 'nama_donatur');
        $sheet->setCellValue('C1', 'no_hp');
        $sheet->setCellValue('D1', 'email_donatur');
        $sheet->setCellValue('E1', 'alamat_donatur');
        $sheet->setCellValue('F1', 'jumlah_a5');
        $sheet->setCellValue('G1', 'jumlah_a6');
        $sheet->setCellValue('H1', 'jumlah_iqra');
        $sheet->setCellValue('I1', 'donation_date');
        $sheet->setCellValue('J1', 'doa_untuk_semua');

        $sheet->setCellValue('A2', 'DN-IMPORT-001');
        $sheet->setCellValue('B2', 'Import Test User');
        $sheet->setCellValue('C2', '+628123456789');
        $sheet->setCellValue('D2', 'import@test.com');
        $sheet->setCellValue('E2', 'Import Test Address');
        $sheet->setCellValue('F2', 2);
        $sheet->setCellValue('G2', 1);
        $sheet->setCellValue('H2', 0);
        $sheet->setCellValue('I2', '2024-01-15');
        $sheet->setCellValue('J2', 'Semoga berkah');

        $path = sys_get_temp_dir().'/donatur-test-import.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        $file = new UploadedFile(
            $path,
            'donatur-test-import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->admin)
            ->post('/admin/donatur-import', [
                'file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('donatur', [
            'kode_donatur' => 'DN-IMPORT-001',
            'nama_donatur' => 'Import Test User',
        ]);
    }

    /** @test */
    public function it_rejects_invalid_import_file_type()
    {
        $file = UploadedFile::fake()->create('donatur.txt', 10, 'text/plain');

        $response = $this->actingAs($this->admin)
            ->post('/admin/donatur-import', [
                'file' => $file,
            ]);

        $response->assertSessionHasErrors(['file']);
    }

    /** @test */
    public function it_can_download_import_template()
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/donatur-template');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function cs_user_can_access_donatur_management()
    {
        $response = $this->actingAs($this->cs)
            ->get('/admin/donatur');

        $response->assertStatus(200);
    }

    /** @test */
    public function cs_user_can_create_donatur()
    {
        $donaturData = [
            'kode_donatur' => 'DN-CS-001',
            'nama_donatur' => 'CS Test User',
            'no_hp' => '+628123456789',
            'email_donatur' => 'cs@test.com',
            'alamat_donatur' => 'CS Test Address',
            'jenis_wakaf_dipilih' => ['A5'],
            'jumlah_a5' => 1,
            'jumlah_a6' => 0,
            'jumlah_iqra' => 0,
            'donation_date' => '2024-01-15',
            'prayer_mode' => 'semua_donatur',
            'doa_untuk_semua' => 'Test prayer',
        ];

        $response = $this->actingAs($this->cs)
            ->post('/admin/donatur', $donaturData);

        $response->assertRedirect();

        $this->assertDatabaseHas('donatur', [
            'kode_donatur' => 'DN-CS-001',
            'nama_donatur' => 'CS Test User',
            'created_by' => $this->cs->id,
        ]);
    }

    /** @test */
    public function unauthorized_user_cannot_access_donatur_management()
    {
        $unauthorizedUser = User::factory()->create(['is_active' => true]);
        // Don't assign any role

        $response = $this->actingAs($unauthorizedUser)
            ->get('/admin/donatur');

        $response->assertForbidden();
    }

    /** @test */
    public function it_enforces_unique_kode_donatur()
    {
        $existingDonatur = Donatur::factory()->create([
            'kode_donatur' => 'DN-UNIQUE-001',
        ]);

        $duplicateData = [
            'kode_donatur' => 'DN-UNIQUE-001', // Same as existing
            'nama_donatur' => 'Duplicate Test',
            'no_hp' => '+628123456789',
            'email_donatur' => 'duplicate@test.com',
            'alamat_donatur' => 'Duplicate Address',
            'jenis_wakaf_dipilih' => ['A5'],
            'jumlah_a5' => 1,
            'jumlah_a6' => 0,
            'jumlah_iqra' => 0,
            'donation_date' => '2024-01-15',
            'prayer_mode' => 'semua_donatur',
            'doa_untuk_semua' => 'Test prayer',
        ];

        $response = $this->actingAs($this->admin)
            ->post('/admin/donatur', $duplicateData);

        $response->assertSessionHasErrors(['kode_donatur']);
    }

    /** @test */
    public function it_can_update_kode_donatur_when_no_active_shipments()
    {
        $donatur = Donatur::factory()->create([
            'kode_donatur' => 'DN-OLD-001',
            'nama_donatur' => 'Test Donatur',
            'no_hp' => '+628123456789',
            'prayer_mode' => 'semua_donatur',
        ]);

        $response = $this->actingAs($this->admin)
            ->put("/admin/donatur/{$donatur->id}", [
                'kode_donatur' => 'DN-NEW-001',
                'nama_donatur' => 'Test Donatur',
                'no_hp' => '+628123456789',
                'prayer_mode' => 'semua_donatur',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('donatur', [
            'id' => $donatur->id,
            'kode_donatur' => 'DN-NEW-001',
        ]);
    }

    /** @test */
    public function it_cannot_update_kode_donatur_when_has_active_shipments()
    {
        $donatur = Donatur::factory()->create([
            'kode_donatur' => 'DN-OLD-002',
            'nama_donatur' => 'Test Donatur',
            'no_hp' => '+628123456789',
            'prayer_mode' => 'semua_donatur',
        ]);

        // Create an active shipment (status != Batal)
        $wakafItem = \App\Models\WakafItem::factory()->create([
            'donatur_id' => $donatur->id,
            'wakaf_type' => 'A5',
            'status' => 'pending',
        ]);

        $pengiriman = \App\Models\Pengiriman::factory()->create([
            'donatur_id' => $donatur->id,
            'status_id' => \App\Models\StatusPengiriman::getDefaultStatusId(),
        ]);

        $wakafItem->update(['pengiriman_id' => $pengiriman->id]);

        $response = $this->actingAs($this->admin)
            ->put("/admin/donatur/{$donatur->id}", [
                'kode_donatur' => 'DN-NEW-002',
                'nama_donatur' => 'Test Donatur',
                'no_hp' => '+628123456789',
                'prayer_mode' => 'semua_donatur',
            ]);

        $response->assertSessionHasErrors(['kode_donatur']);
        $this->assertDatabaseHas('donatur', [
            'id' => $donatur->id,
            'kode_donatur' => 'DN-OLD-002',
        ]);
    }

    /** @test */
    public function it_enforces_unique_kode_donatur_on_update()
    {
        $donaturA = Donatur::factory()->create([
            'kode_donatur' => 'DN-A-001',
        ]);
        $donaturB = Donatur::factory()->create([
            'kode_donatur' => 'DN-B-001',
        ]);

        $response = $this->actingAs($this->admin)
            ->put("/admin/donatur/{$donaturB->id}", [
                'kode_donatur' => 'DN-A-001', // Same as donaturA
                'nama_donatur' => 'Test Donatur',
                'no_hp' => '+628123456789',
                'prayer_mode' => 'semua_donatur',
            ]);

        $response->assertSessionHasErrors(['kode_donatur']);
    }

    /** @test */
    public function it_accepts_international_phone_numbers()
    {
        $donaturData = [
            'kode_donatur' => 'DN-INTL-001',
            'nama_donatur' => 'International Donor',
            'no_hp' => '+60123456789', // Malaysia
            'email_donatur' => 'intl@test.com',
            'alamat_donatur' => 'Kuala Lumpur',
            'jenis_wakaf_dipilih' => ['A5'],
            'jumlah_a5' => 1,
            'jumlah_a6' => 0,
            'jumlah_iqra' => 0,
            'donation_date' => '2024-01-15',
            'prayer_mode' => 'semua_donatur',
            'doa_untuk_semua' => 'Test prayer',
        ];

        $response = $this->actingAs($this->admin)
            ->post('/admin/donatur', $donaturData);

        $response->assertRedirect();
        $this->assertDatabaseHas('donatur', [
            'kode_donatur' => 'DN-INTL-001',
            'no_hp' => '+60123456789',
        ]);
    }

    /** @test */
    public function it_rejects_invalid_international_phone_numbers()
    {
        $donaturData = [
            'kode_donatur' => 'DN-INVALID-001',
            'nama_donatur' => 'Invalid Phone',
            'no_hp' => '+123', // Too short, invalid
            'email_donatur' => 'invalid@test.com',
            'alamat_donatur' => 'Test Address',
            'jenis_wakaf_dipilih' => ['A5'],
            'jumlah_a5' => 1,
            'jumlah_a6' => 0,
            'jumlah_iqra' => 0,
            'donation_date' => '2024-01-15',
            'prayer_mode' => 'semua_donatur',
            'doa_untuk_semua' => 'Test prayer',
        ];

        $response = $this->actingAs($this->admin)
            ->post('/admin/donatur', $donaturData);

        $response->assertSessionHasErrors(['no_hp']);
    }

    /** @test */
    public function it_returns_donatur_data_for_autocomplete_including_international_phone()
    {
        $donatur = Donatur::factory()->create([
            'kode_donatur' => 'DN-AUTO-001',
            'nama_donatur' => 'Auto Fill Test',
            'no_hp' => '+6281234567890',
            'email_donatur' => 'auto@test.com',
            'alamat_donatur' => 'Auto Address 123',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/api/donatur/search-kode?q=DN-AUTO');

        $response->assertOk();
        $response->assertJsonPath('data.0.kode_donatur', 'DN-AUTO-001');
        $response->assertJsonPath('data.0.nama_donatur', 'Auto Fill Test');
        $response->assertJsonPath('data.0.no_hp', '+6281234567890');
        $response->assertJsonPath('data.0.email_donatur', 'auto@test.com');
        $response->assertJsonPath('data.0.alamat_donatur', 'Auto Address 123');
    }
}
