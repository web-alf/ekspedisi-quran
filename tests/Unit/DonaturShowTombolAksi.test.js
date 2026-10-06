/**
 * Halaman rincian donatur (/admin/donatur/{id}) dibuka juga oleh role yang hanya
 * boleh MEMBACA — mis. Staff Gudang memegang `donatur.read` untuk melihat tujuan
 * kirim. Tombol Edit & Hapus tidak boleh tampil bagi mereka: tombolnya akan
 * ditolak server (rute butuh donatur.update / donatur.delete), dan pengguna
 * mengira itu kerusakan aplikasi.
 *
 * Vitest di repo ini mengompilasi Svelte ke mode SSR sehingga onMount tidak
 * berjalan; karena itu tes membaca sumber berkas, bukan merender komponen.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const sumber = readFileSync(
    resolve(__dirname, '../../resources/js/Pages/Admin/Donatur/Show.svelte'),
    'utf8',
);

const izinSumber = readFileSync(
    resolve(__dirname, '../../resources/js/utils/permissions.js'),
    'utf8',
);

describe('Donatur Show — tombol aksi mengikuti izin', () => {
    it('memakai izin donatur.update dan donatur.delete, bukan peran', () => {
        expect(sumber).toContain('can.donatur.update()');
        expect(sumber).toContain('can.donatur.delete()');
    });

    it('menyembunyikan tombol Edit di belakang izin update', () => {
        const header = sumber.slice(0, sumber.indexOf('<!-- Actions'));

        expect(header).toMatch(/\{#if bolehUbah\}[\s\S]*?handleEdit[\s\S]*?\{\/if\}/);
    });

    it('menyembunyikan tombol Hapus di belakang izin delete', () => {
        expect(sumber).toMatch(/\{#if bolehHapus\}[\s\S]*?handleDelete[\s\S]*?\{\/if\}/);
    });

    it('menghilangkan seluruh kartu Aksi bila tidak ada wewenang', () => {
        expect(sumber).toContain('{#if adaAksi}');
        expect(sumber).toMatch(/\{#if adaAksi\}[\s\S]*?Aksi[\s\S]*?\{\/if\}/);
    });

    it('setiap pemanggilan handleEdit/handleDelete berada di dalam penjaga izin', () => {
        // Penjaga sejati: menelusuri SELURUH berkas dengan tumpukan blok Svelte,
        // bukan hanya dua tempat yang sudah diperbaiki. Kalau suatu saat ada
        // tombol ketiga ditambahkan tanpa penjaga, tes ini gagal.
        //
        // Menghitung jumlah `{#if}` vs `{/if}` saja tidak cukup — `{#if
        // hasActiveShipments}` juga membuka blok, jadi hitungannya meleset.
        const tag = /\{#(if|each|await)\s+([^}]*)\}|\{\/(if|each|await)\}|\{:else\}/g;
        const tumpukan = [];
        const pelanggar = [];
        let posisiTerakhir = 0;
        let cocok;

        while ((cocok = tag.exec(sumber)) !== null) {
            const potongan = sumber.slice(posisiTerakhir, cocok.index);
            posisiTerakhir = tag.lastIndex;

            for (const klik of potongan.matchAll(/on:click=\{handle(Edit|Delete)\}/g)) {
                const penjaga = ['bolehUbah', 'bolehHapus', 'adaAksi'];
                const terbuka = tumpukan.some((t) => penjaga.includes(t.trim()));

                if (!terbuka) {
                    pelanggar.push(`handle${klik[1]}`);
                }
            }

            if (cocok[1]) {
                tumpukan.push(cocok[2]);
            } else if (cocok[3]) {
                tumpukan.pop();
            }
        }

        expect(
            pelanggar,
            `pemanggilan tanpa penjaga izin: ${pelanggar.join(', ')}`,
        ).toEqual([]);
    });

    it('menyediakan helper izin yang dipakai halaman', () => {
        expect(izinSumber).toContain("update: () => hasPermission('donatur.update')");
        expect(izinSumber).toContain("delete: () => hasPermission('donatur.delete')");
    });
});
