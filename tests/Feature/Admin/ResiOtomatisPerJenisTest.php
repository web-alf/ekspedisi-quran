<?php

use App\Enums\RoleEnum;
use App\Enums\UkuranLabelResi;
use App\Models\JenisQuran;
use App\Models\Pengiriman;
use App\Models\User;
use App\Services\QrCodeService;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Resi di kertas termal A6 untuk semua jenis mushaf (revisi pemilik 02/11/2026).
 *
 * Sebelumnya resi dicetak printer biasa di kertas A5 (mushaf A5/Iqro) atau A6 (mushaf A6) dan
 * cetak massal campuran dipisah per ukuran. Sekarang satu ukuran: kertas termal A6 105 × 148 mm,
 * margin 2 mm, kotak label 101 × 144 mm. Pilihan kertas A5/A6 per jenis (UkuranLabelResi) tinggal
 * dipakai Lembar Muwakif.
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

it('memilih kertas A6 Lembar Muwakif hanya untuk mushaf A6', function (?string $kodeJenis, UkuranLabelResi $ukuran) {
    $jenis = $kodeJenis === null ? null : new JenisQuran(['kode_jenis' => $kodeJenis]);

    expect(UkuranLabelResi::untukJenis($jenis))->toBe($ukuran);
})->with([
    'A6' => ['A6', UkuranLabelResi::Kecil],
    'a6 huruf kecil' => ['a6', UkuranLabelResi::Kecil],
    'A5' => ['A5', UkuranLabelResi::Standar],
    'Iqro' => ['IQRA', UkuranLabelResi::Standar],
    'tanpa jenis' => [null, UkuranLabelResi::Standar],
]);

it('mencetak resi satuan semua jenis mushaf di kertas termal A6', function (string $kodeJenis) {
    $resi = resiBerjenis($kodeJenis);

    $this->actingAs($this->admin)
        ->get(route('admin.thermal-print.single', $resi))
        ->assertSuccessful()
        ->assertViewIs('admin.thermal-print.label-termal-a6')
        ->assertSee('size: 105mm 148mm', false)
        ->assertSee('margin: 2mm;', false)
        ->assertSee('Kertas termal <b>A6 (105 × 148 mm)</b>', false)
        ->assertSee($resi->no_resi)
        ->assertSee($this->jenis[$kodeJenis]->nama_jenis)
        ->assertDontSee('size: 148mm 210mm', false);
})->with([
    'A5' => ['A5'],
    'A6' => ['A6'],
    'Iqro' => ['IQRA'],
]);

it('mencetak massal resi berukuran sama di kertas termal A6', function () {
    $resi = [resiBerjenis('A6'), resiBerjenis('A6')];

    $html = $this->actingAs($this->admin)
        ->post(route('admin.thermal-print.bulk'), ['pengiriman_ids' => collect($resi)->pluck('id')->all()])
        ->assertSuccessful()
        ->assertViewIs('admin.thermal-print.bulk-termal-a6')
        ->assertViewHas('totalLabels', 2)
        ->assertSee('size: 105mm 148mm', false)
        ->assertSee('height: 288mm;', false)
        ->assertDontSee('<div class="lembar">', false)
        ->getContent();

    expect(substr_count($html, '<div class="thermal-label"'))->toBe(2);
});

it('mencetak massal campuran A5, Iqro, dan A6 sekaligus tanpa halaman pilih ukuran', function () {
    $a5 = resiBerjenis('A5');
    $iqro = resiBerjenis('IQRA');
    $a6 = resiBerjenis('A6');

    $html = $this->actingAs($this->admin)
        ->post(route('admin.thermal-print.bulk'), ['pengiriman_ids' => [$a5->id, $a6->id, $iqro->id]])
        ->assertSuccessful()
        ->assertViewIs('admin.thermal-print.bulk-termal-a6')
        ->assertViewHas('totalLabels', 3)
        ->assertSee([$a5->no_resi, $iqro->no_resi, $a6->no_resi])
        ->assertDontSee('Pilih Ukuran Label', false)
        ->getContent();

    expect(substr_count($html, '<div class="thermal-label"'))->toBe(3);
});

