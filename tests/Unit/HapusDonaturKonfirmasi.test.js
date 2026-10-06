/**
 * Menghapus donatur TIDAK menyisakan arsip: donatur, seluruh item wakaf, dan seluruh
 * resinya lenyap bersamaan (terbukti di PagarHapusDonaturTest). Karena itu tombolnya
 * tidak boleh bisa ditekan tanpa sadar — pengguna harus tahu APA yang hilang.
 *
 * Tes ini menjaga dua hal pada halaman donatur:
 *   1. konfirmasi menyebutkan rincian yang akan terhapus (jumlah item & resi),
 *   2. kode donatur wajib diketik, dan kodenya ikut dikirim sebagai pagar server.
 *
 * Vitest di repo ini mengompilasi Svelte ke mode SSR sehingga onMount tidak
 * berjalan; karena itu tes membaca sumber berkas, bukan merender komponen.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const show = readFileSync(
    resolve(__dirname, '../../resources/js/Pages/Admin/Donatur/Show.svelte'),
    'utf8',
);

const index = readFileSync(
    resolve(__dirname, '../../resources/js/Pages/Admin/Donatur/Index.svelte'),
    'utf8',
);

const store = readFileSync(
    resolve(__dirname, '../../resources/js/stores/dialog.js'),
    'utf8',
);

const dialog = readFileSync(
    resolve(__dirname, '../../resources/js/Components/ConfirmDialog.svelte'),
    'utf8',
);

describe('Hapus donatur tidak bisa ditekan tanpa sadar', () => {
    it('halaman detail memakai konfirmasi hapus permanen', () => {
        expect(show).toContain('confirmDeletePermanen');
        expect(show).not.toMatch(/dialog\.confirmDelete\(/);
    });

    it('halaman daftar memakai konfirmasi hapus permanen', () => {
        expect(index).toContain('confirmDeletePermanen');
        expect(index).not.toMatch(/dialog\.confirmDelete\(/);
    });

    it('konfirmasi menyebutkan jumlah item dan resi yang ikut terhapus', () => {
        expect(show).toMatch(/jumlahItem/);
        expect(show).toMatch(/jumlahResi/);
        expect(show).toMatch(/item wakaf/);
    });

    it('kode donatur wajib diketik sebelum tombol aktif', () => {
        expect(show).toMatch(/ketikUntuk:\s*donatur\.kode_donatur/);
        expect(index).toMatch(/ketikUntuk:\s*donation\.kode_donatur/);
    });

    it('kode donatur ikut dikirim sebagai pagar di server', () => {
        expect(show).toContain('konfirmasi_kode=');
        expect(index).toContain('konfirmasi_kode=');
    });

    it('dialog menerima requireText dan mengunci tombolnya', () => {
        expect(dialog).toContain('export let requireText');
        expect(dialog).toContain('disabled={!cocok}');
        expect(dialog).toContain('if (!cocok)');
    });

    it('store meneruskan requireText ke dialog', () => {
        expect(store).toContain('requireText');
        expect(store).toMatch(/confirmDeletePermanen/);
    });
});
