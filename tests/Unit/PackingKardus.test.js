/**
 * Alur "packing kardus": mushaf datang sudah dalam kardus/dos dari produksi,
 * jadi gudang tidak memindai tiap mushaf — cukup menyebut jenis + jumlah kerdus.
 *
 * Yang dikunci di sini:
 *   - kekurangan eks tampil sebagai peringatan (kerdus separuh tidak boleh
 *     terlihat seperti kerdus penuh),
 *   - resi yang tidak cukup ruang dilaporkan, bukan diam-diam dilewati,
 *   - pelampauan target harian dilaporkan,
 *   - halaman benar-benar memakai modul ini (bukan salinan logika).
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';
import { ringkasHasilKardus, labelKerdus } from '../../resources/js/utils/kardusPacking.js';

const halaman = readFileSync(
    resolve(__dirname, '../../resources/js/Pages/Warehouse/Packing.svelte'),
    'utf8',
);

function tanpaKomentar(teks) {
    return teks
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/(^|[^:])\/\/[^\n]*/g, '$1');
}

const sumber = tanpaKomentar(halaman);

const hasilDasar = {
    jenis: { id: 1, kode_jenis: 'A5', nama_jenis: 'Al-Quran Ukuran A5', kapasitas: 20 },
    jumlah_kerdus: 1,
    kerdus: [
        {
            id: 7,
            kode_kerdus: 'KB-20261013-003-A5-01',
            jumlah_terisi: 20,
            kapasitas: 20,
            status: 'sealed',
            penuh: true,
            resi: ['EQ-2025-00001'],
        },
    ],
    total_mushaf: 20,
    diminta: 20,
    kekurangan: 0,
    tertahan: null,
    target: 50,
    selesai: 20,
    melebihi_target: false,
    tugas_selesai: false,
};

describe('Ringkasan hasil catat kerdus', () => {
    it('menyebut jumlah kerdus dan total mushaf', () => {
        const r = ringkasHasilKardus(hasilDasar);

        expect(r.judul).toBe('1 kerdus A5 · 20 mushaf tercatat');
        expect(r.kerdus[0].kode).toBe('KB-20261013-003-A5-01');
        expect(r.kerdus[0].keterangan).toBe('20/20 eks · tersegel');
        expect(r.peringatan).toEqual([]);
    });

    it('memperingatkan kerdus yang tidak penuh (kekurangan eks)', () => {
        const r = ringkasHasilKardus({
            ...hasilDasar,
            kerdus: [{ ...hasilDasar.kerdus[0], jumlah_terisi: 12, status: 'filling', penuh: false }],
            total_mushaf: 12,
            kekurangan: 8,
        });

        expect(r.kerdus[0].keterangan).toBe('12/20 eks · terisi');
        expect(r.peringatan[0]).toContain('Kurang 8 eks dari 20');
        expect(r.peringatan[0]).toContain('terisi');
    });

    it('melaporkan resi yang tidak cukup ruang tanpa menyebutnya dilewati', () => {
        const r = ringkasHasilKardus({
            ...hasilDasar,
            total_mushaf: 0,
            diminta: 20,
            kekurangan: 20,
            kerdus: [{ ...hasilDasar.kerdus[0], jumlah_terisi: 0, kapasitas: 20, status: 'filling', penuh: false }],
            tertahan: { no_resi: 'EQ-2026-21550', butuh: 60, tersedia: 20 },
        });

        const gabung = r.peringatan.join(' ');
        expect(gabung).toContain('EQ-2026-21550');
        expect(gabung).toContain('butuh 60 eks');
        expect(gabung).toContain('urutan FIFO tidak dilanggar');
    });

    it('memperingatkan bila tugas melewati target harian', () => {
        const r = ringkasHasilKardus({ ...hasilDasar, selesai: 60, target: 50, melebihi_target: true });

        expect(r.peringatan.join(' ')).toContain('60/50');
    });

    it('aman terhadap jawaban kosong / rusak', () => {
        expect(ringkasHasilKardus(null)).toBeNull();
        expect(ringkasHasilKardus({})).toEqual({
            judul: '0 kerdus  · 0 mushaf tercatat',
            kerdus: [],
            peringatan: [],
        });
    });
});

describe('Label pilihan kerdus', () => {
    it('menghitung eks dari kapasitas jenis', () => {
        expect(labelKerdus({ kode_jenis: 'A5', kapasitas: 20 }, 2)).toBe('2 kerdus A5 = 40 eks');
        expect(labelKerdus({ kode_jenis: 'IQRO', kapasitas: 160 }, 1)).toBe('1 kerdus IQRO = 160 eks');
    });

    it('tidak menampilkan apa pun bila jenis belum dipilih', () => {
        expect(labelKerdus(null, 1)).toBe('');
    });
});

describe('Halaman packing memakai alur kardus ini', () => {
    it('punya tab Kardus yang mengirim jenis + jumlah kerdus', () => {
        expect(sumber).toContain("activeTab = 'kardus'");
        expect(sumber).toContain('/admin/warehouse/packing/catat-kerdus');
        expect(sumber).toContain('jenis_quran_id: Number(kardusJenisId)');
        expect(sumber).toContain('jumlah_kerdus: Number(kardusJumlah)');
    });

    it('menampilkan hasil dan peringatannya', () => {
        expect(sumber).toContain('ringkasHasilKardus(data.data)');
        expect(sumber).toContain('{#each kardusHasil.peringatan as pesan}');
        expect(sumber).toContain('{kardusHasil.judul}');
    });
});
