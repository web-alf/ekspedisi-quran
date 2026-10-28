<?php

use App\Enums\RoleEnum;
use App\Enums\UkuranLabelResi;
use App\Models\JenisQuran;
use App\Models\Pengiriman;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ukuran resi otomatis per jenis mushaf (keputusan pemilik 28/10/2026).
 *
 * Sampul A6 hanya 14 × 10 cm, jadi resi A6 dicetak 9 × 13 cm; A5 dan Iqro tetap 10 × 15 cm.
 * Cetak massal campuran dipisah per ukuran karena printer thermal hanya memuat satu ukuran rol.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole(RoleEnum::SUPER_ADMIN->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->jenis = [
        'A5' => JenisQuran::where('kode_jenis', 'A5')->first() ?? JenisQuran::factory()->a5()->create(),
        'A6' => JenisQuran::where('kode_jenis', 'A6')->first() ?? JenisQuran::factory()->a6()->create(),
        'IQRA' => JenisQuran::where('kode_jenis', 'IQRA')->first() ?? JenisQuran::factory()->iqra()->create(),
    ];
});

function resiBerjenis(string $kodeJenis): Pengiriman
{
    return Pengiriman::factory()->create(['jenis_quran_id' => test()->jenis[$kodeJenis]->id]);
}

it('memilih label 9 × 13 cm hanya untuk mushaf A6', function (?string $kodeJenis, UkuranLabelResi $ukuran) {
    $jenis = $kodeJenis === null ? null : new JenisQuran(['kode_jenis' => $kodeJenis]);

    expect(UkuranLabelResi::untukJenis($jenis))->toBe($ukuran);
})->with([
    'A6' => ['A6', UkuranLabelResi::Kecil],
    'a6 huruf kecil' => ['a6', UkuranLabelResi::Kecil],
    'A5' => ['A5', UkuranLabelResi::Standar],
    'Iqro' => ['IQRA', UkuranLabelResi::Standar],
    'tanpa jenis' => [null, UkuranLabelResi::Standar],
]);

it('mencetak resi satuan dengan ukuran sesuai jenis mushaf', function (string $kodeJenis, string $ukuranHalaman, string $template) {
    $resi = resiBerjenis($kodeJenis);

    $this->actingAs($this->admin)
        ->get(route('admin.thermal-print.single', $resi))
        ->assertSuccessful()
        ->assertViewIs($template)
        ->assertSee($ukuranHalaman, false)
        ->assertSee($resi->no_resi);
})->with([
    'A5' => ['A5', 'size: 100mm 150mm', 'admin.thermal-print.label-100x150'],
    'A6' => ['A6', 'size: 90mm 130mm', 'admin.thermal-print.label-90x130'],
    'Iqro' => ['IQRA', 'size: 100mm 150mm', 'admin.thermal-print.label-100x150'],
]);

it('langsung mencetak massal bila semua resi berukuran sama', function () {
    $resi = [resiBerjenis('A6'), resiBerjenis('A6')];

    $this->actingAs($this->admin)
        ->post(route('admin.thermal-print.bulk'), ['pengiriman_ids' => collect($resi)->pluck('id')->all()])
        ->assertSuccessful()
        ->assertViewIs('admin.thermal-print.bulk-90x130')
        ->assertViewHas('totalLabels', 2)
        ->assertSee('size: 90mm 130mm', false)
        ->assertSee('height: 260mm', false);
});

it('meminta pilih ukuran bila cetak massal mencampur A5, Iqro, dan A6', function () {
    $a5 = resiBerjenis('A5');
    $iqro = resiBerjenis('IQRA');
    $a6 = resiBerjenis('A6');

    $response = $this->actingAs($this->admin)
        ->post(route('admin.thermal-print.bulk'), ['pengiriman_ids' => [$a5->id, $a6->id, $iqro->id]])
        ->assertSuccessful()
        ->assertViewIs('admin.thermal-print.bulk-pilih-ukuran')
        ->assertViewHas('totalDipilih', 3)
        ->assertSeeInOrder(['Label 10 × 15 cm', 'Label 9 × 13 cm'])
        ->assertSee('Cetak 2 label 10 × 15 cm')
        ->assertSee('Cetak 1 label 9 × 13 cm')
        ->assertSee('name="ukuran" value="90x130"', false);

    $kelompok = $response->viewData('kelompokUkuran');
    expect($kelompok->pluck('ukuran')->all())->toBe([UkuranLabelResi::Standar, UkuranLabelResi::Kecil])
        ->and($kelompok[0]['pengirimanList']->pluck('id')->sort()->values()->all())->toBe(collect([$a5->id, $iqro->id])->sort()->values()->all())
        ->and($kelompok[1]['pengirimanList']->pluck('id')->all())->toBe([$a6->id]);
});

