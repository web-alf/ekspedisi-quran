/**
 * Riwayat Packing di halaman Proses Packing (29/10/2026).
 *
 * Halaman ini dulu hanya berisi pesan "Tidak Ada Tugas Packing" bila akun tidak punya
 * tugas hari ini. Seksi riwayat harus berada DI LUAR blok {#if noTaskMessage} supaya tampil
 * dengan atau tanpa tugas, dan partial reload setelah mencatat kerdus harus ikut memuat
 * noTaskMessage — kerdus terakhir bisa menyelesaikan tugas.
 *
 * Tes membaca sumber berkas (Vitest repo ini mengompilasi Svelte ke mode SSR).
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const sumber = readFileSync(resolve(__dirname, '../../resources/js/Pages/Warehouse/Packing.svelte'), 'utf8');
const markup = sumber.slice(sumber.indexOf('<AdminLayout>'));

describe('Seksi Riwayat Packing', () => {
    it('berada setelah blok tugas sehingga tampil dengan atau tanpa tugas hari ini', () => {
        // Blok tugas dibuka cabang pantauan pemantau (06/11/2026), lalu kartu tanpa tugas.
        const awalBlokTugas = markup.indexOf('{#if noTaskMessage && pantauan}');
        const seksi = markup.indexOf('judul="Riwayat Packing"');

        expect(awalBlokTugas).toBeGreaterThan(-1);
        expect(seksi).toBeGreaterThan(awalBlokTugas);
        // Penutup blok tugas ({/if} sebelum seksi) muncul sebelum seksi riwayat.
        const antara = markup.slice(awalBlokTugas, seksi);
        expect(antara.lastIndexOf('{/if}')).toBeGreaterThan(antara.lastIndexOf('{:else}'));
    });

    it('memakai kerangka RingkasanSection dan kolom petugas hanya untuk pemantau', () => {
        expect(markup).toMatch(/<RingkasanSection\s+icon="clock"\s+judul="Riwayat Packing"/);
        expect(markup).toMatch(/\{#if riwayatSemuaStaf\}\s*<th class="py-2 pr-4">Petugas<\/th>/);
        expect(markup).toContain('Belum ada riwayat packing');
    });

    it('tidak lagi menampilkan kolom Status (30/10/2026)', () => {
        const seksi = markup.slice(markup.indexOf('judul="Riwayat Packing"'));

        expect(seksi).not.toContain('>Status</th>');
        expect(seksi).not.toMatch(/Kedaluwarsa|Kadaluarsa/);
        expect(sumber).not.toContain('STATUS_RIWAYAT');
    });

    it('tautan Lihat semua juga tampil untuk pemantau dan riwayat mengikuti tab gudang (06/11/2026)', () => {
        const seksi = markup.slice(markup.indexOf('judul="Riwayat Packing"'));

        expect(seksi).toContain('{#if riwayatPacking.length > 0}');
        expect(seksi).not.toContain('!riwayatSemuaStaf && riwayatPacking.length > 0');
        expect(seksi).toContain('{#each riwayatTampil as baris (baris.id)}');
    });

    it('partial reload setelah mencatat kerdus ikut memuat pesan tugas selesai', () => {
        expect(sumber).toMatch(/only: \[[^\]]*'noTaskMessage'[^\]]*'tugasSelesai'[^\]]*\]/);
    });
});
