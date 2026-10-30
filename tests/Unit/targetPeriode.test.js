/**
 * Util target periode (30/10/2026): pilihan minggu/bulan di modal Target Periode dan teks laju.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';
import { opsiPeriode, jumlahHariPeriode, seninMinggu, statusLaju } from '../../resources/js/utils/targetPeriode.js';

// Kamis 12 November 2026.
const hariIni = new Date(2026, 10, 12);

describe('opsiPeriode', () => {
    it('bulanan dimulai bulan berjalan, tanpa bulan yang sudah lewat', () => {
        const opsi = opsiPeriode('bulanan', hariIni, 3);

        expect(opsi.map((o) => o.nilai)).toEqual(['2026-11-01', '2026-12-01', '2027-01-01']);
        expect(opsi[0].label).toBe('November 2026 (1–30 Nov)');
    });

    it('mingguan dimulai Senin minggu berjalan dan menandai minggu ini', () => {
        const opsi = opsiPeriode('mingguan', hariIni, 3);

        expect(opsi.map((o) => o.nilai)).toEqual(['2026-11-09', '2026-11-16', '2026-11-23']);
        expect(opsi[0].label).toBe('Minggu ini · 9–15 Nov 2026');
        expect(opsiPeriode('mingguan', new Date(2026, 10, 26), 1)[0].label).toBe('Minggu ini · 23–29 Nov 2026');
        expect(opsiPeriode('mingguan', new Date(2026, 10, 30), 1)[0].label).toBe('Minggu ini · 30 Nov – 6 Des 2026');
    });

    it('Senin dari hari Minggu adalah Senin enam hari sebelumnya', () => {
        expect(seninMinggu(new Date(2026, 10, 15)).getDate()).toBe(9);
    });

    it('menghitung jumlah hari periode untuk bantuan "per hari"', () => {
        expect(jumlahHariPeriode('bulanan', '2026-11-01')).toBe(30);
        expect(jumlahHariPeriode('bulanan', '2027-02-01')).toBe(28);
        expect(jumlahHariPeriode('mingguan', '2026-11-09')).toBe(7);
    });
});

describe('statusLaju', () => {
    const dasar = { status: 'berjalan', target: 1200, dikemas: 430 };

    it.each([
        [{ ...dasar, selisih_laju: -50 }, 'Tertinggal 50 dari laju', 'amber'],
        [{ ...dasar, selisih_laju: 19 }, 'Di depan 19 dari laju', 'green'],
        [{ ...dasar, selisih_laju: 0 }, 'Sesuai laju', 'green'],
        [{ ...dasar, dikemas: 1200, selisih_laju: 720 }, 'Target tercapai', 'green'],
        [{ ...dasar, status: 'akan_datang', dikemas: 0, selisih_laju: 0 }, 'Belum dimulai', 'gray'],
    ])('%o → %s', (target, teks, warna) => {
        expect(statusLaju(target)).toEqual({ teks, warna });
    });
});

describe('Pemakaian di halaman', () => {
    const baca = (rel) => readFileSync(resolve(__dirname, '../../resources/js', rel), 'utf8');

    it('tombol dan aksi Ubah/Hapus hanya untuk yang boleh mengatur', () => {
        const monitor = baca('Pages/Supervisor/WarehouseMonitor.svelte');

        expect(monitor).toMatch(/\{#if bolehAturTargetPeriode\}\s*<button\s+on:click=\{\(\) => bukaTargetPeriode\(\)\}/);
        expect(monitor).toMatch(/\{#if bolehAturTargetPeriode\}\s*<td[\s\S]*?Ubah[\s\S]*?Hapus/);
        expect(monitor).toMatch(/targetPeriode = response\.data\.targetPeriode/);
    });
});
