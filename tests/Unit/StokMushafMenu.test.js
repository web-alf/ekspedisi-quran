/**
 * Navigasi halaman Stok Mushaf (Fase 3 rombakan gudang) + halaman Perpindahan Mushaf.
 *
 * Sejak 26/10/2026 dokumen transfer STOK tidak lagi hidup di halaman Stok Mushaf: form dan
 * daftarnya pindah ke halaman Perpindahan Mushaf sebagai tab "Stok tanpa muwakif", supaya
 * kedua jenis dokumen perpindahan (mushaf ber-resi & stok tanpa muwakif) ada di satu tempat
 * dan dokumen yang menunggu diterima muncul di kartu "Perlu Tindakan" dashboard.
 *
 * Menu mengikuti izin `stok.read`: pegang stok.read (semua peran gudang)
 * melihatnya; role tanpa izin itu tidak. Sengaja item mandiri — bukan tab
 * tersembunyi di halaman lain (aturan UI pemilik: fitur berdiri sendiri = menu
 * sendiri, tidak menduplikasi navigasi).
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const navigasi = readFileSync(
    resolve(__dirname, '../../resources/js/Layouts/AdminLayout.svelte'),
    'utf8',
).replace(/<!--[\s\S]*?-->/g, '');

const halamanStok = readFileSync(
    resolve(__dirname, '../../resources/js/Pages/Warehouse/StokMushaf.svelte'),
    'utf8',
).replace(/\/\*[\s\S]*?\*\//g, '');

const halamanPerpindahan = readFileSync(
    resolve(__dirname, '../../resources/js/Pages/Admin/TransferMushaf/Index.svelte'),
    'utf8',
).replace(/\/\*[\s\S]*?\*\//g, '');

describe('Navigasi halaman Stok Mushaf', () => {
    it('ada di menu dengan izin stok.read', () => {
        expect(navigasi).toContain("label: 'Stok Mushaf'");
        expect(navigasi).toContain("route: '/admin/warehouse/stok'");
        expect(navigasi).toContain("requiredPermissions: ['stok.read']");
    });

    it('halaman stok menampilkan isi, entri pergerakan, dan tautan ke perpindahan', () => {
        expect(halamanStok).toContain('Belum Ada Muwakif');
        expect(halamanStok).toContain('/admin/warehouse/stok/entri');
        // Form + daftar dokumen transfer pindah; halaman ini menyisakan tautan ke sana.
        expect(halamanStok).toContain('href="/admin/transfer-mushaf"');
        expect(halamanStok).not.toContain('/admin/warehouse/stok/transfer');
        expect(halamanStok).not.toContain('transferAsal');
    });

    /**
     * 27/10/2026: kartu isi stok memakai KOMPONEN & SUMBER DATA yang sama dengan dashboard
     * (KartuStok + RingkasanGudangService::stokKartu), jadi bentuknya identik dengan kartu
     * stok yang sudah ada di halaman lain — bukan kartu rancangan sendiri, dan bukan tabel.
     */
    it('isi stok memakai komponen KartuStok yang sama dengan dashboard', () => {
        expect(halamanStok).not.toContain('<table');
        expect(halamanStok).toContain("import KartuStok from '../../Components/Warehouse/KartuStok.svelte'");
        expect(halamanStok).toContain('export let stokKartu = []');
        expect(halamanStok).toContain('{#each stokKartu as grup');
        expect(halamanStok).toContain('{grup.gudang}');
        expect(halamanStok).toContain('<KartuStok {baris} />');
        // Kartu tidak lagi menyusun isi sendiri (kelas/angka buatan halaman).
        expect(halamanStok).not.toContain('rincianDus(');
        expect(halamanStok).not.toContain('kelompokStok');
    });

    it('aksi tulis stok tergantung izin, bukan keterlihatan saja', () => {
        expect(halamanStok).toContain('{#if bolehKelola}');
    });

    /**
     * Kejadian produksi 19/10/2026: POST ke /stok/entri dijawab REDIRECT 302 (bukan
     * JSON 422) karena fetch tidak mengirim `Accept: application/json` + X-Requested-With
     * — Laravel menganggapnya kunjungan halaman biasa. Browser menerima HTML, `res.json()`
     * gagal, dan petugas hanya melihat "Gagal menghubungi server." tanpa tahu sebabnya.
     */
    it('meminta JSON dari server supaya galat validasi tidak berubah jadi pengalihan halaman', () => {
        expect(halamanStok).toContain("Accept: 'application/json'");
        expect(halamanStok).toContain("'X-Requested-With': 'XMLHttpRequest'");
        // Respons non-JSON tetap harus menghasilkan pesan yang bisa dibaca.
        expect(halamanStok).toContain('Gagal (kode ${res.status})');
    });

    /**
     * Controller mengirim props datar (`gudang`, `jenis`, `terjemahan` sebagai teks).
     * Halaman pernah membacanya sebagai objek (`stok.gudang?.nama`) sehingga kolom
     * Gudang dan Jenis tampil KOSONG walau stoknya ada.
     *
     * Sejak 27/10/2026 kartu isi stok memakai komponen KartuStok (baris dari stokKartu);
     * yang tersisa memakai props datar adalah daftar Pergerakan Terakhir.
     */
    it('membaca props datar yang benar-benar dikirim controller', () => {
        expect(halamanStok).toContain('{baris.gudang}');
        expect(halamanStok).toContain('{baris.jenis}');
        expect(halamanStok).toContain('{baris.terjemahan}');
        expect(halamanStok).toContain('{baris.jumlah_eks}');
        expect(halamanStok).not.toContain('stok.gudang?.nama');
        expect(halamanStok).not.toContain('stok.jenisQuran?.nama_jenis');
    });

    /**
     * Temuan lapangan: A5/A6 ada varian ber-terjemahan dan tanpa. Varian WAJIB dipilih,
     * jadi tombol tidak boleh aktif sebelum pilihannya ada (server juga menolak 422).
     */
    it('mewajibkan pilihan varian pada form entri stok', () => {
        expect(halamanStok).toContain("'Dengan terjemahan'], [0, 'Tanpa terjemahan'");
        // Tombol simpan entri dijaga oleh bolehSimpan() (yang menolak varian kosong).
        expect(halamanStok).toContain('disabled={!bolehRekam}');
        // Kewajiban varian ditegakkan oleh bolehSimpan() di stokPratinjau.js
        // (diuji terpisah di tests/Unit/StokPratinjau.test.js).
        expect(halamanStok).toContain('terjemahan: entriTerjemahan');
        expect(halamanStok).toContain('terjemahan: Number(entriTerjemahan)');
    });

    it('menampilkan pratinjau stok sekarang ke sesudah sebelum disimpan', () => {
        expect(halamanStok).toContain('stokSaatIni(stokList');
        expect(halamanStok).toContain('hitungHasil(entriTipe, stokSekarang');
        expect(halamanStok).toContain('→ menjadi');
        expect(halamanStok).toContain('tidak cukup');
    });

    it('tidak menampilkan kolom gudang untuk SPV (gudang mengikuti akun)', () => {
        expect(halamanStok).toContain('{#if !tercakupGudang}');
        expect(halamanStok).toContain('gudang mengikuti akun Anda');
    });

    it('menjelaskan padanan dus dari eks yang diketik', () => {
        expect(halamanStok).toContain('padananDus');
        expect(halamanStok).toContain('1 dus {namaJenis(entriJenis) || \'jenis ini\'} = {kapasitasJenis(entriJenis) || 0} eks');
    });
});

