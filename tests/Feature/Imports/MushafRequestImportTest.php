<?php

use App\Imports\MushafRequestImport;
use App\Models\MushafRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->import = new MushafRequestImport;
});

// ============================================
// PARSE JUMLAH KEBUTUHAN TESTS
// ============================================

test('it parses simple quantity number', function () {
    $result = invokeMethod($this->import, 'parseJumlahKebutuhan', ['100']);

    expect($result['mushaf_a5'])->toBe(100);
    expect($result['mushaf_a6'])->toBe(0);
    expect($result['iqra'])->toBe(0);
    expect($result['total_mushaf'])->toBe(100);
    expect($result['jenis_diminta'])->toBe(['A5']);
});

test('it parses quantity with mushaf keyword', function () {
    $result = invokeMethod($this->import, 'parseJumlahKebutuhan', ['75 mushaf']);

    expect($result['mushaf_a5'])->toBe(75);
    expect($result['mushaf_a6'])->toBe(0);
    expect($result['iqra'])->toBe(0);
    expect($result['total_mushaf'])->toBe(75);
    expect($result['jenis_diminta'])->toBe(['A5']);
});

test('it parses mixed a5 and a6', function () {
    $result = invokeMethod($this->import, 'parseJumlahKebutuhan', ['50 A5, 30 A6']);

    expect($result['mushaf_a5'])->toBe(50);
    expect($result['mushaf_a6'])->toBe(30);
    expect($result['iqra'])->toBe(0);
    expect($result['total_mushaf'])->toBe(80);
    expect($result['jenis_diminta'])->toContain('A5', 'A6');
});

test('it parses all types with iqra', function () {
    $result = invokeMethod($this->import, 'parseJumlahKebutuhan', ['50 A5, 30 A6, 20 iqra']);

    expect($result['mushaf_a5'])->toBe(50);
    expect($result['mushaf_a6'])->toBe(30);
    expect($result['iqra'])->toBe(20);
    expect($result['total_mushaf'])->toBe(80);
    expect($result['jenis_diminta'])->toContain('A5', 'A6', 'IQRA');
});

test('it parses case insensitive input', function () {
    $result = invokeMethod($this->import, 'parseJumlahKebutuhan', ['50 MUSHAF A5, 30 IQRA']);

    expect($result['mushaf_a5'])->toBe(50);
    expect($result['mushaf_a6'])->toBe(0);
    expect($result['iqra'])->toBe(30);
    expect($result['jenis_diminta'])->toContain('A5', 'IQRA');
});

test('it parses quantity only iqra', function () {
    $result = invokeMethod($this->import, 'parseJumlahKebutuhan', ['50 iqra']);

    expect($result['mushaf_a5'])->toBe(0);
    expect($result['mushaf_a6'])->toBe(0);
    expect($result['iqra'])->toBe(50);
    expect($result['jenis_diminta'])->toBe(['IQRA']);
});

test('it handles empty quantity input', function () {
    $result = invokeMethod($this->import, 'parseJumlahKebutuhan', ['']);

    expect($result['mushaf_a5'])->toBe(0);
    expect($result['mushaf_a6'])->toBe(0);
    expect($result['iqra'])->toBe(0);
    expect($result['total_mushaf'])->toBe(0);
    expect($result['jenis_diminta'])->toBe([]);
});

// ============================================
// NORMALIZE PHONE NUMBER TESTS
// ============================================

test('it normalizes phone number starting with 08', function () {
    $result = invokeMethod($this->import, 'normalizePhoneNumber', ['081234567890']);

    expect($result)->toBe('6281234567890');
});

test('it normalizes phone number with dashes', function () {
    $result = invokeMethod($this->import, 'normalizePhoneNumber', ['0856-7890-1234']);

    expect($result)->toBe('6285678901234');
});

test('it normalizes phone number starting with 62', function () {
    $result = invokeMethod($this->import, 'normalizePhoneNumber', ['62878-5555-6666']);

    expect($result)->toBe('6287855556666');
});

test('it normalizes phone number with plus and spaces', function () {
    $result = invokeMethod($this->import, 'normalizePhoneNumber', ['+62 812 3456 7890']);

    expect($result)->toBe('6281234567890');
});

test('it handles phone number with parentheses', function () {
    $result = invokeMethod($this->import, 'normalizePhoneNumber', ['(0856) 7890-1234']);

    expect($result)->toBe('6285678901234');
});

