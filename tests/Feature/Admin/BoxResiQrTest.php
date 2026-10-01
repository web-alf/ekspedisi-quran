<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use App\Models\User;
use Database\Seeders\JenisQuranSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

uses(RefreshDatabase::class);

/**
 * QR per-eks di dalam kerdus.
 *
 * Quran (A5/A6) dan Iqra (IQRO) adalah baris Pengiriman yang terpisah, jadi
 * masing-masing punya QR sendiri untuk tracking. Halaman detail kerdus harus
 * menampilkan QR tiap eks, bukan hanya QR kerdus.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(JenisQuranSeeder::class);
});

function qrManager(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::MANAGER->value);

    return $user;
}

function kerdusDengan(array $pengirimanList): PackingBox
{
    $task = DailyPackingTask::factory()->create([
        'user_id' => User::factory()->create()->id,
        'tanggal_tugas' => today(),
    ]);

    $box = PackingBox::factory()->create([
        'daily_packing_task_id' => $task->id,
        'jenis_quran_id' => $pengirimanList[0]->jenis_quran_id,
        'kapasitas' => count($pengirimanList),
        'jumlah_terisi' => count($pengirimanList),
        'status' => PackingBox::STATUS_SEALED,
        'sealed_at' => now(),
    ]);

    foreach ($pengirimanList as $i => $pengiriman) {
        PackingItem::create([
            'packing_box_id' => $box->id,
            'pengiriman_id' => $pengiriman->id,
            'packed_by' => $task->user_id,
            'urutan_dalam_box' => $i + 1,
            'scan_method' => 'qr',
            'packed_at' => now(),
        ]);
    }

    return $box->fresh();
}

it('mengirim QR tersendiri untuk tiap eks di dalam kerdus', function () {
    $jenis = JenisQuran::pluck('id', 'kode_jenis');
    $donatur = Donatur::factory()->create();

    // Satu Quran A5 dan satu Iqra — dua jenis, dua QR berbeda.
    $quran = Pengiriman::factory()->create([
        'donatur_id' => $donatur->id,
        'jenis_quran_id' => $jenis['A5'],
        'no_resi' => 'EQ-2026-00001',
    ]);
    $iqra = Pengiriman::factory()->create([
        'donatur_id' => $donatur->id,
        'jenis_quran_id' => $jenis['IQRO'],
        'no_resi' => 'EQ-2026-00002',
    ]);

    $box = kerdusDengan([$quran, $iqra]);

    $props = $this->actingAs(qrManager())
        ->get(route('admin.box-tracking.show', $box))
        ->assertSuccessful()
        ->viewData('page')['props'];

    $items = collect($props['items']);

    expect($items)->toHaveCount(2)
        // Setiap eks punya QR sendiri...
        ->and($items->every(fn ($i) => str_starts_with($i['resi_qr_base64'] ?? '', 'data:image/svg+xml;base64,')))->toBeTrue()
        // ...dan QR itu tidak boleh sama, karena resinya berbeda.
        ->and($items->pluck('resi_qr_base64')->unique())->toHaveCount(2);

    // Jenisnya terbaca: Quran dan Iqra dibedakan.
    expect($items->pluck('kode_jenis')->all())->toEqualCanonicalizing(['A5', 'IQRO']);
});

it('menyertakan tautan tracking untuk tiap eks', function () {
    $jenis = JenisQuran::first();
    $pengiriman = Pengiriman::factory()->create([
        'jenis_quran_id' => $jenis->id,
        'no_resi' => 'EQ-2026-00009',
    ]);

    $box = kerdusDengan([$pengiriman]);

    $props = $this->actingAs(qrManager())
        ->get(route('admin.box-tracking.show', $box))
        ->assertSuccessful()
        ->viewData('page')['props'];

    $item = $props['items'][0];

    // Tautan tracking inilah yang jadi tujuan QR per-eks, dan isinya no_resi.
    expect($item['tracking_url'])->toContain('EQ-2026-00009');
});

it('QR per-eks isinya no_resi, sama seperti QR yang sudah dipakai gudang', function () {
    // Kalau format ini diubah, QR yang sudah dicetak tidak akan dikenali lagi
    // oleh pemindai yang ada.
    $pengiriman = Pengiriman::factory()->create(['no_resi' => 'EQ-2026-00077']);

    $base64 = $pengiriman->getResiQRBase64();

    expect($base64)->toStartWith('data:image/svg+xml;base64,');

    // Bandingkan dengan QR dari sumber yang sama (kode resi) — harus identik
    // dengan format yang dipakai QRCodeController untuk QR per-resi.
    $pembanding = 'data:image/svg+xml;base64,'.base64_encode(
        QrCode::format('svg')
            ->size(200)->margin(1)->errorCorrection('L')
            ->generate('EQ-2026-00077')
    );

    expect($base64)->toBe($pembanding);
});

it('tetap menampilkan halaman walau QR satu eks gagal dibuat', function () {
    // Kegagalan QR satu baris tidak boleh menghilangkan seluruh daftar isi.
    $jenis = JenisQuran::first();
    $pengiriman = Pengiriman::factory()->create(['jenis_quran_id' => $jenis->id]);
    $box = kerdusDengan([$pengiriman]);

    QrCode::shouldReceive('format')
        ->andThrow(new RuntimeException('imagick tidak tersedia'));

    $props = $this->actingAs(qrManager())
        ->get(route('admin.box-tracking.show', $box))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($props['items'])->toHaveCount(1)
        ->and($props['items'][0]['resi_qr_base64'])->toBeNull()
        // Data lain tetap ada walau QR-nya gagal.
        ->and($props['items'][0]['no_resi'])->not->toBeNull();
});

it('tetap cepat saat kerdus Iqra penuh 160 eks', function () {
    // Kasus terberat: satu kerdus Iqra = 160 eks = 160 QR sekali render.
    // PNG butuh ~9 detik di sini, jadi tes ini juga menjaga agar formatnya
    // tidak dikembalikan ke PNG tanpa disadari.
    $jenisIqro = JenisQuran::where('kode_jenis', 'IQRO')->first();
    $donatur = Donatur::factory()->create();

    $daftar = collect(range(1, 160))->map(fn ($i) => Pengiriman::factory()->create([
        'donatur_id' => $donatur->id,
        'jenis_quran_id' => $jenisIqro->id,
    ]));

    $box = kerdusDengan($daftar->all());

    $mulai = microtime(true);

    $props = $this->actingAs(qrManager())
        ->get(route('admin.box-tracking.show', $box))
        ->assertSuccessful()
        ->viewData('page')['props'];

    $detik = microtime(true) - $mulai;

    expect($props['items'])->toHaveCount(160)
        ->and(collect($props['items'])->every(
            fn ($i) => str_starts_with($i['resi_qr_base64'] ?? '', 'data:image/svg+xml;base64,')
        ))->toBeTrue()
        // Batas longgar: hanya untuk menangkap kemunduran besar (PNG ~9 detik).
        ->and($detik)->toBeLessThan(6.0);
});

it('tidak membocorkan QR ke peran tanpa izin kerdus', function () {
    DB::beginTransaction();
    try {
        $cs = User::factory()->create(['is_active' => true]);
        $cs->assignRole(RoleEnum::CUSTOMER_SERVICE->value);

        $this->actingAs($cs)->get('/admin/box-tracking')->assertForbidden();
    } finally {
        DB::rollBack();
    }
});
