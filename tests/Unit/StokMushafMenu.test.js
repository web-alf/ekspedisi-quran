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
});