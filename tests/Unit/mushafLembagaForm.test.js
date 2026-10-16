import { describe, it, expect } from 'vitest';
import { buatFormLembaga } from '../../resources/js/utils/mushafLembagaForm.js';

/**
 * Isi form "Informasi Lembaga" pada halaman detail permintaan mushaf.
 *
 * Bug asli: form itu TIDAK membawa ID wilayah. Dropdown di
 * AddressFormIndonesia memilih wilayah lewat `provinsi_id`, `kota_kabupaten_id`,
 * `kecamatan_id`, dan `kelurahan_desa_id` — bukan lewat namanya. Karena ID-nya
 * kosong, keempat dropdown tampil "Pilih Provinsi" / "Pilih Kota/Kabupaten" / dst.
 * walaupun provinsi..kelurahan tersimpan lengkap di basis data.
 *
 * Dampak lanjutannya bukan sekadar kosmetik: nilai yang dikirim balik saat
 * menyimpan berasal dari dropdown, jadi wilayah yang benar bisa ikut terhapus.
 *
 * Tes ini mengunci pemetaannya: setiap ID wilayah WAJIB ikut, dalam bentuk teks.
 */

/** Data wilayah nyata: SD NEGERI DEMAAN, Jepara. */
const permintaan = {
    nama_lembaga: 'SD NEGERI DEMAAN',
    kategori_lembaga: 'Sekolah/Madrasah',
    alamat_lengkap: 'JALAN SUNAN MANTINGAN NO 24A DEMAAN JEPARA',
    provinsi: 'JAWA TENGAH',
    provinsi_id: '33',
    kota_kabupaten: 'KABUPATEN JEPARA',
    kota_kabupaten_id: '3320',
    kecamatan: 'JEPARA',
    kecamatan_id: '3320070',
    kelurahan_desa: 'DEMAAN',
    kelurahan_desa_id: '3320070001',
    kode_pos: '59419',
    alamat_detail: 'JALAN SUNAN MANTINGAN NO 24A',
    latitude: '-6.59958411',
    longitude: '110.65751000',
    urgensi_request: 'Karena antusias dan semangat dari peserta didik',
    sumber_info: 'WhatsApp',
};

describe('buatFormLembaga — ID wilayah', () => {
    it('membawa keempat ID wilayah, bukan hanya namanya', () => {
        const form = buatFormLembaga(permintaan);

        expect(form.provinsi_id).toBe('33');
        expect(form.kota_kabupaten_id).toBe('3320');
        expect(form.kecamatan_id).toBe('3320070');
        expect(form.kelurahan_desa_id).toBe('3320070001');
    });

    it('tetap membawa nama wilayahnya', () => {
        const form = buatFormLembaga(permintaan);

        expect(form.provinsi).toBe('JAWA TENGAH');
        expect(form.kota_kabupaten).toBe('KABUPATEN JEPARA');
        expect(form.kecamatan).toBe('JEPARA');
        expect(form.kelurahan_desa).toBe('DEMAAN');
    });

    it('menyeragamkan ID menjadi teks walau server mengirim angka', () => {
        // <option value="33"> selalu string; angka 33 tidak akan cocok
        const form = buatFormLembaga({
            provinsi_id: 33,
            kota_kabupaten_id: 3320,
            kecamatan_id: 3320070,
            kelurahan_desa_id: 3320070001,
        });

        expect(form.provinsi_id).toBe('33');
        expect(form.kota_kabupaten_id).toBe('3320');
        expect(form.kecamatan_id).toBe('3320070');
        expect(form.kelurahan_desa_id).toBe('3320070001');
    });

    it('menjadikan ID yang tidak ada sebagai teks kosong, bukan "undefined"', () => {
        const form = buatFormLembaga({ provinsi: 'JAWA TENGAH' });

        expect(form.provinsi_id).toBe('');
        expect(form.kota_kabupaten_id).toBe('');
        expect(form.kecamatan_id).toBe('');
        expect(form.kelurahan_desa_id).toBe('');
        expect(form.provinsi).toBe('JAWA TENGAH');
    });

    it('tidak pernah menghasilkan teks "null" atau "undefined"', () => {
        const form = buatFormLembaga({
            provinsi_id: null,
            kota_kabupaten_id: undefined,
            kecamatan_id: '',
            kelurahan_desa_id: 0,
        });

        for (const nilai of Object.values(form)) {
            expect(String(nilai)).not.toMatch(/undefined|null/);
        }
    });

    it('tahan dipanggil tanpa argumen', () => {
        const form = buatFormLembaga();

        expect(form.provinsi_id).toBe('');
        expect(form.nama_lembaga).toBe('');
    });

    it('membawa kolom lain apa adanya', () => {
        const form = buatFormLembaga(permintaan);

        expect(form.nama_lembaga).toBe('SD NEGERI DEMAAN');
        expect(form.kategori_lembaga).toBe('Sekolah/Madrasah');
        expect(form.kode_pos).toBe('59419');
        expect(form.alamat_detail).toBe('JALAN SUNAN MANTINGAN NO 24A');
        expect(form.latitude).toBe('-6.59958411');
        expect(form.longitude).toBe('110.65751000');
        expect(form.sumber_info).toBe('WhatsApp');
    });
});
