<?php

use App\Models\WakafItem;

/**
 * Pilihan doa standar dari halaman website ("DOA MUWAKIF YANG MASIH HIDUP" / "DOA MUWAKIF
 * YANG SUDAH MENINGGAL" + doanya) tidak ikut tercetak di label resi — keputusan pemilik
 * 30/10/2026. Data impor Des 2025 – Jun 2026 menyimpan keduanya utuh di depan doa pribadi;
 * yang dicetak hanya doa pribadinya. Data asli tidak diubah.
 */
function templatDoaWebsite(string $pemisah = '  '): string
{
    return 'DOA MUWAKIF YANG MASIH HIDUP'.$pemisah
        .'"Ya Allah, limpahkan rahmat-Mu kepada muwakif dan keluarganya. Lapangkan rezekinya, berkahi umurnya, sehatkan badannya, serta mudahkan segala urusannya di dunia dan di akhirat. Aamiin ya Rabbal \'aalamiin."  '
        .'DOA MUWAKIF YANG SUDAH MENINGGAL "Ya Allah, ampunilah dosa-dosa almarhum/ah, limpahkan rahmat-Mu kepadanya, dan masukkan ia ke dalam Surga-Mu. Jadikan wakaf Al-Qur\'an ini sebagai amal jariyah yang terus mengalir pahalanya untuk almarhum/ah. Aamiin ya Rabbal \'aalamiin."';
}

it('hanya mencetak doa pribadi di belakang pilihan doa website', function (string $doa, ?string $tercetak) {
    expect(WakafItem::doaUntukLabel($doa))->toBe($tercetak);
})->with([
    'templat + doa pribadi' => [templatDoaWebsite().' Semoga Ibu Konaah Allah beri kesehatan. Aamiin', 'Semoga Ibu Konaah Allah beri kesehatan. Aamiin'],
    'templat + "&" + doa pribadi' => [templatDoaWebsite().'  & semoga diberi kesehatan', 'semoga diberi kesehatan'],
    'templat + "DAN" + doa pribadi' => [templatDoaWebsite().' DAN  semoga jadi anak yang soleh.', 'semoga jadi anak yang soleh.'],
    'templat dengan \\n literal' => [templatDoaWebsite(' \\n').' semoga diberikan kesembuhan', 'semoga diberikan kesembuhan'],
    'templat dengan baris baru' => [str_replace('  "', "\n\"", templatDoaWebsite())."\n\nsemoga lekas sembuh", 'semoga lekas sembuh'],
    'templat dengan kutip lengkung' => [str_replace(['"Ya', '."'], ['“Ya', '.”'], templatDoaWebsite()).' semoga berkah', 'semoga berkah'],
    'hanya templat' => [templatDoaWebsite(), null],
    'doa biasa tidak disentuh' => ['Semoga menjadi amal jariyah yang terus mengalir.', 'Semoga menjadi amal jariyah yang terus mengalir.'],
    'doa biasa yang menyebut muwakif' => ['Semoga Allah merahmati muwakif ini.', 'Semoga Allah merahmati muwakif ini.'],
    'doa biasa "muwakif yang" dengan spasi ganda tidak dirapikan' => ['Semoga istri muwakif yang  sedang hamil sehat.', 'Semoga istri muwakif yang  sedang hamil sehat.'],
]);

it('doa untuk lembar wakaf sama dengan database: tidak dipotong, \n teks dibaca sebagai baris baru', function () {
    $panjang = str_repeat('Semoga berkah dan menjadi amal jariyah. ', 15);

    expect(WakafItem::doaUntukLembar($panjang))->toBe(trim($panjang))
        ->and(WakafItem::doaUntukLembar('Semoga sehat\nAamiin'))->toBe("Semoga sehat\nAamiin")
        ->and(WakafItem::doaUntukLembar(templatDoaWebsite().' \n semoga lekas sembuh'))->toBe('semoga lekas sembuh')
        ->and(WakafItem::doaUntukLembar(templatDoaWebsite()))->toBeNull()
        ->and(WakafItem::doaUntukLembar(null))->toBeNull();
});

it('tidak mencetak pilihan doa website di label resi termal A6 maupun lembar muwakif A5/A6', function () {
    $resi = static fn (string $doa) => (object) [
        'no_resi' => 'EQ-2026-01000',
        'tanggal_wakaf' => '2026-06-05',
        'donatur' => (object) ['nama_donatur' => 'Ahmad', 'kode_donatur' => 'DON001'],
        'wakafItem' => (object) ['wakif_name' => 'Rayhan', 'doa_request' => $doa],
        'jenisQuran' => (object) ['nama_jenis' => 'Al-Quran Ukuran A5'],
        'qr_code_path' => null,
    ];
    $data = ['printDate' => '30/10/2026', 'printTime' => '10:00', 'qrUrl' => null, 'appLogo' => null, 'contactAddress' => 'Semarang'];

    foreach ([
        [$resi(templatDoaWebsite()), null],
        [$resi(templatDoaWebsite().' Semoga lekas sembuh'), 'Semoga lekas sembuh'],
    ] as [$item, $doaPribadi]) {
        foreach (['a5', 'a6'] as $ukuran) {
            // Label resi (tombol Generate & Print), kertas termal A6 untuk semua jenis
            foreach ([
                view('admin.thermal-print.label-termal-a6', [...$data, 'pengiriman' => $item])->render(),
                view('admin.thermal-print.bulk-termal-a6', [...$data, 'pengirimanList' => collect([$item]), 'totalLabels' => 1])->render(),
            ] as $html) {
                expect($html)->not->toContain('MUWAKIF YANG')
                    ->and($html)->not->toContain('limpahkan rahmat-Mu kepada muwakif');

                $doaPribadi === null
                    ? expect($html)->not->toContain('<td class="value-col doa-col')
                    : expect($html)->toMatch('/doa-col[^"]*">'.preg_quote($doaPribadi, '/').'</');
            }

            // Lembar muwakif (tombol Cetak Lembar Muwakif)
            $lembar = view('admin.thermal-print.partials.lembar-wakaf-halaman', [
                'daftar' => collect([$item]), 'kertas' => strtoupper($ukuran), 'judulHalaman' => 'Lembar',
            ])->render();

            expect($lembar)->not->toContain('MUWAKIF YANG')
                ->and($lembar)->toContain('Mohon didoakan hajat dan cita-cita muwakif:');

            $doaPribadi === null
                ? expect($lembar)->not->toContain('<div class="doa-isi">')
                : expect($lembar)->toContain('<div class="doa-isi">'.$doaPribadi.'</div>');
        }
    }
});
