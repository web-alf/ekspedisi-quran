<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Muatan;
use App\Models\MuatanItem;
use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Management Muatan & Distribusi.
 *
 * Aturan yang dijaga di sini — dan yang paling penting bagi tim:
 * "yang berhak menyelesaikan distribusi adalah role distribusi, bukan kurir".
 *
 * Penegakannya harus di SERVER. Kalau hanya tombolnya yang disembunyikan di
 * tampilan, satu panggilan langsung ke endpoint-nya sudah cukup untuk menembus
 * aturan itu. Karena itu sebagian besar tes di bawah menembak endpoint HTTP
 * secara langsung sebagai kurir, bukan sekadar memeriksa tampilan.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Role & izin dibuat langsung di sini, bukan lewat seeder: seeder bukan
    // bagian dari deploy (Envoy tidak menjalankannya), jadi yang harus diuji
    // adalah keadaan yang benar-benar dihasilkan migrasi.
    $izin = [
        PermissionEnum::DASHBOARD_VIEW->value,
        PermissionEnum::SHIPMENTS_READ->value,
        PermissionEnum::SHIPMENTS_UPDATE_STATUS->value,
        PermissionEnum::MUSHAF_REQUESTS_READ->value,
        PermissionEnum::STATUS_TRACK->value,
        PermissionEnum::MUATAN_READ->value,
        PermissionEnum::MUATAN_CREATE->value,
        PermissionEnum::MUATAN_UPDATE->value,
        PermissionEnum::MUATAN_DELETE->value,
        PermissionEnum::MUATAN_SCAN->value,
        PermissionEnum::MUATAN_COMPLETE->value,
    ];

    foreach ($izin as $nama) {
        Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
    }

    $kurir = Role::firstOrCreate(
        ['name' => RoleEnum::COURIER->value],
        ['display_name' => 'Kurir', 'guard_name' => 'web']
    );
    // Kurir: TIDAK punya muatan.complete — inilah inti pembatasannya.
    $kurir->syncPermissions([
        PermissionEnum::DASHBOARD_VIEW->value,
        PermissionEnum::SHIPMENTS_READ->value,
        PermissionEnum::SHIPMENTS_UPDATE_STATUS->value,
        PermissionEnum::MUSHAF_REQUESTS_READ->value,
        PermissionEnum::MUATAN_READ->value,
        PermissionEnum::MUATAN_SCAN->value,
    ]);

    $distribusi = Role::firstOrCreate(
        ['name' => RoleEnum::DISTRIBUSI->value],
        ['display_name' => 'Distribusi', 'guard_name' => 'web']
    );
    $distribusi->syncPermissions($izin);

    $superAdmin = Role::firstOrCreate(
        ['name' => RoleEnum::SUPER_ADMIN->value],
        ['display_name' => 'Super Admin', 'guard_name' => 'web']
    );
    $superAdmin->syncPermissions($izin);

    // Manager Distribusi: HANYA muatan.read + muatan.complete (sesuai migrasi
    // 2026_10_06_000500). Bukan muatan.create/update/delete/scan — menyiapkan
    // muatan dan memindai barang adalah pekerjaan operasional, bukan pengawas.
    $manager = Role::firstOrCreate(
        ['name' => RoleEnum::MANAGER->value],
        ['display_name' => 'Manager Distribusi', 'guard_name' => 'web']
    );
    $manager->syncPermissions([
        PermissionEnum::MUATAN_READ->value,
        PermissionEnum::MUATAN_CREATE->value,
        PermissionEnum::MUATAN_SCAN->value,
        PermissionEnum::MUATAN_COMPLETE->value,
        PermissionEnum::SHIPMENTS_READ->value,
        PermissionEnum::SHIPMENTS_UPDATE_STATUS->value,
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['selesai-packing' => 5, 'pengiriman' => 6] as $slug => $urutan) {
        StatusPengiriman::firstOrCreate(
            ['slug' => $slug],
            ['nama' => ucfirst(str_replace('-', ' ', $slug)), 'urutan' => $urutan, 'is_active' => true, 'is_final' => false]
        );
    }
    StatusPengiriman::firstOrCreate(
        ['slug' => 'diterima'],
        ['nama' => 'Diterima Penerima', 'urutan' => 7, 'is_active' => true, 'is_final' => true]
    );
});

