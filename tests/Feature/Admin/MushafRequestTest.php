<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
});

test('admin can access mushaf requests index page', function () {
    $response = $this->actingAs($this->admin)->get('/admin/mushaf-requests');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/MushafRequest/Index')
        ->has('mushafRequests')
        ->has('mapData')
        ->has('stats')
    );
});

test('mushaf requests page includes map data for leaflet', function () {
    $response = $this->actingAs($this->admin)->get('/admin/mushaf-requests');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/MushafRequest/Index')
        ->where('mapData', fn ($mapData) => is_array($mapData) || $mapData instanceof \Illuminate\Support\Collection)
    );
});

test('mushaf requests page returns required props for map initialization', function () {
    $response = $this->actingAs($this->admin)->get('/admin/mushaf-requests');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/MushafRequest/Index')
        ->has('mushafRequests.data')
        ->has('mapData')
        ->has('stats')
        ->has('filters')
    );
});

// ============================================
// UPDATE INFO TESTS
// ============================================

test('admin can update mushaf request info', function () {
    $this->markTestSkipped('Route /admin/mushaf-requests/{id}/info removed; use updateLembaga or updateQuantities instead.');
    $mushafRequest = \App\Models\MushafRequest::factory()->create([
        'nama_lembaga' => 'TPQ Lama',
        'kategori_lembaga' => 'TPQ',
        'alamat_lengkap' => 'Alamat Lama',
        'nama_pengurus_1' => 'Pengurus Lama',
        'jabatan_pengurus_1' => 'Ketua',
        'whatsapp_pengurus_1' => '628123456789',
    ]);

    $response = $this->actingAs($this->admin)->patch("/admin/mushaf-requests/{$mushafRequest->id}/info", [
        'nama_lembaga' => 'TPQ Baru',
        'kategori_lembaga' => 'Pondok Pesantren',
        'alamat_lengkap' => 'Alamat Baru',
        'urgensi_request' => 'tinggi',
        'nama_pengurus_1' => 'Pengurus Baru',
        'jabatan_pengurus_1' => 'Direktur',
        'whatsapp_pengurus_1' => '628987654321',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $mushafRequest->refresh();
    expect($mushafRequest->nama_lembaga)->toBe('TPQ Baru');
    expect($mushafRequest->kategori_lembaga)->toBe('Pondok Pesantren');
    expect($mushafRequest->alamat_lengkap)->toBe('Alamat Baru');
    expect($mushafRequest->urgensi_request)->toBe('tinggi');
    expect($mushafRequest->nama_pengurus_1)->toBe('Pengurus Baru');
    expect($mushafRequest->jabatan_pengurus_1)->toBe('Direktur');
    expect($mushafRequest->whatsapp_pengurus_1)->toBe('628987654321');
});

test('update info requires nama_lembaga', function () { $this->markTestSkipped('test targets removed /info endpoint with file validation');
    $mushafRequest = \App\Models\MushafRequest::factory()->create();

    $response = $this->actingAs($this->admin)->patch("/admin/mushaf-requests/{$mushafRequest->id}/info", [
        'nama_lembaga' => '', // Empty
        'alamat_lengkap' => 'Alamat',
        'nama_pengurus_1' => 'Pengurus',
        'jabatan_pengurus_1' => 'Ketua',
        'whatsapp_pengurus_1' => '628123456789',
    ]);

    $response->assertSessionHasErrors('nama_lembaga');
});

test('update info requires alamat_lengkap', function () { $this->markTestSkipped('test targets removed /info endpoint with file validation');
    $mushafRequest = \App\Models\MushafRequest::factory()->create();

    $response = $this->actingAs($this->admin)->patch("/admin/mushaf-requests/{$mushafRequest->id}/info", [
        'nama_lembaga' => 'TPQ Test',
        'alamat_lengkap' => '', // Empty
        'nama_pengurus_1' => 'Pengurus',
        'jabatan_pengurus_1' => 'Ketua',
        'whatsapp_pengurus_1' => '628123456789',
    ]);

    $response->assertSessionHasErrors('alamat_lengkap');
});

test('update info requires pengurus 1 fields', function () { $this->markTestSkipped('test targets removed /info endpoint with file validation');
    $mushafRequest = \App\Models\MushafRequest::factory()->create();

    $response = $this->actingAs($this->admin)->patch("/admin/mushaf-requests/{$mushafRequest->id}/info", [
        'nama_lembaga' => 'TPQ Test',
        'alamat_lengkap' => 'Alamat',
        'nama_pengurus_1' => '', // Empty
        'jabatan_pengurus_1' => 'Ketua',
        'whatsapp_pengurus_1' => '628123456789',
    ]);

    $response->assertSessionHasErrors('nama_pengurus_1');
});

test('update info can update pengurus 2', function () { $this->markTestSkipped('test targets removed /info endpoint with file validation');
    $mushafRequest = \App\Models\MushafRequest::factory()->create([
        'nama_pengurus_2' => null,
        'jabatan_pengurus_2' => null,
        'whatsapp_pengurus_2' => null,
    ]);

    $response = $this->actingAs($this->admin)->patch("/admin/mushaf-requests/{$mushafRequest->id}/info", [
        'nama_lembaga' => $mushafRequest->nama_lembaga,
        'alamat_lengkap' => $mushafRequest->alamat_lengkap,
        'nama_pengurus_1' => $mushafRequest->nama_pengurus_1,
        'jabatan_pengurus_1' => $mushafRequest->jabatan_pengurus_1,
        'whatsapp_pengurus_1' => $mushafRequest->whatsapp_pengurus_1,
        'nama_pengurus_2' => 'Pengurus 2',
        'jabatan_pengurus_2' => 'Wakil',
        'whatsapp_pengurus_2' => '628111222333',
    ]);

    $response->assertRedirect();
    $mushafRequest->refresh();
    expect($mushafRequest->nama_pengurus_2)->toBe('Pengurus 2');
    expect($mushafRequest->jabatan_pengurus_2)->toBe('Wakil');
    expect($mushafRequest->whatsapp_pengurus_2)->toBe('628111222333');
});

test('update info can update GPS coordinates', function () { $this->markTestSkipped('test targets removed /info endpoint with file validation');
    $mushafRequest = \App\Models\MushafRequest::factory()->create([
        'latitude' => null,
        'longitude' => null,
    ]);

    $response = $this->actingAs($this->admin)->patch("/admin/mushaf-requests/{$mushafRequest->id}/info", [
        'nama_lembaga' => $mushafRequest->nama_lembaga,
        'alamat_lengkap' => $mushafRequest->alamat_lengkap,
        'nama_pengurus_1' => $mushafRequest->nama_pengurus_1,
        'jabatan_pengurus_1' => $mushafRequest->jabatan_pengurus_1,
        'whatsapp_pengurus_1' => $mushafRequest->whatsapp_pengurus_1,
        'latitude' => -6.123456,
        'longitude' => 106.789012,
    ]);

    $response->assertRedirect();
    $mushafRequest->refresh();
    expect((float) $mushafRequest->latitude)->toBe(-6.123456);
    expect((float) $mushafRequest->longitude)->toBe(106.789012);
});

test('update info validates GPS coordinate ranges', function () { $this->markTestSkipped('test targets removed /info endpoint with file validation');
    $mushafRequest = \App\Models\MushafRequest::factory()->create();

    // Test invalid latitude
    $response = $this->actingAs($this->admin)->patch("/admin/mushaf-requests/{$mushafRequest->id}/info", [
        'nama_lembaga' => $mushafRequest->nama_lembaga,
        'alamat_lengkap' => $mushafRequest->alamat_lengkap,
        'nama_pengurus_1' => $mushafRequest->nama_pengurus_1,
        'jabatan_pengurus_1' => $mushafRequest->jabatan_pengurus_1,
        'whatsapp_pengurus_1' => $mushafRequest->whatsapp_pengurus_1,
        'latitude' => 91, // Invalid: > 90
        'longitude' => 100,
    ]);

    $response->assertSessionHasErrors('latitude');
});

test('update info can upload foto santri', function () { $this->markTestSkipped('test targets removed /info endpoint with file validation');
    \Storage::fake('public');

    $mushafRequest = \App\Models\MushafRequest::factory()->create([
        'foto_santri_path' => null,
    ]);

    $file = \Illuminate\Http\UploadedFile::fake()->image('foto-santri.jpg', 100, 100);

    $response = $this->actingAs($this->admin)->patch("/admin/mushaf-requests/{$mushafRequest->id}/info", [
        'nama_lembaga' => $mushafRequest->nama_lembaga,
        'alamat_lengkap' => $mushafRequest->alamat_lengkap,
        'nama_pengurus_1' => $mushafRequest->nama_pengurus_1,
        'jabatan_pengurus_1' => $mushafRequest->jabatan_pengurus_1,
        'whatsapp_pengurus_1' => $mushafRequest->whatsapp_pengurus_1,
        'foto_santri' => $file,
    ]);

    $response->assertRedirect();
    $mushafRequest->refresh();

    expect($mushafRequest->foto_santri_path)->not->toBeNull();
    \Storage::disk('public')->assertExists($mushafRequest->foto_santri_path);
});

test('update info can delete foto santri', function () { $this->markTestSkipped('test targets removed /info endpoint with file validation');
    \Storage::fake('public');

    // Create file first
    $filePath = 'mushaf-requests/foto-santri/test.jpg';
    \Storage::disk('public')->put($filePath, 'dummy content');

    $mushafRequest = \App\Models\MushafRequest::factory()->create([
        'foto_santri_path' => $filePath,
    ]);

    $response = $this->actingAs($this->admin)->patch("/admin/mushaf-requests/{$mushafRequest->id}/info", [
        'nama_lembaga' => $mushafRequest->nama_lembaga,
        'alamat_lengkap' => $mushafRequest->alamat_lengkap,
        'nama_pengurus_1' => $mushafRequest->nama_pengurus_1,
        'jabatan_pengurus_1' => $mushafRequest->jabatan_pengurus_1,
        'whatsapp_pengurus_1' => $mushafRequest->whatsapp_pengurus_1,
        'delete_foto_santri' => true,
    ]);

    $response->assertRedirect();
    $mushafRequest->refresh();

    expect($mushafRequest->foto_santri_path)->toBeNull();
    \Storage::disk('public')->assertMissing($filePath);
});

test('update info replaces old file when uploading new one', function () { $this->markTestSkipped('test targets removed /info endpoint with file validation');
    \Storage::fake('public');

    // Create old file
    $oldFilePath = 'mushaf-requests/foto-lembaga/old.jpg';
    \Storage::disk('public')->put($oldFilePath, 'old content');

    $mushafRequest = \App\Models\MushafRequest::factory()->create([
        'foto_lembaga_path' => $oldFilePath,
    ]);

    $newFile = \Illuminate\Http\UploadedFile::fake()->image('new.jpg', 100, 100);

    $response = $this->actingAs($this->admin)->patch("/admin/mushaf-requests/{$mushafRequest->id}/info", [
        'nama_lembaga' => $mushafRequest->nama_lembaga,
        'alamat_lengkap' => $mushafRequest->alamat_lengkap,
        'nama_pengurus_1' => $mushafRequest->nama_pengurus_1,
        'jabatan_pengurus_1' => $mushafRequest->jabatan_pengurus_1,
        'whatsapp_pengurus_1' => $mushafRequest->whatsapp_pengurus_1,
        'foto_lembaga' => $newFile,
    ]);

    $response->assertRedirect();
    $mushafRequest->refresh();

    // Old file should be deleted
    \Storage::disk('public')->assertMissing($oldFilePath);
    // New file should exist
    expect($mushafRequest->foto_lembaga_path)->not->toBeNull();
    expect($mushafRequest->foto_lembaga_path)->not->toBe($oldFilePath);
    \Storage::disk('public')->assertExists($mushafRequest->foto_lembaga_path);
});

test('update info validates image file types', function () { $this->markTestSkipped('test targets removed /info endpoint with file validation');
    \Storage::fake('public');

    $mushafRequest = \App\Models\MushafRequest::factory()->create();

    $file = \Illuminate\Http\UploadedFile::fake()->create('document.pdf', 100);

    $response = $this->actingAs($this->admin)->patch("/admin/mushaf-requests/{$mushafRequest->id}/info", [
        'nama_lembaga' => $mushafRequest->nama_lembaga,
        'alamat_lengkap' => $mushafRequest->alamat_lengkap,
        'nama_pengurus_1' => $mushafRequest->nama_pengurus_1,
        'jabatan_pengurus_1' => $mushafRequest->jabatan_pengurus_1,
        'whatsapp_pengurus_1' => $mushafRequest->whatsapp_pengurus_1,
        'foto_santri' => $file, // PDF instead of image
    ]);

    $response->assertSessionHasErrors('foto_santri');
});

test('update info validates document file types for file_nama_santri', function () { $this->markTestSkipped('test targets removed /info endpoint with file validation');
    \Storage::fake('public');

    $mushafRequest = \App\Models\MushafRequest::factory()->create();

    $file = \Illuminate\Http\UploadedFile::fake()->image('image.jpg', 100, 100);

    $response = $this->actingAs($this->admin)->patch("/admin/mushaf-requests/{$mushafRequest->id}/info", [
        'nama_lembaga' => $mushafRequest->nama_lembaga,
        'alamat_lengkap' => $mushafRequest->alamat_lengkap,
        'nama_pengurus_1' => $mushafRequest->nama_pengurus_1,
        'jabatan_pengurus_1' => $mushafRequest->jabatan_pengurus_1,
        'whatsapp_pengurus_1' => $mushafRequest->whatsapp_pengurus_1,
        'file_nama_santri' => $file, // Image instead of document
    ]);

    $response->assertSessionHasErrors('file_nama_santri');
});
