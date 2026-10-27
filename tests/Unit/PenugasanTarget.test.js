/**
 * Modal "Assign Target Harian" (28/10/2026): jenis mushaf wajib dipilih, dan gudang tugas
 * mengikuti penempatan staf — kolom "Gudang penugasan" dihapus dari modal.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';
import { isianJenis, gudangTugas, bolehSimpanTarget } from '../../resources/js/utils/penugasanTarget.js';

const pilihanJenis = [
    { nilai: '1:0', jenis_quran_id: 1, terjemahan: false, label: 'A5 Tanpa Terjemahan' },
    { nilai: '1:1', jenis_quran_id: 1, terjemahan: true, label: 'A5 Terjemahan' },
    { nilai: '4:0', jenis_quran_id: 4, terjemahan: false, label: 'A6 Tanpa Terjemahan' },
    { nilai: '4:1', jenis_quran_id: 4, terjemahan: true, label: 'A6 Terjemahan' },
    { nilai: '5:-', jenis_quran_id: 5, terjemahan: null, label: 'Iqro' },
];

const kandidat = [
    { id: 10, name: 'Aisyah', gudang_tugas: 'Gudang Semarang' },
    { id: 11, name: 'Budi', gudang_tugas: null },
];

const monitor = readFileSync(
    resolve(__dirname, '../../resources/js/Pages/Supervisor/WarehouseMonitor.svelte'),
    'utf8',
).replace(/<!--[\s\S]*?-->/g, '');

describe('isianJenis', () => {
    it('menerjemahkan nilai pilihan menjadi jenis dan varian', () => {
        expect(isianJenis(pilihanJenis, '1:1')).toEqual({ jenis_quran_id: 1, terjemahan: true });
        expect(isianJenis(pilihanJenis, '4:0')).toEqual({ jenis_quran_id: 4, terjemahan: false });
    });

    it('mengirim terjemahan null untuk Iqro', () => {
        expect(isianJenis(pilihanJenis, '5:-')).toEqual({ jenis_quran_id: 5, terjemahan: null });
    });

    it('mengembalikan null bila belum dipilih atau tidak dikenal', () => {
        expect(isianJenis(pilihanJenis, '')).toBeNull();
        expect(isianJenis(pilihanJenis, '9:1')).toBeNull();
    });
});

describe('gudangTugas', () => {
    it('membaca gudang penempatan staf, cocok untuk id angka maupun string', () => {
        expect(gudangTugas(kandidat, '10')).toBe('Gudang Semarang');
        expect(gudangTugas(kandidat, 10)).toBe('Gudang Semarang');
    });

    it('null untuk staf belum ditempatkan atau tidak ada', () => {
        expect(gudangTugas(kandidat, 11)).toBeNull();
        expect(gudangTugas(kandidat, '')).toBeNull();
    });
});

describe('bolehSimpanTarget', () => {
    const isian = { userId: '10', target: 80, jenis: '1:1' };

    it('boleh bila staf, target, dan jenis lengkap', () => {
        expect(bolehSimpanTarget(isian, pilihanJenis, kandidat)).toEqual({ boleh: true, alasan: '' });
    });

    it('menolak staf yang belum ditempatkan di gudang', () => {
        expect(bolehSimpanTarget({ ...isian, userId: '11' }, pilihanJenis, kandidat).alasan).toContain('Penempatan Gudang');
    });

    it('mewajibkan jenis dan target 1–200', () => {
        expect(bolehSimpanTarget({ ...isian, jenis: '' }, pilihanJenis, kandidat)).toEqual({ boleh: false, alasan: 'Pilih jenis mushaf.' });
        expect(bolehSimpanTarget({ ...isian, target: 0 }, pilihanJenis, kandidat).boleh).toBe(false);
        expect(bolehSimpanTarget({ ...isian, target: 201 }, pilihanJenis, kandidat).boleh).toBe(false);
        expect(bolehSimpanTarget({ ...isian, userId: '' }, pilihanJenis, kandidat).boleh).toBe(false);
    });
});

describe('Halaman Monitor — modal Assign Target', () => {
    it('tidak lagi menawarkan pilihan gudang penugasan', () => {
        expect(monitor).not.toMatch(/pilihGudang|gudangKerja|assign-gudang|gudang_id:/);
        expect(monitor).toContain('Gudang tugas mengikuti penempatan staf');
    });

    it('menampilkan pilihan Jenis Mushaf di bawah Target Mushaf dan mengirim isiannya', () => {
        const posisiTarget = monitor.indexOf('id="target-mushaf"');
        const posisiJenis = monitor.indexOf('id="jenis-mushaf"');

        expect(posisiTarget).toBeGreaterThan(-1);
        expect(posisiJenis).toBeGreaterThan(posisiTarget);
        expect(monitor).toMatch(/\{#each pilihanJenis as pilihan \(pilihan\.nilai\)\}\s*<option value=\{pilihan\.nilai\}>\{pilihan\.label\}<\/option>/);
        expect(monitor).toContain('...isianJenis(pilihanJenis, assignTarget.jenis)');
        expect(monitor).toContain('disabled={isSubmitting || !cekAssign.boleh}');
    });

    it('mengisi ulang jenis saat target tugas yang ada diubah, dan menandai jenis di daftar tugas', () => {
        expect(monitor).toContain('openAssignModal(task.user_id, task.jenis_nilai)');
        expect(monitor).toContain('assignTarget.user_id = userId\n');
        expect(monitor).not.toContain('userId.toString()');
        expect(monitor).toMatch(/\{#if task\.jenis\}[\s\S]*?\{task\.jenis\}/);
    });
});
