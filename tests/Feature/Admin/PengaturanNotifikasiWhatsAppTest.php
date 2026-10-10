<?php

use App\Enums\RoleEnum;
use App\Models\User;
use App\Models\WhatsAppNotification;
use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTemplate;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Halaman pengaturan Notifikasi WhatsApp.
 *
 * Dua hal yang paling penting dijaga di sini:
 *   1. Kunci API bisa MASUK tapi tidak pernah bisa KELUAR — halaman tidak boleh
 *      mengirim balik nilainya, dan menyimpan pengaturan lain tidak boleh
 *      menghapusnya.
 *   2. Halaman ini hanya untuk yang berhak. Notifikasi berarti mengirim pesan ke
 *      donatur atas nama lembaga, jadi batas perannya harus benar.
 */
function waAdminSiapkan(): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    test()->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

function waAdminSuper(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::SUPER_ADMIN->value);

    return $user;
}

beforeEach(function () {
    waAdminSiapkan();
});

it('menampilkan halaman notifikasi WhatsApp untuk super-admin', function () {
    $pengaturan = WhatsAppSetting::factory()->create(['api_key' => 'kunci-rahasia']);
    WhatsAppTemplate::factory()->create(['name' => 'sertifikat_siap', 'title' => 'Sertifikat siap']);

    $this->actingAs(waAdminSuper())
        ->get('/admin/whatsapp')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/WhatsApp/Index')
            ->where('pengaturan.kunci_terisi', true)
            ->has('templates', 1)
            ->has('notifikasi.data')
            ->where('ringkasan.gagal', 0)
        );
});

it('tidak pernah mengirimkan nilai kunci API ke halaman', function () {
    WhatsAppSetting::factory()->create(['api_key' => 'kunci-rahasia-jangan-sampai-bocor']);

    $respons = $this->actingAs(waAdminSuper())->get('/admin/whatsapp');

    $respons->assertOk()
        ->assertInertia(fn ($page) => $page->missing('pengaturan.api_key')
            ->where('pengaturan.kunci_terisi', true)
        );

    // Pemeriksaan lapis kedua: nilainya tidak boleh ada di mana pun pada muatan
    // halaman, bukan hanya tidak ada di kunci yang diperiksa di atas.
    expect($respons->getContent())->not->toContain('kunci-rahasia-jangan-sampai-bocor');
});

it('menyimpan pengaturan tanpa menghapus kunci API yang sudah ada', function () {
    WhatsAppSetting::factory()->create(['api_key' => 'kunci-lama']);

    $this->actingAs(waAdminSuper())->put('/admin/whatsapp/pengaturan', [
        'base_url' => 'https://api.starsender.online',
        'sender_number' => '628111222333',
        'is_active' => true,
        'delay_seconds' => 5,
        'max_per_minute' => 30,
        'quiet_hours_start' => '22:00',
        'quiet_hours_end' => '06:00',
        'api_key' => '', // kosong = jangan ubah
    ])->assertRedirect();

    $pengaturan = WhatsAppSetting::active();

    expect($pengaturan->api_key)->toBe('kunci-lama')
        ->and($pengaturan->delay_seconds)->toBe(5)
        ->and($pengaturan->max_per_minute)->toBe(30)
        ->and($pengaturan->quiet_hours_start)->toBe('22:00:00')
        ->and($pengaturan->is_active)->toBeTrue();
});

it('mengganti kunci API bila kolomnya diisi', function () {
    WhatsAppSetting::factory()->create(['api_key' => 'kunci-lama']);

    $this->actingAs(waAdminSuper())->put('/admin/whatsapp/pengaturan', [
        'base_url' => 'https://api.starsender.online',
        'is_active' => true,
        'delay_seconds' => 0,
        'max_per_minute' => 20,
        'api_key' => 'kunci-baru',
    ])->assertRedirect();

    expect(WhatsAppSetting::active()->api_key)->toBe('kunci-baru');
});

it('menolak pengaturan yang tidak sah', function () {
    WhatsAppSetting::factory()->create();

    $this->actingAs(waAdminSuper())->put('/admin/whatsapp/pengaturan', [
        'base_url' => 'bukan-url',
        'delay_seconds' => -5,
        'max_per_minute' => 0,
        'quiet_hours_start' => '25:99',
    ])->assertSessionHasErrors(['base_url', 'delay_seconds', 'max_per_minute', 'quiet_hours_start']);
});

it('menyimpan perubahan template', function () {
    $template = WhatsAppTemplate::factory()->create(['name' => 'sertifikat_siap', 'title' => 'Lama']);

    $this->actingAs(waAdminSuper())->put("/admin/whatsapp/template/{$template->id}", [
        'title' => 'Baru',
        'content' => 'Halo {nama}, selamat.',
        'is_active' => false,
    ])->assertRedirect();

    expect($template->fresh()->title)->toBe('Baru')
        ->and($template->fresh()->is_active)->toBeFalse();
});

it('mengirim pesan uji lewat halaman', function () {
    Http::fake([
        '*/api/check-number' => Http::response(['success' => true, 'data' => ['status' => true], 'message' => 'ok']),
        '*/api/send' => Http::response(['success' => true, 'data' => [], 'message' => 'Success sent message']),
    ]);

    WhatsAppSetting::factory()->create(['api_key' => 'kunci-uji']);

    $this->actingAs(waAdminSuper())
        ->post('/admin/whatsapp/uji-kirim', ['nomor' => '08123456789'])
        ->assertRedirect()
        ->assertSessionHas('success');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send'));
});

it('menolak uji kirim saat kunci API belum ada', function () {
    Http::fake();
    WhatsAppSetting::factory()->create(['api_key' => null]);

    $this->actingAs(waAdminSuper())
        ->post('/admin/whatsapp/uji-kirim', ['nomor' => '08123456789'])
        ->assertRedirect()
        ->assertSessionHas('error');

    Http::assertNothingSent();
});

it('mengembalikan pesan gagal ke antrean saat dikirim ulang', function () {
    Queue::fake();
    WhatsAppSetting::factory()->create();

    $notifikasi = WhatsAppNotification::factory()->gagal()->create();

    $this->actingAs(waAdminSuper())
        ->post("/admin/whatsapp/notifikasi/{$notifikasi->id}/kirim-ulang")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($notifikasi->fresh()->status)->toBe('menunggu')
        ->and($notifikasi->fresh()->error_message)->toBeNull();
});

it('menolak mengirim ulang pesan yang sudah terkirim', function () {
    Queue::fake();
    WhatsAppSetting::factory()->create();

    $notifikasi = WhatsAppNotification::factory()->terkirim()->create();

    $this->actingAs(waAdminSuper())
        ->post("/admin/whatsapp/notifikasi/{$notifikasi->id}/kirim-ulang")
        ->assertRedirect()
        ->assertSessionHas('error');

    Queue::assertNothingPushed();
});

it('menolak akses bagi role yang tidak berhak', function () {
    foreach ([RoleEnum::WAREHOUSE->value, RoleEnum::CUSTOMER_SERVICE->value, RoleEnum::SUPERVISOR->value, RoleEnum::COURIER->value] as $namaPeran) {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($namaPeran);

        $this->actingAs($user)->get('/admin/whatsapp')->assertForbidden();
    }
});