it('cetak resi mengabaikan ukuran kiriman halaman pilih ukuran lama', function () {
    $a5 = resiBerjenis('A5');
    $a6 = resiBerjenis('A6');

    // Tab lama yang masih membuka halaman pilih ukuran resi tetap mencetak semua pilihannya.
    $this->actingAs($this->admin)
        ->post(route('admin.thermal-print.bulk'), ['pengiriman_ids' => [$a5->id, $a6->id], 'ukuran' => 'a6'])
        ->assertSuccessful()
        ->assertViewIs('admin.thermal-print.bulk-termal-a6')
        ->assertViewHas('totalLabels', 2)
        ->assertSee([$a5->no_resi, $a6->no_resi]);
});

it('menolak ukuran yang tidak dikenal atau pilihan kosong', function () {
    $a5 = resiBerjenis('A5');

    $this->actingAs($this->admin)
        ->post(route('admin.thermal-print.bulk'), ['pengiriman_ids' => [$a5->id], 'ukuran' => '80x120'])
        ->assertSessionHasErrors(['ukuran' => 'Ukuran label tidak dikenal.']);

    $this->actingAs($this->admin)
        ->post(route('admin.thermal-print.bulk'), [])
        ->assertSessionHasErrors(['pengiriman_ids' => 'Pilih minimal satu pengiriman untuk dicetak.']);
});

it('menampilkan preview label di kertas termal A6 untuk contoh mushaf A5 dan A6', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.thermal-print.preview', ['ukuran' => 'a6']))
        ->assertSuccessful()
        ->assertViewIs('admin.thermal-print.label-termal-a6')
        ->assertSee('Al-Quran A6');

    $this->actingAs($this->admin)
        ->get(route('admin.thermal-print.preview'))
        ->assertSuccessful()
        ->assertViewIs('admin.thermal-print.label-termal-a6')
        ->assertSee('Al-Quran Ukuran A5');
});

it('resi A5 dan A6 (tombol Generate & Print) memakai kotak label termal yang sama, bukan lembar muwakif', function () {
    foreach (['A5', 'A6'] as $kode) {
        $resi = resiBerjenis($kode);

        $this->actingAs($this->admin)
            ->get(route('admin.thermal-print.single', $resi))
            ->assertSuccessful()
            ->assertSee('<div class="thermal-label" data-skala-maks="1.45">', false)
            ->assertSee('width: 101mm;', false)
            ->assertSee('height: 144mm;', false)
            ->assertDontSee('Mohon didoakan hajat dan cita-cita muwakif', false)
            ->assertDontSee('logo-alfatihah.png', false);
    }
});

function resiLembarWakaf(string $jenis = 'Al-Quran Ukuran A5'): object
{
    return (object) [
        'no_resi' => 'EQ-2026-21534',
        'tanggal_wakaf' => '2026-06-05',
        'donatur' => (object) ['nama_donatur' => 'Tito Bramantyo', 'kode_donatur' => 'CTQ 3290', 'no_hp' => '081234567890'],
        'wakafItem' => (object) ['wakif_name' => 'Tito Bramantyo', 'wakif_phone' => '085612345678', 'doa_request' => "Semoga kak Tito bisa menjadi imam keluarga yang soleh.\nAamiin"],
        'jenisQuran' => (object) ['nama_jenis' => $jenis],
    ];
}

function lembarMuwakif(object $resi, string $kertas): string
{
    return view('admin.thermal-print.partials.lembar-wakaf-halaman', [
        'daftar' => collect([$resi]),
        'kertas' => $kertas,
        'judulHalaman' => 'Lembar Muwakif',
    ])->render();
}

