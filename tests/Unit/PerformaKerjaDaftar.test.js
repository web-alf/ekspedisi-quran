/**
 * Halaman Performa Kerja — daftar freelancer per tanggal untuk SPV/Manager (23/10/2026).
 *
 * Dashboard SPV kini hanya menampilkan freelancer yang sudah kerja hari ini; daftar
 * lengkap per tanggal (termasuk yang tidak mulai) pindah ke halaman ini. Tes membaca
 * sumber berkas seperti DashboardGudangKonsisten.test.js.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

function tanpaKomentar(teks) {
    return teks
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/(^|[^:])\/\/[^\n]*/g, '$1');
}

const sumber = readFileSync(resolve(__dirname, '../../resources/js/Pages/Warehouse/Performance.svelte'), 'utf8');
const batasMarkup = sumber.indexOf('</script>');
const skrip = tanpaKomentar(sumber.slice(0, batasMarkup));
const markup = tanpaKomentar(sumber.slice(batasMarkup));
// Batas akhirnya {:else} milik {#if daftarKerja} (diikuti wadah performa pribadi),
// bukan {:else} pertama di dalam daftar.
const blokTim = markup.match(/\{#if daftarKerja\}([\s\S]*?)\{:else\}\s*<div class="max-w-7xl mx-auto space-y-6">/)?.[1] ?? '';

describe('Performa Kerja — daftar freelancer per tanggal', () => {
    it('mendeklarasikan prop daftarKerja dan merender blok tim terpisah dari performa pribadi', () => {
        expect(skrip).toMatch(/export let daftarKerja\s*=\s*null/);
        expect(blokTim).not.toBe('');
        expect(markup).toMatch(/\{:else\}[\s\S]*Riwayat Tugas[\s\S]*\{\/if\}\s*<\/AdminLayout>/);
    });

    it('menampilkan daftar ke bawah, satu baris per freelancer', () => {
        expect(blokTim).toMatch(/<ul[^>]*divide-y[\s\S]*\{#each stafTampil as s \(s\.id\)\}[\s\S]*<li/);
    });

    it('memuat ulang hanya daftarKerja saat tanggal diganti, tanpa melewati hari ini', () => {
        expect(skrip).toMatch(/router\.get\('\/admin\/warehouse\/performance', \{ tanggal \}/);
        expect(skrip).toMatch(/only: \['daftarKerja'\]/);
        expect(skrip).toMatch(/tanggal > daftarKerja\.hari_ini/);
        expect(blokTim).toMatch(/<input type="date"[^>]*max=\{daftarKerja\.hari_ini\}/);
    });

    it('membedakan label status hari ini dan tanggal lampau', () => {
        expect(skrip).toMatch(/'Sedang kerja'/);
        expect(skrip).toMatch(/'Tidak tuntas'/);
        expect(skrip).toMatch(/'Belum mulai'/);
        expect(skrip).toMatch(/'Tidak mulai'/);
    });

    it('memakai komponen ringkasan yang sama dengan dashboard', () => {
        expect(blokTim).toMatch(/<RingkasanSection\b/);
        expect(blokTim).toMatch(/<StatTile\b/);
        expect(blokTim).toMatch(/<PilTitik\b/);
        expect(blokTim).not.toMatch(/#[0-9a-fA-F]{6}\b/);
    });

    it('menampilkan pesan kosong bila tidak ada yang ditugaskan', () => {
        expect(blokTim).toMatch(/\{#if stafTampil\.length > 0\}[\s\S]*\{:else\}[\s\S]*Tidak ada freelancer yang ditugaskan/);
    });
});
