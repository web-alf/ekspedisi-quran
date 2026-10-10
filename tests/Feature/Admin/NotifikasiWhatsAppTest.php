<?php

use App\Jobs\WhatsApp\KirimWhatsAppJob;
use App\Models\WhatsAppNotification;
use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\StarSenderClient;
use App\Services\WhatsApp\WhatsAppNotifier;
use App\Services\WhatsApp\WhatsAppRateLimiter;
use App\Services\WhatsApp\WhatsAppTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Notifikasi WhatsApp.
 *
 * Yang diuji di sini bukan "ada kelasnya", tapi tiga sifat yang menentukan
 * fiturnya berguna atau berbahaya:
 *
 *   1. Notifikasi TIDAK PERNAH membuat alur utama gagal — penyedia yang mati
 *      hanya menghasilkan baris antrean berstatus gagal.
 *   2. Idempoten — peristiwa yang sama tidak menghasilkan pesan kedua.
 *   3. Tidak ada percobaan ulang otomatis setelah penyedia MENOLAK, karena
 *      StarSender tidak punya kunci idempotensi: percobaan ulang berisiko
 *      mengirim pesan kedua ke orang yang sama.
 */

/** Nama helper sengaja diberi awalan `wa` — Pest memuat semua berkas uji sekaligus. */
function waSetting(array $atribut = []): WhatsAppSetting
{
    return WhatsAppSetting::factory()->create($atribut);
}

function waTemplate(string $nama = 'sertifikat_siap', array $atribut = []): WhatsAppTemplate
{
    return WhatsAppTemplate::factory()->create(array_merge([
        'name' => $nama,
        'content' => 'Halo {nama}, batch {batch} selesai.',
        'variables' => ['nama', 'batch'],
    ], $atribut));
}

// ---------------------------------------------------------------------------
// Pengaturan & keamanan kunci API
// ---------------------------------------------------------------------------

it('menyimpan kunci API terenkripsi dan tidak membocorkannya saat diserialkan', function () {
    waSetting(['api_key' => 'rahasia-starsender-123']);

    $mentah = DB::table('whatsapp_settings')->value('api_key');

    expect($mentah)->not->toBe('rahasia-starsender-123')
        ->and($mentah)->toContain('eyJ')  // payload terenkripsi Laravel
        ->and(WhatsAppSetting::active()->api_key)->toBe('rahasia-starsender-123')
        ->and(WhatsAppSetting::active()->toArray())->not->toHaveKey('api_key');
});

it('menyatakan belum siap kirim bila kunci API kosong atau saklarnya mati', function () {
    expect(waSetting(['api_key' => null])->siapKirim())->toBeFalse()
        ->and(waSetting(['is_active' => false])->siapKirim())->toBeFalse()
        ->and(waSetting()->siapKirim())->toBeTrue();
});

