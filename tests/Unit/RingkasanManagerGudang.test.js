/**
 * Turunan data dashboard Manager Gudang (desain 27/10/2026).
 *
 * Satu filter gudang menggantikan seksi stok yang dulu diulang per gudang, jadi setiap
 * angka di halaman harus menurut filter yang sama — dan berasal dari baris `per_gudang`
 * yang sama dari server.
 */
import { describe, it, expect } from 'vitest';
import {
    SEMUA_GUDANG,
    gudangTampil,
    totalBelumAdaWakif,
    packingCakupan,
    stokPerJenis,
    stokHabisDiSatuGudang,
    urutkanStafBelumMulaiDulu,
    dokumenTerkait,
} from '../../resources/js/utils/ringkasanManagerGudang.js';

const muwakif = (a5, a6) => [
    { label: 'A5 · Terjemahan', eks: a5, status: a5 > 0 ? 'normal' : 'habis' },
    { label: 'A6 · Terjemahan', eks: a6, status: a6 > 0 ? (a6 < 20 ? 'rendah' : 'normal') : 'habis' },
];

const perGudang = [
    {
        id: 1, nama: 'Gudang Semarang', kode: 'SMG', target: 600, selesai: 450, kerdus_tersegel: 9,
        staf_total: 8, staf_belum_mulai: 1, stok: { muwakif: muwakif(420, 260) },
    },
    {
        id: 2, nama: 'Gudang Boyolali', kode: 'BTL', target: 400, selesai: 100, kerdus_tersegel: 5,
        staf_total: 6, staf_belum_mulai: 2, stok: { muwakif: muwakif(180, 0) },
    },
];

const ringkasan = {
    gabungan: { target: 1000, selesai: 550, kerdus_tersegel: 14, staf_bertugas: 14, staf_belum_mulai: 3 },
    per_gudang: perGudang,
};

describe('gudangTampil', () => {
    it('mengembalikan semua gudang untuk "semua"', () => {
        expect(gudangTampil(perGudang, SEMUA_GUDANG)).toHaveLength(2);
    });

    it('menyaring satu gudang, cocok untuk id angka maupun string', () => {
        expect(gudangTampil(perGudang, '2').map((g) => g.kode)).toEqual(['BTL']);
        expect(gudangTampil(perGudang, 1).map((g) => g.kode)).toEqual(['SMG']);
    });

    it('mengembalikan kosong untuk id yang tidak ada', () => {
        expect(gudangTampil(perGudang, '99')).toEqual([]);
    });
});

describe('totalBelumAdaWakif', () => {
    it('menjumlahkan semua jenis', () => {
        expect(totalBelumAdaWakif(perGudang[0].stok)).toBe(680);
    });

    it('aman untuk stok kosong', () => {
        expect(totalBelumAdaWakif(undefined)).toBe(0);
        expect(totalBelumAdaWakif({})).toBe(0);
    });
});

describe('packingCakupan', () => {
    it('memakai angka gabungan server untuk semua gudang', () => {
        expect(packingCakupan(ringkasan, SEMUA_GUDANG)).toEqual({
            target: 1000, selesai: 550, kerdus: 14, staf: 14, belumMulai: 3, persen: 55,
        });
    });

    it('memakai baris gudang itu sendiri untuk satu gudang', () => {
        expect(packingCakupan(ringkasan, '2')).toEqual({
            target: 400, selesai: 100, kerdus: 5, staf: 6, belumMulai: 2, persen: 25,
        });
    });

    it('persen nol bila target nol, dan aman tanpa ringkasan', () => {
        expect(packingCakupan({ gabungan: { target: 0, selesai: 5 } }).persen).toBe(0);
        expect(packingCakupan(null)).toMatchObject({ target: 0, selesai: 0, persen: 0 });
    });
});

