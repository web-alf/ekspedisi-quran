/**
 * Kartu Dashboard Gudang diperbesar dan bar "Progress kemas gudang" dibuang (01/11/2026).
 *
 * Kotak angka (StatTile, KartuStok, JatahGudang, KartuTargetPeriode, PilTitik) juga dipakai
 * Proses Packing, Performa Kerja, Stok Mushaf, dan Monitor & Penugasan. Ukuran besar hanya
 * untuk Dashboard Gudang, jadi komponennya punya prop `besar` (bawaan false) dan hanya
 * dashboard yang mengisinya. Tes membaca sumber berkas seperti DashboardGudangKonsisten.test.js.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const baca = (rel) => readFileSync(resolve(__dirname, '../../resources/js', rel), 'utf8');

const halaman = baca('Pages/Warehouse/Dashboard.svelte');
const ringkasan = halaman.slice(halaman.indexOf('{#if ringkasanGudang}'), halaman.indexOf('{#if !adalahPemantau || todayTask}'));
const blokSpv = ringkasan.slice(0, ringkasan.indexOf('{#if ringkasanManager}'));
const blokManager = ringkasan.slice(ringkasan.indexOf('{#if ringkasanManager}'));

/** Isi seksi "Packing Hari Ini" pada satu blok ringkasan. */
const seksiPacking = (blok) => {
    const awal = blok.indexOf('judul="Packing Hari Ini');
    return blok.slice(awal, blok.indexOf('</RingkasanSection>', awal));
};

describe('Dashboard Gudang — kartu besar', () => {
    it('semua kotak angka di ringkasan dashboard memakai ukuran besar', () => {
        const pemakaian = [...ringkasan.matchAll(/<(StatTile|KartuStok|JatahGudang|KartuTargetPeriode)\b[^>]*>/g)];
        expect(pemakaian.length).toBeGreaterThan(0);
        for (const [tag] of pemakaian) {
            expect(tag, tag).toMatch(/\sbesar(\s|\/|>)/);
        }
    });

    it('pil di dalam kartu dashboard ikut besar', () => {
        const pil = [...ringkasan.matchAll(/<PilTitik\b[^>]*>/g)].map(([tag]) => tag);
        expect(pil.length).toBeGreaterThan(0);
        for (const tag of pil) {
            expect(tag, tag).toMatch(/\sbesar(\s|>)/);
        }
    });

    it('jarak antar kartu di grid ringkasan 16 px (gap-4), tidak ada lagi gap-3', () => {
        expect(ringkasan).not.toMatch(/class="grid [^"]*\bgap-3\b/);
        expect(ringkasan).toContain('<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">');
    });

    it('kartu stok per jenis Manager (ditulis di halaman) memakai ukuran yang sama', () => {
        expect(blokManager).toContain('rounded-xl p-4 sm:p-5 min-h-[7.5rem] sm:min-h-[8.5rem] min-w-0');
        expect(blokManager).toMatch(/text-3xl sm:text-4xl font-bold text-gray-900 leading-none">\s*\{numberId\(jenis\.total\)\}/);
    });
});

describe('Dashboard Gudang — tanpa bar Progress kemas gudang', () => {
    it('SPV tidak lagi menampilkan teks dan bar progres kemas', () => {
        const seksi = seksiPacking(blokSpv);
        expect(seksi).not.toBe('');
        expect(halaman).not.toContain('Progress kemas gudang');
        expect(seksi).not.toContain('staf bertugas');
        expect(seksi).not.toMatch(/h-2\.5 rounded-full bg-gray-200/);
        expect(seksi).not.toMatch(/width: \{Math\.min\(100, persenPacking\)\}%/);
    });

    it('Manager tidak lagi menampilkan bar progres utama, bar kecil per gudang tetap ada', () => {
        const seksi = seksiPacking(blokManager);
        expect(seksi).not.toMatch(/h-2\.5 rounded-full bg-gray-200/);
        expect(seksi).not.toMatch(/width: \{Math\.min\(100, packingManager\.persen\)\}%/);
        expect(seksi).toMatch(/\{#if semuaGudang\}[\s\S]*?h-1\.5 rounded-full bg-gray-200[\s\S]*?g\.persen/);
    });

    it('persen tercapai tetap tampil di kanan judul dan di pil kartu Sudah dikemas', () => {
        expect(seksiPacking(blokSpv)).toMatch(/slot="kanan"[\s\S]*?\{persenPacking\}%/);
        expect(seksiPacking(blokSpv)).toMatch(/<PilTitik besar class="mt-2">\{persenPacking\}%<\/PilTitik>/);
        expect(seksiPacking(blokManager)).toMatch(/<PilTitik besar class="mt-2">\{packingManager\.persen\}%<\/PilTitik>/);
    });
});

describe('Halaman lain tetap memakai ukuran kartu biasa', () => {
    it.each([
        ['Pages/Warehouse/Packing.svelte', /<StatTile\b[^>]*>/g],
        ['Pages/Warehouse/Performance.svelte', /<(StatTile|PilTitik)\b[^>]*>/g],
        ['Pages/Warehouse/StokMushaf.svelte', /<KartuStok\b[^>]*>/g],
        ['Pages/Supervisor/WarehouseMonitor.svelte', /<(JatahGudang|KartuTargetPeriode)\b[^>]*>/g],
    ])('%s tidak mengisi prop besar', (berkas, pola) => {
        const tag = [...baca(berkas).matchAll(pola)].map(([t]) => t);
        expect(tag.length).toBeGreaterThan(0);
        for (const t of tag) {
            expect(t, t).not.toMatch(/\sbesar(\s|\/|>)/);
        }
    });

    it.each(['StatTile', 'KartuStok', 'JatahGudang', 'KartuTargetPeriode', 'PilTitik'])(
        '%s: prop besar bawaannya false dan ukuran biasa tetap ada',
        (nama) => {
            const sumber = baca(`Components/Warehouse/${nama}.svelte`);
            expect(sumber).toMatch(/export let besar = false;/);
            expect(sumber).toMatch(/text-\[10px\]|text-xs/);
        },
    );

    it('ukuran biasa StatTile tidak berubah', () => {
        const sumber = baca('Components/Warehouse/StatTile.svelte');
        expect(sumber).toContain("besar ? 'p-4 sm:p-5 min-h-[7.5rem] sm:min-h-[8.5rem]' : 'p-3'");
        expect(sumber).toContain("besar ? 'text-xs mb-1.5' : 'text-[10px] mb-1'");
        expect(sumber).toContain("besar ? 'text-3xl sm:text-4xl' : 'text-2xl'");
    });
});
