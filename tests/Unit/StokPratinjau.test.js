/**
 * Formulir "Catat Pergerakan" stok mushaf (21/10/2026).
 *
 * Pratinjau "stok sekarang → sesudah" dan label tombol yang menyebut jumlah dihitung di
 * modul ini, supaya bisa diuji tanpa merender halaman.
 */
import { describe, it, expect } from 'vitest';
import { stokSaatIni, hitungHasil, bolehSimpan, labelVarian } from '../../resources/js/utils/stokPratinjau.js';

const stokList = [
    { jenis_id: 1, varian: false, gudang_id: 1, eks: 12 },
    { jenis_id: 1, varian: true, gudang_id: 1, eks: 8 },
    { jenis_id: 2, varian: false, gudang_id: 1, eks: 40 },
    { jenis_id: 1, varian: false, gudang_id: 2, eks: 99 },
];

describe('stokSaatIni', () => {
    it('mencocokkan pasangan jenis × varian, bukan salah satunya saja', () => {
        expect(stokSaatIni(stokList, { jenisId: 1, terjemahan: 0, gudangId: 1 })).toBe(12);
        expect(stokSaatIni(stokList, { jenisId: 1, terjemahan: 1, gudangId: 1 })).toBe(8);
        expect(stokSaatIni(stokList, { jenisId: 2, terjemahan: 0, gudangId: 1 })).toBe(40);
    });

    it('mencocokkan id angka maupun string dari elemen select', () => {
        expect(stokSaatIni(stokList, { jenisId: '1', terjemahan: '0', gudangId: '1' })).toBe(12);
    });

    it('memisahkan gudang: stok gudang lain tidak ikut terhitung', () => {
        expect(stokSaatIni(stokList, { jenisId: 1, terjemahan: 0, gudangId: 2 })).toBe(99);
    });

    it('mengembalikan 0 bila kombinasi belum pernah tercatat', () => {
        expect(stokSaatIni(stokList, { jenisId: 9, terjemahan: 0, gudangId: 1 })).toBe(0);
    });

    it('tidak menghitung apa pun sebelum jenis & varian dipilih', () => {
        expect(stokSaatIni(stokList, { jenisId: '', terjemahan: '', gudangId: 1 })).toBe(0);
        expect(stokSaatIni(stokList, { jenisId: 1, terjemahan: null, gudangId: 1 })).toBe(0);
    });
});

describe('hitungHasil', () => {
    it('menambah untuk pergerakan masuk', () => {
        expect(hitungHasil('masuk', 12, 10)).toEqual({ akhir: 22, cukup: true, selisih: 22 });
    });

    it('mengurangi untuk pergerakan keluar', () => {
        expect(hitungHasil('keluar', 12, 5)).toEqual({ akhir: 7, cukup: true, selisih: 7 });
    });

    it('menandai tidak cukup ketika pengurangan melebihi stok', () => {
        const hasil = hitungHasil('keluar', 12, 20);
        expect(hasil.cukup).toBe(false);
        expect(hasil.akhir).toBe(-8);
        expect(hasil.selisih).toBe(8);
    });

    it('menganggap stok pas sebagai cukup', () => {
        expect(hitungHasil('keluar', 12, 12)).toEqual({ akhir: 0, cukup: true, selisih: 0 });
    });
});

describe('bolehSimpan', () => {
    const lengkap = { tipe: 'masuk', gudang: 1, jenis: 1, terjemahan: '0', jumlah: 10 };

    it('boleh disimpan saat semua isian lengkap', () => {
        expect(bolehSimpan(lengkap)).toEqual({ boleh: true, alasan: '' });
    });

    it('menolak bila varian belum dipilih, dan menyebut alasannya', () => {
        const hasil = bolehSimpan({ ...lengkap, terjemahan: '' });
        expect(hasil.boleh).toBe(false);
        expect(hasil.alasan).toContain('varian');
    });

    it('menolak jumlah nol atau negatif', () => {
        expect(bolehSimpan({ ...lengkap, jumlah: 0 }).boleh).toBe(false);
        expect(bolehSimpan({ ...lengkap, jumlah: -3 }).boleh).toBe(false);
    });

    it('menolak bila gudang atau jenis belum dipilih', () => {
        expect(bolehSimpan({ ...lengkap, gudang: '' }).alasan).toContain('Gudang');
        expect(bolehSimpan({ ...lengkap, jenis: '' }).alasan).toContain('jenis');
    });
});

describe('labelVarian', () => {
    it('memakai istilah yang sama dengan tabel dan transfer', () => {
        expect(labelVarian(1)).toBe('Dengan terjemahan');
        expect(labelVarian(0)).toBe('Tanpa terjemahan');
        expect(labelVarian('')).toBe('');
    });
});