describe('Halaman Perpindahan Mushaf — dua tab dokumen', () => {
    it('menyediakan tab mushaf ber-resi dan tab stok tanpa muwakif', () => {
        expect(halamanPerpindahan).toContain("label: 'Mushaf ber-resi'");
        expect(halamanPerpindahan).toContain("label: 'Stok tanpa muwakif'");
        expect(halamanPerpindahan).toContain('role="tablist"');
        // Tab stok hanya untuk pemegang izin stok.transfer.
        expect(halamanPerpindahan).toContain("bolehTransferStok ? [{ kunci: 'stok'");
    });

    it('memuat form + daftar dokumen transfer stok beserta aksinya', () => {
        expect(halamanPerpindahan).toContain('/admin/warehouse/stok/transfer');
        expect(halamanPerpindahan).toContain('Dokumen Transfer Stok Baru');
        expect(halamanPerpindahan).toContain('{dokumen.terjemahan}');
        expect(halamanPerpindahan).toContain('gudang TUJUAN menerima');
    });

    it('mewajibkan varian pada form transfer stok dan mengirimnya sebagai angka', () => {
        expect(halamanPerpindahan).toContain('Pilih varian...');
        // Nilai opsi berupa teks: varian yang diisi otomatis saat ganti jenis juga teks
        // ('0'/'1'), dan bind:value pada <select> mencocokkan nilainya secara ketat.
        expect(halamanPerpindahan).toContain('<option value="1">Ada terjemahan</option>');
        expect(halamanPerpindahan).toContain("stokTerjemahan === ''");
        expect(halamanPerpindahan).toContain('terjemahan: Number(stokTerjemahan)');
    });

    it('menahan dokumen yang melebihi sisa stok asal sebelum dikirim ke server', () => {
        expect(halamanPerpindahan).toContain('sisa stok asal');
        expect(halamanPerpindahan).toContain('melebihi stok asal');
        expect(halamanPerpindahan).toContain('sisaStokAsal !== null && Number(stokJumlah) > sisaStokAsal');
    });

    it('membatasi tombol terima/batalkan sesuai aturan server', () => {
        expect(halamanPerpindahan).toContain('peranPjGudang || (gudangSaya && Number(gudangSaya) === Number(dokumen.tujuan_id))');
        expect(halamanPerpindahan).toContain('Number(penggunaId) === Number(dokumen.dibuat_oleh_id)');
    });

    it('meminta JSON dari server untuk aksi dokumen (galat validasi tetap terbaca)', () => {
        expect(halamanPerpindahan).toContain("Accept: 'application/json'");
        expect(halamanPerpindahan).toContain("'X-Requested-With': 'XMLHttpRequest'");
        expect(halamanPerpindahan).toContain('Gagal (kode ${res.status})');
    });
});

