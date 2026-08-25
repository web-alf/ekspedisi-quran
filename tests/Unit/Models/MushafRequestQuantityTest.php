<?php

use App\Models\MushafRequest;

test('total_mushaf_approved returns correct sum when approved quantities are set', function () {
    $request = new MushafRequest([
        'jumlah_mushaf_a5' => 50,
        'jumlah_mushaf_a6' => 30,
        'jumlah_iqra' => 20,
        'jumlah_mushaf_a5_approved' => 40,
        'jumlah_mushaf_a6_approved' => 25,
        'jumlah_iqra_approved' => 15,
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
    ]);

    expect($request->quantity_change_percentage)->toBe(0.0);
});

test('total_mushaf returns correct sum of requested quantities', function () {
    $request = new MushafRequest([
        'jumlah_mushaf_a5' => 50,
        'jumlah_mushaf_a6' => 30,
        'jumlah_iqra' => 20,
    ]);

    expect($request->total_mushaf)->toBe(100);
});
