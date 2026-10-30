/**
 * Seksi "Siap Dikemas" di Proses Packing (30/10/2026): kerangka komponen yang sama dengan
 * seksi lain (RingkasanSection, StatTile, PilTitik), letaknya di atas pemindai, dan angkanya
 * dimuat ulang setelah scan / catat kerdus.
 *
 * Tes membaca sumber berkas (Vitest repo ini mengompilasi Svelte ke mode SSR).
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const sumber = readFileSync(resolve(__dirname, '../../resources/js/Pages/Warehouse/Packing.svelte'), 'utf8');
const markup = sumber.slice(sumber.indexOf('<AdminLayout>'));

describe('Seksi Siap Dikemas', () => {
    it('memakai kerangka seksi, kotak angka, dan pil yang sama dengan dashboard', () => {
        expect(markup).toMatch(/<RingkasanSection\s+icon="archive-box"\s+judul="Siap Dikemas/);
        expect(markup).toContain('<StatTile label="Resi siap"');
        expect(markup).toContain('<StatTile label="Mushaf siap"');
        expect(markup).toContain('<StatTile label="Sisa tugas Anda"');
        expect(markup).toMatch(/<PilTitik warna="amber">Kurang/);
    });

    it('diletakkan di atas pemindai', () => {
        expect(markup.indexOf('judul="Siap Dikemas')).toBeLessThan(markup.indexOf('<!-- Left: Scanner -->'));
    });

    it('dimuat ulang setelah scan berhasil dan setelah catat kerdus', () => {
        expect(sumber).toMatch(/recentScans = \[newScan[^\n]*\n\s*muatUlangSiapDikemas\(\);/);
        expect(sumber).toMatch(/router\.reload\(\{ only: \['siapDikemas'\] \}\)/);
        expect(sumber).toMatch(/only: \[[^\]]*'riwayatPacking', 'siapDikemas'\]/);
    });
});
