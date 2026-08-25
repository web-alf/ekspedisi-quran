<?php

namespace Tests\Feature\Requests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DonaturCreationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles and permissions if they don't exist
        $this->createRolesAndPermissions();

        // Create test users
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super_admin');

        // Seed required data
        $this->seed(\Database\Seeders\JenisQuranSeeder::class);
    }

    protected function createRolesAndPermissions()
    {
        try {
            // Create roles
            $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);

            // Create permissions
            $donaturPermissions = [
                'donatur.read',
                'donatur.create',
                'donatur.update',
                'donatur.delete',
            ];

            foreach ($donaturPermissions as $permission) {
                Permission::firstOrCreate(['name' => $permission]);
            }

            $superAdminRole->syncPermissions($donaturPermissions);
        } catch (\Exception $e) {
            // Permissions might already exist
        }
    }

    /** @test */
    public function it_can_create_donatur_without_alamat_donatur()
    {
        $donaturData = [
            'kode_donatur' => 'DN-NO-ADDRESS-001',
            'nama_donatur' => 'Test User No Address',
            'no_hp' => '08123456789',
            'email_donatur' => 'no_address@example.com',
            // alamat_donatur is intentionally omitted
            'donation_date' => now()->format('Y-m-d'),
            'jenis_wakaf_dipilih' => ['A5'],
            'jumlah_a5' => 2,
            'jumlah_a6' => 0,
            'jumlah_iqra' => 0,
            'prayer_mode' => 'semua_donatur',
            'doa_untuk_semua' => 'May Allah accept our prayers',
        ];

        $response = $this->actingAs($this->admin)
            ->post('/admin/donatur', $donaturData);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify donatur was created in database
        $this->assertDatabaseHas('donatur', [
            'kode_donatur' => 'DN-NO-ADDRESS-001',
            'nama_donatur' => 'Test User No Address',
            'no_hp' => '08123456789',
            'email_donatur' => 'no_address@example.com',
            'alamat_donatur' => null, // Should be null since it wasn't provided
        ]);
    }

    /** @test */
    public function it_can_create_donatur_with_alamat_donatur()
    {
        $donaturData = [
            'kode_donatur' => 'DN-WITH-ADDRESS-001',
            'nama_donatur' => 'Test User With Address',
            'no_hp' => '08123456790',
            'email_donatur' => 'with_address@example.com',
            'alamat_donatur' => 'Jl. Test Address No. 123, Jakarta',
            'donation_date' => now()->format('Y-m-d'),
            'jenis_wakaf_dipilih' => ['A5'],
            'jumlah_a5' => 1,
            'jumlah_a6' => 0,
            'jumlah_iqra' => 0,
            'prayer_mode' => 'semua_donatur',
            'doa_untuk_semua' => 'May Allah bless us',
        ];

        $response = $this->actingAs($this->admin)
            ->post('/admin/donatur', $donaturData);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify donatur was created in database with address
        $this->assertDatabaseHas('donatur', [
            'kode_donatur' => 'DN-WITH-ADDRESS-001',
            'nama_donatur' => 'Test User With Address',
            'no_hp' => '08123456790',
            'email_donatur' => 'with_address@example.com',
            'alamat_donatur' => 'Jl. Test Address No. 123, Jakarta',
        ]);
    }
}
