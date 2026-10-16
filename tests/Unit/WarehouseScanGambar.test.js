import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, fireEvent, waitFor } from '@testing-library/svelte';
import { writable } from 'svelte/store';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Opsi "Pilih gambar QR" di halaman gudang yang memakai pemindai sendiri
 * (createScanner dari scanner-core), bukan LazyQRScanner: Box Scanner dan
 * Packing. Teks dari gambar harus masuk ke handler hasil pindai yang sama
 * dengan kamera, dan tombolnya tetap ada saat izin kamera belum/tidak ada.
 */

const bacaQrDariGambar = vi.fn();
const axiosPost = vi.fn();
const scanImage = vi.fn();

vi.mock('../../resources/js/utils/qrGambar.js', async (asli) => ({
    ...(await asli()),
    bacaQrDariGambar: (...args) => bacaQrDariGambar(...args),
}));

vi.mock('axios', () => ({
    default: { post: (...args) => axiosPost(...args), get: vi.fn(() => Promise.resolve({ data: {} })) },
}));

vi.mock('qr-scanner', () => ({ default: { scanImage: (...args) => scanImage(...args) } }));

vi.mock('html5-qrcode', () => ({
    Html5QrcodeScanner: class {
        render() {}
        clear() {}
    },
    Html5Qrcode: class {},
}));

vi.mock('@inertiajs/svelte', () => ({
    router: { visit: vi.fn(), get: vi.fn(), post: vi.fn(), reload: vi.fn(), on: vi.fn() },
    page: writable({
        props: { auth: { user: { id: 1, name: 'Gudang', role: 'warehouse', permissions: [] } }, flash: {} },
        url: '/admin/warehouse/box-scanner',
    }),
    usePage: () => ({ props: {} }),
    inertia: () => ({}),
    Link: vi.fn(),
}));

vi.mock('../../resources/js/utils/auth.js', () => ({ logout: vi.fn(), safeLogout: vi.fn() }));

const { default: BoxScanner } = await import('../../resources/js/Pages/Warehouse/BoxScanner.svelte');

const KODE_KERDUS = 'KB-20261016-003-A5-01';

function pilihGambar(container) {
    const input = container.querySelector('[data-testid="input-gambar-qr"]');
    return fireEvent.change(input, {
        target: { files: [new File(['isi'], 'kerdus.jpg', { type: 'image/jpeg' })] },
    });
}

function baca(relatif) {
    return fs.readFileSync(path.resolve(__dirname, '../../resources/js', relatif), 'utf8');
}