test('it handles already normalized phone number', function () {
    $result = invokeMethod($this->import, 'normalizePhoneNumber', ['6281234567890']);

    expect($result)->toBe('6281234567890');
});

// ============================================
// GOOGLE MAPS LINK PARSING TESTS
// ============================================

test('it extracts coordinates from google maps url with q parameter', function () {
    $url = 'https://maps.google.com/?q=-6.123456,106.789012';
    $result = invokeMethod($this->import, 'parseGoogleMapsLink', [$url]);

    expect($result['lat'])->toBe(-6.123456);
    expect($result['lng'])->toBe(106.789012);
});

test('it extracts coordinates from google maps url with at symbol', function () {
    $url = 'https://www.google.com/maps/place/@-6.123456,106.789012,17z';
    $result = invokeMethod($this->import, 'parseGoogleMapsLink', [$url]);

    expect($result['lat'])->toBe(-6.123456);
    expect($result['lng'])->toBe(106.789012);
});

test('it extracts coordinates from google maps url with ll parameter', function () {
    $url = 'https://maps.google.com/maps?ll=-6.123456,106.789012&z=15';
    $result = invokeMethod($this->import, 'parseGoogleMapsLink', [$url]);

    expect($result['lat'])->toBe(-6.123456);
    expect($result['lng'])->toBe(106.789012);
});

test('it returns null for shortened google maps url', function () {
    $url = 'https://goo.gl/maps/shortened';
    $result = invokeMethod($this->import, 'parseGoogleMapsLink', [$url]);

    expect($result)->toBeNull();
});

test('it returns null for empty google maps url', function () {
    $result = invokeMethod($this->import, 'parseGoogleMapsLink', ['']);

    expect($result)->toBeNull();
});

test('it returns null for invalid google maps url', function () {
    $url = 'https://www.example.com';
    $result = invokeMethod($this->import, 'parseGoogleMapsLink', [$url]);

    expect($result)->toBeNull();
});

// ============================================
// PARSE URGENSI TESTS
// ============================================

test('it parses urgensi rendah', function () {
    expect(invokeMethod($this->import, 'parseUrgensi', ['rendah']))->toBe('rendah');
    expect(invokeMethod($this->import, 'parseUrgensi', ['low']))->toBe('rendah');
    expect(invokeMethod($this->import, 'parseUrgensi', ['1']))->toBe('rendah');
});

test('it parses urgensi sedang', function () {
    expect(invokeMethod($this->import, 'parseUrgensi', ['sedang']))->toBe('sedang');
    expect(invokeMethod($this->import, 'parseUrgensi', ['medium']))->toBe('sedang');
    expect(invokeMethod($this->import, 'parseUrgensi', ['normal']))->toBe('sedang');
    expect(invokeMethod($this->import, 'parseUrgensi', ['2']))->toBe('sedang');
});

test('it parses urgensi tinggi', function () {
    expect(invokeMethod($this->import, 'parseUrgensi', ['tinggi']))->toBe('tinggi');
    expect(invokeMethod($this->import, 'parseUrgensi', ['high']))->toBe('tinggi');
    expect(invokeMethod($this->import, 'parseUrgensi', ['3']))->toBe('tinggi');
});

test('it parses urgensi mendesak', function () {
    expect(invokeMethod($this->import, 'parseUrgensi', ['mendesak']))->toBe('mendesak');
    expect(invokeMethod($this->import, 'parseUrgensi', ['urgent']))->toBe('mendesak');
    expect(invokeMethod($this->import, 'parseUrgensi', ['emergency']))->toBe('mendesak');
    expect(invokeMethod($this->import, 'parseUrgensi', ['4']))->toBe('mendesak');
});

test('it defaults to sedang for unknown urgensi', function () {
    expect(invokeMethod($this->import, 'parseUrgensi', ['unknown']))->toBe('sedang');
    expect(invokeMethod($this->import, 'parseUrgensi', ['']))->toBe('sedang');
});

test('it handles urgensi as deskripsi kebutuhan', function () {
    // Test dengan deskripsi seperti di file Excel
    expect(invokeMethod($this->import, 'parseUrgensi', ['Banyak Al-Qur\'an yang sudah rusak']))->toBe('sedang');
    expect(invokeMethod($this->import, 'parseUrgensi', ['Untuk kebutuhan Al-Qur\'an saat naik jilid']))->toBe('sedang');
    expect(invokeMethod($this->import, 'parseUrgensi', ['Untuk pembelajaran disekolah sejumlah siswa']))->toBe('sedang');
});

