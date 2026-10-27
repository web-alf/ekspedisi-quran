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

    it('SPV memuat empat kartu tahap resi dan lima kartu muwakif', () => {
        for (const label of ['Proses Pesan', 'Proses Packing', 'Selesai Packing', 'Sudah Dikarduskan · Siap Distribusi']) {
            expect(blokSpv).toContain(`label="${label}"`);
        }
        // Kartu muwakif dirender dari props (lima baris tetap dari server).
        expect(blokSpv).toMatch(/stok\.muwakif as baris/);
        expect(blokSpv).toContain('Mushaf belum ada muwakif');
    });

    it('Manager memuat lima kartu alur dengan label dan satuan yang disepakati klien', () => {
        const kartuAlur = [
            ['Sedang Dipesan', 'eks'],
            ['Stok Siap, Belum Ada Wakif', 'eks'],
            ['Sedang Dikemas', 'resi'],
            ['Selesai Dikemas', 'resi'],
            // Dihitung per KERDUS tersegel, bukan per eks (keputusan 27/10/2026).
            ['Siap Dikirim', 'kerdus'],
        ];
        for (const [label, satuan] of kartuAlur) {
            expect(blokManager).toMatch(new RegExp(`label="${label}"[\\s\\S]*?satuan="${satuan}"`));
        }
        expect(blokManager).toMatch(/label="Siap Dikirim"\s+nilai=\{numberId\(g\.stok\.kerdus\.jumlah\)\}/);
        // Stok tanpa resi bukan tahap alur — dibedakan dengan garis putus.
        expect(blokManager).toMatch(/label="Stok Siap, Belum Ada Wakif"[\s\S]*?nada="cadangan"/);
    });

    it('Manager: satu seksi alur per gudang; SPV satu seksi gudangnya', () => {
        expect(blokManager).toContain('{#each gudangManager as g (g.id)}');
        expect(blokManager).toContain('judul="Alur Mushaf — {g.nama}"');
        expect(blokManager).not.toContain('judul="Stok Seluruh Gudang"');

        expect(blokSpv).toContain('judul="Stok {ringkasanGudang.gudang.nama}"');
    });

    it('Manager menyebut resi yang belum punya gudang di luar seksi per gudang', () => {
        // Resi tanpa gudang tidak masuk Semarang maupun Boyolali; tanpa catatan ini angkanya
        // hilang dari pandangan PJ.
        expect(blokManager).toMatch(/\{#if adaTanpaGudang\}[\s\S]*?tanpaGudang\.pemesanan[\s\S]*?tanpaGudang\.pemesanan_eks/);
        expect(blokManager).toContain('Belum punya gudang tujuan');
    });

    it('seksi stok tidak memakai tabel', () => {
        expect(blokManager).not.toContain('<table');
        const seksi = blokSpv.slice(blokSpv.indexOf('judul="Stok'), blokSpv.indexOf('</RingkasanSection>', blokSpv.indexOf('judul="Stok')));
        expect(seksi).not.toContain('<table');
    });

    it('komponen kartu memakai kelas kotak angka yang sudah ada', () => {
        expect(kartu).toContain('rounded-xl p-3');
        expect(kartu).toMatch(/biasa: 'border-gray-200'/);
        expect(kartu).toContain('text-[10px] font-semibold uppercase tracking-wide text-gray-500');
        expect(kartu).toContain('text-2xl font-bold text-gray-900');
    });

    it('kartu hanya menampilkan keterangan bila diisi', () => {
        expect(kartu).toMatch(/\{#if keterangan\}/);
    });
});
