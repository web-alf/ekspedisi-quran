/**
 * Kartu "Jatah dari Manager Gudang" (30/10/2026): jatah PJ ke SPV dibanding yang sudah
 * dibagi ke freelancer, di Dashboard Gudang (SPV & Manager) dan Monitor & Penugasan.
 *
 * Tes membaca sumber berkas (Vitest repo ini mengompilasi Svelte ke mode SSR).
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const baca = (rel) => readFileSync(resolve(__dirname, '../../resources/js', rel), 'utf8')
    .replace(/<!--[\s\S]*?-->/g, '');

const komponen = baca('Components/Warehouse/JatahGudang.svelte');
const dashboard = baca('Pages/Warehouse/Dashboard.svelte');
const monitor = baca('Pages/Supervisor/WarehouseMonitor.svelte');

describe('Kartu Jatah Gudang', () => {
    it('menampilkan sisa belum dibagi, kelebihan, atau sudah terbagi semua', () => {
        expect(komponen).toContain('Sisa {jatah.sisa} belum dibagi');
        expect(komponen).toContain('Lebih {jatah.lebih} dari jatah');
        expect(komponen).toContain('Sudah terbagi semua');
        expect(komponen).toContain('Belum ada jatah hari ini');
        // Satu-satunya hex yang boleh di komponen gudang: merah merek.
        expect(komponen.replaceAll('#eb3434', '')).not.toMatch(/#[0-9a-fA-F]{6}\b/);
    });

    it('dipakai di seksi Packing Hari Ini untuk SPV dan Manager', () => {
        expect(dashboard).toContain('<JatahGudang besar jatah={ringkasanGudang.packing.jatah} />');
        expect(dashboard).toMatch(/\{#each gudangManager as g \(g\.id\)\}\s*\{#if g\.jatah\}\s*<JatahGudang besar jatah=\{g\.jatah\} nama=\{g\.nama\} \/>/);
    });

    it('dipakai di Monitor & Penugasan dan ikut diperbarui polling /data', () => {
        expect(monitor).toContain('export let jatahGudang = []');
        expect(monitor).toMatch(/<JatahGudang jatah=\{g\}/);
        expect(monitor).toMatch(/jatahGudang = response\.data\.jatahGudang/);
    });
});