describe('Box Scanner — pilih gambar QR', () => {
    beforeEach(() => {
        bacaQrDariGambar.mockReset();
        axiosPost.mockReset();
        scanImage.mockReset();
        axiosPost.mockResolvedValue({ data: { success: true, data: { box: { kode_kerdus: KODE_KERDUS }, summary: {}, items: [] } } });
    });

    it('menawarkan pilih gambar saat izin kamera belum diberikan', () => {
        const { getByText } = render(BoxScanner);

        expect(getByText('Izin Kamera Diperlukan')).toBeTruthy();
        expect(getByText('Pilih gambar QR')).toBeTruthy();
    });

    it('mengirim kode kerdus dari gambar ke endpoint pindai yang sama dengan kamera', async () => {
        bacaQrDariGambar.mockResolvedValue(KODE_KERDUS);
        const { container } = render(BoxScanner);

        await pilihGambar(container);

        await waitFor(() => expect(axiosPost).toHaveBeenCalledTimes(1));
        expect(axiosPost).toHaveBeenCalledWith('/admin/warehouse/box-scanner/scan', { qr_code: KODE_KERDUS });
    });

    it('tidak memanggil server bila gambar tidak memuat QR', async () => {
        const { GagalBacaGambarQR, PESAN_TANPA_QR } = await import('../../resources/js/utils/qrGambar.js');
        bacaQrDariGambar.mockRejectedValue(new GagalBacaGambarQR('tanpa-qr', PESAN_TANPA_QR));
        const { container, findByRole } = render(BoxScanner);

        await pilihGambar(container);

        expect((await findByRole('alert')).textContent).toBe(PESAN_TANPA_QR);
        expect(axiosPost).not.toHaveBeenCalled();
    });

    it('FileQRScanner lama meneruskan teks QR, bukan objek event', async () => {
        // Dulu event.detail ({ decodedText, source }) diteruskan utuh, lalu
        // handleScanSuccess memanggil .trim() pada objek — selalu gagal.
        scanImage.mockResolvedValue(KODE_KERDUS);
        const { container, getByText } = render(BoxScanner);

        await fireEvent.click(getByText('Gunakan File Scanner'));
        const input = container.querySelector('input[type="file"]');
        await fireEvent.change(input, {
            target: { files: [new File(['isi'], 'kerdus.png', { type: 'image/png' })] },
        });

        await waitFor(() => expect(axiosPost).toHaveBeenCalledTimes(1));
        expect(axiosPost).toHaveBeenCalledWith('/admin/warehouse/box-scanner/scan', { qr_code: KODE_KERDUS });
    });

    it('mematikan kamera sebelum memproses gambar, seperti setelah pindai kamera', () => {
        const sumber = baca('Pages/Warehouse/BoxScanner.svelte');
        const blok = sumber.slice(sumber.indexOf('function onGambarQrTerbaca'), sumber.indexOf('function onScanFailure'));

        expect(blok).toContain('scannerStore.cleanup()');
        expect(blok).toContain('onScanSuccess(event.detail.text');
        expect(blok.indexOf('scannerStore.cleanup()')).toBeLessThan(blok.indexOf('onScanSuccess('));
    });

    it('menampilkan pilih gambar juga di bawah tampilan kamera', () => {
        const sumber = baca('Pages/Warehouse/BoxScanner.svelte');
        const setelahKamera = sumber.slice(sumber.indexOf('id="qr-reader"'));

        expect(setelahKamera.indexOf('<PilihGambarQR')).toBeGreaterThan(-1);
        expect(setelahKamera.indexOf('<PilihGambarQR')).toBeLessThan(setelahKamera.indexOf('{/if}'));
    });
});

describe('Packing — pilih gambar QR', () => {
    const sumber = baca('Pages/Warehouse/Packing.svelte');

    it('memasang PilihGambarQR di mode kamera', () => {
        expect(sumber).toContain("import PilihGambarQR from '../../Components/PilihGambarQR.svelte'");
        expect(sumber).toMatch(/<PilihGambarQR on:scanSuccess=\{onGambarQrTerbaca\}/);
    });

    it('menaruh tombol di luar syarat kamera menyala/izin, hanya disembunyikan di mode File', () => {
        // Tombol harus ada saat kamera mati atau izinnya ditolak; di mode File
        // sudah ada FileQRScanner, jadi tidak perlu dobel.
        const awalBlok = sumber.lastIndexOf('{#if !showFileScanner}', sumber.indexOf('<PilihGambarQR'));
        const blok = sumber.slice(awalBlok, sumber.indexOf('<PilihGambarQR'));

        expect(awalBlok).toBeGreaterThan(-1);
        expect(blok).toContain('id="qr-reader"');
        expect(blok).not.toContain('{#if !isScanning');
        expect((blok.match(/\{#if /g) || []).length).toBe(1);
    });

    it('meneruskan teks gambar ke processScannedItem, handler yang sama dengan kamera', () => {
        const blok = sumber.slice(
            sumber.indexOf('function onGambarQrTerbaca'),
            sumber.indexOf('async function catatKerdus')
        );

        expect(blok).toContain('processScannedItem(teks)');
        expect(blok).toContain('scanResult = teks');
        // Tidak lewat onScanSuccess: peredam frame berulang di sana akan
        // mengabaikan gambar yang sengaja dipilih ulang setelah gagal.
        expect(blok).not.toContain('onScanSuccess(');
    });

    it('memakai processScannedItem juga di jalur kamera', () => {
        const blok = sumber.slice(sumber.indexOf('function onScanSuccess'), sumber.indexOf('function onScanFailure'));

        expect(blok).toContain('processScannedItem(decodedText)');
    });
});
