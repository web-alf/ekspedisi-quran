/**
 * Field "Status Baru" di panel Update Status Massal dulu selalu menampilkan
 * placeholder "Pilih Status Baru..." walau resi sudah dipilih — pengguna tidak
 * tahu resi itu sekarang ada di tahap mana, padahal status tujuan yang benar
 * ditentukan oleh status sekarang.
 *
 * Yang dikunci di sini:
 *   - field menyebut status sekarang (placeholder dinamis + baris keterangan),
 *   - opsi yang sama dengan status sekarang DITANDAI dan DINONAKTIFKAN, karena
 *     server melompati transisi ke status yang sama (validateStatusTransition)
 *     sehingga memilihnya = menekan Update tanpa hasil,
 *   - pilihan dengan status beragam diberi peringatan, bukan diam-diam.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';
import {
    ringkasStatusTerpilih,
    labelPilihStatus,
    adalahStatusSekarang,
} from '../../resources/js/utils/statusTerpilih.js';

const halaman = readFileSync(
    resolve(__dirname, '../../resources/js/Pages/Admin/Pengiriman/Index.svelte'),
    'utf8',
);

function tanpaKomentar(teks) {
    return teks
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/(^|[^:])\/\/[^\n]*/g, '$1');
}

const sumber = tanpaKomentar(halaman);

const baris = (id, nama = null, statusId = null) => ({
    id,
    status: nama === null ? null : { id: statusId, nama },
});

describe('Ringkasan status resi terpilih', () => {
    it('menyebut status sekarang saat resi terpilih seragam', () => {
        const r = ringkasStatusTerpilih([baris(1, 'Proses Packing', 4), baris(2, 'Proses Packing', 4)]);

        expect(r.beragam).toBe(false);
        expect(r.nama).toBe('Proses Packing');
        expect(r.id).toBe(4);
        expect(labelPilihStatus(r)).toBe('Pilih Status Baru... (sekarang: Proses Packing)');
    });

    it('memperingatkan bila status resi terpilih beragam', () => {
        const r = ringkasStatusTerpilih([baris(1, 'Proses Packing', 4), baris(2, 'Proses Pemesanan', 1)]);

        expect(r.beragam).toBe(true);
        expect(r.jumlahStatus).toBe(2);
        expect(labelPilihStatus(r)).toBe('Pilih Status Baru... (2 status berbeda)');
        expect(adalahStatusSekarang(r, 4)).toBe(false);
    });

    it('kembali ke placeholder biasa bila tidak ada yang dipilih', () => {
        expect(labelPilihStatus(ringkasStatusTerpilih([]))).toBe('Pilih Status Baru...');
    });

    it('tidak menandai apa pun bila baris belum membawa status', () => {
        const r = ringkasStatusTerpilih([baris(1), baris(2)]);

        expect(r.kosong).toBe(false);
        expect(r.nama).toBe('');
        expect(labelPilihStatus(r)).toBe('Pilih Status Baru...');
    });

    it('mencocokkan id status angka maupun string', () => {
        const r = ringkasStatusTerpilih([baris(1, 'Proses Packing', '4')]);

        expect(adalahStatusSekarang(r, 4)).toBe(true);
        expect(adalahStatusSekarang(r, 5)).toBe(false);
    });
});

describe('Halaman Data Resi memakai ringkasan itu', () => {
    it('menghitung ringkasan dari baris yang dipilih', () => {
        expect(sumber).toContain('ringkasStatusTerpilih(barisTerpilih)');
        expect(sumber).toContain('selectedItems.includes(p.id)');
    });

    it('menampilkan status sekarang di field Status Baru', () => {
        expect(sumber).toContain('{labelPilihStatus(ringkasanStatus)}');
        expect(sumber).toContain('Status sekarang:');
    });

    it('menonaktifkan opsi yang sama dengan status sekarang', () => {
        expect(sumber).toContain('{@const sekarang = adalahStatusSekarang(ringkasanStatus, status.id)}');
        expect(sumber).toContain('disabled={sekarang}');
        expect(sumber).toContain("(status sekarang)");
    });
});
