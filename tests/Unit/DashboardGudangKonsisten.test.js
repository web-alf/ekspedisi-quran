/**
 * Dashboard Gudang — ringkasan SPV (satu gudang) dan Manager (dua gudang) seragam.
 *
 * Bug yang dijaga tes ini (23/10/2026): kedua ringkasan ditulis sebagai dua blok
 * markup terpisah di halaman yang sama, lalu hanya blok Manager yang dirombak.
 * Hasilnya dua gaya dalam satu halaman — kotak statistik, pil, warna aksen (hex
 * lepas #5e6ad2), ikon judul, urutan bagian, sampai kolom "Produksi" yang hilang
 * dari tabel beban Manager. Kini keduanya memakai komponen bersama di
 * Components/Warehouse; tes ini memastikan tidak ada yang kembali menulis
 * markupnya sendiri.
 *
 * Seperti DashboardGudangKerdus.test.js, tes membaca sumber berkas (Vitest repo
 * ini mengompilasi Svelte ke mode SSR).
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const baca = (rel) => readFileSync(resolve(__dirname, '../../resources/js', rel), 'utf8');

function tanpaKomentar(teks) {
    return teks
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/(^|[^:])\/\/[^\n]*/g, '$1');
}

const sumber = baca('Pages/Warehouse/Dashboard.svelte');
const batasMarkup = sumber.indexOf('</script>');
const skrip = tanpaKomentar(sumber.slice(0, batasMarkup));
const markup = tanpaKomentar(sumber.slice(batasMarkup));

const KOMPONEN = ['RingkasanSection', 'StatTile', 'PilTitik', 'KartuStok', 'PerluTindakan', 'FreelancerKerjaHariIni'];
const sumberKomponen = Object.fromEntries(
    KOMPONEN.map((n) => [n, tanpaKomentar(baca(`Components/Warehouse/${n}.svelte`))]),
);

