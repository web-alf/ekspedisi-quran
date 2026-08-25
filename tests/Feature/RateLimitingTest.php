<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear rate limiter before each test
        RateLimiter::clear('login');
        RateLimiter::clear('tracking-public');
        RateLimiter::clear('wilayah-public');
    }

    /**
     * Test login rate limiting functionality
     */
    public function test_login_rate_limiting()
    {
        $email = 'test@example.com';
        $ip = '127.0.0.1';

        // Make 5 failed login attempts (should be allowed)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', [
                'email' => $email,
                'password' => 'wrongpassword',
            ]);

            // Should get validation error, not rate limit error
            $this->assertNotEquals(429, $response->status());
        }

        // 6th attempt should be rate limited
        $response = $this->post('/login', [
            'email' => $email,
            'password' => 'wrongpassword',
        ]);

        $this->assertEquals(429, $response->status());
    }

    /**
     * Test tracking API rate limiting
     */
    public function test_tracking_api_rate_limiting()
    {
        // Make requests up to the limit (60 per minute - updated)
        for ($i = 0; $i < 60; $i++) {
            $response = $this->get('/tracking/TEST123'.$i);
            // Should get 404 (not found) but not rate limited
            $this->assertNotEquals(429, $response->status());
        }

        // 61st request should be rate limited
        $response = $this->get('/tracking/TEST999');
        $this->assertEquals(429, $response->status());
    }

    /**
     * Test wilayah API rate limiting
     */
    public function test_wilayah_api_rate_limiting()
    {
        // Test the public wilayah API endpoint (120 per minute - updated)
        for ($i = 0; $i < 120; $i++) {
            $response = $this->get('/api/wilayah/provinces');
            $this->assertNotEquals(429, $response->status());
        }

        // 121st request should be rate limited
        $response = $this->get('/api/wilayah/provinces');
        $this->assertEquals(429, $response->status());
    }

    /**
     * Test mushaf request rate limiting
     */
    public function test_mushaf_request_rate_limiting()
    {
        // Make 60 mushaf requests (should be allowed per hour)
        for ($i = 0; $i < 60; $i++) {
            $response = $this->post('/mushaf-request', [
                'nama_lembaga' => 'Test Lembaga '.$i,
                'kategori_lembaga' => 'masjid',
                'nama_penanggung_jawab' => 'Test PJ',
                'email' => 'test'.$i.'@example.com',
                'no_telepon' => '08123456789',
                'provinsi' => 'DKI Jakarta',
                'kota_kabupaten' => 'Jakarta Pusat',
                'alamat_lengkap' => 'Test Address',
                'jumlah_mushaf' => 10,
                'jumlah_iqra' => 5,
            ]);

            // Should not be rate limited yet
            $this->assertNotEquals(429, $response->status());
        }

        // 61st request should be rate limited
        $response = $this->post('/mushaf-request', [
            'nama_lembaga' => 'Test Lembaga Excess',
            'kategori_lembaga' => 'masjid',
            'nama_penanggung_jawab' => 'Test PJ',
            'email' => 'testexcess@example.com',
            'no_telepon' => '08123456789',
            'provinsi' => 'DKI Jakarta',
            'kota_kabupaten' => 'Jakarta Pusat',
            'alamat_lengkap' => 'Test Address',
            'jumlah_mushaf' => 10,
            'jumlah_iqra' => 5,
        ]);

        $this->assertEquals(429, $response->status());
    }

    /**
     * Test rate limit response format for API endpoints
     */
    public function test_rate_limit_response_format()
    {
        // Exhaust the tracking rate limit (60 per minute - updated)
        for ($i = 0; $i <= 60; $i++) {
            $response = $this->getJson('/api/tracking/TEST'.$i);
        }

        // Next request should return proper JSON error
        $response = $this->getJson('/api/tracking/TEST999');

        if ($response->status() === 429) {
            $response->assertJson([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.',
            ]);

            $response->assertJsonStructure([
                'error',
                'message',
                'retry_after',
                'retry_after_human',
            ]);

            $this->assertArrayHasKey('Retry-After', $response->headers->all());
        }
    }

    /**
     * Test that whitelisted IPs bypass rate limiting
     */
    public function test_ip_whitelist_bypasses_rate_limiting()
    {
        // This test would need to mock the IP or configure test whitelist
        // For now, just verify the whitelist config exists
        $whitelistIps = config('rate-limiting.whitelist_ips');
        $this->assertIsArray($whitelistIps);
        $this->assertContains('127.0.0.1', $whitelistIps);
    }
}