// ============================================
// COLLECTION/IMPORT TESTS
// ============================================

test('it successfully imports minimal mushaf request', function () {
    $rows = new Collection([
        [
            'nama_lembaga' => 'TPQ Al Falah',
            'nama_penanggung_jawab_1' => 'Ahmad',
            'nomor_hp' => '081234567890',
            'alamat_lengkap' => 'Jl. Test No. 123, Jakarta',
            'link_gmaps' => 'https://maps.google.com/?q=-6.123456,106.789012',
            'jumlah_kebutuhan_mushaf' => '100',
            'urgensi' => 'sedang',
        ],
    ]);

    $this->import->collection($rows);

    expect(MushafRequest::count())->toBe(1);

    $mushafRequest = MushafRequest::first();

    expect($mushafRequest->nama_lembaga)->toBe('TPQ Al Falah');
    expect($mushafRequest->nama_pengurus_1)->toBe('Ahmad');
    expect($mushafRequest->whatsapp_pengurus_1)->toBe('6281234567890');
    expect($mushafRequest->alamat_lengkap)->toBe('Jl. Test No. 123, Jakarta');
    expect($mushafRequest->jumlah_mushaf_a5)->toBe(100);
    expect($mushafRequest->jumlah_mushaf_a6)->toBe(0);
    expect($mushafRequest->jumlah_iqra)->toBe(0);
    expect((float) $mushafRequest->latitude)->toBe(-6.123456);
    expect((float) $mushafRequest->longitude)->toBe(106.789012);
    expect($mushafRequest->urgensi_request)->toBe('sedang');
    expect($mushafRequest->status)->toBe('pending');
    expect($mushafRequest->sumber_info)->toBe('Import Excel');
    expect($mushafRequest->kategori_lembaga)->toBe('Lembaga Lainnya');
    expect($mushafRequest->jabatan_pengurus_1)->toBe('Penanggung Jawab');
});

test('it imports mushaf request with mixed quantities', function () {
    $rows = new Collection([
        [
            'nama_lembaga' => 'MI Darul Huda',
            'nama_penanggung_jawab_1' => 'Siti',
            'nomor_hp' => '0856-7890-1234',
            'alamat_lengkap' => 'Jl. Pendidikan No. 45, Surabaya',
            'link_gmaps' => '',
            'jumlah_kebutuhan_mushaf' => '50 A5, 30 A6, 20 iqra',
            'urgensi' => 'tinggi',
        ],
    ]);

    $this->import->collection($rows);

    $mushafRequest = MushafRequest::first();

    expect($mushafRequest->jumlah_mushaf_a5)->toBe(50);
    expect($mushafRequest->jumlah_mushaf_a6)->toBe(30);
    expect($mushafRequest->jumlah_iqra)->toBe(20);
    expect($mushafRequest->jumlah_mushaf)->toBe(80);
    expect($mushafRequest->jenis_mushaf_diminta)->toContain('A5', 'A6', 'IQRA');
    expect($mushafRequest->urgensi_request)->toBe('tinggi');
});

test('it imports multiple mushaf requests', function () {
    $rows = new Collection([
        [
            'nama_lembaga' => 'TPQ 1',
            'nama_penanggung_jawab_1' => 'Ahmad',
            'nomor_hp' => '081111111111',
            'alamat_lengkap' => 'Alamat 1',
            'link_gmaps' => '',
            'jumlah_kebutuhan_mushaf' => '50',
            'urgensi' => 'sedang',
        ],
        [
            'nama_lembaga' => 'TPQ 2',
            'nama_penanggung_jawab_1' => 'Budi',
            'nomor_hp' => '082222222222',
            'alamat_lengkap' => 'Alamat 2',
            'link_gmaps' => '',
            'jumlah_kebutuhan_mushaf' => '75',
            'urgensi' => 'tinggi',
        ],
        [
            'nama_lembaga' => 'TPQ 3',
            'nama_penanggung_jawab_1' => 'Citra',
            'nomor_hp' => '083333333333',
            'alamat_lengkap' => 'Alamat 3',
            'link_gmaps' => '',
            'jumlah_kebutuhan_mushaf' => '100',
            'urgensi' => 'mendesak',
        ],
    ]);

    $this->import->collection($rows);

    expect(MushafRequest::count())->toBe(3);
    expect($this->import->getResults()['success_count'])->toBe(3);
    expect($this->import->getResults()['error_count'])->toBe(0);
});

