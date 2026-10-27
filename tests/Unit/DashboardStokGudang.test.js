/**
 * Seksi "Stok" di Dashboard Gudang (keputusan pemilik 27/10/2026).
 *
 * Satu seksi untuk SPV (gudangnya) dan satu untuk Manager (semua gudang), masing-masing
 * berisi dua kelompok kartu: tahap resi (4 kartu) dan mushaf belum ada muwakif (5 kartu).
 * Komponen kartunya SATU (`KartuAlur`) dan kelasnya sama dengan kotak angka dashboard.
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

describe('Seksi Stok dashboard gudang', () => {
    it('memakai komponen KartuAlur di kedua blok', () => {
        expect(halaman).toContain("import KartuAlur from '../../Components/Warehouse/KartuAlur.svelte'");
        expect(blokSpv).toMatch(/<KartuAlur\b/);
        expect(blokManager).toMatch(/<KartuAlur\b/);
    });

    it('memuat empat kartu tahap resi dan lima kartu muwakif di kedua blok', () => {
        for (const blok of [blokSpv, blokManager]) {
            for (const label of ['Proses Pesan', 'Proses Packing', 'Selesai Packing', 'Sudah Dikarduskan · Siap Distribusi']) {
                expect(blok).toContain(`label="${label}"`);
            }
            // Kartu muwakif dirender dari props (lima baris tetap dari server).
            expect(blok).toContain('{#each ');
            expect(blok).toMatch(/stok\.muwakif as baris/);
            expect(blok).toContain('Mushaf belum ada muwakif');
        }
    });

    it('SPV memakai judul gudangnya, Manager memakai judul seluruh gudang', () => {
        expect(blokSpv).toContain('judul="Stok {ringkasanGudang.gudang.nama}"');
        expect(blokManager).toContain('judul="Stok Seluruh Gudang"');
    });

    it('seksi stok tidak memakai tabel', () => {
        for (const blok of [blokSpv, blokManager]) {
            const seksi = blok.slice(blok.indexOf('judul="Stok'), blok.indexOf('</RingkasanSection>', blok.indexOf('judul="Stok')));
            expect(seksi).not.toContain('<table');
        }
    });

    it('komponen kartu memakai kelas kotak angka yang sudah ada', () => {
        expect(kartu).toContain('border border-gray-200 rounded-xl p-3');
        expect(kartu).toContain('text-[10px] font-semibold uppercase tracking-wide text-gray-500');
        expect(kartu).toContain('text-2xl font-bold text-gray-900');
    });

    it('kartu hanya menampilkan keterangan bila diisi', () => {
        expect(kartu).toMatch(/\{#if keterangan\}/);
    });
});
