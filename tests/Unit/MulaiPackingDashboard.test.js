/**
 * Tombol "Mulai / Lanjutkan Packing" di dashboard freelancer (30/10/2026).
 *
 * Dulu tombolnya memakai meta csrf-token yang diisi sekali saat halaman dibuka. Setelah HP
 * lama terkunci atau sesi berganti, POST /start-scanning dijawab 419 berupa HTML, json()
 * gagal, dan pengguna hanya melihat "Terjadi kesalahan saat memulai tugas" — terus-menerus
 * sampai halaman dimuat ulang manual.
 */
import { describe, it, expect, afterEach } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';
import { headerCsrfTerbaru } from '../../resources/js/utils/auth.js';

const sumber = readFileSync(resolve(__dirname, '../../resources/js/Pages/Warehouse/Dashboard.svelte'), 'utf8');
const mulai = sumber.slice(sumber.indexOf('async function startScanning'), sumber.indexOf('async function markNotificationRead'));

describe('headerCsrfTerbaru', () => {
    afterEach(() => {
        document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
        document.head.innerHTML = '';
    });

    it('memakai cookie XSRF-TOKEN terbaru sebagai X-XSRF-TOKEN', () => {
        document.head.innerHTML = '<meta name="csrf-token" content="token-basi">';
        document.cookie = 'XSRF-TOKEN=eyJpdiI6ImFiYyJ9%3D%3D; path=/';

        expect(headerCsrfTerbaru()).toEqual({ 'X-XSRF-TOKEN': 'eyJpdiI6ImFiYyJ9==' });
    });

    it('jatuh ke meta csrf-token bila cookie tidak ada', () => {
        document.head.innerHTML = '<meta name="csrf-token" content="token-meta">';

        expect(headerCsrfTerbaru()).toEqual({ 'X-CSRF-TOKEN': 'token-meta' });
    });
});

describe('Tombol Mulai / Lanjutkan Packing', () => {
    it('meminta JSON dan memakai token CSRF terbaru', () => {
        expect(mulai).toContain("'Accept': 'application/json'");
        expect(mulai).toContain('...headerCsrfTerbaru()');
        expect(mulai).not.toContain('meta[name="csrf-token"]');
    });

    it('memuat ulang halaman saat sesi basi (419) atau berakhir (401)', () => {
        expect(mulai).toMatch(/response\.status === 419 \|\| response\.status === 401\)\s*\{[\s\S]*?window\.location\.reload\(\);/);
    });

    it('tidak gagal membaca respons bukan JSON dan menyegarkan kartu tugas yang basi', () => {
        expect(mulai).toContain('response.json().catch(() => null)');
        expect(mulai).toContain("router.reload({ only: ['todayTask', 'activeBox'] })");
    });

    it('poling ikut mengosongkan atau memperbarui status tugas', () => {
        expect(sumber).toMatch(/if \(!data\.todayTask\) \{\s*todayTask = null;/);
        expect(sumber).toContain('todayTask.status !== data.todayTask.status');
    });
});