test('it generates unique no_request for each import', function () {
    $rows = new Collection([
        [
            'nama_lembaga' => 'TPQ 1',
            'nama_penanggung_jawab_1' => 'Ahmad',
            'nomor_hp' => '081111111111',
            'alamat_lengkap' => 'Alamat 1',
            'link_gmaps' => '',
            'jumlah_kebutuhan_mushaf' => '50',
            'urgensi' => 'sedang',
        ],
        [
            'nama_lembaga' => 'TPQ 2',
            'nama_penanggung_jawab_1' => 'Budi',
            'nomor_hp' => '082222222222',
            'alamat_lengkap' => 'Alamat 2',
            'link_gmaps' => '',
            'jumlah_kebutuhan_mushaf' => '75',
            'urgensi' => 'tinggi',
        ],
    ]);

    $this->import->collection($rows);

    $requests = MushafRequest::all();

    expect($requests->count())->toBe(2);
    expect($requests[0]->no_request)->not->toBe($requests[1]->no_request);
    expect($requests[0]->no_request)->toMatch('/^REQ-\d{4}-\d{5}$/');
    expect($requests[1]->no_request)->toMatch('/^REQ-\d{4}-\d{5}$/');
});

test('it handles import with missing nama_penanggung_jawab', function () {
    $rows = new Collection([
        [
            'nama_lembaga' => 'TPQ Test',
            'nama_penanggung_jawab_1' => '', // Empty
            'nomor_hp' => '081234567890',
            'alamat_lengkap' => 'Alamat Test',
            'link_gmaps' => '',
            'jumlah_kebutuhan_mushaf' => '50',
            'urgensi' => 'sedang',
        ],
    ]);

    $this->import->collection($rows);

    expect(MushafRequest::count())->toBe(0);
    expect($this->import->getResults()['error_count'])->toBe(1);
    expect($this->import->getResults()['errors'][0]['error'])->toContain('Nama penanggung jawab wajib diisi');
});

test('it sets all optional fields to default values', function () {
    $rows = new Collection([
        [
            'nama_lembaga' => 'TPQ Test',
            'nama_penanggung_jawab_1' => 'Ahmad',
            'nomor_hp' => '081234567890',
            'alamat_lengkap' => 'Alamat Test',
            'link_gmaps' => '',
            'jumlah_kebutuhan_mushaf' => '50',
            'urgensi' => 'sedang',
        ],
    ]);

    $this->import->collection($rows);

    $mushafRequest = MushafRequest::first();

    // Verify all optional fields have defaults or are null
    expect($mushafRequest->nama_pengurus_2)->toBe('-');
    expect($mushafRequest->jabatan_pengurus_2)->toBe('-');
    expect($mushafRequest->whatsapp_pengurus_2)->toBe('-');
    expect($mushafRequest->kategori_lembaga)->toBe('Lembaga Lainnya');
    expect($mushafRequest->jabatan_pengurus_1)->toBe('Penanggung Jawab');
    expect($mushafRequest->provinsi)->toBeNull();
    expect($mushafRequest->provinsi_id)->toBeNull();
    expect($mushafRequest->kota_kabupaten)->toBeNull();
    expect($mushafRequest->kota_kabupaten_id)->toBeNull();
    expect($mushafRequest->kecamatan)->toBeNull();
    expect($mushafRequest->kecamatan_id)->toBeNull();
    expect($mushafRequest->kelurahan_desa)->toBeNull();
    expect($mushafRequest->kelurahan_desa_id)->toBeNull();
    expect($mushafRequest->kode_pos)->toBeNull();
    expect($mushafRequest->alamat_detail)->toBeNull();
    expect($mushafRequest->foto_santri_path)->toBeNull();
    expect($mushafRequest->foto_lembaga_path)->toBeNull();
    expect($mushafRequest->file_nama_santri_path)->toBeNull();
});

// ============================================
// HELPER FUNCTION
// ============================================

/**
 * Helper function to invoke private/protected methods
 */
function invokeMethod(&$object, $methodName, array $parameters = [])
{
    $reflection = new \ReflectionClass(get_class($object));
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);

    return $method->invokeArgs($object, $parameters);
}
