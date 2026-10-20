/**
 * Pilihan gudang pada formulir Stok Mushaf (21/10/2026).
 *
 * Keluhan pemilik: saat login sebagai SPV Semarang, formulir "Catat Pergerakan" masih
 * menawarkan Gudang Boyolali. Server memang menolaknya (403), tetapi opsi yang pasti
 * ditolak tidak boleh ditawarkan sama sekali.
 */
import { describe, it, expect } from 'vitest';
import { gudangUntukEntri, gudangTujuanTransfer, gudangAwal } from '../../resources/js/utils/pilihanGudang.js';

const gudangList = [
    { id: 1, nama: 'Gudang Semarang' },
    { id: 2, nama: 'Gudang Boyolali' },
];

describe('gudangUntukEntri', () => {
    it('SPV hanya mendapat gudangnya sendiri', () => {
        expect(gudangUntukEntri(gudangList, { gudangSaya: 1, tercakupGudang: true }))
            .toEqual([{ id: 1, nama: 'Gudang Semarang' }]);
    });

    it('mencocokkan id angka maupun string', () => {
        expect(gudangUntukEntri(gudangList, { gudangSaya: '2', tercakupGudang: true }))
            .toEqual([{ id: 2, nama: 'Gudang Boyolali' }]);
    });

    it('PJ/super-admin tetap melihat semua gudang', () => {
        expect(gudangUntukEntri(gudangList, { gudangSaya: null, tercakupGudang: false })).toHaveLength(2);
    });

    it('SPV yang belum ditempatkan tidak mendapat pilihan apa pun (gagal-tertutup)', () => {
        expect(gudangUntukEntri(gudangList, { gudangSaya: null, tercakupGudang: true })).toEqual([]);
    });
});

describe('gudangTujuanTransfer', () => {
    it('tidak menawarkan gudang asal sebagai tujuan', () => {
        expect(gudangTujuanTransfer(gudangList, 1)).toEqual([{ id: 2, nama: 'Gudang Boyolali' }]);
    });

    it('tanpa asal terpilih, semua gudang ditawarkan', () => {
        expect(gudangTujuanTransfer(gudangList, '')).toHaveLength(2);
    });
});

describe('gudangAwal', () => {
    it('SPV langsung terisi gudangnya', () => {
        expect(gudangAwal({ gudangSaya: 1, tercakupGudang: true })).toBe('1');
    });

    it('peran lain mulai kosong supaya memilih sendiri', () => {
        expect(gudangAwal({ gudangSaya: null, tercakupGudang: false })).toBe('');
    });
});
