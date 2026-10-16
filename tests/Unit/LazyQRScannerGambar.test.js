import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, fireEvent, waitFor } from '@testing-library/svelte';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Opsi "Pilih gambar QR" di pemindai bersama.
 *
 * LazyQRScanner dipakai panel Pindai Kerdus (Muatan), halaman Pindai Kurir, dan
 * Scan QR. Karena tombolnya ada di komponen ini, semua halaman itu ikut — dan
 * hasil gambar harus keluar lewat event `scanSuccess` yang sama dengan hasil
 * kamera supaya alur setelahnya tidak berubah.
 */

const bacaQrDariGambar = vi.fn();

vi.mock('../../resources/js/utils/qrGambar.js', async (asli) => ({
    ...(await asli()),
    bacaQrDariGambar: (...args) => bacaQrDariGambar(...args),
}));

// Kamera di jsdom selalu gagal; yang diuji di sini hanya jalur gambar.
vi.mock('html5-qrcode', () => ({
    Html5Qrcode: class {
        static getCameras() {
            return Promise.resolve([]);
        }
        start() {
            return Promise.reject(Object.assign(new Error('tidak ada kamera'), { name: 'NotFoundError' }));
        }
        stop() {
            return Promise.resolve();
        }
        clear() {}
    },
}));

const { default: LazyQRScanner } = await import('../../resources/js/Components/LazyQRScanner.svelte');

function pilihGambar(container, nama = 'QR.png') {
    const input = container.querySelector('[data-testid="input-gambar-qr"]');
    return fireEvent.change(input, {
        target: { files: [new File(['isi'], nama, { type: 'image/png' })] },
    });
}

describe('LazyQRScanner — pilih gambar QR', () => {
    beforeEach(() => {
        bacaQrDariGambar.mockReset();
        navigator.mediaDevices.getUserMedia.mockResolvedValue({ getTracks: () => [] });
    });

    it('menampilkan tombol pilih gambar', () => {
        const { getByText } = render(LazyQRScanner);

        expect(getByText('Pilih gambar QR')).toBeTruthy();
    });

    it('menaruh tombol pilih gambar di luar cabang loading/error kamera', () => {
        // Tombol ini paling dibutuhkan justru saat kamera gagal atau izinnya
        // ditolak, jadi ia tidak boleh ikut tersembunyi di cabang mana pun.
        // (onMount tidak berjalan di vitest repo ini, sehingga kegagalan kamera
        // tidak bisa dirender — yang dikunci adalah posisinya di markup.)
        const sumber = fs.readFileSync(
            path.resolve(__dirname, '../../resources/js/Components/LazyQRScanner.svelte'),
            'utf8'
        );
        const markup = sumber
            .slice(sumber.indexOf('</script>'), sumber.indexOf('<style>'))
            .replace(/<!--[\s\S]*?-->/g, '');
        const posisiTombol = markup.indexOf('<PilihGambarQR');
        const sebelum = markup.slice(0, posisiTombol);
        const terbuka = (sebelum.match(/\{#if /g) || []).length;
        const tertutup = (sebelum.match(/\{\/if\}/g) || []).length;

        expect(posisiTombol).toBeGreaterThan(-1);
        // Satu-satunya {#if} yang masih terbuka adalah {#if showPilihGambar}.
        expect(terbuka - tertutup).toBe(1);
        expect(sebelum.lastIndexOf('{#if showPilihGambar}')).toBeGreaterThan(sebelum.lastIndexOf('{/if}'));
    });

    it('dapat disembunyikan lewat prop showPilihGambar', () => {
        const { queryByText } = render(LazyQRScanner, { props: { showPilihGambar: false } });

        expect(queryByText('Pilih gambar QR')).toBeNull();
    });

    it.each([
        ['QR resi', 'EQ-2026-17725'],
        ['QR resi format JSON lama', '{"type":"ekspedisi_quran","no_resi":"EQ-2025-01417"}'],
        ['QR kerdus', 'KB-20261016-003-A5-01'],
    ])('meneruskan %s dari gambar sebagai scanSuccess dengan detail.text', async (_, isiQr) => {
        bacaQrDariGambar.mockResolvedValue(isiQr);
        const { container, component } = render(LazyQRScanner);
        const terima = vi.fn();
        component.$on('scanSuccess', terima);

        await pilihGambar(container);

        await waitFor(() => expect(terima).toHaveBeenCalledTimes(1));
        expect(terima.mock.calls[0][0].detail.text).toBe(isiQr);
    });
});

describe('Scan QR Data Resi (ScanStatus) — pilih gambar QR', () => {
    const sumber = fs.readFileSync(
        path.resolve(__dirname, '../../resources/js/Pages/Admin/Pengiriman/ScanStatus.svelte'),
        'utf8'
    );

    it('memasang PilihGambarQR yang meneruskan ke alur pindai kamera', () => {
        expect(sumber).toContain("import PilihGambarQR from '@/Components/PilihGambarQR.svelte'");
        expect(sumber).toMatch(/<PilihGambarQR on:scanSuccess=\{onGambarQrTerbaca\}/);

        const blok = sumber.slice(sumber.indexOf('function onGambarQrTerbaca'), sumber.indexOf('function onScanFailure'));
        expect(blok).toContain('scannerStore.cleanup()');
        expect(blok).toContain('onScanSuccess(event.detail.text');
    });

    it('menawarkan gambar juga saat izin kamera belum/tidak diberikan', () => {
        const markup = sumber.slice(sumber.indexOf('</script>'));
        const blokIzin = markup.slice(markup.indexOf('Izin Kamera Diperlukan'), markup.indexOf('<!-- Scanner Active -->'));

        expect(blokIzin).toContain('<PilihGambarQR');
    });

    it('tetap menampilkan formulir status setelah gambar terbaca walau kamera tidak diizinkan', () => {
        // Formulir berada di cabang {:else}; tanpa syarat tambahan ini hasil
        // gambar tidak pernah terlihat saat izin kamera ditolak.
        expect(sumber).toContain('{#if !$scannerStore.permissionGranted && !showStatusForm && !successData}');
    });
});
