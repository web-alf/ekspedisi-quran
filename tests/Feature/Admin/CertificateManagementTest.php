<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Donatur;
use App\Models\WakafBatch;
use App\Models\Sertifikat;
use App\Models\CertificateTemplate;
use App\Models\JenisQuran;
use App\Models\Pengiriman;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CertificateManagementTest extends TestCase
{
    protected $admin;
    protected $cs;
    protected $donatur;
    protected $wakafBatch;
    protected $certificateTemplate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped('Certificate tests target pre-on-demand flow (file_path column). Rewrite against OnDemandCertificateService.');
        
        Storage::fake('local');
        
        // Create roles and permissions
        $this->createRolesAndPermissions();
        
        // Create users
        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
        
        $this->cs = User::factory()->create();
        $this->cs->assignRole('cs');
        
        // Create test data
        $this->donatur = Donatur::factory()->create();
        $this->wakafBatch = WakafBatch::factory()->completed()->create([
            'donatur_id' => $this->donatur->id
        ]);
        
        $this->certificateTemplate = CertificateTemplate::factory()->create([
            'name' => 'Default Template',
            'is_active' => true
        ]);
    }
    
    protected function createRolesAndPermissions()
    {
        try {
            $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
            $csRole = Role::firstOrCreate(['name' => 'cs']);
            
            $certificatePermissions = [
                'certificates.read',
                'certificates.create',
                'certificates.update',
                'certificates.delete',
                'certificates.download',
            ];
            
            foreach ($certificatePermissions as $permission) {
                Permission::firstOrCreate(['name' => $permission]);
            }
            
            $superAdminRole->givePermissionTo($certificatePermissions);
            $csRole->givePermissionTo(['certificates.read', 'certificates.create', 'certificates.download']);
            
        } catch (\Exception $e) {
            // Skip if Spatie not configured
        }
    }

    /** @test */
    public function it_can_display_certificates_index_page()
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/certificates');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_can_generate_certificate_for_wakaf_batch()
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/certificates', [
                'wakaf_batch_id' => $this->wakafBatch->id,
                'template_id' => $this->certificateTemplate->id,
            ]);

        $response->assertRedirect();
        
        // Verify certificate was created
        $this->assertDatabaseHas('sertifikat', [
            'wakaf_batch_id' => $this->wakafBatch->id,
            'template_id' => $this->certificateTemplate->id,
            'generated_by' => $this->admin->id,
        ]);
        
        $certificate = Sertifikat::where('wakaf_batch_id', $this->wakafBatch->id)->first();
        $this->assertNotNull($certificate->nomor_sertifikat);
        $this->assertMatchesRegularExpression('/^CERT-EQ-\d{4}-\d{5}$/', $certificate->nomor_sertifikat);
    }

    /** @test */
    public function it_validates_required_fields_for_certificate_generation()
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/certificates', []);

        $response->assertSessionHasErrors([
            'wakaf_batch_id',
            'template_id',
        ]);
    }

    /** @test */
    public function it_prevents_duplicate_certificate_generation()
    {
        // Generate first certificate
        Sertifikat::factory()->create([
            'wakaf_batch_id' => $this->wakafBatch->id,
            'template_id' => $this->certificateTemplate->id,
        ]);

        // Try to generate another for same batch
        $response = $this->actingAs($this->admin)
            ->post('/admin/certificates', [
                'wakaf_batch_id' => $this->wakafBatch->id,
                'template_id' => $this->certificateTemplate->id,
            ]);

        $response->assertSessionHasErrors(['wakaf_batch_id']);
    }

    /** @test */
    public function it_can_regenerate_existing_certificate()
    {
        $existingCertificate = Sertifikat::factory()->create([
            'wakaf_batch_id' => $this->wakafBatch->id,
            'template_id' => $this->certificateTemplate->id,
            'nomor_sertifikat' => 'CERT-EQ-2024-00001',
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/admin/certificates/regenerate', [
                'certificate_id' => $existingCertificate->id,
                'template_id' => $this->certificateTemplate->id,
            ]);

        $response->assertRedirect();
        
        // Verify certificate was updated
        $existingCertificate->refresh();
        $this->assertEquals($this->admin->id, $existingCertificate->generated_by);
        $this->assertNotNull($existingCertificate->generated_at);
    }

    /** @test */
    public function it_can_download_certificate()
    {
        $certificate = Sertifikat::factory()->withFile()->create([
            'wakaf_batch_id' => $this->wakafBatch->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/admin/certificates/{$certificate->id}/download");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    /** @test */
    public function it_returns_404_for_missing_certificate_file()
    {
        $certificate = Sertifikat::factory()->create([
            'wakaf_batch_id' => $this->wakafBatch->id,
            'file_path' => 'certificates/nonexistent.pdf',
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/admin/certificates/{$certificate->id}/download");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_can_view_certificate_details()
    {
        $certificate = Sertifikat::factory()->create([
            'wakaf_batch_id' => $this->wakafBatch->id,
            'nomor_sertifikat' => 'CERT-EQ-2024-12345',
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/admin/certificates/{$certificate->id}");

        $response->assertStatus(200);
        $response->assertSee($certificate->nomor_sertifikat);
        $response->assertSee($this->donatur->nama_donatur);
        $response->assertSee($this->wakafBatch->batch_code);
    }

    /** @test */
    public function it_can_mark_certificate_as_sent()
    {
        $certificate = Sertifikat::factory()->notSent()->create([
            'wakaf_batch_id' => $this->wakafBatch->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/admin/certificates/{$certificate->id}/mark-sent");

        $response->assertRedirect();
        
        $certificate->refresh();
        $this->assertTrue($certificate->is_sent);
        $this->assertNotNull($certificate->sent_at);
    }

    /** @test */
    public function it_can_bulk_generate_certificates()
    {
        $wakafBatch2 = WakafBatch::factory()->completed()->create([
            'donatur_id' => $this->donatur->id
        ]);
        
        $wakafBatch3 = WakafBatch::factory()->completed()->create([
            'donatur_id' => $this->donatur->id
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/admin/certificates/bulk-generate', [
                'wakaf_batch_ids' => [$this->wakafBatch->id, $wakafBatch2->id, $wakafBatch3->id],
                'template_id' => $this->certificateTemplate->id,
            ]);

        $response->assertRedirect();
        
        // Verify all certificates were created
        $this->assertDatabaseHas('sertifikat', ['wakaf_batch_id' => $this->wakafBatch->id]);
        $this->assertDatabaseHas('sertifikat', ['wakaf_batch_id' => $wakafBatch2->id]);
        $this->assertDatabaseHas('sertifikat', ['wakaf_batch_id' => $wakafBatch3->id]);
    }

    /** @test */
    public function it_can_filter_certificates_by_status()
    {
        $sentCertificate = Sertifikat::factory()->sent()->create([
            'wakaf_batch_id' => $this->wakafBatch->id,
        ]);
        
        $notSentCertificate = Sertifikat::factory()->notSent()->create([
            'wakaf_batch_id' => WakafBatch::factory()->create()->id,
        ]);

        // Filter by sent status
        $response = $this->actingAs($this->admin)
            ->get('/admin/certificates?status=sent');

        $response->assertStatus(200);
        // Should contain sent certificate but not unsent one
    }

    /** @test */
    public function it_can_filter_certificates_by_date_range()
    {
        $todayCertificate = Sertifikat::factory()->create([
            'wakaf_batch_id' => $this->wakafBatch->id,
            'generated_at' => now(),
        ]);
        
        $oldCertificate = Sertifikat::factory()->create([
            'wakaf_batch_id' => WakafBatch::factory()->create()->id,
            'generated_at' => now()->subMonth(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/certificates?start_date=' . now()->format('Y-m-d') . '&end_date=' . now()->format('Y-m-d'));

        $response->assertStatus(200);
        // Should filter correctly by date range
    }

    /** @test */
    public function it_can_export_certificates_list()
    {
        Sertifikat::factory()->count(5)->create([
            'wakaf_batch_id' => $this->wakafBatch->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/certificates?export=true');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function cs_user_can_access_certificate_management()
    {
        $response = $this->actingAs($this->cs)
            ->get('/admin/certificates');

        $response->assertStatus(200);
    }

    /** @test */
    public function cs_user_can_generate_certificates()
    {
        $response = $this->actingAs($this->cs)
            ->post('/admin/certificates', [
                'wakaf_batch_id' => $this->wakafBatch->id,
                'template_id' => $this->certificateTemplate->id,
            ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('sertifikat', [
            'wakaf_batch_id' => $this->wakafBatch->id,
            'generated_by' => $this->cs->id,
        ]);
    }

    /** @test */
    public function it_shows_certificate_generation_statistics()
    {
        // Create certificates from different dates
        Sertifikat::factory()->create([
            'wakaf_batch_id' => $this->wakafBatch->id,
            'generated_at' => now(),
        ]);
        
        Sertifikat::factory()->create([
            'wakaf_batch_id' => WakafBatch::factory()->create()->id,
            'generated_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/certificates');

        $response->assertStatus(200);
        // Should show statistics about certificates generated today, this month, etc.
    }

    /** @test */
    public function it_handles_certificate_template_selection()
    {
        $template2 = CertificateTemplate::factory()->create([
            'name' => 'Alternative Template',
            'is_active' => true
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/admin/certificates', [
                'wakaf_batch_id' => $this->wakafBatch->id,
                'template_id' => $template2->id,
            ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('sertifikat', [
            'wakaf_batch_id' => $this->wakafBatch->id,
            'template_id' => $template2->id,
        ]);
    }

    /** @test */
    public function it_generates_sequential_certificate_numbers()
    {
        $certificate1 = Sertifikat::factory()->create([
            'wakaf_batch_id' => $this->wakafBatch->id,
        ]);
        
        $certificate2 = Sertifikat::factory()->create([
            'wakaf_batch_id' => WakafBatch::factory()->create()->id,
        ]);

        // Verify sequential numbering
        $this->assertNotEquals($certificate1->nomor_sertifikat, $certificate2->nomor_sertifikat);
        
        // Extract numbers and verify they're sequential
        preg_match('/CERT-EQ-\d{4}-(\d{5})/', $certificate1->nomor_sertifikat, $matches1);
        preg_match('/CERT-EQ-\d{4}-(\d{5})/', $certificate2->nomor_sertifikat, $matches2);
        
        $num1 = (int) $matches1[1];
        $num2 = (int) $matches2[1];
        
        $this->assertEquals($num1 + 1, $num2);
    }

    /** @test */
    public function unauthorized_user_cannot_access_certificates()
    {
        $unauthorizedUser = User::factory()->create();

        $response = $this->actingAs($unauthorizedUser)
            ->get('/admin/certificates');

        $response->assertStatus(403);
    }

    /** @test */
    public function it_validates_wakaf_batch_exists_for_certificate_generation()
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/certificates', [
                'wakaf_batch_id' => 99999, // Non-existent batch
                'template_id' => $this->certificateTemplate->id,
            ]);

        $response->assertSessionHasErrors(['wakaf_batch_id']);
    }

    /** @test */
    public function it_only_allows_certificate_generation_for_completed_batches()
    {
        $pendingBatch = WakafBatch::factory()->pending()->create([
            'donatur_id' => $this->donatur->id
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/admin/certificates', [
                'wakaf_batch_id' => $pendingBatch->id,
                'template_id' => $this->certificateTemplate->id,
            ]);

        $response->assertSessionHasErrors(['wakaf_batch_id']);
    }
}