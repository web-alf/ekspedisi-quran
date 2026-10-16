<?php

use App\Helpers\ProvinceHelper;

it('menyamakan ejaan provinsi yang berbeda menjadi satu kunci', function (string $a, string $b) {
    expect(ProvinceHelper::canonical($a))->toBe(ProvinceHelper::canonical($b));
    expect(ProvinceHelper::isSame($a, $b))->toBeTrue();
})->with([
    'yogyakarta: singkat vs lengkap' => ['DI YOGYAKARTA', 'DAERAH ISTIMEWA YOGYAKARTA'],
    'yogyakarta: GeoJSON vs data' => ['DAERAH ISTIMEWA YOGYAKARTA', 'DI YOGYAKARTA'],
    'bangka belitung' => ['KEPULAUAN BANGKA BELITUNG', 'BANGKA BELITUNG'],
    'aceh: ejaan lama' => ['DI. ACEH', 'ACEH'],
    'aceh: nanggroe' => ['NANGGROE ACEH DARUSSALAM', 'ACEH'],
    'banten: PROBANTEN' => ['PROBANTEN', 'BANTEN'],
    'NTB: ejaan lama' => ['NUSATENGGARA BARAT', 'NUSA TENGGARA BARAT'],
    'papua: Irian Jaya' => ['IRIAN JAYA BARAT', 'PAPUA BARAT'],
    'jakarta' => ['JAKARTA', 'DKI JAKARTA'],
    'kepulauan riau bukan riau' => ['KEP RIAU', 'KEPULAUAN RIAU'],
]);

it('tidak menyatukan provinsi yang memang berbeda', function () {
    // Jebakan paling berbahaya: Riau vs Kepulauan Riau adalah dua provinsi berbeda
    expect(ProvinceHelper::isSame('RIAU', 'KEPULAUAN RIAU'))->toBeFalse();
    expect(ProvinceHelper::isSame('JAWA BARAT', 'JAWA TENGAH'))->toBeFalse();
    expect(ProvinceHelper::isSame('PAPUA', 'PAPUA BARAT'))->toBeFalse();
    expect(ProvinceHelper::isSame('NUSA TENGGARA BARAT', 'NUSA TENGGARA TIMUR'))->toBeFalse();
});

it('menerima input dari berbagai format tanpa kehilangan huruf', function () {
    expect(ProvinceHelper::canonical('  jawa   tengah  '))->toBe('JAWA TENGAH');
    expect(ProvinceHelper::canonical('Provinsi Jawa Barat'))->toBe('JAWA BARAT');
    expect(ProvinceHelper::canonical('JAWA-TIMUR'))->toBe('JAWA TIMUR');
    expect(ProvinceHelper::canonical('Jawa Tengah'))->toBe('JAWA TENGAH');
});

it('mengembalikan string kosong untuk input kosong', function () {
    expect(ProvinceHelper::canonical(null))->toBe('');
    expect(ProvinceHelper::canonical(''))->toBe('');
    expect(ProvinceHelper::canonical('   '))->toBe('');
    expect(ProvinceHelper::isSame(null, ''))->toBeFalse();
});

it('mencocokkan nama provinsi pada GeoJSON 32 provinsi dengan data modern', function () {
    // Kasus nyata di produksi: 2 provinsi ini sebelumnya hilang dari peta
    expect(ProvinceHelper::isSame('DI YOGYAKARTA', 'DAERAH ISTIMEWA YOGYAKARTA'))->toBeTrue();
    expect(ProvinceHelper::isSame('KEPULAUAN BANGKA BELITUNG', 'BANGKA BELITUNG'))->toBeTrue();
});
