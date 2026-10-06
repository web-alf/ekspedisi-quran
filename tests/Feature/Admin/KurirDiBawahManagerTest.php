<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\Muatan;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * Kurir berada di bawah seorang manager distribusi.
 *
 * Sebelum ini hubungan itu hanya ada di luar sistem: keempat manager sama-sama
 * melihat semua muatan dan bisa menugaskan kurir mana pun, sehingga pemisahan
 * tim tidak punya wujud. Berkas ini mengunci wujud barunya — penanda
 * `manager_id` pada data pengguna, lingkup muatan yang mengikutinya, dan
 * penegakan di server atas semua jalur yang menyentuh muatan.
 *
 * Yang diuji lewat endpoint sungguhan, bukan lewat pemanggilan fungsi langsung,
 * supaya kebocoran jalur (mis. menebak ID muatan kurir lain) ikut tertangkap.
 */
beforeEach(function () {
    StatusPengiriman::query()->delete();

    // RefreshDatabase menjalankan migrasi tetapi TIDAK menjalankan seeder, jadi
    // role yang dipakai tes harus dipastikan ada lebih dulu. Tanpa ini, kegagalan
    // "RoleDoesNotExist" menyamar sebagai kegagalan wewenang.
    foreach ([
        RoleEnum::MANAGER->value => 'Manager Distribusi',
        RoleEnum::COURIER->value => 'Kurir',
        RoleEnum::WAREHOUSE->value => 'Gudang',
    ] as $name => $display) {
        Role::firstOrCreate(['name' => $name], ['display_name' => $display, 'guard_name' => 'web']);
    }

    foreach ([
        PermissionEnum::MUATAN_READ->value,
        PermissionEnum::MUATAN_CREATE->value,
        PermissionEnum::MUATAN_SCAN->value,
        PermissionEnum::MUATAN_COMPLETE->value,
        PermissionEnum::MUATAN_UPDATE->value,
        PermissionEnum::SHIPMENTS_READ->value,
    ] as $nama) {
        Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
    }

    $statuses = [
        ['nama' => 'Proses Pemesanan', 'slug' => 'pemesanan'],
        ['nama' => 'Proses Produksi', 'slug' => 'produksi'],
        ['nama' => 'Proses Kedatangan', 'slug' => 'kedatangan'],
        ['nama' => 'Proses Packing', 'slug' => 'packing'],
        ['nama' => 'Selesai Packing', 'slug' => 'selesai-packing'],
        ['nama' => 'Proses Pengiriman', 'slug' => 'pengiriman'],
        ['nama' => 'Diterima Penerima', 'slug' => 'diterima'],
        ['nama' => 'Batal', 'slug' => 'batal'],
    ];

    foreach ($statuses as $index => $status) {
        StatusPengiriman::create($status + ['urutan' => $index + 1, 'is_active' => true]);
    }
});

/**
 * Manager dengan izin distribusi lengkap.
 */
function managerDistribusi(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::MANAGER->value);
    $user->givePermissionTo([
        PermissionEnum::MUATAN_READ->value,
        PermissionEnum::MUATAN_CREATE->value,
        PermissionEnum::MUATAN_SCAN->value,
        PermissionEnum::MUATAN_COMPLETE->value,
        PermissionEnum::MUATAN_UPDATE->value,
        PermissionEnum::SHIPMENTS_READ->value,
    ]);

    return $user;
}

/**
 * Kurir yang berada di bawah seorang manager.
 */
function kurirDiBawah(?User $manager = null): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::COURIER->value);

    if ($manager !== null) {
        $user->forceFill(['manager_id' => $manager->id])->save();
    }

    return $user->fresh();
}

/**
 * Resi untuk keperluan uji.
 *
 * Nomornya berurutan, bukan acak: `no_resi` berbatas unik dan nomor acak pernah
 * bertabrakan antar-tes, menghasilkan kegagalan yang menyesatkan.
 */
