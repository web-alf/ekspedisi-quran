/**
 * Navigasi halaman Stok Mushaf (Fase 3 rombakan gudang).
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

describe('Navigasi halaman Stok Mushaf', () => {
    it('ada di menu dengan izin stok.read', () => {
        expect(navigasi).toContain("label: 'Stok Mushaf'");
        expect(navigasi).toContain("route: '/admin/warehouse/stok'");
        expect(navigasi).toContain("requiredPermissions: ['stok.read']");
    });

    it('halaman stok menampilkan isi + dokumen transfer', () => {
        expect(halamanStok).toContain('Belum Ada Muwakif');
        expect(halamanStok).toContain('/admin/warehouse/stok/entri');
        expect(halamanStok).toContain('/admin/warehouse/stok/transfer');
        expect(halamanStok).toContain('SPV gudang TUJUAN menerima');
    });

    it('aksi tulis tergantung izin, bukan keterlihatan saja', () => {
        expect(halamanStok).toContain('{#if bolehKelola}');
        expect(halamanStok).toContain('{#if bolehTransfer}');
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
     */
    it('membaca props datar yang benar-benar dikirim controller', () => {
        expect(halamanStok).toContain('{stok.gudang}');
        expect(halamanStok).toContain('{stok.jenis}');
        expect(halamanStok).toContain('{stok.terjemahan}');
        expect(halamanStok).not.toContain('stok.gudang?.nama');
        expect(halamanStok).not.toContain('stok.jenisQuran?.nama_jenis');
        expect(halamanStok).toContain('{baris.terjemahan}');
        expect(halamanStok).toContain('{transfer.terjemahan}');
    });

    /**
     * Temuan lapangan: A5/A6 ada varian ber-terjemahan dan tanpa. Varian WAJIB dipilih,
     * jadi tombol tidak boleh aktif sebelum pilihannya ada (server juga menolak 422).
     */
    it('mewajibkan pilihan terjemahan pada kedua form', () => {
        expect(halamanStok).toContain('Pilih varian...');
        expect(halamanStok).toContain('Ada terjemahan');
        expect(halamanStok).toContain('Tanpa terjemahan');
        expect(halamanStok).toContain("disabled={!entriGudang || !entriJenis || entriTerjemahan === '' || sibuk}");
        expect(halamanStok).toContain("disabled={!transferAsal || !transferTujuan || !transferJenis || transferTerjemahan === ''");
        // Varian ikut terkirim ke server pada kedua form.
        expect(halamanStok).toContain('terjemahan: Number(entriTerjemahan)');
        expect(halamanStok).toContain('terjemahan: Number(transferTerjemahan)');
    });

    it('menjelaskan padanan dus dari eks yang diketik', () => {
        expect(halamanStok).toContain('padananDus');
        expect(halamanStok).toContain('1 dus {namaJenis(entriJenis) || \'jenis ini\'} = {kapasitasJenis(entriJenis) || 0} eks');
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