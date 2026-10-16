<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Sertifikat;
use App\Models\WakafBatch;
use App\Models\Pengiriman;
use App\Models\CertificateTemplate;
use Illuminate\Support\Facades\Storage;

class SertifikatTest extends TestCase
{
    /** @test */
    public function it_can_create_sertifikat()
    {
        $sertifikat = Sertifikat::factory()->create();

        $this->assertDatabaseHas('sertifikat', [
            'id' => $sertifikat->id,
        ]);
    }

    /** @test */
    public function it_auto_generates_nomor_sertifikat_when_creating()
    {
        $sertifikat = Sertifikat::factory()->create(['nomor_sertifikat' => null]);

        $this->assertNotNull($sertifikat->nomor_sertifikat);
        $this->assertMatchesRegularExpression('/^CERT-EQ-\d{4}-\d{5}$/', $sertifikat->nomor_sertifikat);
    }

    /** @test */
    public function it_generates_unique_nomor_sertifikat()
    {
        $sertifikat1 = Sertifikat::factory()->create();
        $sertifikat2 = Sertifikat::factory()->create();

        $this->assertNotEquals($sertifikat1->nomor_sertifikat, $sertifikat2->nomor_sertifikat);
    }

    /** @test */
    public function it_auto_sets_generated_at_when_creating()
    {
        $sertifikat = Sertifikat::factory()->create(['generated_at' => null]);

        $this->assertNotNull($sertifikat->generated_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $sertifikat->generated_at);
    }

    /** @test */
    public function it_belongs_to_wakaf_batch()
    {
        $wakafBatch = WakafBatch::factory()->create();
        $sertifikat = Sertifikat::factory()->create(['wakaf_batch_id' => $wakafBatch->id]);

        $this->assertInstanceOf(WakafBatch::class, $sertifikat->wakafBatch);
        $this->assertEquals($wakafBatch->id, $sertifikat->wakafBatch->id);
    }

    /** @test */
    public function it_belongs_to_pengiriman_for_legacy_support()
    {
        $pengiriman = Pengiriman::factory()->create();
        $sertifikat = Sertifikat::factory()->legacy()->create(['pengiriman_id' => $pengiriman->id]);

        $this->assertInstanceOf(Pengiriman::class, $sertifikat->pengiriman);
        $this->assertEquals($pengiriman->id, $sertifikat->pengiriman->id);
    }

    /** @test */
    public function it_has_formatted_generated_at_accessor()
    {
        $sertifikat = Sertifikat::factory()->create([
            'generated_at' => '2024-01-15 14:30:00'
        ]);

        $this->assertEquals('15 Jan 2024 14:30', $sertifikat->formatted_generated_at);
    }

    /** @test */
    public function it_has_formatted_sent_at_accessor()
    {
        $sertifikat = Sertifikat::factory()->sent()->create([
            'sent_at' => '2024-01-16 10:15:00'
        ]);

        $this->assertEquals('16 Jan 2024 10:15', $sertifikat->formatted_sent_at);
    }

    /** @test */
    public function it_returns_null_for_formatted_dates_when_null()
    {
        $sertifikat = Sertifikat::factory()->notSent()->create([
            'sent_at' => null
        ]);

        $this->assertNull($sertifikat->formatted_sent_at);
    }

    /** @test */
    public function it_has_download_url_accessor()
    {
        $sertifikat = Sertifikat::factory()->create();

        $expectedUrl = route('admin.certificates.download', $sertifikat->id);
        $this->assertEquals($expectedUrl, $sertifikat->download_url);
    }

    /** @test */
    public function it_has_view_url_accessor()
    {
        $sertifikat = Sertifikat::factory()->create();

        $expectedUrl = route('admin.certificates.view', $sertifikat->id);
        $this->assertEquals($expectedUrl, $sertifikat->view_url);
    }

    /** @test */
    public function it_can_check_if_file_exists()
    {
        $this->markTestSkipped('file_path column removed (on-demand cert generation)');
    }

    /** @test */
    public function it_returns_false_when_file_does_not_exist()
    {
        $this->markTestSkipped('file_path column removed (on-demand cert generation)');
    }

    /** @test */
    public function it_can_get_storage_path()
    {
        $this->markTestSkipped('file_path column removed (on-demand cert generation)');
    }

    /** @test */
    public function it_corrects_storage_path_when_missing_prefix()
    {
        $this->markTestSkipped('file_path column removed (on-demand cert generation)');
    }

    /** @test */
    public function it_can_mark_as_sent()
    {
        $sertifikat = Sertifikat::factory()->notSent()->create();

        $this->assertFalse($sertifikat->is_sent);
        $this->assertNull($sertifikat->sent_at);

        $sertifikat->markAsSent();

        $this->assertTrue($sertifikat->fresh()->is_sent);
        $this->assertNotNull($sertifikat->fresh()->sent_at);
    }

    /** @test */
    public function it_can_get_file_content()
    {
        $this->markTestSkipped('file_path column removed (on-demand cert generation)');
    }

    /** @test */
    public function it_throws_exception_when_getting_content_of_nonexistent_file()
    {
        $this->markTestSkipped('file_path column removed (on-demand cert generation)');
    }

    /** @test */
    public function it_has_scope_for_generated_today()
    {
        $today = Sertifikat::factory()->create(['generated_at' => now()]);
        $yesterday = Sertifikat::factory()->create(['generated_at' => now()->subDay()]);

        $todayResults = Sertifikat::generatedToday()->get();

        $this->assertCount(1, $todayResults);
        $this->assertEquals($today->id, $todayResults->first()->id);
    }

    /** @test */
    public function it_has_scope_for_generated_this_month()
    {
        $thisMonth = Sertifikat::factory()->create(['generated_at' => now()]);
        $lastMonth = Sertifikat::factory()->create(['generated_at' => now()->subMonth()]);

        $thisMonthResults = Sertifikat::generatedThisMonth()->get();

        $this->assertCount(1, $thisMonthResults);
        $this->assertEquals($thisMonth->id, $thisMonthResults->first()->id);
    }

    /** @test */
    public function it_has_scope_for_not_sent()
    {
        $sent = Sertifikat::factory()->sent()->create();
        $notSent = Sertifikat::factory()->notSent()->create();

        $notSentResults = Sertifikat::notSent()->get();

        $this->assertCount(1, $notSentResults);
        $this->assertEquals($notSent->id, $notSentResults->first()->id);
    }

    /** @test */
    public function it_generates_sequential_nomor_sertifikat()
    {
        $year = date('Y');
        $prefix = "CERT-EQ-{$year}-";

        $sertifikat1 = Sertifikat::factory()->create();
        $sertifikat2 = Sertifikat::factory()->create();

        $this->assertStringStartsWith($prefix, $sertifikat1->nomor_sertifikat);
        $this->assertStringStartsWith($prefix, $sertifikat2->nomor_sertifikat);

        // Extract numbers and verify they're sequential
        $num1 = (int) substr($sertifikat1->nomor_sertifikat, -5);
        $num2 = (int) substr($sertifikat2->nomor_sertifikat, -5);

        $this->assertEquals($num1 + 1, $num2);
    }

    /** @test */
    public function it_casts_boolean_fields_properly()
    {
        $sertifikat = Sertifikat::factory()->sent()->create();

        $this->assertTrue($sertifikat->is_sent);
        $this->assertIsBool($sertifikat->is_sent);
    }

    /** @test */
    public function it_casts_datetime_fields_properly()
    {
        $sertifikat = Sertifikat::factory()->sent()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $sertifikat->generated_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $sertifikat->sent_at);
    }
}