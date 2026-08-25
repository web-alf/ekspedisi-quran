<?php

namespace Tests\Feature\Requests;

use App\Http\Requests\StoreDonaturRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StoreDonaturRequestTest extends TestCase
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
    public function it_validates_alamat_donatur_is_optional()
    {
        $this->actingAs($this->admin);

        // Test data without alamat_donatur
        $donaturData = [
            'kode_donatur' => 'DN-OPTIONAL-001',
            'nama_donatur' => 'Test User Without Address',
            'no_hp' => '08123456789',
            'email_donatur' => 'test@example.com',
            'donation_date' => now()->format('Y-m-d'),
            'jenis_wakaf_dipilih' => ['A5'],
            'jumlah_a5' => 1,
            'prayer_mode' => 'semua_donatur',
            'doa_untuk_semua' => 'Test prayer',
        ];

        // Create request instance and validate
        $request = new StoreDonaturRequest;
        $rules = $request->rules();

        $validator = Validator::make($donaturData, $rules);

        $this->assertTrue($validator->passes(),
            'Validation should pass when alamat_donatur is not provided. Errors: '.
            json_encode($validator->errors()->toArray())
        );
    }

    /** @test */
    public function it_validates_alamat_donatur_when_provided()
    {
        $this->actingAs($this->admin);

        // Test data with alamat_donatur
        $donaturDataWithAddress = [
            'kode_donatur' => 'DN-ADDRESS-001',
            'nama_donatur' => 'Test User With Address',
            'no_hp' => '08123456790',
            'email_donatur' => 'test2@example.com',
            'alamat_donatur' => 'Jl. Test Address No. 123',
            'donation_date' => now()->format('Y-m-d'),
            'jenis_wakaf_dipilih' => ['A5'],
            'jumlah_a5' => 1,
            'prayer_mode' => 'semua_donatur',
            'doa_untuk_semua' => 'Test prayer',
        ];

        $request = new StoreDonaturRequest;
        $rules = $request->rules();

        $validator = Validator::make($donaturDataWithAddress, $rules);

        $this->assertTrue($validator->passes(),
            'Validation should pass when alamat_donatur is provided. Errors: '.
            json_encode($validator->errors()->toArray())
        );
    }

    /** @test */
    public function it_validates_alamat_donatur_max_length()
    {
        $this->actingAs($this->admin);

        // Test data with alamat_donatur exceeding max length (500)
        $donaturDataTooLong = [
            'kode_donatur' => 'DN-LONG-001',
            'nama_donatur' => 'Test User Long Address',
            'no_hp' => '08123456791',
            'email_donatur' => 'test3@example.com',
            'alamat_donatur' => str_repeat('a', 501), // Exceeds 500 chars
            'donation_date' => now()->format('Y-m-d'),
            'jenis_wakaf_dipilih' => ['A5'],
            'jumlah_a5' => 1,
            'prayer_mode' => 'semua_donatur',
            'doa_untuk_semua' => 'Test prayer',
        ];

        $request = new StoreDonaturRequest;
        $rules = $request->rules();

        $validator = Validator::make($donaturDataTooLong, $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('alamat_donatur'));
    }
}
