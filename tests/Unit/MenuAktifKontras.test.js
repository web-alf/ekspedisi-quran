/**
 * Kontras menu sidebar yang SEDANG AKTIF.
 *
 * Kejadian 27/10/2026: item aktif memakai latar merah + teks/ikon putih, tetapi kelas
 * `hover:bg-gray-100` di daftar kelas yang sama menang saat kursor melintas sehingga
 * latarnya berubah abu terang sementara ikonnya tetap putih — menu aktif jadi tak terbaca
 * (putih di atas putih). Aturan: menu aktif TIDAK boleh punya utilitas `hover:` sendiri;
 * gaya hover hanya untuk menu yang tidak aktif.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const layout = readFileSync(
    resolve(__dirname, '../../resources/js/Layouts/AdminLayout.svelte'),
    'utf8',
);

/** Ambil pasangan ternary `kondisi ? 'kelas aktif' : 'kelas biasa'` di sidebar. */
function pasanganMenu(sumber) {
    const pola = /is(?:CurrentRoute|DropdownActive)\([^)]*\)\s*\?\s*'([^']*)'\s*:\s*(?:\(?\s*)?'([^']*)'/g;
    const hasil = [];
    let m;
    while ((m = pola.exec(sumber)) !== null) {
        hasil.push({ aktif: m[1], biasa: m[2] });
    }
    return hasil;
}

describe('Menu sidebar aktif tetap terbaca', () => {
    const pasangan = pasanganMenu(layout);

    it('menemukan pasangan kelas menu yang diperiksa', () => {
        expect(pasangan.length).toBeGreaterThanOrEqual(3);
    });

    it('menu aktif memakai latar merah dan teks putih', () => {
        const menunya = pasangan.filter((p) => p.aktif.includes('bg-[#eb3434]') || p.aktif.includes('bg-gray-100'));
        expect(menunya.length).toBeGreaterThanOrEqual(3);
        for (const p of menunya.filter((x) => x.aktif.includes('bg-[#eb3434]'))) {
            expect(p.aktif).toContain('text-white');
        }
    });

    it('menu aktif tidak membawa kelas hover yang menimpa latarnya', () => {
        for (const p of pasangan) {
            expect(p.aktif).not.toMatch(/\bhover:/);
        }
    });

    it('gaya hover hanya dipasang pada menu yang tidak aktif', () => {
        const adaHover = pasangan.filter((p) => /\bhover:/.test(p.biasa));
        expect(adaHover.length).toBeGreaterThanOrEqual(3);
        for (const p of adaHover) {
            expect(p.biasa).toMatch(/hover:(bg|text)-/);
        }
    });
});