function resiUji(string $slug = 'selesai-packing'): Pengiriman
{
    static $urut = 0;
    $urut++;

    return Pengiriman::factory()->create([
        'no_resi' => 'EQ-2026-'.str_pad((string) $urut, 5, '0', STR_PAD_LEFT),
        'status_id' => StatusPengiriman::where('slug', $slug)->value('id'),
        'alamat_tujuan' => 'Jl. Contoh No. 1, Surabaya',
    ]);
}

it('menyimpan atasan pada data pengguna', function () {
    $manager = managerDistribusi();
    $kurir = kurirDiBawah($manager);

    expect($kurir->fresh()->manager_id)->toBe($manager->id)
        ->and($kurir->manager->id)->toBe($manager->id)
        ->and($manager->bawahan()->pluck('id')->all())->toContain($kurir->id);
});

it('mengembalikan hanya diri sendiri untuk pengguna yang bukan manager', function () {
    // idBawahan() dipakai sebagai satu daftar lingkup tanpa bercabang di
    // pemanggil: manager mendapat bawahannya, kurir mendapat dirinya.
    $kurir = kurirDiBawah();

    expect($kurir->idBawahan())->toBe([$kurir->id]);
});

it('mengembalikan bawahan beserta dirinya sendiri untuk manager', function () {
    $manager = managerDistribusi();
    $anakBuah = kurirDiBawah($manager);

    $lingkup = $manager->idBawahan();

    expect($lingkup)->toContain($anakBuah->id)
        ->and($lingkup)->toContain($manager->id);
});

it('memisahkan muatan antar manager', function () {
    $managerA = managerDistribusi();
    $managerB = managerDistribusi();

    $muatanA = Muatan::factory()->untukKurir(kurirDiBawah($managerA))->create();
    $muatanB = Muatan::factory()->untukKurir(kurirDiBawah($managerB))->create();

    $dataA = actingAs($managerA)
        ->get(route('admin.muatan.index'))
        ->assertSuccessful()
        ->viewData('page')['props']['muatan']['data'];

    $dataB = actingAs($managerB)
        ->get(route('admin.muatan.index'))
        ->assertSuccessful()
        ->viewData('page')['props']['muatan']['data'];

    expect(collect($dataA)->pluck('id')->all())->toBe([$muatanA->id])
        ->and(collect($dataB)->pluck('id')->all())->toBe([$muatanB->id]);
});

it('menolak manager membuka detail muatan kurir manager lain', function () {
    // Penegakan harus ada di server, bukan hanya pada daftar yang disaring:
    // alamat detail bisa diketik langsung.
    $managerA = managerDistribusi();
    $managerB = managerDistribusi();
    $muatanB = Muatan::factory()->untukKurir(kurirDiBawah($managerB))->create();

    actingAs($managerA)->get(route('admin.muatan.show', $muatanB))->assertForbidden();
});

it('menolak manager memindai resi ke muatan kurir manager lain', function () {
    $managerA = managerDistribusi();
    $managerB = managerDistribusi();
    $muatanB = Muatan::factory()->untukKurir(kurirDiBawah($managerB))->create();

    actingAs($managerA)
        ->postJson(route('admin.muatan.scan', $muatanB), ['no_resi' => resiUji()->no_resi])
        ->assertForbidden();

    expect($muatanB->fresh()->total_resi)->toBe(0);
});

it('menolak manager mengubah muatan kurir manager lain', function () {
    $managerA = managerDistribusi();
    $managerB = managerDistribusi();
    $muatanB = Muatan::factory()->untukKurir(kurirDiBawah($managerB))->create();

    actingAs($managerA)
        ->put(route('admin.muatan.update', $muatanB), [
            'nama_muatan' => 'Diubah manager lain',
            'tanggal_muatan' => now()->format('Y-m-d'),
        ])
        ->assertForbidden();

    expect($muatanB->fresh()->nama_muatan)->not->toBe('Diubah manager lain');
});

