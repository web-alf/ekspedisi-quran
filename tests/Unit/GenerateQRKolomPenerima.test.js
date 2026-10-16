/**
 * Kolom "Penerima" di tab Generate QR (/admin/pengiriman?mode=generate-qr).
 *
 * Dua hal yang dikunci:
 *
 * 1. Nama penerima `pengiriman.nama_penerima` kosong di SELURUH resi (belum ada
 *    permintaan mushaf yang di-approve), jadi selnya harus benar-benar terbaca
 *    kosong. Placeholder lama "Data tidak tersedia" membuat kolom tampak berisi
 *    padahal tidak ada penerima — dan kolom itu sebelumnya juga menampilkan nama
 *    LEMBAGA (baris kedua) dari permintaan mushaf yang tertaut.
 * 2. Lembaga yang tampil diambil dari permintaan mushaf yang tertaut, bukan dari
 *    kolom resi (`$item->mushafRequest?->nama_lembaga ?? $item->nama_lembaga`).
 *    Tautan itu hanya sah untuk permintaan yang sudah di-approve, jadi mekanisme
 *    ini yang membuat nama lembaga muncul walau kolom resi kosong.
 *
 * Vitest di repo ini mengompilasi Svelte ke mode SSR sehingga onMount tidak jalan;
 * karena itu tes membaca sumber berkas, bukan merender komponen.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const halaman = readFileSync(
    resolve(__dirname, '../../resources/js/Pages/Admin/Pengiriman/GenerateQR.svelte'),
    'utf8',
);

const pengendali = readFileSync(
    resolve(__dirname, '../../app/Http/Controllers/Admin/PengirimanController.php'),
    'utf8',
);

function tanpaKomentar(teks) {
    return teks
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/(^|[^:])\/\/[^\n]*/g, '$1');
}

const sumber = tanpaKomentar(halaman);

describe('Kolom Penerima di tab Generate QR', () => {
    it('menampilkan tanda kosong, bukan "Data tidak tersedia", bila penerima belum ada', () => {
        expect(sumber).toContain("{item.nama_penerima || '-'}");
        expect(sumber).not.toContain("item.nama_penerima || 'Data tidak tersedia'");
    });

    it('menampilkan tanda kosong pada baris lembaga', () => {
        expect(sumber).toContain("{item.nama_lembaga || '-'}");
    });

    it('juga menandai kosong pada pratinjau QR yang belum dibuat', () => {
        expect(sumber).toContain("{qr.pengiriman.nama_penerima || '-'}");
        expect(sumber).not.toContain("qr.pengiriman.nama_penerima || 'Data tidak tersedia'");
    });

    it('mengambil lembaga dari permintaan mushaf yang tertaut di pengendali', () => {
        expect(pengendali).toContain("'nama_lembaga' => $item->mushafRequest?->nama_lembaga ?? $item->nama_lembaga");
    });

    it('memuat relasi mushafRequest pada kueri daftar Generate QR', () => {
        expect(pengendali).toMatch(/'mushafRequest:id,pengiriman_id,nama_lembaga,kategori_lembaga'/);
    });
});