it('menghitung jam tenang yang melewati tengah malam dengan benar', function () {
    $setting = waSetting(['quiet_hours_start' => '22:00', 'quiet_hours_end' => '06:00']);

    expect($setting->sedangJamTenang(new DateTime('2026-10-10 23:30:00')))->toBeTrue()
        ->and($setting->sedangJamTenang(new DateTime('2026-10-10 03:00:00')))->toBeTrue()
        ->and($setting->sedangJamTenang(new DateTime('2026-10-10 12:00:00')))->toBeFalse();

    $siang = waSetting(['quiet_hours_start' => '12:00', 'quiet_hours_end' => '13:00']);

    expect($siang->sedangJamTenang(new DateTime('2026-10-10 12:30:00')))->toBeTrue()
        ->and($siang->sedangJamTenang(new DateTime('2026-10-10 23:30:00')))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Penyusunan pesan
// ---------------------------------------------------------------------------

it('mengisi placeholder dan membuang sisa placeholder yang tidak terisi', function () {
    $penyusun = app(WhatsAppTemplateService::class);

    $terisi = waTemplate('uji_isi', ['content' => 'Halo {nama}, resi {resi}.']);
    expect($penyusun->susun($terisi, ['nama' => 'Ahmad', 'resi' => 'EQ-1']))
        ->toBe('Halo Ahmad, resi EQ-1.');

    // Placeholder yang tidak ada datanya JANGAN terlihat wakif: pesan berisi
    // "{link}" terbaca seperti sistem rusak.
    $kurang = waTemplate('uji_kurang', ['content' => 'Halo {nama}, lihat {link} ya.']);
    expect($penyusun->susun($kurang, ['nama' => 'Ahmad']))
        ->toBe('Halo Ahmad, lihat  ya.');
});

// ---------------------------------------------------------------------------
// Antrean
// ---------------------------------------------------------------------------

it('mengantrekan pesan dan menjadwalkan job saat pengaturannya siap', function () {
    Queue::fake();
    waSetting();
    waTemplate();

    $notifikasi = app(WhatsAppNotifier::class)->antri(
        'sertifikat_siap',
        'sertifikat_uji_1',
        '08123456789',
        ['nama' => 'Ahmad', 'batch' => 'WB-1']
    );

    expect($notifikasi->status)->toBe('menunggu')
        ->and($notifikasi->body)->toBe('Halo Ahmad, batch WB-1 selesai.')
        ->and($notifikasi->attempts)->toBe(0);

    Queue::assertPushed(KirimWhatsAppJob::class, fn ($job) => $job->notificationId === $notifikasi->id);
});

it('tidak mengirim pesan kedua untuk peristiwa yang sama', function () {
    Queue::fake();
    waSetting();
    waTemplate();

    $notifier = app(WhatsAppNotifier::class);
    $pertama = $notifier->antri('sertifikat_siap', 'kunci_ganda', '08123456789', ['nama' => 'A', 'batch' => 'B']);
    $kedua = $notifier->antri('sertifikat_siap', 'kunci_ganda', '08123456789', ['nama' => 'A', 'batch' => 'B']);

    expect(WhatsAppNotification::query()->count())->toBe(1)
        ->and($kedua->id)->toBe($pertama->id);

    Queue::assertPushed(KirimWhatsAppJob::class, 1);
});

it('mencatat dilewati tanpa mengirim saat template dimatikan', function () {
    Queue::fake();
    waSetting();
    waTemplate('sertifikat_siap', ['is_active' => false]);

    $notifikasi = app(WhatsAppNotifier::class)->antri('sertifikat_siap', 'kunci_mati', '08123456789');

    expect($notifikasi->status)->toBe('dilewati')
        ->and($notifikasi->error_message)->toContain('dimatikan');

    Queue::assertNothingPushed();
});

it('mencatat dilewati saat nomor donatur belum ada', function () {
    Queue::fake();
    waSetting();
    waTemplate();

    $notifikasi = app(WhatsAppNotifier::class)->antri('sertifikat_siap', 'kunci_nomor', null);

    expect($notifikasi->status)->toBe('dilewati')
        ->and($notifikasi->error_message)->toContain('Nomor WhatsApp');

    Queue::assertNothingPushed();
});

it('mencatat dilewati saat notifikasi belum diaktifkan', function () {
    Queue::fake();
    waSetting(['is_active' => false]);
    waTemplate();

    $notifikasi = app(WhatsAppNotifier::class)->antri('sertifikat_siap', 'kunci_nonaktif', '08123456789');

    expect($notifikasi->status)->toBe('dilewati')
        ->and($notifikasi->error_message)->toContain('belum diaktifkan');

    Queue::assertNothingPushed();
});

it('tidak mencatat apa pun untuk peristiwa yang belum punya template', function () {
    Queue::fake();
    waSetting();

    $hasil = app(WhatsAppNotifier::class)->antri('peristiwa_asing', 'kunci_asing', '08123456789');

    expect($hasil)->toBeNull()
        ->and(WhatsAppNotification::query()->count())->toBe(0);
});

it('menahan pengiriman saat jam tenang tapi tetap mencatatnya sebagai menunggu', function () {
    Queue::fake();
    waSetting(['quiet_hours_start' => '00:00', 'quiet_hours_end' => '23:59']);
    waTemplate();

    $notifikasi = app(WhatsAppNotifier::class)->antri('sertifikat_siap', 'kunci_tenang', '08123456789');

    expect($notifikasi->status)->toBe('menunggu')
        ->and($notifikasi->error_message)->toBeNull();

    // Ditahan, bukan dibuang: perintah whatsapp:kirim-tertunda yang mengurasnya.
    Queue::assertNothingPushed();
});

// ---------------------------------------------------------------------------
// Pengiriman (job)
// ---------------------------------------------------------------------------

it('mengirim lewat StarSender dan menandai terkirim', function () {
    Http::fake(['api.starsender.online/*' => Http::response([
        'success' => true, 'data' => ['id' => 'MSG-77'], 'message' => 'Success sent message',
    ])]);

    waSetting(['delay_seconds' => 0]);
    $notifikasi = WhatsAppNotification::factory()->create(['recipient' => '08123456789']);

    (new KirimWhatsAppJob($notifikasi->id))->handle(app(WhatsAppRateLimiter::class));

    $notifikasi->refresh();

    expect($notifikasi->status)->toBe('terkirim')
        ->and($notifikasi->attempts)->toBe(1)
        ->and($notifikasi->provider_message_id)->toBe('MSG-77')
        ->and($notifikasi->sent_at)->not->toBeNull();

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/api/send')
            && $request->hasHeader('Authorization')
            && $request['messageType'] === 'text'
            && $request['to'] === '628123456789'
            && $request['body'] === 'Pesan uji.';
    });
});

it('menandai gagal dengan pesan penyedia dan TIDAK melempar exception', function () {
    Http::fake(['api.starsender.online/*' => Http::response([
        'success' => false, 'data' => [], 'message' => 'Device not connected',
    ], 200)]);

    waSetting();
    $notifikasi = WhatsAppNotification::factory()->create();

    // Tidak boleh melempar: kalau melempar, barisnya tetap 'menunggu' dan
    // pengguna hanya melihatnya sebagai job gagal yang tidak pernah dibuka.
    (new KirimWhatsAppJob($notifikasi->id))->handle(app(WhatsAppRateLimiter::class));

    $notifikasi->refresh();

    expect($notifikasi->status)->toBe('gagal')
        ->and($notifikasi->error_message)->toBe('Device not connected')
        ->and($notifikasi->sent_at)->toBeNull();
});