it('menolak kurir menyentuh muatan kurir lain walau tahu ID-nya', function () {
    // Kasus "muatan tanpa pemilik" juga tertutup: kurir_id kosong bukan berarti
    // bebas diambil alih.
    $kurirA = kurirDiBawah();
    $kurirB = kurirDiBawah();
    $muatanB = Muatan::factory()->untukKurir($kurirB)->create();

    actingAs($kurirA)->get(route('admin.muatan.show', $muatanB))->assertForbidden();
    actingAs($kurirA)
        ->postJson(route('admin.muatan.scan', $muatanB), ['no_resi' => resiUji()->no_resi])
        ->assertForbidden();
});

it('menolak menyentuh muatan yang belum ditugaskan ke kurir mana pun', function () {
    $manager = managerDistribusi();
    $kurir = kurirDiBawah($manager);

    $muatan = Muatan::factory()->create(['kurir_id' => null]);

    actingAs($kurir)->get(route('admin.muatan.show', $muatan))->assertForbidden();
    actingAs($manager)->get(route('admin.muatan.show', $muatan))->assertForbidden();
});

it('mengizinkan kurir membuka muatannya sendiri', function () {
    // Penjagaan tidak boleh kebablasan: kalau ini gagal, kurir tidak bisa lagi
    // bekerja pada muatannya sendiri.
    $kurir = kurirDiBawah();
    $muatan = Muatan::factory()->untukKurir($kurir)->create();

    actingAs($kurir)->get(route('admin.muatan.show', $muatan))->assertSuccessful();
});

it('mengizinkan manager membuka dan memindai muatan bawahannya', function () {
    $manager = managerDistribusi();
    $kurir = kurirDiBawah($manager);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();

    actingAs($manager)->get(route('admin.muatan.show', $muatan))->assertSuccessful();

    actingAs($manager)
        ->postJson(route('admin.muatan.scan', $muatan), ['no_resi' => resiUji()->no_resi])
        ->assertSuccessful();

    expect($muatan->fresh()->total_resi)->toBe(1);
});

it('tidak mengubah perilaku untuk role di luar kurir dan manager', function () {
    // Gudang, supervisor, dan super-admin tidak dibatasi lingkup: pekerjaan
    // mereka memang lintas tim (menyiapkan dan memeriksa semua muatan).
    $gudang = User::factory()->create(['is_active' => true]);
    $gudang->assignRole(RoleEnum::WAREHOUSE->value);
    $gudang->givePermissionTo([
        PermissionEnum::MUATAN_READ->value,
        PermissionEnum::MUATAN_SCAN->value,
    ]);

    $manager = managerDistribusi();
    $muatan = Muatan::factory()->untukKurir(kurirDiBawah($manager))->create();

    actingAs($gudang)->get(route('admin.muatan.show', $muatan))->assertSuccessful();
    actingAs($gudang)
        ->postJson(route('admin.muatan.scan', $muatan), ['no_resi' => resiUji()->no_resi])
        ->assertSuccessful();
});

