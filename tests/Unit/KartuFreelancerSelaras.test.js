/**
 * Card halaman freelancer selaras dengan dashboard super-admin (29/10/2026).
 *
 * Acuan kartu dashboard super-admin: `rounded-xl`, `border-gray-100`, `shadow-sm`, padding
 * menyusut di layar kecil, kotak ikon beraksen. Proses Packing, Daftar Kerdus, dan Performa
 * Kerja dulu memakai `rounded-lg` + `border-gray-200` + `p-6` tetap; kartu angka Data Resi
 * memotong labelnya ("Pe…", "Dal…") karena ikon di samping label pada kolom sempit.
 *
 * Tes membaca sumber berkas (Vitest repo ini mengompilasi Svelte ke mode SSR).
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const baca = (rel) => readFileSync(resolve(__dirname, '../../resources/js/Pages', rel), 'utf8')
    .replace(/<!--[\s\S]*?-->/g, '');

const HALAMAN = {
    'Proses Packing': 'Warehouse/Packing.svelte',
    'Daftar Kerdus': 'Admin/BoxTracking/Index.svelte',
    'Performa Kerja': 'Warehouse/Performance.svelte',
    'Data Resi': 'Admin/Pengiriman/Index.svelte',
};

describe('Card halaman freelancer mengikuti kartu dashboard super-admin', () => {
    it.each(Object.entries(HALAMAN))('%s tidak lagi memakai kartu rounded-lg/border-gray-200 lama', (_, berkas) => {
        const sumber = baca(berkas);

        expect(sumber).not.toMatch(/bg-white rounded-lg shadow-sm border/);
        expect(sumber).toContain('bg-white rounded-xl shadow-sm border border-gray-100');
    });

    it('kartu angka Daftar Kerdus dan Performa Kerja berpola ikon di atas, angka, lalu label', () => {
        for (const berkas of [HALAMAN['Daftar Kerdus'], HALAMAN['Performa Kerja']]) {
            const sumber = baca(berkas);

            expect(sumber).toContain('w-10 h-10 rounded-lg flex items-center justify-center mb-3 {kartu.aksen}');
            expect(sumber).toContain('<p class="text-xl md:text-2xl font-bold');
            // Ikon bulat di kanan (pola lama Performa Kerja) tidak dipakai lagi.
            expect(sumber).not.toContain('rounded-full">\n            <HeroIcon');
        }
    });

    it('label kartu angka Data Resi tidak dipotong', () => {
        const sumber = baca(HALAMAN['Data Resi']);
        const kartuAngka = sumber.slice(
            sumber.indexOf('<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-6 mb-6">'),
            sumber.indexOf('<!-- Search and Filters -->'),
        );

        expect(kartuAngka).not.toContain('truncate');
        expect(kartuAngka.match(/flex flex-col xl:flex-row xl:items-center/g)).toHaveLength(5);
    });
});