describe('stokPerJenis', () => {
    it('mengelompokkan per jenis dengan rincian tiap gudang, urutan dari server', () => {
        const hasil = stokPerJenis(perGudang);

        expect(hasil.map((j) => j.label)).toEqual(['A5 · Terjemahan', 'A6 · Terjemahan']);
        expect(hasil[0]).toEqual({
            label: 'A5 · Terjemahan',
            total: 600,
            gudang: [
                { id: 1, nama: 'Gudang Semarang', eks: 420, status: 'normal' },
                { id: 2, nama: 'Gudang Boyolali', eks: 180, status: 'normal' },
            ],
        });
        expect(hasil[1].gudang[1]).toMatchObject({ eks: 0, status: 'habis' });
    });

    it('menurunkan status bila server tidak mengirimnya', () => {
        const hasil = stokPerJenis([{ id: 1, nama: 'G', stok: { muwakif: [{ label: 'IQRA', eks: 0 }] } }]);
        expect(hasil[0].gudang[0].status).toBe('habis');
    });
});

describe('stokHabisDiSatuGudang', () => {
    it('menyebut jenis yang habis di satu gudang dengan gudang sumbernya', () => {
        expect(stokHabisDiSatuGudang(perGudang)).toEqual([
            { label: 'A6 · Terjemahan', gudang: 'Gudang Boyolali', sumber: 'Gudang Semarang', eksSumber: 260 },
        ]);
    });

    it('tidak memberi saran bila semua gudang sama-sama habis', () => {
        const kosong = perGudang.map((g) => ({ ...g, stok: { muwakif: muwakif(0, 0) } }));
        expect(stokHabisDiSatuGudang(kosong)).toEqual([]);
    });

    it('tidak menyarankan memindahkan stok yang sudah rendah', () => {
        const rendah = [
            { ...perGudang[0], stok: { muwakif: [{ label: 'A6 · Terjemahan', eks: 18, status: 'rendah' }] } },
            { ...perGudang[1], stok: { muwakif: [{ label: 'A6 · Terjemahan', eks: 0, status: 'habis' }] } },
        ];
        expect(stokHabisDiSatuGudang(rendah)).toEqual([]);
    });

    it('tidak memberi saran bila hanya ada satu gudang', () => {
        expect(stokHabisDiSatuGudang([perGudang[1]])).toEqual([]);
    });
});

describe('urutkanStafBelumMulaiDulu', () => {
    it('menaruh yang belum mulai di atas, lalu sedang kerja, lalu selesai — stabil', () => {
        const staf = [
            { nama: 'A', status: 'selesai' },
            { nama: 'B', status: 'mulai' },
            { nama: 'C', status: 'tidak_mulai' },
            { nama: 'D', status: 'mulai' },
            { nama: 'E' },
        ];

        expect(urutkanStafBelumMulaiDulu(staf).map((s) => s.nama)).toEqual(['C', 'E', 'B', 'D', 'A']);
    });

    it('tidak mengubah array aslinya', () => {
        const staf = [{ nama: 'A', status: 'selesai' }, { nama: 'B', status: 'tidak_mulai' }];
        urutkanStafBelumMulaiDulu(staf);
        expect(staf.map((s) => s.nama)).toEqual(['A', 'B']);
    });
});

describe('dokumenTerkait', () => {
    const dokumen = [
        { kode: 'TM-1', asal: 'Gudang Semarang', tujuan: 'Gudang Boyolali' },
        { kode: 'TM-2', asal: 'Gudang Boyolali', tujuan: 'Gudang Semarang' },
        { kode: 'TM-3', asal: 'Gudang Lain', tujuan: 'Gudang Lain 2' },
    ];

    it('menampilkan semua dokumen untuk semua gudang', () => {
        expect(dokumenTerkait(dokumen, perGudang, SEMUA_GUDANG)).toHaveLength(3);
    });

    it('menyaring dokumen yang asal atau tujuannya gudang yang tampil', () => {
        expect(dokumenTerkait(dokumen, [perGudang[0]], '1').map((d) => d.kode)).toEqual(['TM-1', 'TM-2']);
    });
});
