/**
 * Dashboard Gudang (/admin/warehouse) — daftar kerdus.
 *
 * Bug yang dijaga tes ini: server sudah lama mengirim prop `recentBoxes`
 * (DashboardController::getRecentBoxesForUser), tetapi halaman Svelte-nya tidak
 * pernah mendeklarasikannya. Prop yang tidak dideklarasikan dibuang Svelte tanpa
 * pesan apa pun — jadi seorang staf yang kerdusnya sudah terisi sebagian melihat
 * dashboard yang seolah-olah ia belum pernah mengerjakan apa pun, padahal
 * datanya ada. Tes backend tidak bisa menangkapnya: prop-nya memang benar-benar
 * terkirim.
 *
 * Vitest di repo ini mengompilasi Svelte ke mode SSR sehingga onMount tidak
 * berjalan; karena itu tes membaca sumber berkas, bukan merender komponen.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const sumber = readFileSync(
    resolve(__dirname, '../../resources/js/Pages/Warehouse/Dashboard.svelte'),
    'utf8',
);

/**
 * Buang komentar sebelum menegaskan apa pun.
 *
 * Kalau tidak, sebuah baris yang di-COMMENT (`// export let recentBoxes = []`)
 * tetap memenuhi regex dan tesnya lulus tepat pada kerusakan yang ingin
 * dicegahnya — sudah dibuktikan: tanpa pembersihan ini, menghapus isi fiturnya
 * pun tidak membuat tes gagal.
 */
function tanpaKomentar(teks) {
    return teks
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/(^|[^:])\/\/[^\n]*/g, '$1');
}

const batasMarkup = sumber.indexOf('</script>');
const skrip = tanpaKomentar(sumber.slice(0, batasMarkup));
const markup = tanpaKomentar(sumber.slice(batasMarkup));

describe('Dashboard Gudang — daftar kerdus', () => {
    it('mendeklarasikan prop recentBoxes yang dikirim server', () => {
        expect(skrip).toMatch(/export let recentBoxes\s*=\s*\[\]/);
    });

    it('merender daftar kerdus di markup, bukan hanya di skrip', () => {
        expect(markup).toMatch(/\{#each recentBoxes as \w+\}/);
    });

    it('menampilkan pesan kosong bila belum ada kerdus', () => {
        expect(markup).toMatch(/\{#if recentBoxes\.length > 0\}[\s\S]*\{:else\}[\s\S]*\{\/if\}/);
    });

    it('menyegarkan daftar kerdus saat status dashboard diambil ulang', () => {
        // Tanpa ini, kerdus baru baru muncul setelah halaman dimuat ulang.
        expect(skrip).toMatch(/data\.recentBoxes/);
        expect(skrip).toMatch(/recentBoxes\s*=\s*data\.recentBoxes/);
    });

    it('menjaga agar daftar kosong tidak mempertahankan data lama', () => {
        // Berbeda dengan sharedBoxes yang bersyarat `length > 0`, daftar kerdus
        // harus ikut kosong bila memang kosong.
        expect(skrip).toMatch(/Array\.isArray\(data\.recentBoxes\)/);
    });

    it('menyembunyikan tautan Pelacakan Kerdus di belakang izin warehouse.boxes.view', () => {
        expect(skrip).toMatch(/can\.warehouse\.boxes\.view\(\)/);

        const blokTautan = markup.match(/\{#if bolehLacakKerdus\}[\s\S]*?\{\/if\}/);
        expect(blokTautan, 'tautan tanpa penjaga izin').not.toBeNull();
        expect(blokTautan[0]).toContain('/admin/box-tracking');
    });
});
