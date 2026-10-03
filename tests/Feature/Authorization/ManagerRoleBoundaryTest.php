<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Http\Middleware\PermissionMiddleware;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cakupan: batas kewenangan role manager dan jaminan super-admin.
 *
 * Konteks: role manager sebelumnya hanya ada di database produksi (dibuat manual,
 * 70 izin) dan tidak tercatat di kode. Setelah didaftarkan ke RolePermissionSeeder,
 * manager sengaja tidak diberi permissions.* maupun *.delete pada data produksi.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('mendaftarkan role manager di RoleEnum', function () {
    expect(RoleEnum::MANAGER->value)->toBe('manager');
});

it('membuat role manager saat seeder dijalankan', function () {
    expect(Role::where('name', 'manager')->exists())->toBeTrue();
});

it('tidak memberi manager izin mengelola definisi permission', function () {
    $manager = Role::where('name', 'manager')->first();
    $perms = $manager->permissions->pluck('name');

    expect($perms)->not->toContain(PermissionEnum::PERMISSIONS_READ->value)
        ->and($perms)->not->toContain(PermissionEnum::PERMISSIONS_CREATE->value)
        ->and($perms)->not->toContain(PermissionEnum::PERMISSIONS_UPDATE->value)
        ->and($perms)->not->toContain(PermissionEnum::PERMISSIONS_DELETE->value);
});

it('tidak memberi manager izin hapus data produksi', function () {
    $manager = Role::where('name', 'manager')->first();
    $perms = $manager->permissions->pluck('name');

    foreach ([
        PermissionEnum::DONATUR_DELETE->value,
        PermissionEnum::SHIPMENTS_DELETE->value,
        PermissionEnum::MUSHAF_REQUESTS_DELETE->value,
        PermissionEnum::CERTIFICATES_DELETE->value,
        PermissionEnum::WAKAF_BATCH_DELETE->value,
        PermissionEnum::TEMPLATES_DELETE->value,
    ] as $forbidden) {
        expect($perms)->not->toContain($forbidden);
    }
});

it('tetap memberi manager wewenang baca dan ubah', function () {
    $manager = Role::where('name', 'manager')->first();
    $perms = $manager->permissions->pluck('name');

    foreach ([
        PermissionEnum::DASHBOARD_VIEW->value,
        PermissionEnum::SHIPMENTS_READ->value,
        PermissionEnum::SHIPMENTS_UPDATE->value,
        PermissionEnum::MUSHAF_REQUESTS_APPROVE->value,
    ] as $expected) {
        expect($perms)->toContain($expected);
    }

    // certificates.* dan templates.* sengaja DICABUT dari manager: sertifikat
    // adalah dokumen pertanggungjawaban wakaf dan bukan bagian alur kerja manager
    // distribusi. Dulu tes ini justru menuntut manager memilikinya.
    foreach ([
        PermissionEnum::CERTIFICATES_GENERATE->value,
        PermissionEnum::CERTIFICATES_READ->value,
        PermissionEnum::TEMPLATES_READ->value,
    ] as $revoked) {
        expect($perms)->not->toContain($revoked);
    }

    // donatur.* sengaja DICABUT dari manager: pengelolaan data donatur tetap milik
    // customer-service saja. Dulu tes ini menuntut manager memilikinya.
    foreach ([
        PermissionEnum::DONATUR_READ->value,
        PermissionEnum::DONATUR_UPDATE->value,
        PermissionEnum::DONATUR_CREATE->value,
    ] as $revoked) {
        expect($perms)->not->toContain($revoked);
    }
});

it('memberi manager izin lebih banyak daripada supervisor', function () {
    $manager = Role::where('name', 'manager')->first()->permissions->count();
    $supervisor = Role::where('name', 'supervisor')->first()->permissions->count();

    expect($manager)->toBeGreaterThan($supervisor);
});

it('menolak manager membuka halaman manajemen user dan role', function () {
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole('manager');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($manager)->get('/admin/users')->assertForbidden();
    $this->actingAs($manager)->get('/admin/roles')->assertForbidden();
});

it('menolak manager membuka halaman permissions', function () {
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole('manager');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($manager)->get('/admin/permissions')->assertForbidden();
});