it('mencetak hanya resi berukuran yang dipilih dari pilihan campuran', function () {
    $a5 = resiBerjenis('A5');
    $a6 = resiBerjenis('A6');

    $this->actingAs($this->admin)
        ->post(route('admin.thermal-print.bulk'), ['pengiriman_ids' => [$a5->id, $a6->id], 'ukuran' => '90x130'])
        ->assertSuccessful()
        ->assertViewIs('admin.thermal-print.bulk-90x130')
        ->assertViewHas('totalLabels', 1)
        ->assertSee($a6->no_resi)
        ->assertDontSee($a5->no_resi);

    $this->actingAs($this->admin)
        ->post(route('admin.thermal-print.bulk'), ['pengiriman_ids' => [$a5->id, $a6->id], 'ukuran' => '100x150'])
        ->assertSuccessful()
        ->assertViewIs('admin.thermal-print.bulk-100x150')
        ->assertSee($a5->no_resi)
        ->assertDontSee($a6->no_resi);
});

it('menolak ukuran yang tidak dikenal atau tidak ada di pilihan', function () {
    $a5 = resiBerjenis('A5');

    $this->actingAs($this->admin)
        ->post(route('admin.thermal-print.bulk'), ['pengiriman_ids' => [$a5->id], 'ukuran' => '80x120'])
        ->assertSessionHasErrors(['ukuran' => 'Ukuran label tidak dikenal.']);

    $this->actingAs($this->admin)
        ->post(route('admin.thermal-print.bulk'), ['pengiriman_ids' => [$a5->id], 'ukuran' => '90x130'])
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->post(route('admin.thermal-print.bulk'), [])
        ->assertSessionHasErrors(['pengiriman_ids' => 'Pilih minimal satu pengiriman untuk dicetak.']);
});

it('menampilkan preview label 9 × 13 cm', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.thermal-print.preview', ['ukuran' => '90x130']))
        ->assertSuccessful()
        ->assertViewIs('admin.thermal-print.label-90x130')
        ->assertSee('Al-Quran A6');

    $this->actingAs($this->admin)
        ->get(route('admin.thermal-print.preview'))
        ->assertSuccessful()
        ->assertViewIs('admin.thermal-print.label-100x150');
});

it('label 9 × 13 cm memuat jenis di kotak resi, doa bertingkat, tanpa mengulang resi dan tanggal', function () {
    $doa = mb_substr(str_repeat('Semoga Allah merahmati almarhum dan menerima amal jariyahnya. ', 10), 0, 250);
    $resi = (object) [
        'no_resi' => 'EQ-2026-00988',
        'donatur' => (object) ['nama_donatur' => 'Ahmad', 'kode_donatur' => 'DON001'],
        'wakafItem' => (object) ['wakif_name' => 'Rayhan', 'doa_request' => $doa],
        'jenisQuran' => (object) ['nama_jenis' => 'Al-Quran A6'],
        'qr_code_path' => null,
    ];
    $data = ['printDate' => '28/10/2026', 'printTime' => '10:00', 'qrUrl' => null, 'appLogo' => null, 'contactAddress' => 'Semarang'];

    $satuan = view('admin.thermal-print.label-90x130', [...$data, 'pengiriman' => $resi])->render();
    $massal = view('admin.thermal-print.bulk-90x130', [...$data, 'pengirimanList' => collect([$resi]), 'totalLabels' => 1])->render();

    foreach ([$satuan, $massal] as $html) {
        expect($html)->toContain('size: 90mm 130mm')
            ->and($html)->toContain('<div class="jenis-badge">Al-Quran A6</div>')
            ->and($html)->toContain('class="value-col doa-col doa-panjang"')
            ->and($html)->toContain(e($doa))
            ->and($html)->not->toContain('<td class="section-header">Jenis Wakaf</td>')
            ->and($html)->not->toContain('<td class="label-col">Tanggal:</td>')
            ->and($html)->not->toContain('<td class="label-col">Resi:</td>');
    }

    expect($satuan)->toMatch('/\.thermal-label \{\s*width: 86mm;\s*height: 126mm;/')
        ->and($massal)->toMatch('/\.thermal-label \{\s*width: 90mm;\s*height: 130mm;/');
});