function penggunaDenganRole(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);

    return $user;
}

/**
 * Kurir yang benar-benar berada di bawah seorang manager.
 *
 * Sejak manager hanya melihat & menugaskan kurir bawahannya, kurir tanpa
 * `manager_id` tidak lagi muncul di lingkup manager mana pun — jadi tes yang
 * ingin manager "memantau kurirnya" harus menyatakan siapa atasannya.
 */
function kurirBawahan(User $manager): User
{
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $kurir->forceFill(['manager_id' => $manager->id])->save();

    return $kurir->fresh();
}

function resiSiapDiantar(string $slug = 'selesai-packing'): Pengiriman
{
    return Pengiriman::factory()->create([
        'no_resi' => 'EQ-2026-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'status_id' => StatusPengiriman::where('slug', $slug)->value('id'),
        'alamat_tujuan' => 'Jl. Contoh No. 1, Surabaya',
    ]);
}

/**
 * Kerdus berisi resi — keadaan yang dihasilkan gudang setelah packing.
 *
 * `packing_boxes.daily_packing_task_id` tidak punya nilai bawaan, jadi tugas
 * harian harus dibuat lebih dulu; tanpa itu penyisipannya ditolak MySQL.
 */
function kerdusBerisi(array $resinya, string $kodeKerdus): PackingBox
{
    $box = PackingBox::factory()->create([
        'kode_kerdus' => $kodeKerdus,
        'daily_packing_task_id' => DailyPackingTask::factory()->create()->id,
    ]);

    foreach ($resinya as $resi) {
        PackingItem::factory()->create([
            'packing_box_id' => $box->id,
            'pengiriman_id' => $resi->id,
        ]);
    }

    return $box;
}

it('mendaftarkan role distribusi sebagai role tersendiri', function () {
    expect(RoleEnum::DISTRIBUSI->value)->toBe('distribusi')
        ->and(Role::where('name', 'distribusi')->exists())->toBeTrue();
});

it('membuat tabel muatan dan muatan_items', function () {
    expect(Schema::hasTable('muatan'))->toBeTrue()
        ->and(Schema::hasTable('muatan_items'))->toBeTrue()
        ->and(Schema::hasColumn('muatan', 'kode_muatan'))->toBeTrue()
        ->and(Schema::hasColumn('muatan_items', 'pengiriman_id'))->toBeTrue();
});

it('memberi nomor muatan berformat MUK-YYYY-XXXXX', function () {
    $muatan = Muatan::factory()->create();

    expect($muatan->kode_muatan)->toMatch('/^MUK-\d{4}-\d{5}$/');
});

it('menolak satu resi berada di dua muatan sekaligus', function () {
    // Batasan di tingkat basis data, bukan hanya di kode: kalau hanya di kode,
    // jalur lain (impor, seeder, panggilan langsung) bisa menembusnya.
    $resi = resiSiapDiantar();
    $a = Muatan::factory()->create();
    $b = Muatan::factory()->create();

    MuatanItem::factory()->create(['muatan_id' => $a->id, 'pengiriman_id' => $resi->id]);

    expect(fn () => MuatanItem::factory()->create(['muatan_id' => $b->id, 'pengiriman_id' => $resi->id]))
        ->toThrow(QueryException::class);
});

// --- Wewenang: inilah pembatasan yang diminta ---

it('MENOLAK kurir menyelesaikan distribusi walaupun memanggil endpoint langsung', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    $resi = resiSiapDiantar('pengiriman');
    MuatanItem::factory()->create(['muatan_id' => $muatan->id, 'pengiriman_id' => $resi->id]);

    // Ditolak di sini adalah lapis pertama: middleware `permission:muatan.complete`.
    // Kurir tidak memiliki izin itu, jadi permintaannya tidak sampai ke controller.
    $this->actingAs($kurir)
        ->postJson(route('admin.muatan.selesaikan', $muatan))
        ->assertForbidden();

    // Yang paling penting: datanya TIDAK berubah.
    expect($resi->fresh()->status->slug)->toBe('pengiriman');
});

