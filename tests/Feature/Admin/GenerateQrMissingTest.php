<?php

use App\Models\JenisQuran;
use App\Models\Pengiriman;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::firstOrCreate(['name' => 'warehouse.qr.bulk_generate', 'guard_name' => 'web']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Storage::fake('local');
    Storage::fake('public');

    $this->qr = app(QrCodeService::class);
});

it('membuat berkas QR dan menyimpan path serta datanya ke resi', function () {
    $pengiriman = Pengiriman::factory()->create();

    expect($pengiriman->qr_code_path)->toBeNull();

    $path = $this->qr->buatDanSimpan($pengiriman);
    $pengiriman->refresh();

    expect($path)->toStartWith('public/qr-codes/')
        ->and($path)->toEndWith('.svg')
        ->and($path)->toContain($pengiriman->no_resi);

    Storage::assertExists($path);

    // Isi QR hanya nomor resi — bukan JSON. Inilah yang dibaca pemindai gudang.
    expect($pengiriman->qr_code_path)->toBe($path)
        ->and($pengiriman->qr_code_data)->toBe($pengiriman->no_resi);

    // SVG yang benar-benar berisi, bukan berkas kosong.
    expect(Storage::get($path))->toContain('<svg');
});

it('menganggap QR belum ada bila berkasnya hilang dari disk', function () {
    // Inilah kasus yang bikin resi tampak siap padahal gudang tidak bisa
    // memindainya: kolom terisi, berkasnya tidak ada.
    $pengiriman = Pengiriman::factory()->create();
    $path = $this->qr->buatDanSimpan($pengiriman);
    $pengiriman->refresh();

    expect($this->qr->sudahAda($pengiriman))->toBeTrue();

    Storage::delete($path);

    expect($this->qr->sudahAda($pengiriman->refresh()))->toBeFalse();
});

it('menganggap QR belum ada bila kolomnya kosong', function () {
    $pengiriman = Pengiriman::factory()->create();

    expect($this->qr->sudahAda($pengiriman))->toBeFalse();
});

it('membuatkan QR untuk semua resi yang belum punya, dan melewati yang sudah', function () {
    $sudahPunya = Pengiriman::factory()->create();
    $this->qr->buatDanSimpan($sudahPunya);

    $belum = Pengiriman::factory()->count(3)->create();

    $this->artisan('qr:generate-missing')
        ->expectsOutputToContain('Akan dibuatkan QR: 3')
        ->assertSuccessful();

    foreach ($belum as $p) {
        expect($p->refresh()->qr_code_path)->not->toBeNull();
        Storage::assertExists($p->qr_code_path);
    }

    // Tidak dibuat ulang: berkas resi yang sudah punya QR tetap satu saja.
    expect(Storage::files('public/qr-codes'))->toHaveCount(4);
});

it('tidak membuat berkas apa pun saat --dry-run', function () {
    Pengiriman::factory()->count(2)->create();

    $this->artisan('qr:generate-missing --dry-run')
        ->expectsOutputToContain('mode uji')
        ->assertSuccessful();

    expect(Storage::files('public/qr-codes'))->toBeEmpty()
        ->and(Pengiriman::whereNotNull('qr_code_path')->count())->toBe(0);
});

it('menghormati --limit', function () {
    Pengiriman::factory()->count(5)->create();

    $this->artisan('qr:generate-missing --limit=2')->assertSuccessful();

    expect(Pengiriman::whereNotNull('qr_code_path')->count())->toBe(2);
});

it('membatasi ke satu jenis Quran lewat --jenis', function () {
    // Jenis Quran dibuat lewat factory supaya kolomnya benar-benar ada —
    // basis data uji berbeda dari produksi, jadi id tidak bisa ditebak.
    // `kode_jenis` unik, jadi dipakai firstOrCreate.
    $a5 = JenisQuran::firstOrCreate(['kode_jenis' => 'A5'], ['nama_jenis' => 'Al-Quran Ukuran A5']);
    $a6 = JenisQuran::firstOrCreate(['kode_jenis' => 'A6'], ['nama_jenis' => 'Al-Quran Ukuran A6']);

    $resiA5 = Pengiriman::factory()->count(2)->create(['jenis_quran_id' => $a5->id]);
    Pengiriman::factory()->count(3)->create(['jenis_quran_id' => $a6->id]);

    $this->artisan("qr:generate-missing --jenis={$a5->id}")->assertSuccessful();

    foreach ($resiA5 as $p) {
        expect($p->refresh()->qr_code_path)->not->toBeNull();
    }

    expect(Pengiriman::where('jenis_quran_id', $a6->id)->whereNotNull('qr_code_path')->count())->toBe(0);
});

it('membuat ulang QR yang berkasnya hilang walau kolomnya masih terisi', function () {
    $pengiriman = Pengiriman::factory()->create();
    $lama = $this->qr->buatDanSimpan($pengiriman);
    $pengiriman->refresh();

    Storage::delete($lama);

    // Tanpa pemeriksaan berkas, resi ini akan dianggap beres dan dilewati
    // selamanya — padahal gudang tidak punya QR untuk dipindai.
    $this->artisan('qr:generate-missing')->assertSuccessful();

    $baru = $pengiriman->refresh()->qr_code_path;

    expect($baru)->not->toBeNull();
    Storage::assertExists($baru);
});

it('tidak menumpuk berkas saat QR dibuat ulang', function () {
    // Nama berkas stabil per resi: membuat ulang menimpa, bukan menumpuk
    // sampah yang tidak dirujuk kolom mana pun.
    $pengiriman = Pengiriman::factory()->create();
    $this->qr->buatDanSimpan($pengiriman);

    $this->artisan('qr:generate-missing --buat-ulang')->assertSuccessful();

    expect(Storage::files('public/qr-codes'))->toHaveCount(1);
});