/**
 * Monitor gudang: statistik performa dijaga izin LAIN (`supervisor.performance.view`)
 * daripada halamannya (`supervisor.warehouse.monitor`). Peran gudang boleh membuka
 * halaman, jadi dulu halaman memanggil endpoint performanya tiap 30 detik dan menerima
 * 403 beruntun (produksi 19/10/2026, 14:18–15:06). Kartunya kini hanya dirender dan
 * datanya hanya diambil bila izinnya ada.
 */
const monitor = readFileSync(
    resolve(__dirname, '../../resources/js/Pages/Supervisor/WarehouseMonitor.svelte'),
    'utf8',
).replace(/\/\*[\s\S]*?\*\//g, '');

describe('Monitor gudang: kartu performa mengikuti izin', () => {
    it('berhenti memanggil endpoint performa bila izinnya tidak ada', () => {
        expect(monitor).toContain('export let bolehLihatPerforma = false');
        expect(monitor).toContain('if (!bolehLihatPerforma) return;');
        expect(monitor).toContain('if (bolehLihatPerforma && selectedMonth ===');
    });

    it('menyembunyikan kartu performa dan tombol export tanpa izin', () => {
        expect(monitor).toContain('{#if bolehLihatPerforma}');
        expect(monitor).toContain('{#if bolehExportPerforma}');
        expect(monitor).toContain('export let bolehExportPerforma = false');
    });
});

describe('Varian terjemahan hanya untuk jenis yang memilikinya (IQRA tanpa varian)', () => {
    it('form entri stok menyembunyikan pilihan varian untuk jenis tanpa varian', () => {
        expect(halamanStok).toMatch(/adaVarianEntri = jenisPunyaVarian\(jenisList, entriJenis\)/);
        expect(halamanStok).toMatch(/varianSesudahGantiJenis\(jenisList, entriJenis, entriTerjemahan, jenisSebelumnya\)/);
        expect(halamanStok).toMatch(/\{#if adaVarianEntri\}\s*<div>\s*<span[^>]*>Varian/);
        // Pratinjau tidak menyebut "tanpa terjemahan" untuk IQRA.
        expect(halamanStok).not.toContain('{namaJenis(entriJenis)} {labelVarian');
        expect(halamanStok).toContain('{namaJenis(entriJenis)}{sebutanVarianEntri}');
    });

    it('form transfer stok menyembunyikan pilihan varian untuk jenis tanpa varian', () => {
        expect(halamanPerpindahan).toMatch(/adaVarianStok = jenisPunyaVarian\(jenisList, stokJenis\)/);
        expect(halamanPerpindahan).toMatch(/varianSesudahGantiJenis\(jenisList, stokJenis, stokTerjemahan, stokJenisSebelumnya\)/);
        expect(halamanPerpindahan).toMatch(/\{#if adaVarianStok\}\s*<label[^>]*>\s*<span[^>]*>Varian/);
    });
});

