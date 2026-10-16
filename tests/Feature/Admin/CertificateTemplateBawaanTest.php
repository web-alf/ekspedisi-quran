<?php

use App\Models\CertificateTemplate;
use App\Models\Sertifikat;
use App\Models\User;
use App\Models\WakafBatch;
use App\Services\OnDemandCertificateService;
use Database\Seeders\CertificateTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Template sertifikat bawaan.
 *
 * Sampai sekarang produksi tidak punya satu pun template, dan akibatnya seluruh
 * jalur sertifikat berhenti di tengah: PDF-nya mustahil dibuat. Berkas ini menjaga
 * tiga hal — template bawaan benar-benar terpasang beserta gambarnya, sertifikat
 * nyata bisa dicetak dengannya, dan seeder ini tidak merusak pekerjaan admin.
 */
it('memasang template bawaan beserta berkas gambarnya', function () {
    User::factory()->create();

    $this->seed(CertificateTemplateSeeder::class);

    $template = CertificateTemplate::all();

    expect($template)->toHaveCount(2)
        ->and(CertificateTemplate::where('is_default', true)->count())->toBe(1);

    foreach ($template as $satu) {
        expect(Storage::disk('public')->exists($satu->template_file_path))->toBeTrue()
            ->and($satu->width)->toBe(2000)
            ->and($satu->height)->toBe(1414)
            ->and($satu->is_active)->toBeTrue();

        // Posisi tiap field harus berada di dalam kanvas, kalau tidak teksnya
        // tercetak di luar sertifikat.
        foreach ($satu->field_positions as $nama => $posisi) {
            expect($posisi)->toHaveKeys(['x', 'y', 'font_size', 'color'])
                ->and($posisi['x'])->toBeGreaterThan(0)->toBeLessThanOrEqual(2000)
                ->and($posisi['y'])->toBeGreaterThan(0)->toBeLessThanOrEqual(1414);
        }
    }
});

it('dapat mencetak sertifikat nyata dengan template bawaan', function () {
    User::factory()->create();

    $this->seed(CertificateTemplateSeeder::class);

    $batch = WakafBatch::factory()->create(['total_quran' => 12]);

    $pdf = app(OnDemandCertificateService::class)->generateOnDemand($batch);

    expect($pdf)->not->toBeNull()
        ->and(Sertifikat::count())->toBe(1)
        ->and(Sertifikat::first()->template_id)
        ->toBe(CertificateTemplate::where('is_default', true)->value('id'));
});

it('tidak menimpa template yang sudah ada dan tidak merebut posisi bawaan', function () {
    $milikAdmin = CertificateTemplate::factory()->create([
        'name' => 'Punya Admin',
        'slug' => 'punya-admin',
        'is_default' => true,
        'field_positions' => [
            'wakif_name' => ['x' => 10, 'y' => 20, 'font_size' => 30, 'color' => '#111111'],
        ],
    ]);

    $this->seed(CertificateTemplateSeeder::class);
    // Dijalankan dua kali harus aman.
    $this->seed(CertificateTemplateSeeder::class);

    expect(CertificateTemplate::count())->toBe(3)
        ->and(CertificateTemplate::where('is_default', true)->count())->toBe(1)
        ->and($milikAdmin->fresh()->is_default)->toBeTrue()
        ->and($milikAdmin->fresh()->field_positions['wakif_name']['x'])->toBe(10);
});
