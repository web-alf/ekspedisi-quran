/**
 * Panel "Update Status Massal" di /admin/pengiriman muncul untuk staf gudang,
 * tetapi tombol Update-nya TIDAK ADA: tombol digerbangi `shipments.bulk-update`
 * sementara rutenya juga hanya menerima izin itu — dan tak satu pun akun gudang
 * memegangnya. Padahal jalur per-resi (Ubah Status) justru boleh mereka pakai.
 *
 * Kontrak yang dikunci di sini:
 *   - tombol Update Status Massal  = izin yang SAMA dengan rutenya
 *     (`shipments.bulk-update|shipments.update-status`)
 *   - tombol Set Alamat Massal     = `shipments.bulk-update` saja; sebelumnya
 *     digerbangi `shipments.update` sehingga tampil untuk role yang selalu
 *     ditolak server (tombol mati).
 *
 * Vitest di repo ini mengompilasi Svelte ke mode SSR sehingga onMount tidak
 * berjalan; karena itu tes membaca sumber berkas, bukan merender komponen.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const halaman = readFileSync(
    resolve(__dirname, '../../resources/js/Pages/Admin/Pengiriman/Index.svelte'),
    'utf8',
);

const rute = readFileSync(resolve(__dirname, '../../routes/web.php'), 'utf8');

function tanpaKomentar(teks) {
    return teks
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/(^|[^:])\/\/[^\n]*/g, '$1');
}

const markup = tanpaKomentar(halaman);

describe('Pengiriman — tombol bulk mengikuti izin rutenya', () => {
    it('memakai izin bulk-update ATAU update-status untuk tombol Update Status', () => {
        expect(markup).toContain('can.shipments.bulkUpdate()');
        expect(markup).toContain('can.shipments.updateStatus()');
        expect(markup).toMatch(/\$: canBulkStatus = canBulkUpdate \|\| can\.shipments\.updateStatus\(\)/);
    });

    it('rute bulk-status menerima izin yang sama dengan tombolnya', () => {
        expect(rute).toMatch(
            /Route::post\('pengiriman\/bulk-status'[\s\S]*?middleware\('permission:shipments\.bulk-update\|shipments\.update-status'\)/,
        );
    });

    it('menjaga rute set alamat massal tetap hanya untuk izin bulk', () => {
        expect(rute).toMatch(
            /Route::post\('pengiriman\/bulk-alamat'[\s\S]*?middleware\('permission:shipments\.bulk-update'\)/,
        );
    });

    it('setiap tombol bulk berada di dalam penjaga izin yang benar', () => {
        // Penjaga sejati: menelusuri SELURUH berkas dengan tumpukan blok Svelte,
        // bukan hanya dua tempat yang sudah diperbaiki. Menghitung jumlah `{#if}`
        // vs `{/if}` saja tidak cukup — blok lain juga membuka/menutup blok.
        const tag = /\{#(if|each|await|key)\s+([^}]*)\}|\{\/(if|each|await|key)\}|\{:else(?:\s+if\s+[^}]*)?\}/g;
        const tumpukan = [];
        const pelanggar = [];
        let posisiTerakhir = 0;
        let cocok;

        const penjaga = { bulkUpdateStatus: 'canBulkStatus', bulkSetAlamat: 'canBulkUpdate' };

        while ((cocok = tag.exec(markup)) !== null) {
            const potongan = markup.slice(posisiTerakhir, cocok.index);
            posisiTerakhir = tag.lastIndex;

            for (const klik of potongan.matchAll(/on:click=\{(bulkUpdateStatus|bulkSetAlamat)\}/g)) {
                const wajib = penjaga[klik[1]];
                if (!tumpukan.some((t) => t.trim() === wajib)) {
                    pelanggar.push(`${klik[1]} tanpa penjaga ${wajib}`);
                }
            }

            if (cocok[1]) {
                tumpukan.push(cocok[2]);
            } else if (cocok[3]) {
                tumpukan.pop();
            }
        }

        expect(pelanggar, `pemanggilan tanpa penjaga izin: ${pelanggar.join(', ')}`).toEqual([]);
    });

    it('tidak lagi memakai izin shipments.update untuk kartu Set Alamat', () => {
        // izin itu tetap boleh dipakai di tempat lain (tombol Edit baris), jadi
        // yang diperiksa hanya bahwa kartu alamat tidak lagi bergantung padanya.
        // Catatan: komentar `<!-- ... -->` sudah dibuang, jadi penanda posisinya
        // memakai teks yang benar-benar dirender (judul <h4>).
        const judul = 'Set Alamat dari Permintaan Mushaf';
        const sebelumKlik = markup.slice(markup.indexOf(judul), markup.indexOf('bulkSetAlamat'));

        expect(sebelumKlik).not.toContain('{#if canUpdate}');
        expect(markup).toMatch(/\{#if canBulkUpdate\}\s*<div class="bg-white rounded-lg p-4 border">[\s\S]*?Set Alamat dari Permintaan Mushaf/);
    });

    it('menyediakan helper izin yang dipakai halaman', () => {
        const izin = readFileSync(
            resolve(__dirname, '../../resources/js/utils/permissions.js'),
            'utf8',
        );

        expect(izin).toContain("bulkUpdate: () => hasPermission('shipments.bulk-update')");
        expect(izin).toContain("updateStatus: () => hasPermission('shipments.update-status')");
    });
});
