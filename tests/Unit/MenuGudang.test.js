/**
 * Sidebar khusus peran gudang (keputusan pemilik sistem).
 *
 * Login gudang (PJ `gudang@`, SPV, maupun freelancer) harus melihat sidebar
 * SELURUHNYA menu gudang — bukan menu admin yang tersisa karena ada izin
 * campuran. Isi daftar tetap difilter izin, jadi mata tiap peran berbeda:
 * - PJ/SPV: semua menu gudang;
 * - freelancer: tanpa Monitor & Penugasan (izinnya tidak ada).
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const nav = readFileSync(
    resolve(__dirname, '../../resources/js/Layouts/AdminLayout.svelte'),
    'utf8',
).replace(/<!--[\s\S]*?-->/g, '');

it('ada cabang isPeranGudang yang menimpa menu admin', () => {
    expect(nav).toContain('isPeranGudang');
    expect(nav).toContain('function getAllNavigationItems() {\n    if (isPeranGudang) {');
});

it('menu gudang memuat daftar lengkap dengan rute yang benar', () => {
    for (const label of [
        'Dashboard Gudang',
        'Proses Packing',
        'Stok Mushaf',
        'Daftar Kerdus',
        'Monitor & Penugasan',
        'Data Resi',
        'Performa Kerja',
    ]) {
        expect(nav).toContain(`label: '${label}'`);
    }

    for (const rute of [
        '/admin/warehouse',
        '/admin/warehouse/packing',
        '/admin/warehouse/stok',
        '/admin/box-tracking',
        '/admin/supervisor/warehouse-monitor',
        '/admin/pengiriman',
        '/admin/warehouse/performance',
    ]) {
        expect(nav).toContain(`route: '${rute}'`);
    }
});

it('peran yang menandai gudang: warehouse, manager-repacking, spv-gudang', () => {
    for (const peran of ['warehouse', 'manager-repacking', 'spv-gudang']) {
        expect(nav).toContain(`'${peran}'`);
    }
});