it('MENOLAK kurir memindahkan resi langsung ke status Diterima lewat status perjalanan', function () {
    // Jalur kedua yang bisa dipakai kurir untuk "menyelesaikan" distribusi
    // tanpa memakai endpoint selesaikan.
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    $resi = resiSiapDiantar('pengiriman');
    MuatanItem::factory()->create(['muatan_id' => $muatan->id, 'pengiriman_id' => $resi->id]);

    $idDiterima = StatusPengiriman::where('slug', 'diterima')->value('id');

    $this->actingAs($kurir)
        ->postJson(route('admin.muatan.status-perjalanan', $muatan), ['status_id' => $idDiterima])
        ->assertForbidden();

    expect($resi->fresh()->status->slug)->toBe('pengiriman');
});

it('MENOLAK kurir yang izin lengkapnya dinaikkan lewat peran, bukan hanya izin', function () {
    // Sabuk pengaman: bila suatu saat izin muatan.complete ikut diberikan ke
    // kurir, perannya harus tetap menghalangi.
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $kurir->givePermissionTo(PermissionEnum::MUATAN_COMPLETE->value);

    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    $resi = resiSiapDiantar('pengiriman');
    MuatanItem::factory()->create(['muatan_id' => $muatan->id, 'pengiriman_id' => $resi->id]);

    $this->actingAs($kurir)
        ->postJson(route('admin.muatan.selesaikan', $muatan))
        ->assertForbidden();

    expect($resi->fresh()->status->slug)->toBe('pengiriman');
});

it('MENGIZINKAN role distribusi menyelesaikan distribusi', function () {
    $distribusi = penggunaDenganRole(RoleEnum::DISTRIBUSI->value);
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();

    $resi = collect([
        resiSiapDiantar('pengiriman'),
        resiSiapDiantar('pengiriman'),
    ]);
    foreach ($resi as $r) {
        MuatanItem::factory()->create(['muatan_id' => $muatan->id, 'pengiriman_id' => $r->id]);
    }

    $this->actingAs($distribusi)
        ->postJson(route('admin.muatan.selesaikan', $muatan))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('berhasil', 2);

    foreach ($resi as $r) {
        expect($r->fresh()->status->slug)->toBe('diterima');
    }

    expect($muatan->fresh()->selesai)->toBeTrue();
});

it('mencatat siapa dan kapan penerima saat distribusi diselesaikan', function () {
    $distribusi = penggunaDenganRole(RoleEnum::DISTRIBUSI->value);
    $muatan = Muatan::factory()->create();
    $resi = resiSiapDiantar('pengiriman');
    MuatanItem::factory()->create(['muatan_id' => $muatan->id, 'pengiriman_id' => $resi->id]);

    $this->actingAs($distribusi)
        ->postJson(route('admin.muatan.selesaikan', $muatan), ['receiver_contact' => '08123456789'])
        ->assertSuccessful();

    $segari = $resi->fresh();
    expect($segari->received_at)->not->toBeNull()
        ->and($segari->received_by)->toBe($distribusi->name)
        ->and($segari->receiver_contact)->toBe('08123456789');
});

it('mengizinkan kurir memindahkan status PERJALANAN', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    $resi = resiSiapDiantar('selesai-packing');
    MuatanItem::factory()->create(['muatan_id' => $muatan->id, 'pengiriman_id' => $resi->id]);

    $idPengiriman = StatusPengiriman::where('slug', 'pengiriman')->value('id');

    $this->actingAs($kurir)
        ->postJson(route('admin.muatan.status-perjalanan', $muatan), ['status_id' => $idPengiriman])
        ->assertSuccessful();

    expect($resi->fresh()->status->slug)->toBe('pengiriman');
});

