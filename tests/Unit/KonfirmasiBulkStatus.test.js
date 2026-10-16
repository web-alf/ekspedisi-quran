/**
 * Modal konfirmasi "Update Status Massal" dulu berbunyi
 * "Apakah Anda yakin ingin mengupdate status 1 pengiriman?" — hanya JUMLAH,
 * tanpa status tujuan. Pengguna yang memilih "Proses Produksi" tidak melihat
 * pilihannya di mana pun pada layar konfirmasi.
 *
 * Vitest di repo ini mengompilasi Svelte ke mode SSR sehingga onMount tidak
 * berjalan; karena itu logika pesannya dipisah ke modul biasa dan diuji di sini.
 */
import { describe, it, expect } from 'vitest';
import { pesanKonfirmasiUpdateStatus, namaStatusTerpilih } from '../../resources/js/utils/konfirmasiBulk.js';

const daftarStatus = [
    { id: 1, nama: 'Proses Pemesanan', slug: 'pemesanan' },
    { id: 2, nama: 'Proses Produksi', slug: 'produksi' },
    { id: 3, nama: 'Proses Kedatangan/Penurunan', slug: 'kedatangan' },
    { id: 4, nama: 'Proses Packing', slug: 'packing' },
];

describe('Pesan konfirmasi update status massal', () => {
    it('menyebut status tujuan, bukan hanya jumlah resi', () => {
        expect(pesanKonfirmasiUpdateStatus(1, daftarStatus, 2)).toBe('Ubah status 1 resi menjadi "Proses Produksi"?');
    });

    it('mencocokkan id angka maupun string dari elemen select', () => {
        expect(namaStatusTerpilih(daftarStatus, 4)).toBe('Proses Packing');
        expect(namaStatusTerpilih(daftarStatus, '4')).toBe('Proses Packing');
    });

    it('menghitung jumlah resi yang dipilih', () => {
        expect(pesanKonfirmasiUpdateStatus(7, daftarStatus, 3)).toContain('7 resi');
    });

    it('mengatakan terus terang bila status belum dipilih', () => {
        const pesan = pesanKonfirmasiUpdateStatus(1, daftarStatus, '');

        expect(pesan).toContain('status yang tidak dikenal');
        expect(pesan).not.toContain('""');
    });

    it('tidak mencetak tanda kutip kosong untuk id yang tidak ada di daftar', () => {
        const pesan = pesanKonfirmasiUpdateStatus(2, daftarStatus, 99);

        expect(pesan).toBe('Ubah status 2 resi menjadi status yang tidak dikenal?');
        expect(pesan).not.toContain('""');
    });

    it('aman bila daftar status belum dimuat', () => {
        expect(() => pesanKonfirmasiUpdateStatus(1, undefined, 2)).not.toThrow();
        expect(pesanKonfirmasiUpdateStatus(1, undefined, 2)).toContain('status yang tidak dikenal');
    });
});
