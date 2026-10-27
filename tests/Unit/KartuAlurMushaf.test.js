/**
 * Empat kartu "Alur Mushaf" di Dashboard Gudang (keputusan pemilik 27/10/2026).
 *
 * Dashboard punya DUA blok ringkasan — SPV (satu gudang) dan Manager (dua gudang) —
 * dan keduanya wajib memakai komponen kartu yang SAMA. Kalau satu blok disunting sendiri,
 * tampilannya menyimpang; tes ini mengunci: komponen diimpor, empat kartu ada di kedua
 * blok, dan rincian per gudang hanya dirender oleh komponen (bukan tabel/kelas buatan).
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const halaman = readFileSync(
    resolve(__dirname, '../../resources/js/Pages/Warehouse/Dashboard.svelte'),
    'utf8',
);

const kartu = readFileSync(
    resolve(__dirname, '../../resources/js/Components/Warehouse/KartuAlur.svelte'),
    'utf8',
);

const blokSpv = halaman.slice(halaman.indexOf('{#if ringkasanGudang}'), halaman.indexOf('{#if ringkasanManager}'));
const blokManager = halaman.slice(halaman.indexOf('{#if ringkasanManager}'));

describe('Kartu Alur Mushaf', () => {
    it('memakai komponen KartuAlur, bukan markah kartu buatan halaman', () => {
        expect(halaman).toContain("import KartuAlur from '../../Components/Warehouse/KartuAlur.svelte'");
        expect(blokSpv).toMatch(/<KartuAlur\b/);
        expect(blokManager).toMatch(/<KartuAlur\b/);
    });

    it('memuat empat kartu yang diminta di kedua blok', () => {
        for (const blok of [blokSpv, blokManager]) {
            const label = [...blok.matchAll(/label="([^"]+)"/g)].map((m) => m[1]);
            expect(label).toContain('Proses Pemesanan');
            expect(label).toContain('Quran Ready · Belum Ada Muwakif');
            expect(label).toContain('Proses Packing');
            expect(label).toContain('Selesai Packing · Siap Distribusi');
        }
    });

    it('seksi Alur Mushaf tidak memakai tabel', () => {
        const seksi = blokSpv.slice(blokSpv.indexOf('judul="Alur Mushaf"') - 400);
        expect(seksi.slice(0, 2500)).not.toContain('<table');
    });

    it('komponen kartu memakai kelas kotak angka yang sudah ada', () => {
        // Samakan dengan StatTile: border, radius 12px, label kapital kecil, angka 2xl.
        expect(kartu).toContain('border border-gray-200 rounded-xl p-3');
        expect(kartu).toContain('text-[10px] font-semibold uppercase tracking-wide text-gray-500');
        expect(kartu).toContain('text-2xl font-bold text-gray-900');
    });

    it('menyembunyikan rincian gudang bila gudangnya hanya satu', () => {
        expect(kartu).toMatch(/\{#if rincian\.length > 1\}/);
    });
});
