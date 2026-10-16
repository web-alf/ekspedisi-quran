<?php

use App\Models\MushafRequest;

/*
 * Kolom `jumlah_mushaf_approved` (nullable) adalah penanda "jumlah disetujui sudah
 * ditetapkan"; kolom pecahannya NOT NULL default 0 sehingga 0 tidak bisa dibedakan
 * dari "belum diisi". Karena itu setiap kasus "sudah diedit" di bawah menyertakan
 * `jumlah_mushaf_approved` — persis seperti kondisi baris setelah disimpan
 * (hook `saving` pada model mengisinya dari penjumlahan pecahan).
 */

test('total_mushaf_approved returns correct sum when approved quantities are set', function () {
    $request = new MushafRequest([
        'jumlah_mushaf_a5' => 50,
        'jumlah_mushaf_a6' => 30,
        'jumlah_iqra' => 20,
        'jumlah_mushaf_a5_approved' => 40,
        'jumlah_mushaf_a6_approved' => 25,
        'jumlah_iqra_approved' => 15,
        'jumlah_mushaf_approved' => 80,
    ]);

    expect($request->total_mushaf_approved)->toBe(80); // 40 + 25 + 15
});

test('total_mushaf_approved falls back to requested quantities when approved is null', function () {
    $request = new MushafRequest([
        'jumlah_mushaf_a5' => 50,
        'jumlah_mushaf_a6' => 30,
        'jumlah_iqra' => 20,
        'jumlah_mushaf_a5_approved' => null,
        'jumlah_mushaf_a6_approved' => null,
        'jumlah_iqra_approved' => null,
        'jumlah_mushaf_approved' => null,
    ]);

    expect($request->total_mushaf_approved)->toBe(100); // 50 + 30 + 20 (fallback)
});

test('has_quantity_change returns true when approved differs from requested', function () {
    $request = new MushafRequest([
        'jumlah_mushaf_a5' => 50,
        'jumlah_mushaf_a6' => 30,
        'jumlah_iqra' => 20,
        'jumlah_mushaf_a5_approved' => 40,
        'jumlah_mushaf_a6_approved' => 25,
        'jumlah_iqra_approved' => 15,
        'jumlah_mushaf_approved' => 80,
    ]);

    expect($request->has_quantity_change)->toBeTrue();
});

test('has_quantity_change returns false when approved equals requested', function () {
    $request = new MushafRequest([
        'jumlah_mushaf_a5' => 50,
        'jumlah_mushaf_a6' => 30,
        'jumlah_iqra' => 20,
        'jumlah_mushaf_a5_approved' => 50,
        'jumlah_mushaf_a6_approved' => 30,
        'jumlah_iqra_approved' => 20,
        'jumlah_mushaf_approved' => 100,
    ]);

    expect($request->has_quantity_change)->toBeFalse();
});

test('has_quantity_change returns false when approved is null', function () {
    $request = new MushafRequest([
        'jumlah_mushaf_a5' => 50,
        'jumlah_mushaf_a6' => 30,
        'jumlah_iqra' => 20,
    ]);

    expect($request->has_quantity_change)->toBeFalse();
});

test('quantity_change_percentage returns correct percentage for decrease', function () {
    $request = new MushafRequest([
        'jumlah_mushaf_a5' => 100,
        'jumlah_mushaf_a6' => 0,
        'jumlah_iqra' => 0,
        'jumlah_mushaf_a5_approved' => 80,
        'jumlah_mushaf_a6_approved' => 0,
        'jumlah_iqra_approved' => 0,
        'jumlah_mushaf_approved' => 80,
    ]);

    expect($request->quantity_change_percentage)->toBe(-20.0); // (80-100)/100 * 100 = -20%
});

test('quantity_change_percentage returns correct percentage for increase', function () {
    $request = new MushafRequest([
        'jumlah_mushaf_a5' => 100,
        'jumlah_mushaf_a6' => 0,
        'jumlah_iqra' => 0,
        'jumlah_mushaf_a5_approved' => 120,
        'jumlah_mushaf_a6_approved' => 0,
        'jumlah_iqra_approved' => 0,
        'jumlah_mushaf_approved' => 120,
    ]);

    expect($request->quantity_change_percentage)->toBe(20.0); // (120-100)/100 * 100 = 20%
});

test('quantity_change_percentage returns zero when no change', function () {
    $request = new MushafRequest([
        'jumlah_mushaf_a5' => 100,
        'jumlah_mushaf_a6' => 0,
        'jumlah_iqra' => 0,
        'jumlah_mushaf_a5_approved' => 100,
        'jumlah_mushaf_a6_approved' => 0,
        'jumlah_iqra_approved' => 0,
        'jumlah_mushaf_approved' => 100,
    ]);

    expect($request->quantity_change_percentage)->toBe(0.0);
});

test('approved_breakdown marks quantities as not set while the marker column is null', function () {
    $request = new MushafRequest([
        'jumlah_mushaf_a5' => 50,
        'jumlah_mushaf_a6' => 10,
        'jumlah_iqra' => 5,
        'jumlah_iqra_approved' => 99,
        'jumlah_mushaf_approved' => null,
    ]);

    expect($request->has_approved_quantities)->toBeFalse()
        ->and($request->approved_breakdown['total'])->toBe(65); // 50 + 10 + 5, bukan 99
});

test('approved_breakdown reports zero as an approved value once the marker is set', function () {
    $request = new MushafRequest([
        'jumlah_mushaf_a5' => 50,
        'jumlah_mushaf_a6' => 10,
        'jumlah_iqra' => 5,
        'jumlah_mushaf_a5_approved' => 0,
        'jumlah_mushaf_a6_approved' => 0,
        'jumlah_iqra_approved' => 0,
        'jumlah_mushaf_approved' => 0,
    ]);

    expect($request->has_approved_quantities)->toBeTrue()
        ->and($request->approved_breakdown['total'])->toBe(0)
        ->and($request->quantity_change_percentage)->toBe(-100.0);
});

test('total_mushaf returns correct sum of requested quantities', function () {
    $request = new MushafRequest([
        'jumlah_mushaf_a5' => 50,
        'jumlah_mushaf_a6' => 30,
        'jumlah_iqra' => 20,
    ]);

    expect($request->total_mushaf)->toBe(100);
});