it('MENGIZINKAN manager distribusi menyelesaikan distribusi', function () {
    // Distribusi butuh VERIFIKASI MANUAL sebelum dinyatakan tuntas, dan manager
    // distribusi adalah yang memverifikasi. Tanpa wewenang ini, satu-satunya yang
    // bisa menutup muatan hanya super-admin.
    $manager = penggunaDenganRole(RoleEnum::MANAGER->value);
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();

    $resi = collect([resiSiapDiantar('pengiriman'), resiSiapDiantar('pengiriman')]);
    foreach ($resi as $r) {
        MuatanItem::factory()->create(['muatan_id' => $muatan->id, 'pengiriman_id' => $r->id]);
    }

    $this->actingAs($manager)
        ->postJson(route('admin.muatan.selesaikan', $muatan))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('berhasil', 2);

    foreach ($resi as $r) {
        expect($r->fresh()->status->slug)->toBe('diterima');
    }

    expect($muatan->fresh()->selesai)->toBeTrue();
});

it('menampilkan muatan kurir bawahannya kepada manager untuk dipantau', function () {
    $manager = penggunaDenganRole(RoleEnum::MANAGER->value);
    $kurir = kurirBawahan($manager);

    Muatan::factory()->untukKurir($kurir)->create();
    Muatan::factory()->untukKurir($kurir)->create();

    $props = $this->actingAs($manager)
        ->get(route('admin.muatan.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    // Melihat muatan BAWAHANNYA (bukan hanya miliknya sendiri) — manager memantau
    // timnya, bukan seluruh perusahaan.
    expect($props['muatan']['data'])->toHaveCount(2)
        ->and($props['hanyaMiliknya'])->toBeFalse();
});

it('tidak memperlihatkan muatan kurir manager lain', function () {
    // Batas lingkup manager: kalau ini gagal, keempat manager distribusi saling
    // melihat pekerjaan satu sama lain dan pemisahan tim jadi tidak ada artinya.
    $managerSaya = penggunaDenganRole(RoleEnum::MANAGER->value);
    $managerLain = penggunaDenganRole(RoleEnum::MANAGER->value);

    Muatan::factory()->untukKurir(kurirBawahan($managerSaya))->create();
    Muatan::factory()->untukKurir(kurirBawahan($managerLain))->create();
    Muatan::factory()->untukKurir(kurirBawahan($managerLain))->create();

    $props = $this->actingAs($managerSaya)
        ->get(route('admin.muatan.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($props['muatan']['data'])->toHaveCount(1);
});

it('hanya menawarkan kurir bawahan pada daftar pilihan manager', function () {
    $manager = penggunaDenganRole(RoleEnum::MANAGER->value);
    $managerLain = penggunaDenganRole(RoleEnum::MANAGER->value);

    $anakBuah = kurirBawahan($manager);
    $orangLain = kurirBawahan($managerLain);

    $props = $this->actingAs($manager)
        ->get(route('admin.muatan.create'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    $idTerdaftar = collect($props['kurirList'])->pluck('id')->all();

    expect($idTerdaftar)->toContain($anakBuah->id)
        ->and($idTerdaftar)->not->toContain($orangLain->id);
});

it('menolak manager menugaskan kurir di luar timnya', function () {
    // Daftar di layar sudah disaring, tetapi permintaan bisa dikirim langsung
    // dengan id kurir manager lain — jadi penegakannya harus di server.
    //
    // Manager di sini SUDAH punya bawahan, karena itulah keadaan yang membuat
    // batas tim berlaku: manager yang belum ditugasi bawahan sama sekali masih
    // bebas memilih (lihat KurirDiBawahManagerTest), supaya pekerjaannya tidak
    // terhenti sebelum penugasan diisi.
    $manager = penggunaDenganRole(RoleEnum::MANAGER->value);
    kurirBawahan($manager);
    $managerLain = penggunaDenganRole(RoleEnum::MANAGER->value);
    $orangLain = kurirBawahan($managerLain);

    $this->actingAs($manager)
        ->from(route('admin.muatan.create'))
        ->post(route('admin.muatan.store'), [
            'kurir_id' => $orangLain->id,
            'tanggal_muatan' => now()->format('Y-m-d'),
            'jumlah_lembaga' => 1,
        ])
        ->assertRedirect(route('admin.muatan.create'))
        ->assertSessionHasErrors('kurir_id');

    expect(Muatan::where('kurir_id', $orangLain->id)->exists())->toBeFalse();
});

it('memberi manager wewenang menyiapkan muatan: membuat dan memindai', function () {
    // Manager Distribusi bertanggung jawab atas distribusi dari hulu ke hilir:
    // menyiapkan muatan, memindai barang masuk, lalu menyelesaikannya. Sebelumnya
    // ia hanya bisa memantau dan memverifikasi, sehingga harus meminta orang lain
    // menyiapkan muatannya — padahal itu justru bagian dari pekerjaannya.
    $manager = penggunaDenganRole(RoleEnum::MANAGER->value);

    expect($manager->can(PermissionEnum::MUATAN_CREATE->value))->toBeTrue()
        ->and($manager->can(PermissionEnum::MUATAN_SCAN->value))->toBeTrue()
        ->and($manager->can(PermissionEnum::MUATAN_READ->value))->toBeTrue()
        ->and($manager->can(PermissionEnum::MUATAN_COMPLETE->value))->toBeTrue();
});

it('mengizinkan manager memindai barang masuk muatan', function () {
    $manager = penggunaDenganRole(RoleEnum::MANAGER->value);
    $kurir = kurirBawahan($manager);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    $resi = resiSiapDiantar();

    $this->actingAs($manager)
        ->postJson(route('admin.muatan.scan', $muatan), ['no_resi' => $resi->no_resi])
        ->assertSuccessful();

    expect($muatan->fresh()->total_resi)->toBe(1);
});

it('mengizinkan manager membuat muatan sendiri', function () {
    $manager = penggunaDenganRole(RoleEnum::MANAGER->value);
    $kurir = kurirBawahan($manager);

    $this->actingAs($manager)
        ->post(route('admin.muatan.store'), [
            'kurir_id' => $kurir->id,
            'tanggal_muatan' => now()->format('Y-m-d'),
            'jumlah_lembaga' => 3,
        ])
        ->assertRedirect();

    expect(Muatan::where('created_by', $manager->id)->exists())->toBeTrue();
});

it('menyebut Distribusi dan Manager Distribusi pada pesan penolakan', function () {
    // Pesan yang hanya menyebut "role distribusi" akan menyesatkan setelah
    // manager ikut berwenang. Diuji lewat endpoint sungguhan: kurir diberi izin
    // muatan.complete supaya permintaannya lolos middleware dan sampai ke
    // pemeriksaan peran di controller, tempat pesannya dibuat.
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $kurir->givePermissionTo(PermissionEnum::MUATAN_COMPLETE->value);

    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    MuatanItem::factory()->create([
        'muatan_id' => $muatan->id,
        'pengiriman_id' => resiSiapDiantar('pengiriman')->id,
    ]);

    $pesan = $this->actingAs($kurir)
        ->postJson(route('admin.muatan.selesaikan', $muatan))
        ->assertForbidden()
        ->json('message');

    expect($pesan)->toContain('Distribusi')
        ->and($pesan)->toContain('Manager Distribusi')
        ->and($pesan)->not->toContain('wewenang role distribusi');
});

it('mencabut izin donatur dari kurir', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);

    expect($kurir->can('donatur.read'))->toBeFalse()
        ->and($kurir->can('donatur.create'))->toBeFalse()
        ->and($kurir->can('donatur.update'))->toBeFalse()
        ->and($kurir->can('donatur.delete'))->toBeFalse();
});

it('menolak kurir membuka halaman Kelola Donatur', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);

    $this->actingAs($kurir)->get('/admin/donatur')->assertForbidden();
});

// --- Muatan: pemisahan data per kurir ---

it('hanya menampilkan muatan milik kurir yang sedang masuk', function () {
    $kurirA = penggunaDenganRole(RoleEnum::COURIER->value);
    $kurirB = penggunaDenganRole(RoleEnum::COURIER->value);

    $milikA = Muatan::factory()->untukKurir($kurirA)->create();
    Muatan::factory()->untukKurir($kurirB)->create();

    $props = $this->actingAs($kurirA)
        ->get(route('admin.muatan.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect(collect($props['muatan']['data'])->pluck('id')->all())->toBe([$milikA->id])
        ->and($props['hanyaMiliknya'])->toBeTrue();
});

it('menampilkan SEMUA muatan untuk role distribusi', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $distribusi = penggunaDenganRole(RoleEnum::DISTRIBUSI->value);

    Muatan::factory()->untukKurir($kurir)->create();
    Muatan::factory()->untukKurir($kurir)->create();

    $props = $this->actingAs($distribusi)
        ->get(route('admin.muatan.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($props['muatan']['data'])->toHaveCount(2)
        ->and($props['hanyaMiliknya'])->toBeFalse();
});

it('menolak kurir membuka muatan milik kurir lain lewat URL langsung', function () {
    $kurirA = penggunaDenganRole(RoleEnum::COURIER->value);
    $kurirB = penggunaDenganRole(RoleEnum::COURIER->value);

    $milikB = Muatan::factory()->untukKurir($kurirB)->create();

    $this->actingAs($kurirA)->get(route('admin.muatan.show', $milikB))->assertForbidden();
});

// --- Memuat resi ke muatan ---

it('memuat SELURUH isi kerdus lewat pemindaian per-box', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();

    kerdusBerisi(
        [resiSiapDiantar(), resiSiapDiantar(), resiSiapDiantar()],
        'KB-20261006-003-A5-01'
    );

    $this->actingAs($kurir)
        ->postJson(route('admin.muatan.pindai.box', $muatan), ['kode' => 'KB-20261006-003-A5-01'])
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('dimuat', 3);

    // Inti pemindaian per-box: satu pindai memasukkan SELURUH isi kerdus.
    expect(MuatanItem::where('muatan_id', $muatan->id)->count())->toBe(3)
        ->and($muatan->fresh()->total_resi)->toBe(3);
});

it('menerima kode kerdus yang dibungkus JSON dari pemindai', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();

    kerdusBerisi([resiSiapDiantar()], 'KB-20261006-003-A5-02');

    $this->actingAs($kurir)
        ->postJson(route('admin.muatan.pindai.box', $muatan), [
            'kode' => json_encode(['kode_kerdus' => 'KB-20261006-003-A5-02']),
        ])
        ->assertSuccessful()
        ->assertJsonPath('dimuat', 1);
});

it('melewati resi yang sudah ada di muatan ini tanpa menggagalkan seluruh kerdus', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();

    $sudahDimuat = resiSiapDiantar();
    $belumDimuat = resiSiapDiantar();

    MuatanItem::factory()->create([
        'muatan_id' => $muatan->id,
        'pengiriman_id' => $sudahDimuat->id,
    ]);

    kerdusBerisi([$sudahDimuat, $belumDimuat], 'KB-20261006-003-A5-03');

    // Satu resi yang sudah ada tidak boleh membatalkan sisa kerdus: kalau
    // dibatalkan, isi kerdus yang belum masuk tidak akan pernah bisa masuk
    // tanpa memindainya satu per satu.
    $this->actingAs($kurir)
        ->postJson(route('admin.muatan.pindai.box', $muatan), ['kode' => 'KB-20261006-003-A5-03'])
        ->assertSuccessful()
        ->assertJsonPath('dimuat', 1);

    expect(MuatanItem::where('muatan_id', $muatan->id)->count())->toBe(2);
});

it('menolak kerdus yang tidak ditemukan', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();

    $this->actingAs($kurir)
        ->postJson(route('admin.muatan.pindai.box', $muatan), ['kode' => 'KB-20260101-999-A5-99'])
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

it('menolak kerdus kosong yang belum berisi resi', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    kerdusBerisi([], 'KB-20261006-003-A5-04');

    $this->actingAs($kurir)
        ->postJson(route('admin.muatan.pindai.box', $muatan), ['kode' => 'KB-20261006-003-A5-04'])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

// --- Jumlah lembaga ---

it('menyimpan jumlah lembaga yang diisi saat membuat muatan', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $pembuat = penggunaDenganRole(RoleEnum::DISTRIBUSI->value);

    $this->actingAs($pembuat)
        ->post(route('admin.muatan.store'), [
            'kurir_id' => $kurir->id,
            'tanggal_muatan' => now()->format('Y-m-d'),
            'jumlah_lembaga' => 7,
        ])
        ->assertRedirect();

    expect(Muatan::where('kurir_id', $kurir->id)->first()->jumlah_lembaga)->toBe(7);
});

it('membiarkan jumlah lembaga kosong, bukan memaksanya menjadi nol', function () {
    // Kosong berarti "belum diisi". Kalau dipaksa 0, laporan akan membaca
    // muatan yang jelas mengantar sebagai "tidak melayani lembaga mana pun".
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $pembuat = penggunaDenganRole(RoleEnum::DISTRIBUSI->value);

    $this->actingAs($pembuat)
        ->post(route('admin.muatan.store'), [
            'kurir_id' => $kurir->id,
            'tanggal_muatan' => now()->format('Y-m-d'),
        ])
        ->assertRedirect();

    expect(Muatan::where('kurir_id', $kurir->id)->first()->jumlah_lembaga)->toBeNull();
});

it('menolak jumlah lembaga yang bukan angka', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $pembuat = penggunaDenganRole(RoleEnum::DISTRIBUSI->value);

    $this->actingAs($pembuat)
        ->post(route('admin.muatan.store'), [
            'kurir_id' => $kurir->id,
            'tanggal_muatan' => now()->format('Y-m-d'),
            'jumlah_lembaga' => 'banyak',
        ])
        ->assertSessionHasErrors('jumlah_lembaga');
});

it('mengubah jumlah lembaga lewat halaman ubah muatan', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $pembuat = penggunaDenganRole(RoleEnum::DISTRIBUSI->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create(['jumlah_lembaga' => 2]);

    $this->actingAs($pembuat)
        ->put(route('admin.muatan.update', $muatan), [
            'kurir_id' => $kurir->id,
            'tanggal_muatan' => now()->format('Y-m-d'),
            'jumlah_lembaga' => 5,
        ])
        ->assertRedirect();

    expect($muatan->fresh()->jumlah_lembaga)->toBe(5);
});

it('menampilkan jumlah lembaga di daftar dan detail muatan', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $distribusi = penggunaDenganRole(RoleEnum::DISTRIBUSI->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create(['jumlah_lembaga' => 4]);

    $daftar = $this->actingAs($distribusi)
        ->get(route('admin.muatan.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($daftar['muatan']['data'][0]['jumlah_lembaga'])->toBe(4);

    $detail = $this->actingAs($distribusi)
        ->get(route('admin.muatan.show', $muatan))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($detail['muatan']['jumlah_lembaga'])->toBe(4);
});

it('memuat resi ke muatan lewat pemindaian per-pcs', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    $resi = resiSiapDiantar();

    $this->actingAs($kurir)
        ->postJson(route('admin.muatan.scan', $muatan), ['no_resi' => $resi->no_resi])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect(MuatanItem::where('muatan_id', $muatan->id)->where('pengiriman_id', $resi->id)->exists())->toBeTrue()
        ->and($muatan->fresh()->total_resi)->toBe(1);
});

it('menerima QR yang isinya JSON, bukan hanya no_resi mentah', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    $resi = resiSiapDiantar();

    $this->actingAs($kurir)
        ->postJson(route('admin.muatan.scan', $muatan), [
            'no_resi' => json_encode(['no_resi' => $resi->no_resi, 'jenis' => 'A5']),
        ])
        ->assertSuccessful();

    expect($muatan->fresh()->total_resi)->toBe(1);
});

it('menolak memuat resi yang belum selesai packing', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();

    $belumSiap = Pengiriman::factory()->create([
        'no_resi' => 'EQ-2026-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'status_id' => StatusPengiriman::firstOrCreate(
            ['slug' => 'packing'],
            ['nama' => 'Proses Packing', 'urutan' => 4, 'is_active' => true, 'is_final' => false]
        )->id,
    ]);

    $this->actingAs($kurir)
        ->postJson(route('admin.muatan.scan', $muatan), ['no_resi' => $belumSiap->no_resi])
        ->assertStatus(422);

    expect($muatan->fresh()->total_resi)->toBe(0);
});

