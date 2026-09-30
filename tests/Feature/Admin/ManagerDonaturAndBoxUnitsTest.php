<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
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

    it('menghitung satuan pcs/doz dari jumlah keping', function () {
        expect(BoxUnits::breakdown(24))->toMatchArray([
            'pcs' => 24,
            'doz' => 2.0,
            'label' => '2 doz',
        ]);

        expect(BoxUnits::breakdown(12)['label'])->toBe('1 doz');
        expect(BoxUnits::breakdown(30)['label'])->toBe('2,5 doz');
        // 20 keping TIDAK dibulatkan jadi "1 doz" — isi kerdus harus terbaca apa adanya.
        expect(BoxUnits::breakdown(20)['label'])->toBe('20 pcs');
        expect(BoxUnits::breakdown(6)['label'])->toBe('6 pcs');
        expect(BoxUnits::breakdown(0)['label'])->toBe('0 pcs');
    });

    it('menyertakan satuan dan QR pada daftar kerdus', function () {
        $box = makeBox([
            'status' => PackingBox::STATUS_FILLING,
            'jumlah_terisi' => 24,
        ]);

        $this->actingAs(managerUser())
            ->get('/admin/box-tracking')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/BoxTracking/Index')
                ->where('boxes.data.0.satuan.label', '2 doz')
                ->where('boxes.data.0.satuan.pcs', 24)
                ->where('boxes.data.0.kode_kerdus', $box->kode_kerdus)
                ->has('boxes.data.0.qr_code_base64')
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
                ->where('boxes.data.0.satuan.label', '1 doz')
            );
    });

    it('menghitung total satuan hanya dari kerdus tersegel', function () {
        makeBox([
            'status' => PackingBox::STATUS_SEALED,
            'jumlah_terisi' => 24,
        ]);
        // Kerdus yang belum tersegel TIDAK boleh ikut dihitung.
        makeBox(['status' => PackingBox::STATUS_FILLING, 'jumlah_terisi' => 7]);

        $this->actingAs(managerUser())
            ->get('/admin/box-tracking')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('stats.sealed_pcs', 24)
                ->where('stats.sealed_satuan.label', '2 doz')
            );
    });
});