it('tetap mencatat gagal saat StarSender tidak bisa dihubungi sama sekali', function () {
    Http::fake(['api.starsender.online/*' => Http::response('', 500)]);

    waSetting();
    $notifikasi = WhatsAppNotification::factory()->create();

    (new KirimWhatsAppJob($notifikasi->id))->handle(app(WhatsAppRateLimiter::class));

    expect($notifikasi->fresh()->status)->toBe('gagal')
        ->and($notifikasi->fresh()->error_message)->not->toBeNull();
});

it('tidak mengirim ulang pesan yang sudah terkirim', function () {
    Http::fake();
    waSetting();
    $notifikasi = WhatsAppNotification::factory()->terkirim()->create();

    (new KirimWhatsAppJob($notifikasi->id))->handle(app(WhatsAppRateLimiter::class));

    expect($notifikasi->fresh()->attempts)->toBe(1);
    Http::assertNothingSent();
});

it('tidak mengirim apa pun bila pengaturan belum siap, dan menandainya dilewati', function () {
    Http::fake();
    waSetting(['api_key' => null]);
    $notifikasi = WhatsAppNotification::factory()->create();

    (new KirimWhatsAppJob($notifikasi->id))->handle(app(WhatsAppRateLimiter::class));

    expect($notifikasi->fresh()->status)->toBe('dilewati');
    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------
// Pembatas laju
// ---------------------------------------------------------------------------

it('berhenti mengizinkan kirim setelah batas per menit terlampaui', function () {
    Cache::flush();
    $pembatas = app(WhatsAppRateLimiter::class);

    expect($pembatas->bolehKirim(2))->toBeTrue();
    $pembatas->hitungKirim();
    expect($pembatas->bolehKirim(2))->toBeTrue();
    $pembatas->hitungKirim();
    expect($pembatas->bolehKirim(2))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Normalisasi nomor
// ---------------------------------------------------------------------------

it('menormalkan beragam penulisan nomor Indonesia sebelum mengirim', function () {
    Http::fake(['api.starsender.online/*' => Http::response(['success' => true, 'data' => [], 'message' => 'ok'])]);

    $setting = waSetting();
    $klien = StarSenderClient::dari($setting);

    foreach (['08123456789', '+628123456789', '628123456789', '0812-3456-789', '0812 3456 789'] as $nomor) {
        $klien->kirimTeks($nomor, 'uji');
    }

    $terkirim = [];
    Http::assertSent(function ($request) use (&$terkirim) {
        $terkirim[] = $request['to'];

        return true;
    });

    expect(array_unique($terkirim))->toBe(['628123456789']);
});

// ---------------------------------------------------------------------------
// Perintah artisan
// ---------------------------------------------------------------------------

it('menolak uji kirim bila kunci API belum diisi', function () {
    Http::fake();
    waSetting(['api_key' => null]);

    $this->artisan('whatsapp:uji-kirim', ['nomor' => '08123456789'])->assertFailed();

    Http::assertNothingSent();
});

it('mengirim pesan uji dan melaporkan berhasil', function () {
    Http::fake([
        '*/api/check-number' => Http::response(['success' => true, 'data' => ['status' => true], 'message' => 'Number registered']),
        '*/api/send' => Http::response(['success' => true, 'data' => [], 'message' => 'Success sent message']),
    ]);

    waSetting();

    $this->artisan('whatsapp:uji-kirim', ['nomor' => '08123456789'])->assertSuccessful();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send'));
});

it('tidak mengirim pesan uji ke nomor yang tidak terdaftar WhatsApp', function () {
    Http::fake([
        '*/api/check-number' => Http::response(['success' => true, 'data' => ['status' => false], 'message' => 'Number not registered']),
        '*/api/send' => Http::response(['success' => true, 'data' => [], 'message' => 'Success sent message']),
    ]);

    waSetting();

    $this->artisan('whatsapp:uji-kirim', ['nomor' => '08999999999'])->assertFailed();

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/send'));
});

it('menguras antrean tertahan hanya untuk baris yang sudah cukup tua', function () {
    Queue::fake();
    waSetting();

    $baru = WhatsAppNotification::factory()->create(['created_at' => now()->subMinutes(5)]);
    $tua = WhatsAppNotification::factory()->create(['created_at' => now()->subHours(2)]);
    WhatsAppNotification::factory()->terkirim()->create(['created_at' => now()->subHours(3)]);

    $this->artisan('whatsapp:kirim-tertunda')->assertSuccessful();

    Queue::assertPushed(KirimWhatsAppJob::class, fn ($job) => $job->notificationId === $tua->id);
    Queue::assertNotPushed(KirimWhatsAppJob::class, fn ($job) => $job->notificationId === $baru->id);
});