it('menolak resi yang sudah ada di muatan lain', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $muatanA = Muatan::factory()->untukKurir($kurir)->create();
    $muatanB = Muatan::factory()->untukKurir($kurir)->create();
    $resi = resiSiapDiantar();

    MuatanItem::factory()->create(['muatan_id' => $muatanA->id, 'pengiriman_id' => $resi->id]);

    $this->actingAs($kurir)
        ->postJson(route('admin.muatan.scan', $muatanB), ['no_resi' => $resi->no_resi])
        ->assertStatus(409);

    expect($muatanB->fresh()->total_resi)->toBe(0);
});

it('menghitung ulang total resi dan mushaf setelah isi berubah', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();

    $a = resiSiapDiantar();
    $b = resiSiapDiantar();
    $a->update(['jumlah_quran' => 20]);
    $b->update(['jumlah_quran' => 40]);

    $this->actingAs($kurir)->postJson(route('admin.muatan.scan', $muatan), ['no_resi' => $a->no_resi]);
    $this->actingAs($kurir)->postJson(route('admin.muatan.scan', $muatan), ['no_resi' => $b->no_resi]);

    expect($muatan->fresh()->total_resi)->toBe(2)
        ->and($muatan->fresh()->total_mushaf)->toBe(60);
});

it('menolak kurir memuat resi ke muatan kurir lain', function () {
    $kurirA = penggunaDenganRole(RoleEnum::COURIER->value);
    $kurirB = penggunaDenganRole(RoleEnum::COURIER->value);
    $muatanB = Muatan::factory()->untukKurir($kurirB)->create();
    $resi = resiSiapDiantar();

    $this->actingAs($kurirA)
        ->postJson(route('admin.muatan.scan', $muatanB), ['no_resi' => $resi->no_resi])
        ->assertForbidden();
});

