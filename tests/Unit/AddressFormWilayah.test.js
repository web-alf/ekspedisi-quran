import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

/**
 * Dropdown wilayah pada form alamat (AddressFormIndonesia).
 *
 * Bug asli — halaman detail permintaan mushaf menampilkan provinsi, kabupaten,
 * kecamatan, dan kelurahan KOSONG walaupun datanya tersimpan lengkap.
 *
 * Penyebabnya: `onMount` hanya memuat daftar PROVINSI. Daftar kabupaten,
 * kecamatan, dan kelurahan hanya dimuat oleh handler perubahan dropdown — yang
 * tidak pernah berjalan saat membuka data lama, karena tidak ada yang mengubah
 * apa pun. Ketiga dropdown sisanya tinggal kosong, dan karena nilai yang dikirim
 * balik saat menyimpan berasal dari dropdown, wilayah yang sudah benar bisa ikut
 * terhapus.
 *
 * Komponen ini TIDAK bisa diuji dengan render di proyek ini: Vitest di sini
 * mengompilasi Svelte ke mode SSR, sehingga `onMount` tidak pernah berjalan dan
 * setiap komponen yang memuat data saat dipasang akan selalu tampak kosong.
 * Karena itu tes ini memeriksa STRUKTUR pemuatan datanya langsung pada berkas
 * sumber — cukup untuk mengunci regresi yang dimaksud.
 */

const __dirname = dirname(fileURLToPath(import.meta.url));
const BERKAS = resolve(__dirname, '../../resources/js/Components/AddressFormIndonesia.svelte');
const sumber = readFileSync(BERKAS, 'utf8');

/** Isi blok onMount(...) — dari pemanggilannya sampai penutupnya. */
function isiOnMount(teks) {
    const mulai = teks.indexOf('onMount(');
    if (mulai === -1) return '';
    const akhir = teks.indexOf('\n  });', mulai);
    return akhir === -1 ? teks.slice(mulai) : teks.slice(mulai, akhir);
}

describe('AddressFormIndonesia — memuat wilayah saat dipasang', () => {
    it('blok onMount memanggil pemuat rantai wilayah, bukan hanya provinsi', () => {
        const blok = isiOnMount(sumber);

        expect(blok, 'onMount harus ada').not.toBe('');
        expect(blok, 'onMount harus memuat provinsi').toContain('/provinces');

        // Rantai kabupaten/kecamatan/kelurahan harus ikut dimuat saat dipasang.
        // Sebelumnya hanya provinsi yang dimuat, sehingga membuka data lama
        // menampilkan tiga dropdown sisanya kosong.
        expect(blok).toContain('muatWilayahTersimpan');
    });

    it('pemuat rantai wilayah benar-benar memuat kabupaten, kecamatan, dan kelurahan', () => {
        const mulai = sumber.indexOf('async function muatWilayahTersimpan');
        expect(mulai, 'fungsi muatWilayahTersimpan harus ada').toBeGreaterThan(-1);

        const badan = sumber.slice(mulai, mulai + 1200);

        expect(badan, 'harus memuat kabupaten').toContain('/regencies/');
        expect(badan, 'harus memuat kecamatan').toContain('/districts/');
        expect(badan, 'harus memuat kelurahan').toContain('/villages/');
    });

    it('memuat wilayah tersimpan lewat fungsi khusus, bukan lewat handler perubahan', () => {
        const blok = isiOnMount(sumber);

        // Handler perubahan mengosongkan kolom turunannya lebih dulu
        // (lihat handleProvinceChange). Memanggilnya saat komponen dipasang
        // justru MENGHAPUS wilayah yang sudah tersimpan.
        expect(blok).not.toContain('handleProvinceChange');
        expect(blok).not.toContain('handleRegencyChange');
        expect(blok).not.toContain('handleDistrictChange');
    });

    it('fungsi pemuat wilayah tersimpan menjaga ID yang sudah ada', () => {
        const mulai = sumber.indexOf('async function muatWilayahTersimpan');
        const badan = sumber.slice(mulai, mulai + 1200);

        // Hanya memuat daftar; tidak boleh mengosongkan ID wilayah.
        expect(badan).not.toMatch(/form\.\w+_id\s*=\s*''/);
        expect(badan).not.toMatch(/^\s*(regencies|districts|villages)\s*=\s*\[\];/m);
    });
});