it('mencetak lembar muwakif: identitas, doa umum, doa muwakif, QR, dan kode donatur', function () {
    $html = lembarMuwakif(resiLembarWakaf(), 'A5');

    expect($html)->toContain('images/logo-alfatihah.png')
        ->and($html)->toContain("Penyaluran Wakaf Qur'an Untuk<br>Santri Masjid &amp; Penghafal Qur'an")
        ->and($html)->toContain('<span class="label">Nama Wakif</span><span class="titik">:</span><span class="nilai">Tito Bramantyo</span>')
        ->and($html)->toContain('<span class="nilai">05 Juni 2026</span>')
        ->and($html)->toContain("Mohon didoakan orang - orang yang telah mewakafkan Al-qur'an")
        ->and($html)->toContain('Mohon didoakan hajat dan cita-cita muwakif:')
        ->and($html)->toContain("<div class=\"doa-isi\">Semoga kak Tito bisa menjadi imam keluarga yang soleh.\nAamiin</div>")
        ->and($html)->toContain('<div class="no-resi">EQ-2026-21534</div>')
        ->and($html)->toContain('<div class="kode">DON CTQ 3290</div>')
        ->and($html)->toContain('<div class="jenis">Al-Quran Ukuran A5</div>')
        ->and($html)->toContain(app(QrCodeService::class)->svgUntuk('EQ-2026-21534'))
        // Lembar ini tinggal di dalam mushaf: nomor HP dan alamat pengirim tidak dicetak.
        ->and($html)->not->toContain('0812345')
        ->and($html)->not->toContain('0856123')
        ->and($html)->not->toContain('Pengirim');
});

it('lembar muwakif A6 sama persis dengan A5, hanya diperkecil', function () {
    $resi = resiLembarWakaf('Al-Quran Ukuran A6');
    $isi = static fn (string $html): string => substr($html, strpos($html, '<div class="lembar">'));
    $a5 = lembarMuwakif($resi, 'A5');
    $a6 = lembarMuwakif($resi, 'A6');

    expect($isi($a6))->toBe($isi($a5))
        ->and($a5)->toContain('size: 148mm 210mm;')->and($a5)->toContain('--k: 1;')
        ->and($a6)->toContain('size: 105mm 148mm;')->and($a6)->toContain('--k: 0.7095;');
});

it('tombol Cetak Lembar Muwakif mencetak lembar per ukuran, tanpa perlu berkas QR', function () {
    $a6 = [resiBerjenis('A6'), resiBerjenis('A6')];
    Pengiriman::whereIn('id', collect($a6)->pluck('id'))->update(['qr_code_path' => null]);

    $html = $this->actingAs($this->admin)
        ->post(route('admin.thermal-print.lembar-wakaf'), ['pengiriman_ids' => collect($a6)->pluck('id')->all()])
        ->assertSuccessful()
        ->assertViewIs('admin.thermal-print.partials.lembar-wakaf-halaman')
        ->assertSee('size: 105mm 148mm', false)
        ->assertSee('Mohon didoakan hajat dan cita-cita muwakif:', false)
        ->assertDontSee('<div class="thermal-label"', false)
        ->getContent();

    expect(substr_count($html, '<div class="lembar">'))->toBe(2)
        ->and(substr_count($html, '<svg'))->toBeGreaterThanOrEqual(2);
});

it('tombol Cetak Lembar Muwakif meminta pilih ukuran bila A5 dan A6 tercampur', function () {
    $a5 = resiBerjenis('A5');
    $a6 = resiBerjenis('A6');

    $this->actingAs($this->admin)
        ->post(route('admin.thermal-print.lembar-wakaf'), ['pengiriman_ids' => [$a5->id, $a6->id]])
        ->assertSuccessful()
        ->assertViewIs('admin.thermal-print.bulk-pilih-ukuran')
        ->assertSee('Cetak Lembar Muwakif')
        ->assertSee('action="'.route('admin.thermal-print.lembar-wakaf').'"', false);

    $this->actingAs($this->admin)
        ->post(route('admin.thermal-print.lembar-wakaf'), ['pengiriman_ids' => [$a5->id, $a6->id], 'ukuran' => 'a6'])
        ->assertSuccessful()
        ->assertSee($a6->no_resi)
        ->assertDontSee($a5->no_resi);

    // Resi biasa dengan pilihan campuran yang sama langsung dicetak, tanpa halaman pilih ukuran.
    $this->actingAs($this->admin)
        ->post(route('admin.thermal-print.bulk'), ['pengiriman_ids' => [$a5->id, $a6->id]])
        ->assertViewIs('admin.thermal-print.bulk-termal-a6')
        ->assertViewHas('totalLabels', 2);
});