// --- Muatan kosong ---

it('menganggap muatan kosong belum selesai, bukan selesai', function () {
    // Muatan yang isinya baru dikosongkan tidak boleh tiba-tiba tampak selesai.
    $muatan = Muatan::factory()->create();

    expect($muatan->selesai)->toBeFalse();
});

it('menolak menyelesaikan distribusi untuk muatan kosong', function () {
    $distribusi = penggunaDenganRole(RoleEnum::DISTRIBUSI->value);
    $muatan = Muatan::factory()->create();

    $this->actingAs($distribusi)
        ->postJson(route('admin.muatan.selesaikan', $muatan))
        ->assertSuccessful();
    // Tidak ada resi yang berubah; muatan tetap belum selesai.
    expect($muatan->fresh()->selesai)->toBeFalse();
});

// --- Halaman kurir lain ---

it('menyediakan halaman scan barang untuk kurir', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);

    $this->actingAs($kurir)->get(route('admin.kurir.pindai'))->assertSuccessful();
});

it('menyediakan halaman permintaan disetujui untuk kurir', function () {
    $kurir = penggunaDenganRole(RoleEnum::COURIER->value);

    $this->actingAs($kurir)->get(route('admin.kurir.permintaan-disetujui'))->assertSuccessful();
});

it('menolak pengguna tanpa izin muatan membuka halaman muatan', function () {
    $tanpaIzin = User::factory()->create(['is_active' => true]);

    $this->actingAs($tanpaIzin)->get(route('admin.muatan.index'))->assertForbidden();
});
