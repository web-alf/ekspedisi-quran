<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Setting;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed legal settings
        $this->artisan('db:seed', ['--class' => 'LegalSettingsSeeder']);
    }

    /** @test */
    public function privacy_policy_page_loads_successfully()
    {
        $response = $this->get('/privacy-policy');
        
        $response->assertStatus(200);
        $response->assertSee('Kebijakan Privasi');
        $response->assertSee('Ekspedisi Qur\'an');
    }

    /** @test */
    public function terms_of_service_page_loads_successfully()
    {
        $response = $this->get('/terms-of-service');
        
        $response->assertStatus(200);
        $response->assertSee('Syarat dan Ketentuan');
        $response->assertSee('Ekspedisi Qur\'an');
    }

    /** @test */
    public function privacy_policy_has_correct_meta_data()
    {
        $response = $this->get('/privacy-policy');
        
        $response->assertSee('Kebijakan Privasi', false);
        $response->assertSee('privacy', false);
        $response->assertSee('perlindungan data', false);
    }

    /** @test */
    public function terms_of_service_has_correct_meta_data()
    {
        $response = $this->get('/terms-of-service');
        
        $response->assertSee('Syarat dan Ketentuan', false);
        $response->assertSee('terms of service', false);
        $response->assertSee('layanan', false);
    }

    /** @test */
    public function legal_settings_are_seeded_correctly()
    {
        $privacyPolicy = Setting::where('key', 'legal_privacy_policy')->first();
        $termsOfService = Setting::where('key', 'legal_terms_of_service')->first();
        
        $this->assertNotNull($privacyPolicy);
        $this->assertNotNull($termsOfService);
        $this->assertEquals('legal', $privacyPolicy->group);
        $this->assertEquals('legal', $termsOfService->group);
        $this->assertTrue($privacyPolicy->is_public);
        $this->assertTrue($termsOfService->is_public);
    }

    /** @test */
    public function legal_pages_respect_rate_limiting()
    {
        // Make 101 requests (exceeds 100/minute limit)
        for ($i = 0; $i < 101; $i++) {
            $response = $this->get('/privacy-policy');
            if ($i < 100) {
                $this->assertEquals(200, $response->getStatusCode());
            }
        }
        
        // The 101st request should be rate limited
        $response = $this->get('/privacy-policy');
        $this->assertEquals(429, $response->getStatusCode());
    }

    /** @test */
    public function legal_pages_have_proper_content_structure()
    {
        $response = $this->get('/privacy-policy');
        
        // Check for important sections
        $response->assertSee('Informasi yang Kami Kumpulkan');
        $response->assertSee('Penggunaan Informasi');
        $response->assertSee('Keamanan Data');
        $response->assertSee('Hak Anda');
        
        $response = $this->get('/terms-of-service');
        
        // Check for important sections
        $response->assertSee('Penerimaan Syarat');
        $response->assertSee('Tentang Layanan');
        $response->assertSee('Eligibilitas');
        $response->assertSee('Kewajiban Penerima');
    }

    /** @test */
    public function legal_settings_can_be_updated()
    {
        $newContent = '<h2>Updated Privacy Policy</h2><p>New content here.</p>';
        
        Setting::where('key', 'legal_privacy_policy')
            ->update(['value' => $newContent]);
        
        $response = $this->get('/privacy-policy');
        $response->assertSee('Updated Privacy Policy', false);
        $response->assertSee('New content here', false);
    }

    /** @test */
    public function legal_contact_email_is_available()
    {
        $contactEmail = Setting::where('key', 'legal_contact_email')->first();
        
        $this->assertNotNull($contactEmail);
        $this->assertStringContainsString('@', $contactEmail->value);
        $this->assertTrue($contactEmail->is_public);
    }

    /** @test */
    public function legal_pages_have_last_updated_dates()
    {
        $response = $this->get('/privacy-policy');
        $response->assertSee('Terakhir diperbarui');
        
        $response = $this->get('/terms-of-service');
        $response->assertSee('Terakhir diperbarui');
    }

    /** @test */
    public function legal_settings_group_is_available_in_admin()
    {
        // This would require authentication and proper user setup
        // For now, just test that the settings exist with correct group
        $legalSettings = Setting::where('group', 'legal')->get();
        
        $this->assertGreaterThan(0, $legalSettings->count());
        
        // Check that all expected legal settings exist
        $expectedKeys = [
            'legal_privacy_policy',
            'legal_terms_of_service',
            'legal_privacy_policy_updated',
            'legal_terms_of_service_updated',
            'legal_contact_email'
        ];
        
        foreach ($expectedKeys as $key) {
            $this->assertTrue(
                $legalSettings->contains('key', $key),
                "Legal setting {$key} not found"
            );
        }
    }
}
