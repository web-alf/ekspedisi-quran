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
 * 27/10/2026: SUSUNAN seksi Manager berbeda dari SPV (desain Manager Gudang: filter
 * gudang, Alur Mushaf per gudang, Perlu Tindakan paling bawah). Yang tetap dijaga
 * adalah gayanya — komponen yang sama, ikon terdaftar, tanpa hex lepas.
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

    it('SPV mempertahankan urutan bagiannya', () => {
        expect(bagian(blokSpv)).toEqual([
            ['cube', 'Packing Hari Ini'],
            // 30/10/2026: target mingguan/bulanan dari Manager Gudang.
            ['calendar', 'Target Periode'],
            // 27/10/2026: satu seksi "Stok" (tahap resi + mushaf belum ada muwakif).
            ['archive-box', 'Stok {ringkasanGudang.gudang.nama}'],
            ['users', 'Freelancer yang Kerja Hari Ini'],
            ['exclamation-circle', 'Perlu Tindakan'],
            ['clipboard-document-list', 'Beban Gudang'],
            ['archive-box', 'Stok Mushaf'],
        ]);
    });

    it('Manager: alur per gudang, lalu packing, stok per jenis, freelancer, dan Perlu Tindakan paling bawah', () => {
        expect(bagian(blokManager)).toEqual([
            ['archive-box', '{g.nama}'],
            ['cube', 'Packing Hari Ini'],
            ['calendar', 'Target Periode'],
            ['archive-box', 'Stok Belum Ada Wakif'],
            ['users', 'Freelancer yang Kerja Hari Ini'],
            ['exclamation-circle', 'Perlu Tindakan'],
        ]);
        // Satu seksi Alur Mushaf per gudang yang tampil (ditulis sekali, diulang).
        // Judul seksi = nama gudangnya saja (tanpa awalan "Alur Mushaf — ").
        expect(blokManager).toMatch(/\{#each gudangManager as g \(g\.id\)\}\s*<RingkasanSection[\s\S]*?judul="\{g\.nama\}"/);
        // Seksi lama yang angkanya kembar dengan alur per gudang sudah tidak ada.
        for (const judul of ['Stok Seluruh Gudang', 'Performa Gudang', 'Beban Gudang', 'Stok Mushaf']) {
            expect(blokManager).not.toContain(`judul="${judul}"`);
        }
    });

    it('Manager punya satu filter gudang yang dipakai semua seksi', () => {
        expect(blokManager).toMatch(/role="tablist" aria-label="Pilih gudang"/);
        expect(blokManager).toMatch(/stokAktif = SEMUA_GUDANG/);
        expect(skrip).toMatch(/gudangManager = gudangTampil\(ringkasanManager\?\.per_gudang \?\? \[\], stokAktif\)/);
        expect(skrip).toMatch(/packingManager = packingCakupan\(ringkasanManager, stokAktif\)/);
    });

    it('tidak lagi menulis kerangka section sendiri di dalam blok ringkasan', () => {
        expect(blokSpv).not.toMatch(/<section/);
        expect(blokManager).not.toMatch(/<section/);
    });

    it('menampilkan beban SPV sebagai kotak tanpa tahap Produksi; Manager tanpa tabel', () => {
        // "Proses Produksi" dinonaktifkan 27/10/2026: tahap itu tidak pernah dipakai alur.
        expect(skrip).not.toMatch(/\['produksi',\s*'Produksi'\]/);
        expect(skrip).toMatch(/\['pemesanan',\s*'Pemesanan'\]/);
        expect(blokSpv).toMatch(/\{#each TAHAP_BEBAN as/);
        // Beban Manager kembar dengan Alur Mushaf per gudang (query yang sama), jadi dibuang.
        expect(blokManager).not.toMatch(/TAHAP_BEBAN/);
        expect(blokManager).not.toMatch(/<table/);
    });

    it('memakai komponen kartu, perlu tindakan, dan kotak angka yang sama', () => {
        expect(blokSpv).toMatch(/<KartuStok\b/);
        expect(blokManager).toMatch(/<KartuAlur\b/);
        for (const blok of [blokSpv, blokManager]) {
            expect(blok).toMatch(/<PerluTindakan\b/);
            expect(blok).toMatch(/<StatTile\b/);
        }
    });

    // 29/10/2026: satu-satunya hex yang boleh = merah merek #eb3434 milik dashboard super-admin.
    const tanpaMerahMerek = (teks) => teks.replaceAll('#eb3434', '');

    it('tidak memakai warna hex lepas — hanya palet Tailwind dan merah merek', () => {
        expect(tanpaMerahMerek(sumber)).not.toMatch(/#[0-9a-fA-F]{6}\b/);
        for (const [nama, isi] of Object.entries(sumberKomponen)) {
            expect(tanpaMerahMerek(isi), nama).not.toMatch(/#[0-9a-fA-F]{6}\b/);
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
        expect(isi).toMatch(/hasPermission\('supervisor\.warehouse\.monitor'\)/);
        expect(isi).toMatch(/\{#if bolehBukaPenugasan\}[\s\S]*?\/admin\/supervisor\/warehouse-monitor[\s\S]*?\{\/if\}/);
    });

    it('Perlu Tindakan Manager memuat staf belum mulai dan stok habis; SPV tidak', () => {
        expect(blokManager).toMatch(/<PerluTindakan[\s\S]*?stafBelumMulai=[\s\S]*?stokHabis=\{stokHabisManager\}/);
        expect(blokSpv).not.toMatch(/stafBelumMulai=|stokHabis=/);
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

    /**
     * 29/10/2026: kartu disamakan dengan dashboard super-admin — halaman selebar area konten
     * (dulu max-w-7xl di tengah), kotak ikon seksi 40 px beraksen merah merek #eb3434 untuk semua
     * seksi (dulu 32 px, hijau/indigo/biru/amber per seksi).
     */
    it('mengikuti kartu dashboard super-admin: lebar penuh, kotak ikon 40 px, aksen merah', () => {
        const seksi = sumberKomponen.RingkasanSection;

        expect(markup).not.toContain('max-w-7xl mx-auto');
        expect(seksi).toContain('w-10 h-10 rounded-lg bg-red-50 text-[#eb3434]');
        expect(seksi).toContain('<HeroIcon name={icon} class="w-6 h-6" />');
        expect(seksi).not.toMatch(/export let warna/);
        expect(markup).not.toMatch(/<RingkasanSection[^>]*\swarna=/);
        expect(markup).not.toMatch(/indigo-(50|500|600)"?[^>]*>\s*(\{persen|\{packingManager\.persen|<HeroIcon name="chart-bar")/);
        expect(sumberKomponen.PerluTindakan).not.toContain('indigo');
        // Merahnya persis merah merek super-admin, bukan red-600/red-500 bawaan Tailwind.
        expect(markup).toContain('font-bold text-[#eb3434] leading-none');
        expect(markup).not.toMatch(/text-red-600 leading-none|rounded-full bg-red-500 transition-all/);
    });
});
