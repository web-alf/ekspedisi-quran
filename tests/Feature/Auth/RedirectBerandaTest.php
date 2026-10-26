<?php

use App\Models\User;
use App\Support\BerandaPeran;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

// Tiga peran gudang — berandanya sama: dashboard gudang (/admin/warehouse),
// satu-satunya dashboard yang ada di menu sidebar mereka.
dataset('peran gudang', [
    'spv-gudang' => 'spv-gudang',
    'manager-repacking' => 'manager-repacking',
    'warehouse' => 'warehouse',
]);

function buatUserBeranda(string $peran): User
{
    $user = User::factory()->create();
    $user->assignRole($peran);

    return $user;
}

it('login peran gudang mendarat di dashboard gudang', function (string $peran) {
    $user = buatUserBeranda($peran);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/admin/warehouse');
})->with('peran gudang');

it('login SPV dengan url.intended dashboard umum tetap mendarat di dashboard gudang', function (string $intended) {
    $user = buatUserBeranda('spv-gudang');

    // Perjalanan yang dulu bikin bingung: bookmark/histori browser lama
    // /admin/dashboard dikunjungi sebagai guest -> Laravel menyimpannya sebagai
    // url.intended -> login -> mendarat di dashboard umum, padahal menu
    // Dashboard SPV adalah /admin/warehouse.
    $this->session(['url.intended' => $intended]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/admin/warehouse');
})->with([
    '/admin/dashboard' => '/admin/dashboard',
    '/dashboard' => '/dashboard',
    'URL absolut' => 'https://dash.ekspedisiquran.com/admin/dashboard',
    'pakai query string' => '/admin/dashboard?selamat=datang',
]);

it('login SPV dengan url.intended halaman kerja tetap kembali ke halaman itu', function () {
    $user = buatUserBeranda('spv-gudang');

    $this->session(['url.intended' => '/admin/pengiriman']);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/admin/pengiriman');
});

it('login peran non-gudang tetap ke /dashboard', function () {
    $user = buatUserBeranda('customer-service');

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');
});

it('peran gudang yang sudah login membuka /login dialihkan ke dashboard gudang', function (string $peran) {
    $user = buatUserBeranda($peran);

    $this->actingAs($user)->get('/login')->assertRedirect('/admin/warehouse');
})->with('peran gudang');

// Middleware guest harus benar-benar milik aplikasi (bootstrap/app.php), bukan
// bawaan Laravel — bawaan melempar ke '/' sehingga pengguna yang sudah login
// mendarat di landing page publik saat membuka /login.
it('peran non-gudang yang sudah login membuka /login dialihkan ke dashboard umum', function () {
    $user = buatUserBeranda('customer-service');

    $this->actingAs($user)->get('/login')->assertRedirect('/admin/dashboard');
});

it('tamu tetap bisa membuka halaman login', function () {
    $this->get('/login')->assertOk();
});

it('peran gudang membuka /admin/dashboard dialihkan ke dashboard gudang', function (string $peran) {
    $user = buatUserBeranda($peran);

    $this->actingAs($user)->get('/admin/dashboard')->assertRedirect('/admin/warehouse');
})->with('peran gudang');

it('peran gudang membuka /dashboard dialihkan ke dashboard gudang', function (string $peran) {
    $user = buatUserBeranda($peran);

    $this->actingAs($user)->get('/dashboard')->assertRedirect('/admin/warehouse');
})->with('peran gudang');

it('peran non-gudang tetap melihat dashboard umum', function () {
    $user = buatUserBeranda('super-admin');

    $this->actingAs($user)->get('/admin/dashboard')->assertOk();
});

it('mengenali URL dashboard umum dengan tepat', function (string $url, bool $hasil) {
    expect(BerandaPeran::adalahUrlDashboardUmum($url))->toBe($hasil);
})->with([
    '/admin/dashboard' => ['/admin/dashboard', true],
    '/dashboard' => ['/dashboard', true],
    'URL absolut' => ['https://dash.ekspedisiquran.com/admin/dashboard', true],
    'query string' => ['/admin/dashboard?tab=stats', true],
    'garis miring ekor' => ['/admin/dashboard/', true],
    'dashboard gudang' => ['/admin/warehouse', false],
    'packing' => ['/admin/warehouse/packing', false],
    'akar' => ['/', false],
    'URL kosong' => ['', false],
]);