const blokSpv = markup.match(/\{#if ringkasanGudang\}([\s\S]*?)\{\/if\}\s*\{#if ringkasanManager\}/)?.[1] ?? '';
const blokManager = markup.match(/\{#if ringkasanManager\}([\s\S]*?)\n {6}\{\/if\}/)?.[1] ?? '';

/** Pasangan [icon, judul] tiap RingkasanSection, berurutan. */
function bagian(blok) {
    return [...blok.matchAll(/<RingkasanSection\s+icon="([^"]+)"[\s\S]*?judul="([^"]+)"/g)].map((m) => [
        m[1],
        m[2].split(' — ')[0],
    ]);
}

describe('Dashboard Gudang — ringkasan SPV & Manager seragam', () => {
    it('menemukan kedua blok ringkasan', () => {
        expect(blokSpv).not.toBe('');
        expect(blokManager).not.toBe('');
    });

    it('memakai ikon dan urutan bagian yang sama; Manager hanya menambah Performa Gudang', () => {
        const manager = bagian(blokManager);
        const managerTanpaPerforma = manager.filter(([, judul]) => judul !== 'Performa Gudang');

        expect(bagian(blokSpv)).toEqual([
            ['cube', 'Packing Hari Ini'],
            // 27/10/2026: satu seksi "Stok" (tahap resi + mushaf belum ada muwakif).
            ['archive-box', 'Stok {ringkasanGudang.gudang.nama}'],
            ['users', 'Freelancer yang Kerja Hari Ini'],
            ['exclamation-circle', 'Perlu Tindakan'],
            ['clipboard-document-list', 'Beban Gudang'],
            ['archive-box', 'Stok Mushaf'],
        ]);
        // Manager punya seksi "Alur Mushaf" per gudang (2), SPV hanya gudangnya (1) —
        // jadi yang dibandingkan adalah JENIS seksi (gudang berulang diringkas).
        const ringkas = (daftar) => daftar
            .map(([ikon, judul]) => [ikon, judul.startsWith('Stok {') || judul === 'Stok Seluruh Gudang' ? 'Stok' : judul])
            .filter(([, judul], i, arr) => i === 0 || judul !== arr[i - 1][1]);

        expect(ringkas(managerTanpaPerforma)).toEqual(ringkas(bagian(blokSpv)));

        // Seksi "Stok" berdiri sendiri: SPV memakai judul gudangnya, Manager judul gabungan.
        expect(ringkas(managerTanpaPerforma)).toHaveLength(ringkas(bagian(blokSpv)).length);
        expect(manager[1]).toEqual(['archive-box', 'Stok Seluruh Gudang']);
        expect(manager).toContainEqual(['chart-pie', 'Performa Gudang']);
    });

    it('tidak lagi menulis kerangka section sendiri di dalam blok ringkasan', () => {
        expect(blokSpv).not.toMatch(/<section/);
        expect(blokManager).not.toMatch(/<section/);
    });

    it('menampilkan beban sebagai kotak di kedua peran tanpa tahap Produksi', () => {
        // "Proses Produksi" dinonaktifkan 27/10/2026: tahap itu tidak pernah dipakai alur.
        expect(skrip).not.toMatch(/\['produksi',\s*'Produksi'\]/);
        expect(skrip).toMatch(/\['pemesanan',\s*'Pemesanan'\]/);
        expect(blokSpv).toMatch(/\{#each TAHAP_BEBAN as/);
        expect(blokManager).toMatch(/\{#each TAHAP_BEBAN as/);
        expect(blokManager).not.toMatch(/<table/);
    });

    it('memakai komponen kartu stok dan perlu tindakan yang sama', () => {
        for (const blok of [blokSpv, blokManager]) {
            expect(blok).toMatch(/<KartuStok\b/);
            expect(blok).toMatch(/<PerluTindakan\b/);
            expect(blok).toMatch(/<StatTile\b/);
        }
    });

    it('tidak memakai warna hex lepas — hanya palet Tailwind', () => {
        expect(sumber).not.toMatch(/#[0-9a-fA-F]{6}\b/);
        for (const [nama, isi] of Object.entries(sumberKomponen)) {
            expect(isi, nama).not.toMatch(/#[0-9a-fA-F]{6}\b/);
        }
    });

    it('memakai ikon HeroIcon yang terdaftar, bukan karakter teks seperti ✓', () => {
        const heroIcon = baca('Components/UI/HeroIcon.svelte');
        const terdaftar = new Set([...heroIcon.matchAll(/'([a-z0-9-]+)':\s*Icon/g)].map((m) => m[1]));
        const semua = [markup, ...Object.values(sumberKomponen)].join('\n');
        const dipakai = new Set([
            ...[...semua.matchAll(/<HeroIcon name="([^"]+)"/g)].map((m) => m[1]),
            ...[...semua.matchAll(/<RingkasanSection\s+icon="([^"]+)"/g)].map((m) => m[1]),
        ]);

        expect(dipakai.size).toBeGreaterThan(0);
        for (const nama of dipakai) {
            expect(terdaftar.has(nama), `ikon "${nama}" tidak ada di HeroIcon`).toBe(true);
        }
        expect(semua).not.toContain('✓');
    });

    it('menyembunyikan tautan Perlu Tindakan di belakang izin halaman tujuannya', () => {
        const isi = sumberKomponen.PerluTindakan;
        expect(isi).toMatch(/hasPermission\('mushaf\.transfer'\)/);
        expect(isi).toMatch(/hasPermission\('muatan\.read'\)/);
        expect(isi).toMatch(/\{#if bolehBukaTransfer\}[\s\S]*?\/admin\/transfer-mushaf[\s\S]*?\{\/if\}/);
        expect(isi).toMatch(/\{#if bolehBukaMuatan\}[\s\S]*?\/admin\/muatan[\s\S]*?\{\/if\}/);
    });

    it('SPV dan Manager menampilkan freelancer yang ditugaskan hari ini', () => {
        expect(skrip).toMatch(/stafKerjaHariIni\s*=\s*\(ringkasanGudang\?\.packing\?\.per_staf \?\? \[\]\)/);
        expect(skrip).toMatch(/stafKerjaManager\s*=[\s\S]*?g\.staf_kerja/);
        expect(blokSpv).toMatch(/<FreelancerKerjaHariIni staf=\{stafKerjaHariIni\} \/>/);
        expect(blokManager).toMatch(/<FreelancerKerjaHariIni staf=\{stafKerjaManager\} \/>/);

        // Nama yang belum mulai tidak lagi disebut di mana pun.
        expect(sumber).not.toMatch(/belum_nama|gBelumNama/);
        expect(blokSpv).not.toMatch(/\{#each ringkasanGudang\.packing\.per_staf as/);
    });

    it('tautan daftar lengkap per tanggal dijaga izin halaman Performa Kerja', () => {
        const isi = tanpaKomentar(baca('Components/Warehouse/FreelancerKerjaHariIni.svelte'));
        expect(isi).toMatch(/hasPermission\('warehouse\.performance\.view'\)/);
        expect(isi).toMatch(/\{#if bolehLihatPerforma\}[\s\S]*?\/admin\/warehouse\/performance[\s\S]*?\{\/if\}/);
        expect(isi).toMatch(/\{#if staf\.length > 0\}[\s\S]*\{:else\}[\s\S]*Belum ada freelancer/);
    });

    it('menyembunyikan kartu pribadi staf bagi SPV/Manager yang hanya memantau', () => {
        expect(skrip).toMatch(/adalahPemantau\s*=\s*Boolean\(ringkasanGudang \|\| ringkasanManager\)/);
        expect(markup).toMatch(/\{#if !adalahPemantau \|\| todayTask\}/);
        expect(markup).toMatch(/\{#if !adalahPemantau \|\| recentBoxes\.length > 0\}/);
        expect(markup).toMatch(/\{#if !adalahPemantau\}[\s\S]*Performa Minggu Ini/);
    });
});
