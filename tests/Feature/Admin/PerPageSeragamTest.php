<?php

use App\Enums\RoleEnum;
use App\Models\User;
use App\Support\PerPage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Cakupan: ukuran halaman (baris per halaman) SERAGAM di seluruh tabel admin.
 *
 * Dulu tiap controller mematok angkanya sendiri (10, 12, 15, 20, 25, 100, 500),
 * sehingga perilakunya berbeda-beda dan pengguna tidak bisa memilih. Sekarang
 * semuanya lewat App\Support\PerPage dengan daftar dan bawaan yang sama.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function superAdmin(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::SUPER_ADMIN->value);

    return $user;
}

it('menetapkan satu daftar ukuran halaman dan satu bawaan untuk seluruh aplikasi', function () {
    expect(PerPage::OPTIONS)->toBe([10, 20, 50, 100, 200])
        ->and(PerPage::DEFAULT)->toBe(20);
});

it('memaksa ukuran halaman cocok dengan daftar, apa pun yang diminta', function () {
    // Tanpa penjagaan ini, ?per_page=999999 menarik seluruh tabel sekaligus.
    foreach ([0, 1, 7, 13, 30, 999, 999999, -5] as $asal) {
        $req = Request::create('/x', 'GET', ['per_page' => $asal]);
        expect(PerPage::resolve($req))->toBe(20);
    }

    // Nilai yang sah diteruskan apa adanya.
    foreach (PerPage::OPTIONS as $sah) {
        $req = Request::create('/x', 'GET', ['per_page' => $sah]);
        expect(PerPage::resolve($req))->toBe($sah);
    }
});

it('memakai bawaan 20 saat ukuran halaman tidak diminta', function () {
    expect(PerPage::resolve(Request::create('/x', 'GET')))->toBe(20)
        ->and(PerPage::resolve(null))->toBe(20);
});

it('memakai ukuran halaman yang sama di semua tabel admin', function () {
    // Daftar ini SENGAJA lengkap: setiap halaman tabel harus menghormati
    // ?per_page. Kalau ada halaman baru yang memakai angka sendiri, tes ini gagal.
    $halaman = [
        '/admin/donatur' => 'Admin/Donatur/Index',
        '/admin/users' => 'Admin/Users/Index',
        '/admin/roles' => 'Admin/Roles/Index',
        '/admin/permissions' => 'Admin/Permissions/Index',
        '/admin/mushaf-requests' => 'Admin/MushafRequest/Index',
        '/admin/certificates' => 'Admin/Certificates/Index',
        '/admin/certificate-templates' => 'Admin/CertificateTemplates/Index',
        '/admin/box-tracking' => 'Admin/BoxTracking/Index',
        '/admin/testimonials' => 'Admin/Testimonials/Index',
        '/admin/videos' => 'Admin/Videos/Index',
        '/admin/galleries' => 'Admin/Galleries/Index',
        '/admin/faqs' => 'Admin/Faqs/Index',
        '/admin/settings/landing-content/general' => 'Admin/Settings/LandingContent/General',
        '/admin/settings/landing-content/landing' => 'Admin/Settings/LandingContent/Landing',
        '/admin/settings/landing-content/contact' => 'Admin/Settings/LandingContent/Contact',
        '/admin/settings/landing-content/social' => 'Admin/Settings/LandingContent/Social',
        '/admin/settings/landing-content/seo' => 'Admin/Settings/LandingContent/Seo',
        '/admin/settings/landing-content/legal' => 'Admin/Settings/LandingContent/Legal',
    ];

    $user = superAdmin();

    foreach ($halaman as $url => $komponen) {
        $this->actingAs($user)
            ->get($url.'?per_page=50')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component($komponen)
                ->where('perPage', 50)
            );
    }
});

it('menolak ukuran halaman di luar daftar pada setiap tabel admin', function () {
    $user = superAdmin();

    foreach (['/admin/donatur', '/admin/users', '/admin/roles', '/admin/faqs'] as $url) {
        $this->actingAs($user)
            ->get($url.'?per_page=999999')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('perPage', 20));
    }
});

it('mengirimkan daftar pilihan ke frontend supaya pemilihnya bisa dirender', function () {
    $this->actingAs(superAdmin())
        ->get('/admin/donatur')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('perPageOptions', [10, 20, 50, 100, 200])
        );
});

it('tidak menyisakan satu pun paginate dengan angka tetap di controller', function () {
    // Penjaga gaya: inilah yang dulu membuat 20 halaman berbeda perilaku.
    $pelanggar = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(app_path('Http/Controllers'))
    );

    foreach ($iterator as $berkas) {
        if ($berkas->getExtension() !== 'php') {
            continue;
        }

        if (preg_match('/paginate\(\s*\d+\s*\)/', file_get_contents($berkas->getPathname()))) {
            $pelanggar[] = str_replace(app_path().'/', '', $berkas->getPathname());
        }
    }

    expect($pelanggar)->toBe([]);
});
