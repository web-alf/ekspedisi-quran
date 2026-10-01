<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\User;
use App\Support\BoxUnits;
use Database\Seeders\JenisQuranSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(JenisQuranSeeder::class);
});

function managerUser(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::MANAGER->value);

    return $user;
}

describe('Poin #4 — halaman Kelola Donatur dihilangkan untuk manager', function () {
    it('mencabut izin donatur dari role manager di seeder', function () {
        $manager = managerUser();

        foreach (['read', 'create', 'update', 'import', 'export'] as $ability) {
            expect($manager->can("donatur.{$ability}"))->toBeFalse();
        }
    });

    it('menolak manager membuka /admin/donatur', function () {
        $this->actingAs(managerUser())
            ->get('/admin/donatur')
            ->assertForbidden();
    });

    it('tetap mengizinkan customer-service membuka /admin/donatur', function () {
        $cs = User::factory()->create(['is_active' => true]);
        $cs->assignRole(RoleEnum::CUSTOMER_SERVICE->value);

        $this->actingAs($cs)
            ->get('/admin/donatur')
            ->assertSuccessful();
    });

    it('mencabut donatur.read pada database yang sudah ter-seed sebelumnya', function () {
        $managerRole = Role::findByName(RoleEnum::MANAGER->value);
        $managerRole->givePermissionTo(PermissionEnum::DONATUR_READ->value);

        $manager = User::factory()->create(['is_active' => true]);
        $manager->assignRole(RoleEnum::MANAGER->value);
        expect($manager->fresh()->can('donatur.read'))->toBeTrue();

        $this->seed(RolePermissionSeeder::class);

        expect($manager->fresh()->can('donatur.read'))->toBeFalse();
        expect(Permission::where('name', 'donatur.read')->exists())->toBeTrue();
    });

    it('tidak mencabut izin yang dipakai halaman Pengiriman', function () {
        $manager = managerUser();

        expect($manager->can(PermissionEnum::SHIPMENTS_READ->value))->toBeTrue();
        expect($manager->can(PermissionEnum::SHIPMENTS_CREATE->value))->toBeTrue();
    });
});