it('menolak manager membuka halaman sertifikat', function () {
    // Sertifikat adalah dokumen pertanggungjawaban wakaf; pengelolaannya bukan
    // bagian alur kerja manager distribusi (permintaan -> pengiriman -> pantau
    // gudang). Dulu manager memegang certificates.read sehingga menu "Sertifikat"
    // muncul di sidebar-nya padahal tidak seharusnya ada.
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole(RoleEnum::MANAGER->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($manager)->get('/admin/certificates')->assertForbidden();
    $this->actingAs($manager)->get('/admin/certificate-templates')->assertForbidden();
});

it('tidak memberi manager satu pun izin sertifikat maupun template', function () {
    // Memastikan MENU-nya juga hilang: AdminLayout memfilter menu berdasarkan
    // izin, jadi mencabut certificates.read sekaligus menghilangkan grup
    // "Sertifikat" dari sidebar — bukan sekadar menolak URL-nya.
    $manager = Role::where('name', RoleEnum::MANAGER->value)->first();
    $perms = $manager->permissions->pluck('name');

    foreach ([
        PermissionEnum::CERTIFICATES_READ->value,
        PermissionEnum::CERTIFICATES_CREATE->value,
        PermissionEnum::CERTIFICATES_UPDATE->value,
        PermissionEnum::CERTIFICATES_GENERATE->value,
        PermissionEnum::CERTIFICATES_DOWNLOAD->value,
        PermissionEnum::TEMPLATES_READ->value,
        PermissionEnum::TEMPLATES_CREATE->value,
        PermissionEnum::TEMPLATES_UPDATE->value,
    ] as $dicabut) {
        expect($perms)->not->toContain($dicabut);
    }
});

it('menolak manager membuka halaman donatur, tetapi mengizinkan mushaf request', function () {
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole('manager');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Pengelolaan donatur tetap milik customer-service; manager tidak lagi punya
    // donatur.* sejak pencabutan izin tersebut.
    $this->actingAs($manager)->get('/admin/donatur')->assertForbidden();
    $this->actingAs($manager)->get('/admin/mushaf-requests')->assertSuccessful();
});

it('super-admin selalu lolos pemeriksaan izin walau permission belum di-seed', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole(RoleEnum::SUPER_ADMIN->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Permission yang sama sekali belum dibuat di tabel permissions
    expect($admin->can('permission.yang.belum.pernah.dibuat'))->toBeTrue();
});

it('super-admin lolos middleware permission walau izin belum di-seed', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole(RoleEnum::SUPER_ADMIN->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($admin);

    // Middleware permission memakai hasAnyPermission() yang tidak melewati
    // Gate::before, jadi super-admin butuh cabang khusus agar tidak terkunci.
    $middleware = app(PermissionMiddleware::class);
    $request = Request::create('/admin/donatur', 'GET');

    $reached = false;
    $middleware->handle($request, function () use (&$reached) {
        $reached = true;

        return response('ok');
    }, 'izin.yang.belum.di-seed');

    expect($reached)->toBeTrue();
});

it('super-admin diberi seluruh izin oleh seeder, termasuk operasional gudang', function () {
    // Dulu tes ini menuntut yang sebaliknya ("super-admin tidak diberi izin
    // operasional gudang"), sisa dari niat "pengawas strategis" yang tidak pernah
    // benar-benar ditegakkan: Gate::before dan PermissionMiddleware sama-sama
    // meloloskan super-admin, sementara sidebar cek izin mentah — sehingga menunya
    // hilang padahal halamannya bisa dibuka. Sekarang super-admin = akses penuh,
    // satu aturan di semua lapisan.
    $admin = Role::where('name', RoleEnum::SUPER_ADMIN->value)->first();
    $perms = $admin->permissions->pluck('name');

    expect($perms->count())->toBe(Permission::count());

    foreach ([
        PermissionEnum::WAREHOUSE_PACKING_SCAN->value,
        PermissionEnum::WAREHOUSE_PACKING_VIEW->value,
        PermissionEnum::WAREHOUSE_TASKS_VIEW->value,
        PermissionEnum::WAREHOUSE_BOXES_SEAL->value,
    ] as $warehousePermission) {
        expect($perms)->toContain($warehousePermission);
    }
});

it('Gate before tidak memberi keleluasaan pada role non super-admin', function () {
    $courier = User::factory()->create(['is_active' => true]);
    $courier->assignRole(RoleEnum::COURIER->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($courier->can('permission.yang.belum.pernah.dibuat'))->toBeFalse();
});

it('memberi manager nama peran "Manager Distribusi", bukan "Manager"', function () {
    // Display name sebelumnya "Manager", kembar dengan supervisor yang bernama
    // "Supervisor Gudang" dan warehouse yang bernama "Staff Gudang" — tidak jelas
    // siapa mengelola apa. Nama ini murni kosmetik: tidak ada kode yang
    // membandingkan display_name, jadi aman diubah tanpa menyentuh izin.
    $manager = Role::where('name', RoleEnum::MANAGER->value)->first();

    expect($manager->display_name)->toBe('Manager Distribusi');
});

it('mencabut izin operasional gudang dari manager, tetapi menyisakan izin memantau', function () {
    // Manager dulu memegang SELURUH 15 izin warehouse.*, persis sama dengan Staff
    // Gudang: bisa mengemas, menyegel, DAN membongkar kerdus tersegel. Perannya
    // mengawasi distribusi, bukan mengerjakan operasinya — jadi izin yang mengubah
    // keadaan fisik dicabut.
    $manager = Role::where('name', RoleEnum::MANAGER->value)->first();
    $perms = $manager->permissions->pluck('name');

    // Operasional: mengubah keadaan fisik.
    foreach ([
        PermissionEnum::WAREHOUSE_PACKING_SCAN->value,
        PermissionEnum::WAREHOUSE_PACKING_SEAL->value,
        PermissionEnum::WAREHOUSE_BOXES_SEAL->value,
        PermissionEnum::WAREHOUSE_BOX_UPDATE_ANY->value,
        PermissionEnum::WAREHOUSE_BOX_UPDATE_SEALED->value,
        PermissionEnum::WAREHOUSE_TASKS_UPDATE->value,
        PermissionEnum::WAREHOUSE_QR_GENERATE->value,
        PermissionEnum::WAREHOUSE_QR_BULK_GENERATE->value,
        PermissionEnum::WAREHOUSE_QR_SCAN->value,
    ] as $operational) {
        expect($perms)->not->toContain($operational);
    }

    // Memantau: tetap dimiliki supaya halaman gudang masih terbuka.
    foreach ([
        PermissionEnum::WAREHOUSE_DASHBOARD->value,
        PermissionEnum::WAREHOUSE_PACKING_VIEW->value,
        PermissionEnum::WAREHOUSE_BOXES_VIEW->value,
        PermissionEnum::WAREHOUSE_TASKS_VIEW->value,
        PermissionEnum::WAREHOUSE_PERFORMANCE_VIEW->value,
        PermissionEnum::WAREHOUSE_QR_VERIFY->value,
    ] as $readOnly) {
        expect($perms)->toContain($readOnly);
    }
});

it('menolak manager memindai dan menyegel kerdus, tetapi tetap mengizinkan memantau', function () {
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole(RoleEnum::MANAGER->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Tidak boleh lagi mengubah keadaan fisik kerdus.
    expect($manager->can(PermissionEnum::WAREHOUSE_PACKING_SCAN->value))->toBeFalse();
    expect($manager->can(PermissionEnum::WAREHOUSE_PACKING_SEAL->value))->toBeFalse();
    expect($manager->can(PermissionEnum::WAREHOUSE_BOXES_SEAL->value))->toBeFalse();
    expect($manager->can(PermissionEnum::WAREHOUSE_BOX_UPDATE_SEALED->value))->toBeFalse();

    // Halaman gudang tetap terbuka, hanya untuk melihat.
    $this->actingAs($manager)->get('/admin/warehouse')->assertSuccessful();
    $this->actingAs($manager)->get('/admin/warehouse/packing')->assertSuccessful();
    $this->actingAs($manager)->get('/admin/box-tracking')->assertSuccessful();
});

it('menolak manager mengirim pemindaian ke endpoint packing', function () {
    // Penjaga sebenarnya: walau tombolnya ditekan atau permintaan dikirim manual,
    // endpoint harus menolak.
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole(RoleEnum::MANAGER->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($manager)
        ->postJson('/admin/warehouse/packing/scan', ['qr_data' => 'EQ-2026-00001'])
        ->assertForbidden();
});
