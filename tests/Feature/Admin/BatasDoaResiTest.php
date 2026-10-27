<?php

use App\Enums\RoleEnum;
use App\Http\Requests\StoreDonaturRequest;
use App\Http\Requests\StoreWakafItemsRequest;
use App\Imports\DonaturImport;
use App\Models\Donatur;
use App\Models\User;
use App\Models\WakafItem;
use App\Rules\DoaMuatResi;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\PermissionRegistrar;

/**
 * Batas doa 100 karakter agar muat di label resi 100×150 mm (keputusan pemilik 28/10/2026).
 *
 * Doa lebih panjang dulu mendorong bagian "Jenis Wakaf" keluar kotak label. Kini:
 * - doa BARU atau yang DIUBAH maksimal 100 karakter di semua jalur input;
 * - doa lama yang lebih panjang tetap tersimpan dan diterima selama tidak diubah (form edit
 *   mengirim ulang doa yang ada), tetapi dicetak terpotong dengan "…".
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole(RoleEnum::SUPER_ADMIN->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function doaSepanjang(int $panjang): string
{
    return mb_substr(str_repeat('Semoga Allah merahmati almarhum dan menerima amal jariyahnya. ', 30), 0, $panjang);
}

it('memotong doa di label hanya bila lebih dari 100 karakter', function () {
    $tepat = doaSepanjang(100);
    $panjang = doaSepanjang(250);

    expect(WakafItem::doaUntukLabel(null))->toBeNull()
        ->and(WakafItem::doaUntukLabel('   '))->toBeNull()
        ->and(WakafItem::doaUntukLabel(" {$tepat} "))->toBe(trim($tepat))
        ->and(WakafItem::doaUntukLabel($panjang))->toEndWith('…')
        ->and(mb_strlen(WakafItem::doaUntukLabel($panjang)))->toBeLessThanOrEqual(WakafItem::DOA_MAKS_KARAKTER)
        ->and(str_starts_with($panjang, mb_substr(WakafItem::doaUntukLabel($panjang), 0, -1)))->toBeTrue();
});

it('menerima doa sampai 100 karakter, menolak yang lebih, dan menerima doa lama yang tidak diubah', function () {
    $cek = fn (mixed $nilai, ?string $tersimpan = null) => Validator::make(['doa' => $nilai], ['doa' => [new DoaMuatResi($tersimpan)]])->passes();
    $lama = doaSepanjang(300);

    expect($cek(doaSepanjang(100)))->toBeTrue()
        ->and($cek(doaSepanjang(101)))->toBeFalse()
        ->and($cek(null))->toBeTrue()
        ->and($cek($lama, $lama))->toBeTrue()
        ->and($cek($lama.' ', $lama))->toBeTrue()
        ->and($cek(doaSepanjang(200), $lama))->toBeFalse();

    $pesan = Validator::make(['doa' => doaSepanjang(101)], ['doa' => [new DoaMuatResi]])->errors()->first('doa');
    expect($pesan)->toBe('Doa maksimal 100 karakter agar muat di label resi.');
});

it('memakai batas doa di form tambah donatur, tambah item wakaf, dan impor', function () {
    $tolak = fn (array $aturan, array $data, string $kunci) => Validator::make($data, $aturan)->errors()->has($kunci);

    $donatur = (new StoreDonaturRequest)->rules();
    expect($tolak($donatur, ['doa_untuk_semua' => doaSepanjang(101)], 'doa_untuk_semua'))->toBeTrue()
        ->and($tolak($donatur, ['wakif_details' => [['doa_request' => doaSepanjang(101)]]], 'wakif_details.0.doa_request'))->toBeTrue()
        ->and($tolak($donatur, ['doa_untuk_semua' => doaSepanjang(100)], 'doa_untuk_semua'))->toBeFalse();

    $item = (new StoreWakafItemsRequest)->rules();
    expect($tolak($item, ['items' => [['doa_request' => doaSepanjang(101)]]], 'items.0.doa_request'))->toBeTrue();

    $impor = app(DonaturImport::class)->rules();
    expect($tolak($impor, ['doa_untuk_semua' => doaSepanjang(101)], 'doa_untuk_semua'))->toBeTrue()
        ->and($tolak($impor, ['doa_request' => doaSepanjang(101)], 'doa_request'))->toBeTrue();
});

it('menolak doa baru yang terlalu panjang saat mengubah item wakaf, tetapi menerima doa lama yang tidak diubah', function () {
    $donatur = Donatur::factory()->create();
    $lama = doaSepanjang(300);
    $item = WakafItem::factory()->create(['donatur_id' => $donatur->id, 'status' => 'pending', 'doa_request' => $lama]);
    $url = "/admin/donatur/{$donatur->id}/wakaf-items/{$item->id}";

    $this->actingAs($this->admin)
        ->patch($url, ['wakif_name' => 'Rayhan', 'doa_request' => doaSepanjang(150)])
        ->assertSessionHasErrors(['doa_request' => 'Doa maksimal 100 karakter agar muat di label resi.']);

    expect($item->fresh()->doa_request)->toBe($lama);

    $this->actingAs($this->admin)
        ->patch($url, ['wakif_name' => 'Rayhan bin Ahmad', 'doa_request' => $lama])
        ->assertSessionHasNoErrors();

    expect($item->fresh()->wakif_name)->toBe('Rayhan bin Ahmad');
});

it('tetap bisa menyimpan nama wakif donatur lama yang doanya panjang', function () {
    $donatur = Donatur::factory()->create(['prayer_mode' => 'customize_individual']);
    $lama = doaSepanjang(300);
    $item = WakafItem::factory()->create(['donatur_id' => $donatur->id, 'status' => 'pending', 'doa_request' => $lama]);
    $url = "/admin/donatur/{$donatur->id}/update-wakif-names";

    $this->actingAs($this->admin)
        ->patch($url, ['wakif_updates' => [['wakaf_item_id' => $item->id, 'wakif_name' => 'Nama Baru', 'doa_request' => $lama]]])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->patch($url, ['wakif_updates' => [['wakaf_item_id' => $item->id, 'wakif_name' => 'Nama Baru', 'doa_request' => doaSepanjang(120)]]])
        ->assertSessionHasErrors('wakif_updates.0.doa_request');
});

it('menerima doa_untuk_semua lama yang tidak diubah saat mengedit donatur', function () {
    $lama = doaSepanjang(300);
    $donatur = Donatur::factory()->create([
        'no_hp' => '+628123456789',
        'prayer_mode' => 'semua_donatur',
        'doa_untuk_semua' => $lama,
    ]);
    $data = [
        'kode_donatur' => $donatur->kode_donatur,
        'nama_donatur' => 'Nama Donatur',
        'no_hp' => '+628123456789',
        'prayer_mode' => 'semua_donatur',
    ];

    $this->actingAs($this->admin)
        ->put("/admin/donatur/{$donatur->id}", [...$data, 'doa_untuk_semua' => $lama])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->put("/admin/donatur/{$donatur->id}", [...$data, 'doa_untuk_semua' => doaSepanjang(101)])
        ->assertSessionHasErrors('doa_untuk_semua');
});

it('mencetak doa lama terpotong di label resi satuan dan massal', function () {
    $lama = doaSepanjang(400);
    $resi = (object) [
        'no_resi' => 'EQ-2026-00001',
        'donatur' => (object) ['nama_donatur' => 'Ahmad', 'kode_donatur' => 'DON001'],
        'wakafItem' => (object) ['wakif_name' => 'Rayhan', 'doa_request' => $lama],
        'jenisQuran' => (object) ['nama_jenis' => 'Al-Quran A5'],
        'qr_code_path' => null,
    ];
    $data = ['printDate' => '28/10/2026', 'printTime' => '10:00', 'qrUrl' => null, 'appLogo' => null, 'contactAddress' => 'Semarang'];

    $satuan = view('admin.thermal-print.label-100x150', [...$data, 'pengiriman' => $resi])->render();
    $massal = view('admin.thermal-print.bulk-100x150', [...$data, 'pengirimanList' => collect([$resi]), 'totalLabels' => 1])->render();
    $tercetak = e(WakafItem::doaUntukLabel($lama));

    foreach ([$satuan, $massal] as $html) {
        expect($html)->toContain($tercetak)
            ->and($html)->not->toContain(e($lama))
            ->and($html)->toContain('class="value-col doa-col"');
    }

    // Label satuan tidak boleh lagi meluber keluar kotaknya.
    expect($satuan)->toMatch('/\.thermal-label \{[^}]*overflow: hidden;/');
});
