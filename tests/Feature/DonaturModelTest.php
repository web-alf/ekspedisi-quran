<?php

use App\Models\Donatur;
use App\Models\User;
use App\Models\WakafItem;

it('computes total counts from wakaf items correctly', function () {
    $user = User::factory()->create();
    $donatur = Donatur::factory()->create([
        'total_a5_count' => 10,
        'total_a6_count' => 5,
        'total_iqra_count' => 3,
        'created_by' => $user->id,
        'prayer_mode' => 'semua_donatur',
    ]);

    // Create some wakaf items
    WakafItem::factory()->count(8)->create([
        'donatur_id' => $donatur->id,
        'wakaf_type' => 'A5',
        'status' => 'pending',
        'created_by' => $user->id,
    ]);

    WakafItem::factory()->count(3)->create([
        'donatur_id' => $donatur->id,
        'wakaf_type' => 'A6',
        'status' => 'pending',
        'created_by' => $user->id,
    ]);

    WakafItem::factory()->count(2)->create([
        'donatur_id' => $donatur->id,
        'wakaf_type' => 'IQRA',
        'status' => 'pending',
        'created_by' => $user->id,
    ]);

    // Refresh to ensure relationships are loaded
    $donatur->refresh();

    // Test computed attributes (renamed)
    expect($donatur->computed_a5_count)->toBe(8);
    expect($donatur->computed_a6_count)->toBe(3);
    expect($donatur->computed_iqra_count)->toBe(2);

    // Test accessors
    expect($donatur->actual_a5_count)->toBe(8);
    expect($donatur->actual_a6_count)->toBe(3);
    expect($donatur->actual_iqra_count)->toBe(2);

    // Test total quran calculation
    expect($donatur->total_quran)->toBe(13);
});

it('syncs wakaf items with prayer mode correctly', function () {
    $user = User::factory()->create();
    $donatur = Donatur::factory()->create([
        'nama_donatur' => 'Ahmad Yusuf',
        'doa_untuk_semua' => 'Semoga berkah',
        'prayer_mode' => 'semua_donatur',
        'created_by' => $user->id,
    ]);

    // Create wakaf items with different names
    $item1 = WakafItem::factory()->create([
        'donatur_id' => $donatur->id,
        'wakaf_type' => 'A5',
        'status' => 'pending',
        'wakif_name' => 'Different Name',
        'doa_request' => 'Different Prayer',
        'created_by' => $user->id,
    ]);

    $item2 = WakafItem::factory()->create([
        'donatur_id' => $donatur->id,
        'wakaf_type' => 'A6',
        'status' => 'processed', // This should not be updated
        'wakif_name' => 'Another Name',
        'doa_request' => 'Another Prayer',
        'created_by' => $user->id,
    ]);

    $updatedCount = $donatur->syncWakafItemsWithPrayerMode();

    expect($updatedCount)->toBe(1); // Only pending item should be updated

    $item1->refresh();
    $item2->refresh();

    expect($item1->wakif_name)->toBe('Ahmad Yusuf');
    expect($item1->doa_request)->toBe('Semoga berkah');
    expect($item2->wakif_name)->toBe('Another Name'); // Should remain unchanged
    expect($item2->doa_request)->toBe('Another Prayer');
});

it('validates quantity reduction correctly', function () {
    $user = User::factory()->create();
    $donatur = Donatur::factory()->create([
        'created_by' => $user->id,
    ]);

    // Create 10 A5 items: 5 pending, 3 processed, 2 shipped
    WakafItem::factory()->count(5)->create([
        'donatur_id' => $donatur->id,
        'wakaf_type' => 'A5',
        'status' => 'pending',
        'created_by' => $user->id,
    ]);

    WakafItem::factory()->count(3)->create([
        'donatur_id' => $donatur->id,
        'wakaf_type' => 'A5',
        'status' => 'processed',
        'created_by' => $user->id,
    ]);

    WakafItem::factory()->count(2)->create([
        'donatur_id' => $donatur->id,
        'wakaf_type' => 'A5',
        'status' => 'shipped',
        'created_by' => $user->id,
    ]);

    // Should be able to reduce by 5 (only pending items)
    expect($donatur->canReduceQuantity('A5', 5))->toBeTrue();
    expect($donatur->canReduceQuantity('A5', 6))->toBeFalse();

    // Test processed items count
    expect($donatur->getProcessedItemsCount('A5'))->toBe(5);
});

it('generates wakaf items with correct prayer mode', function () {
    $user = User::factory()->create();

    // Test with semua_donatur mode - create and save to database first
    $donatur = Donatur::factory()->create([
        'nama_donatur' => 'Ahmad Yusuf',
        'doa_untuk_semua' => 'Semoga berkah',
        'prayer_mode' => 'semua_donatur',
        'total_a5_count' => 2,
        'total_a6_count' => 1,
        'total_iqra_count' => 1,
        'created_by' => $user->id,
    ]);

    $items = $donatur->generateWakafItems();

    expect($items)->toHaveCount(4);
    expect($items[0]['wakif_name'])->toBe('Ahmad Yusuf');
    expect($items[0]['doa_request'])->toBe('Semoga berkah');

    // Test with customize_individual mode
    $donatur->update(['prayer_mode' => 'customize_individual']);
    $items = $donatur->generateWakafItems();

    expect($items[0]['wakif_name'])->toBeNull();
    expect($items[0]['doa_request'])->toBe('');
});