it('tidak mengirim daftar kurir sama sekali kepada kurir', function () {
    // Daftar kurir hanya untuk manager distribusi. Kurir tidak boleh menerimanya
    // — bahkan namanya sendiri — karena prop itu ikut terkirim ke halaman dan
    // bisa dibaca dari sumber halaman, bukan sekadar disembunyikan di layar.
    $kurir = kurirDiBawah();
    $kurirLain = kurirDiBawah();

    $props = actingAs($kurir)
        ->get(route('admin.muatan.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($props['kurirList'])->toBe([]);

    // Membuktikan tesnya tidak vakum: kalau daftarnya memang terisi, ia akan
    // memuat kedua kurir ini.
    expect(collect($props['kurirList'])->pluck('id')->all())->not->toContain($kurirLain->id);
});

it('mengirim daftar kurir kepada manager distribusi', function () {
    // Penjaga sebaliknya: pembatasan tidak boleh ikut mengosongkan daftar bagi
    // satu-satunya peran yang memang berhak melihatnya.
    $manager = managerDistribusi();
    $kurir = kurirDiBawah($manager);

    $props = actingAs($manager)
        ->get(route('admin.muatan.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect(collect($props['kurirList'])->pluck('id')->all())->toContain($kurir->id);
});

it('tidak memberi kurir akses ke halaman pengelolaan pengguna', function () {
    // Tempat akun kurir terdaftar. Sudah tertutup oleh izin, tetapi dikunci juga
    // karena inilah halaman yang paling gamblang menampilkan daftar pengguna.
    $kurir = kurirDiBawah();

    actingAs($kurir)->get(route('admin.users.index'))->assertForbidden();
});

it('menawarkan seluruh kurir kepada manager yang belum punya bawahan', function () {
    // Tanpa ini, manager yang belum ditugasi bawahan sama sekali melihat daftar
    // KOSONG dan tidak bisa membuat muatan apa pun — pekerjaannya terhenti hanya
    // karena penugasan belum diisi, bukan karena ia tidak berwenang.
    $manager = managerDistribusi();
    $kurirA = kurirDiBawah();
    $kurirB = kurirDiBawah();

    expect($manager->bawahan()->count())->toBe(0);

    $props = actingAs($manager)
        ->get(route('admin.muatan.create'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    $id = collect($props['kurirList'])->pluck('id');

    expect($id)->toContain($kurirA->id)
        ->and($id)->toContain($kurirB->id);
});

it('menyempitkan daftar ke bawahan begitu manager punya bawahan', function () {
    // Pembatasan tetap berlaku penuh setelah ada bawahan — itulah keadaan yang
    // diinginkan, dan hanya keadaan "belum ditugaskan" yang dikecualikan.
    $manager = managerDistribusi();
    $anakBuah = kurirDiBawah($manager);
    $orangLain = kurirDiBawah();

    $props = actingAs($manager)
        ->get(route('admin.muatan.create'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    $id = collect($props['kurirList'])->pluck('id');

    expect($id)->toContain($anakBuah->id)
        ->and($id)->not->toContain($orangLain->id);
});

it('membiarkan manager tanpa bawahan menugaskan kurir mana pun', function () {
    // Sisi server harus sepakat dengan daftar di layar: kalau layar menawarkan
    // seluruh kurir sementara validasi menolaknya, manager hanya melihat galat.
    $manager = managerDistribusi();
    $kurir = kurirDiBawah();

    actingAs($manager)
        ->post(route('admin.muatan.store'), [
            'kurir_id' => $kurir->id,
            'tanggal_muatan' => now()->format('Y-m-d'),
            'jumlah_lembaga' => 2,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Muatan::where('kurir_id', $kurir->id)->exists())->toBeTrue();
});

it('tetap menolak kurir di luar tim setelah manager punya bawahan', function () {
    // Penjaga sebaliknya: pengecualian "belum punya bawahan" tidak boleh
    // melemahkan batas tim begitu bawahan sudah ada.
    $manager = managerDistribusi();
    kurirDiBawah($manager);
    $orangLain = kurirDiBawah();

    actingAs($manager)
        ->from(route('admin.muatan.create'))
        ->post(route('admin.muatan.store'), [
            'kurir_id' => $orangLain->id,
            'tanggal_muatan' => now()->format('Y-m-d'),
            'jumlah_lembaga' => 1,
        ])
        ->assertSessionHasErrors('kurir_id');

    expect(Muatan::where('kurir_id', $orangLain->id)->exists())->toBeFalse();
});

it('melaporkan muatan tanpa pemilik sebagai daftar kosong bagi kurir', function () {
    // Kurir tidak boleh "melihat tapi tidak bisa membuka": daftarnya ikut
    // disaring, jadi yang tampil selalu yang boleh dibuka.
    $kurir = kurirDiBawah();
    Muatan::factory()->create(['kurir_id' => null]);
    Muatan::factory()->untukKurir($kurir)->create();

    $data = actingAs($kurir)
        ->get(route('admin.muatan.index'))
        ->assertSuccessful()
        ->viewData('page')['props']['muatan']['data'];

    expect($data)->toHaveCount(1);
});
