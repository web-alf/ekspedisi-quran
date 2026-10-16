<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use App\Models\PindaiKerdusLog;
use App\Models\StatusHistory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Pindai kerdus oleh sisi distribusi (manager / distribusi).
 *
 * Yang dikunci di sini adalah janji fiturnya kepada pemilik sistem:
 *   1. Yang boleh memindai = sisi distribusi. Kurir memuat barang lewat muatan,
 *      gudang lewat halaman packing — mereka TIDAK boleh memakai halaman ini.
 *   2. Tiap pindai meninggalkan jejak: kerdus apa, SIAPA, JAM berapa, dan berapa eks
 *      mushafnya (A5/A6/Iqra).
 *   3. Input manual (ketik kode) sama sahnya dengan pindai kamera — termasuk kerdus
 *      yang belum terdaftar, tetapi hanya bila jenis & jumlahnya diisi petugas.
 *   4. Angka eks diambil dari ISI KERDUS, bukan dari kiriman layar. Kalau tidak, satu
 *      permintaan HTTP sudah cukup untuk menuliskan "500 eks A5" atas kerdus A6 berisi
 *      40 eks — dan laporan tracking-nya jadi karangan.
 *   5. Pindai TIDAK mengubah status pengiriman apa pun.
 *   6. Pindai kerdus terdaftar = verifikasi isi: petugas wajib mengetik hitungan fisiknya
 *      (lihat VerifikasiIsiKerdusTest untuk cocok/selisih & syarat muat).
 *
 * Izinnya diuji di atas MIGRASI SUNGGUHAN (bukan seeder): jalur deploy produksi hanya
 * menjalankan migrasi, jadi perubahan yang hanya ada di seeder tidak akan sampai ke
 * server.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function petugasPindai(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

/**
 * Kerdus terisi `$eks` eks, lengkap dengan baris item dan resi di dalamnya.
 *
 * Kerdus wajib punya tugas harian (kolomnya NOT NULL), jadi tugasnya ikut dibuat —
 * sama seperti kerdus sungguhan, yang selalu lahir dari tugas packing.
 */
function kerdus(string $kodeJenis, int $eks): PackingBox
{
    $nama = [
        'A5' => 'Al-Quran A5',
        'A6' => 'Al-Quran A6',
        'IQRA' => 'Buku Iqro Jilid 1-6',
    ][$kodeJenis] ?? $kodeJenis;

    $jenis = JenisQuran::firstOrCreate(
        ['kode_jenis' => $kodeJenis],
        ['nama_jenis' => $nama, 'default_capacity' => 20, 'is_active' => true]
    );

    $tugas = DailyPackingTask::factory()->create([
        'user_id' => User::factory()->create(['is_active' => true])->id,
        'tanggal_tugas' => today(),
    ]);

    $box = PackingBox::factory()->create([
        'daily_packing_task_id' => $tugas->id,
        'jenis_quran_id' => $jenis->id,
        'kapasitas' => $eks,
        'jumlah_terisi' => $eks,
        'status' => PackingBox::STATUS_SEALED,
        'sealed_at' => now(),
    ]);

    PackingItem::factory()->create([
        'packing_box_id' => $box->id,
        'quantity' => $eks,
    ]);

    return $box;
}

it('mengirim panel Pindai Kerdus ke halaman muatan untuk manager distribusi', function () {
    $manager = petugasPindai(RoleEnum::MANAGER->value);

    $props = $this->actingAs($manager)
        ->get(route('admin.muatan.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    // Panelnya MENUMPANG halaman muatan — keputusan pemilik sistem: sisi distribusi bekerja
    // di satu halaman, bukan berpindah menu.
    expect($props['auth']['user']['permissions'])->toContain(PermissionEnum::DISTRIBUSI_PINDAI_KERDUS->value)
        ->and($props['dapatMemindaiKerdus'])->toBeTrue()
        ->and($props['pindaiKerdus'])->not->toBeNull()
        ->and($props['pindaiKerdus']['hariIni']['kerdus'])->toBe(0)
        ->and($props['pindaiKerdus']['hariIni']['mushaf'])->toBe(0)
        ->and($props['pindaiKerdus']['log']['data'])->toBe([]);
});

it('juga mengirim panelnya untuk role distribusi dan super-admin', function () {
    foreach ([RoleEnum::DISTRIBUSI, RoleEnum::SUPER_ADMIN] as $role) {
        $props = $this->actingAs(petugasPindai($role->value))
            ->get(route('admin.muatan.index'))
            ->assertSuccessful()
            ->viewData('page')['props'];

        expect($props['dapatMemindaiKerdus'])->toBeTrue()
            ->and($props['pindaiKerdus'])->not->toBeNull();
    }
});

it('tidak mengirim panelnya ke kurir, walau ia memakai halaman muatan', function () {
    // Kurir memang memakai halaman ini (muatan miliknya sendiri), tetapi pindai kerdus
    // bukan pekerjaannya: propnya tidak dibangun sama sekali, bukan sekadar disembunyikan.
    $kurir = petugasPindai(RoleEnum::COURIER->value);

    // Di produksi kurir MEMEGANG muatan.read (diberikan migrasi 2026_10_04), jadi ia memang
    // membuka halaman ini. Seeder mengulang daftar izin kurir tanpa izin itu — keadaan
    // produksinya disiapkan di sini, kalau tidak tesnya cuma membuktikan kurir tak bisa
    // membuka halaman, bukan bahwa panelnya tidak terkirim kepadanya.
    $kurir->givePermissionTo(PermissionEnum::MUATAN_READ->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $kurir = $kurir->fresh();

    $props = $this->actingAs($kurir)
        ->get(route('admin.muatan.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($props['hanyaMiliknya'])->toBeTrue()
        ->and($props['dapatMemindaiKerdus'])->toBeFalse()
        ->and($props['pindaiKerdus'])->toBeNull();
});

it('menolak peran di luar alur muatan dari halaman muatan', function (string $role) {
    $this->actingAs(petugasPindai($role))
        ->get(route('admin.muatan.index'))
        ->assertForbidden();
})->with([
    'gudang' => RoleEnum::WAREHOUSE->value,
    'customer-service' => RoleEnum::CUSTOMER_SERVICE->value,
    'supervisor' => RoleEnum::SUPERVISOR->value,
]);

it('menolak peran lain juga saat memanggil endpoint pencatatannya langsung', function () {
    // Menyembunyikan tombol BUKAN pengamanan: satu panggilan langsung pun tidak boleh lolos.
    $kurir = petugasPindai(RoleEnum::COURIER->value);
    $box = kerdus('A5', 20);

    $this->actingAs($kurir)
        ->postJson(route('admin.pindai-kerdus.store'), ['kode' => $box->kode_kerdus])
        ->assertForbidden();

    expect(PindaiKerdusLog::count())->toBe(0);
});

it('mencatat siapa yang memindai, jam, jenis, dan jumlah eksnya', function () {
    $manager = petugasPindai(RoleEnum::MANAGER->value);
    $box = kerdus('A5', 20);

    $this->actingAs($manager)
        ->postJson(route('admin.pindai-kerdus.store'), [
            'kode' => $box->kode_kerdus,
            'metode' => 'qr',
            'jumlah_fisik' => 20,
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('ulang', false)
        ->assertJsonPath('data.kode_kerdus', $box->kode_kerdus)
        ->assertJsonPath('data.jenis', 'Al-Quran A5')
        ->assertJsonPath('data.jumlah_mushaf', 20)
        ->assertJsonPath('data.oleh', $manager->name);

    $baris = PindaiKerdusLog::first();

    expect($baris)->not->toBeNull()
        ->and($baris->user_id)->toBe($manager->id)
        ->and($baris->packing_box_id)->toBe($box->id)
        ->and($baris->jenis_quran_id)->toBe($box->jenis_quran_id)
        ->and($baris->jumlah_mushaf)->toBe(20)
        ->and($baris->metode)->toBe(PindaiKerdusLog::METODE_QR)
        ->and($baris->asal)->toBe(PindaiKerdusLog::ASAL_KERDUS)
        ->and($baris->created_at->isToday())->toBeTrue();

    $log = $this->actingAs($manager)
        ->get(route('admin.muatan.index'))
        ->viewData('page')['props']['pindaiKerdus']['log']['data'];

    expect($log)->toHaveCount(1)
        ->and($log[0]['oleh'])->toBe($manager->name)
        ->and($log[0]['kode_kerdus'])->toBe($box->kode_kerdus)
        ->and($log[0]['jenis_kode'])->toBe('A5')
        ->and($log[0]['jumlah_mushaf'])->toBe(20)
        ->and($log[0]['metode'])->toBe('qr')
        ->and($log[0]['waktu'])->toMatch('/^\d{2}:\d{2}:\d{2}$/');
});

it('mencatat pindai lewat ketikan sebagai metode manual, dengan jumlah dari isi kerdus', function () {
    $manager = petugasPindai(RoleEnum::MANAGER->value);
    $box = kerdus('A6', 40);

    $this->actingAs($manager)
        ->postJson(route('admin.pindai-kerdus.store'), [
            'kode' => $box->kode_kerdus,
            'metode' => 'manual',
            'jumlah_fisik' => 40,
            // Angka dari layar sengaja salah: yang benar adalah isi kerdus (40 eks A6).
            'jumlah_mushaf' => 999,
            'jenis_quran_id' => null,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.jumlah_mushaf', 40);

    $baris = PindaiKerdusLog::first();

    expect($baris->metode)->toBe(PindaiKerdusLog::METODE_MANUAL)
        ->and($baris->jumlah_mushaf)->toBe(40)
        ->and($baris->jenis_quran_id)->toBe($box->jenis_quran_id);
});

it('membaca kode kerdus dari QR berbentuk JSON maupun teks berlebih', function () {
    $manager = petugasPindai(RoleEnum::MANAGER->value);
    $box = kerdus('A5', 20);

    $this->actingAs($manager)
        ->postJson(route('admin.pindai-kerdus.store'), [
            'kode' => json_encode(['type' => 'box', 'kode_kerdus' => $box->kode_kerdus]),
            'jumlah_fisik' => 20,
        ])
        ->assertSuccessful();

    $this->actingAs($manager)
        ->postJson(route('admin.pindai-kerdus.store'), [
            'kode' => "  \"{$box->kode_kerdus}\" \n",
            'jumlah_fisik' => 20,
        ])
        ->assertSuccessful();

    expect(PindaiKerdusLog::count())->toBe(2)
        ->and(PindaiKerdusLog::pluck('kode_kerdus')->unique()->all())->toBe([$box->kode_kerdus]);
});

it('menolak kerdus tak terdaftar yang jenis dan jumlahnya tidak diisi', function () {
    $manager = petugasPindai(RoleEnum::MANAGER->value);

    $this->actingAs($manager)
        ->postJson(route('admin.pindai-kerdus.store'), ['kode' => 'KB-20260101-999-A5-01'])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('perlu_isian', true);

    expect(PindaiKerdusLog::count())->toBe(0);
});

it('tetap mencatat kerdus tak terdaftar bila petugas mengisi jenis dan jumlahnya', function () {
    $manager = petugasPindai(RoleEnum::MANAGER->value);
    $jenis = JenisQuran::firstOrCreate(
        ['kode_jenis' => 'IQRA'],
        ['nama_jenis' => 'Buku Iqro Jilid 1-6', 'default_capacity' => 20, 'is_active' => true]
    );

    $this->actingAs($manager)
        ->postJson(route('admin.pindai-kerdus.store'), [
            'kode' => 'KB-20260101-999-IQRA-07',
            'metode' => 'manual',
            'jenis_quran_id' => $jenis->id,
            'jumlah_mushaf' => 160,
            'catatan' => 'Label sobek',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.jumlah_mushaf', 160)
        ->assertJsonPath('data.oleh', $manager->name);

    $baris = PindaiKerdusLog::first();

    expect($baris->packing_box_id)->toBeNull()
        ->and($baris->asal)->toBe(PindaiKerdusLog::ASAL_MANDIRI)
        ->and($baris->metode)->toBe(PindaiKerdusLog::METODE_MANUAL)
        ->and($baris->jenis_quran_id)->toBe($jenis->id)
        ->and($baris->jumlah_mushaf)->toBe(160)
        ->and($baris->catatan)->toBe('Label sobek');
});

it('menandai pindai ulang dan menghitung kerdusnya sekali di ringkasan', function () {
    $manager = petugasPindai(RoleEnum::MANAGER->value);
    $box = kerdus('A5', 20);

    $this->actingAs($manager)
        ->postJson(route('admin.pindai-kerdus.store'), ['kode' => $box->kode_kerdus, 'jumlah_fisik' => 20])
        ->assertSuccessful()
        ->assertJsonPath('ulang', false);

    $kedua = $this->actingAs($manager)
        ->postJson(route('admin.pindai-kerdus.store'), ['kode' => $box->kode_kerdus, 'jumlah_fisik' => 20])
        ->assertSuccessful()
        ->assertJsonPath('ulang', true);

    $ringkas = $kedua->json('hari_ini');

    expect($ringkas['pindai'])->toBe(2)      // dua kali memindai
        ->and($ringkas['kerdus'])->toBe(1)   // tetapi satu kerdus
        ->and($ringkas['mushaf'])->toBe(20)  // jumlah eksnya tidak dobel
        ->and(PindaiKerdusLog::count())->toBe(2); // jejaknya tetap lengkap dua-duanya
});

it('meringkas jumlah mushaf per jenis: A5, A6, dan Iqra', function () {
    $manager = petugasPindai(RoleEnum::MANAGER->value);

    $this->actingAs($manager)->postJson(route('admin.pindai-kerdus.store'), ['kode' => kerdus('A5', 20)->kode_kerdus, 'jumlah_fisik' => 20])->assertSuccessful();
    $this->actingAs($manager)->postJson(route('admin.pindai-kerdus.store'), ['kode' => kerdus('A6', 40)->kode_kerdus, 'jumlah_fisik' => 40])->assertSuccessful();
    $terakhir = $this->actingAs($manager)->postJson(route('admin.pindai-kerdus.store'), ['kode' => kerdus('IQRA', 160)->kode_kerdus, 'jumlah_fisik' => 160])->assertSuccessful();

    $ringkas = $terakhir->json('hari_ini');
    $perJenis = collect($ringkas['per_jenis'])->keyBy('kode');

    expect($perJenis['A5']['mushaf'])->toBe(20)
        ->and($perJenis['A6']['mushaf'])->toBe(40)
        ->and($perJenis['IQRA']['mushaf'])->toBe(160)
        ->and($ringkas['mushaf'])->toBe(220)
        ->and($ringkas['kerdus'])->toBe(3);
});

it('memisahkan ringkasan hari ini dari seluruh riwayat', function () {
    $manager = petugasPindai(RoleEnum::MANAGER->value);
    $box = kerdus('A5', 20);

    $this->actingAs($manager)
        ->postJson(route('admin.pindai-kerdus.store'), ['kode' => $box->kode_kerdus, 'jumlah_fisik' => 20])
        ->assertSuccessful();

    // Pindai dipindahkan ke kemarin: yang diuji pemisahan hari ini vs seluruh riwayat.
    PindaiKerdusLog::query()->update(['created_at' => now()->subDay()]);

    $props = $this->actingAs($manager)
        ->get(route('admin.muatan.index'))
        ->viewData('page')['props']['pindaiKerdus'];

    expect($props['hariIni']['kerdus'])->toBe(0)
        ->and($props['hariIni']['mushaf'])->toBe(0)
        ->and($props['total']['kerdus'])->toBe(1)
        ->and($props['total']['mushaf'])->toBe(20)
        // Bawaan filter = hari ini, jadi tabelnya kosong sampai tanggalnya diubah.
        ->and($props['log']['data'])->toBe([])
        ->and($props['filters']['tanggal'])->toBe(today()->toDateString());

    $semua = $this->actingAs($manager)
        ->get(route('admin.muatan.index', ['pindai_tanggal' => '']))
        ->viewData('page')['props']['pindaiKerdus'];

    expect($semua['log']['data'])->toHaveCount(1);
});

it('tidak mengubah status pengiriman isi kerdus', function () {
    $manager = petugasPindai(RoleEnum::MANAGER->value);
    $box = kerdus('A5', 20);

    $pengiriman = $box->packingItems()->first()->pengiriman;
    $statusAwal = $pengiriman->status_id;

    $this->actingAs($manager)
        ->postJson(route('admin.pindai-kerdus.store'), ['kode' => $box->kode_kerdus, 'jumlah_fisik' => 20])
        ->assertSuccessful();

    expect($pengiriman->fresh()->status_id)->toBe($statusAwal)
        ->and(StatusHistory::count())->toBe(0)
        ->and(Pengiriman::count())->toBe(1);
});

it('migrasi izin sudah cukup tanpa seeder: manager, distribusi, dan super-admin boleh', function () {
    // Keadaan sebelum migrasi izin dijalankan: izinnya belum ada sama sekali. Keadaan itu
    // dibuat di sini karena RefreshDatabase sudah menjalankan seluruh migrasi lebih dulu.
    Permission::where('name', PermissionEnum::DISTRIBUSI_PINDAI_KERDUS->value)->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $migrasi = require database_path('migrations/2026_10_14_190100_beri_izin_pindai_kerdus.php');
    $migrasi->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([RoleEnum::MANAGER, RoleEnum::DISTRIBUSI, RoleEnum::SUPER_ADMIN] as $roleEnum) {
        $role = Role::where('name', $roleEnum->value)->first();

        expect($role->fresh()->hasPermissionTo(PermissionEnum::DISTRIBUSI_PINDAI_KERDUS->value))
            ->toBeTrue("role {$roleEnum->value} seharusnya boleh memindai kerdus");
    }

    foreach ([RoleEnum::COURIER, RoleEnum::WAREHOUSE, RoleEnum::CUSTOMER_SERVICE, RoleEnum::SUPERVISOR] as $roleEnum) {
        $role = Role::where('name', $roleEnum->value)->first();

        expect($role->fresh()->hasPermissionTo(PermissionEnum::DISTRIBUSI_PINDAI_KERDUS->value))
            ->toBeFalse("role {$roleEnum->value} seharusnya TIDAK boleh memindai kerdus");
    }

    expect(Permission::where('name', PermissionEnum::DISTRIBUSI_PINDAI_KERDUS->value)->count())->toBe(1);
});