describe('Poin #5 — daftar Quran selesai packing + satuan', function () {
    /**
     * Kerdus wajib punya daily_packing_task_id (NOT NULL) — dibuatkan sekali
     * di sini supaya tiap skenario tidak perlu mengulang.
     */
    function makeBox(array $attributes = []): PackingBox
    {
        $task = DailyPackingTask::factory()->create([
            'user_id' => User::factory()->create()->id,
            'tanggal_tugas' => today(),
        ]);

        return PackingBox::factory()->create(array_merge(
            ['daily_packing_task_id' => $task->id],
            $attributes
        ));
    }

    it('menghitung doz sebagai kerdus penuh, bukan lusin 12', function () {
        // 1 doz = 1 kerdus penuh. Isinya beda per ukuran, jadi pembaginya wajib
        // ikut jenis: A5=20, A6=40, IQRO=160.
        expect(BoxUnits::breakdown(20, 20))->toMatchArray([
            'pcs' => 20,
            'doz' => 1.0,
            'pcs_per_doz' => 20,
            'label' => '1 doz',
        ]);

        expect(BoxUnits::breakdown(40, 40)['label'])->toBe('1 doz');
        expect(BoxUnits::breakdown(160, 160)['label'])->toBe('1 doz');
        expect(BoxUnits::breakdown(80, 40)['label'])->toBe('2 doz');
        expect(BoxUnits::breakdown(320, 160)['label'])->toBe('2 doz');
    });

    it('menyebut keping apa adanya bila kerdus belum penuh', function () {
        // 24 keping pada kerdus A5 (isi 20) bukan "1,2 doz" — gudang membaca
        // "24 pcs" dan tahu tinggal 16 keping lagi untuk doz berikutnya.
        expect(BoxUnits::breakdown(24, 20)['label'])->toBe('24 pcs');
        expect(BoxUnits::breakdown(30, 20)['label'])->toBe('30 pcs');
        expect(BoxUnits::breakdown(6, 20)['label'])->toBe('6 pcs');
        expect(BoxUnits::breakdown(0, 20)['label'])->toBe('0 pcs');
        // Keping apa pun yang belum penuh pada kerdus A6 juga disebut pcs.
        expect(BoxUnits::breakdown(39, 40)['label'])->toBe('39 pcs');
    });

    it('menjelaskan isi 1 doz supaya tidak salah tafsir', function () {
        // Halaman memakai istilah "doz" (dus), jadi isinya harus ditulis jelas.
        expect(BoxUnits::explanation(20, 'A5'))->toBe('1 doz = 20 pcs (A5)');
        expect(BoxUnits::explanation(160, 'IQRO'))->toBe('1 doz = 160 pcs (IQRO)');
        expect(BoxUnits::explanation(0))->toBe('Kapasitas kerdus belum ditentukan');
    });

    it('tidak meledak bila kapasitas kerdus tidak masuk akal', function () {
        // Kapasitas 0 tidak boleh membuat pembagian tak-hingga atau label aneh.
        expect(BoxUnits::breakdown(10, 0)['label'])->toBe('10 pcs')
            ->and(BoxUnits::breakdown(10, 0)['doz'])->toBe(0.0);
    });

    it('menyertakan satuan dan QR pada daftar kerdus', function () {
        // Kerdus A5 berkapasitas 20: 20 keping = tepat 1 doz (kerdus penuh).
        // Jenis dipatok eksplisit: PackingBoxFactory memilih jenis secara acak,
        // sehingga tanpa ini tes kadang memakai A6 (kapasitas 40) dan gagal
        // walaupun kodenya benar.
        $box = makeBox([
            'jenis_quran_id' => JenisQuran::where('kode_jenis', 'A5')->value('id'),
            'status' => PackingBox::STATUS_FILLING,
            'jumlah_terisi' => 20,
            'kapasitas' => 20,
        ]);

        $this->actingAs(managerUser())
            ->get('/admin/box-tracking')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/BoxTracking/Index')
                ->where('boxes.data.0.satuan.label', '1 doz')
                ->where('boxes.data.0.satuan.pcs', 20)
                ->where('boxes.data.0.satuan_keterangan', '1 doz = 20 pcs (A5)')
                ->where('boxes.data.0.kode_kerdus', $box->kode_kerdus)
                ->has('boxes.data.0.qr_code_base64')
            );
    });

    it('memakai isi doz sesuai jenis kerdus, bukan angka tetap 20', function () {
        // Kerdus A6 berisi 40 per doz: 40 keping harus terbaca "1 doz", bukan
        // "40 pcs" atau "3,33 doz" seperti bila memakai asumsi lusin.
        $jenisA6 = JenisQuran::where('kode_jenis', 'A6')->firstOrFail();

        makeBox([
            'status' => PackingBox::STATUS_SEALED,
            'jenis_quran_id' => $jenisA6->id,
            'jumlah_terisi' => 40,
            'kapasitas' => 40,
            'sealed_at' => now(),
        ]);

        $this->actingAs(managerUser())
            ->get('/admin/box-tracking')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('boxes.data.0.satuan.label', '1 doz')
                ->where('boxes.data.0.satuan_keterangan', '1 doz = 40 pcs (A6)')
            );
    });

    it('menghasilkan QR sebagai data URL yang bisa langsung dipakai sebagai src', function () {
        $box = makeBox();

        expect($box->getBoxQRBase64())->toStartWith('data:image/png;base64,');
    });

    it('memfilter hanya kerdus yang sudah selesai packing', function () {
        $sealed = makeBox([
            'status' => PackingBox::STATUS_SEALED,
            'jumlah_terisi' => 12,
            'sealed_at' => now(),
        ]);
        $filling = makeBox(['status' => PackingBox::STATUS_FILLING, 'jumlah_terisi' => 3]);

        $this->actingAs(managerUser())
            ->get('/admin/box-tracking?selesai_packing=1')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->has('boxes.data', 1)
                ->where('boxes.data.0.status', PackingBox::STATUS_SEALED)
                ->where('boxes.data.0.kode_kerdus', $sealed->kode_kerdus)
            );

        expect($sealed->kode_kerdus)->not->toBe($filling->kode_kerdus);
    });

    it('tetap menampilkan halaman walau QR kerdus gagal dibuat', function () {
        // Server produksi hanya punya ekstensi `gd`, tidak `imagick`, sehingga
        // QrCode::format('png') melempar RuntimeException (library-nya mengunci
        // PNG ke ImagickImageBackEnd). Satu kerdus yang QR-nya gagal tidak boleh
        // menjatuhkan seluruh halaman daftar.
        makeBox([
            'status' => PackingBox::STATUS_SEALED,
            'jumlah_terisi' => 12,
        ]);

        QrCode::shouldReceive('format')
            ->andThrow(new RuntimeException('You need to install the imagick extension'));

        $this->actingAs(managerUser())
            ->get('/admin/box-tracking')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('boxes.data.0.qr_code_base64', null)
                // 12 keping pada kerdus A5 (isi 20) belum penuh, jadi disebut pcs.
                ->where('boxes.data.0.satuan.label', '12 pcs')
            );
    });

    it('menghitung total satuan hanya dari kerdus tersegel', function () {
        // Semua satu jenis supaya benar-benar teruji penggabungannya: dua kerdus
        // A5 penuh = 40 keping = 2 doz. Kerdus yang belum tersegel TIDAK dihitung.
        $jenisA5 = JenisQuran::where('kode_jenis', 'A5')->firstOrFail();

        makeBox(['status' => PackingBox::STATUS_SEALED, 'jenis_quran_id' => $jenisA5->id, 'jumlah_terisi' => 20, 'kapasitas' => 20]);
        makeBox(['status' => PackingBox::STATUS_SEALED, 'jenis_quran_id' => $jenisA5->id, 'jumlah_terisi' => 20, 'kapasitas' => 20]);
        makeBox(['status' => PackingBox::STATUS_FILLING, 'jenis_quran_id' => $jenisA5->id, 'jumlah_terisi' => 7, 'kapasitas' => 20]);

        $this->actingAs(managerUser())
            ->get('/admin/box-tracking')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('stats.sealed_pcs', 40)
                ->where('stats.sealed_per_jenis.0.satuan.label', '2 doz')
                ->where('stats.sealed_per_jenis.0.jumlah_kerdus', 2)
            );
    });

    it('memisahkan total per jenis karena isi doz berbeda tiap ukuran', function () {
        // Menjumlahkan keping dari jenis berbeda lalu membaginya satu angka doz
        // selalu salah: 20 keping A5 = 1 doz, 40 keping A6 juga 1 doz, tetapi
        // 60 keping campuran bukan "3 doz". Karena itu dirinci per jenis.
        $jenisA5 = JenisQuran::where('kode_jenis', 'A5')->firstOrFail();
        $jenisA6 = JenisQuran::where('kode_jenis', 'A6')->firstOrFail();

        makeBox([
            'status' => PackingBox::STATUS_SEALED,
            'jenis_quran_id' => $jenisA5->id,
            'jumlah_terisi' => 20,
            'kapasitas' => 20,
        ]);
        makeBox([
            'status' => PackingBox::STATUS_SEALED,
            'jenis_quran_id' => $jenisA6->id,
            'jumlah_terisi' => 40,
            'kapasitas' => 40,
        ]);

        $this->actingAs(managerUser())
            ->get('/admin/box-tracking')
            ->assertSuccessful()
            ->assertInertia(function ($page) {
                $baris = collect($page->toArray()['props']['stats']['sealed_per_jenis']);

                expect($baris)->toHaveCount(2)
                    ->and($baris->pluck('kode_jenis')->all())->toEqualCanonicalizing(['A5', 'A6'])
                    // Keduanya kerdus penuh, jadi keduanya tepat 1 doz.
                    ->and($baris->pluck('satuan.label')->all())->toBe(['1 doz', '1 doz'])
                    ->and($baris->pluck('jumlah_kerdus')->all())->toBe([1, 1]);
            });
    });
});
