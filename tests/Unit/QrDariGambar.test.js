import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, fireEvent, waitFor } from '@testing-library/svelte';
import PilihGambarQR from '../../resources/js/Components/PilihGambarQR.svelte';
import {
    adalahGambar,
    bacaQrDariGambar,
    GagalBacaGambarQR,
    PESAN_BUKAN_GAMBAR,
    PESAN_GAMBAR_RUSAK,
    PESAN_TANPA_QR,
} from '../../resources/js/utils/qrGambar.js';

/**
 * Cadangan pemindai kamera: QR dibaca dari gambar galeri HP / berkas komputer.
 *
 * Pustaka pemindai diganti tiruan — jsdom tidak punya canvas, jadi decode
 * sungguhan dicoba di peramban. Yang dikunci di sini: wadah terpisah dari
 * kamera, pesan kegagalan yang jelas, dan event yang sama dengan hasil kamera.
 */

const KODE_RESI = 'EQ-2026-17725';
const KODE_KERDUS = 'KB-20261016-003-A5-01';

function berkasGambar(nama = 'QR-EQ-2026-17725.png', tipe = 'image/png') {
    return new File(['isi'], nama, { type: tipe });
}

function pustakaTiruan(scanFile) {
    const instans = [];

    class Html5Qrcode {
        constructor(elementId, config) {
            this.elementId = elementId;
            this.config = config;
            this.wadahAdaSaatDibuat = !!document.getElementById(elementId);
            this.scanFile = vi.fn(scanFile);
            this.clear = vi.fn();
            instans.push(this);
        }
    }

    return {
        instans,
        muatPustaka: vi.fn(async () => ({
            Html5Qrcode,
            Html5QrcodeSupportedFormats: { QR_CODE: 0 },
        })),
    };
}

describe('adalahGambar', () => {
    it.each([
        ['png', berkasGambar('a.png', 'image/png')],
        ['jpeg dari galeri', berkasGambar('IMG_0001.jpg', 'image/jpeg')],
        ['svg QR resi', berkasGambar('QR-EQ-2026-17725.svg', 'image/svg+xml')],
        ['tanpa MIME tapi berekstensi gambar', berkasGambar('foto.HEIC', '')],
    ])('menerima %s', (_, berkas) => {
        expect(adalahGambar(berkas)).toBe(true);
    });

    it.each([
        ['pdf', berkasGambar('resi.pdf', 'application/pdf')],
        ['teks', berkasGambar('catatan.txt', 'text/plain')],
        ['kosong', null],
    ])('menolak %s', (_, berkas) => {
        expect(adalahGambar(berkas)).toBe(false);
    });
});

describe('bacaQrDariGambar', () => {
    it('mengembalikan teks QR resi dari gambar', async () => {
        const { muatPustaka, instans } = pustakaTiruan(async () => KODE_RESI);
        const berkas = berkasGambar();

        await expect(bacaQrDariGambar(berkas, { muatPustaka })).resolves.toBe(KODE_RESI);

        expect(instans[0].scanFile).toHaveBeenCalledWith(berkas, false);
    });

    it('mengembalikan teks QR kerdus dari gambar', async () => {
        const { muatPustaka } = pustakaTiruan(async () => KODE_KERDUS);

        await expect(bacaQrDariGambar(berkasGambar('KB.jpg', 'image/jpeg'), { muatPustaka }))
            .resolves.toBe(KODE_KERDUS);
    });

    it('memakai wadah tersembunyi sendiri, bukan wadah kamera, lalu membuangnya', async () => {
        // scanFile() mengosongkan wadahnya dan menolak jalan bila instans yang
        // sama sedang memakai kamera — jadi wadah kamera tidak boleh dipakai.
        const { muatPustaka, instans } = pustakaTiruan(async () => KODE_RESI);

        await bacaQrDariGambar(berkasGambar(), { muatPustaka });

        const pembaca = instans[0];
        expect(pembaca.elementId).not.toBe('qr-scanner-container');
        expect(pembaca.wadahAdaSaatDibuat).toBe(true);
        expect(pembaca.config.verbose).toBe(false);
        expect(document.getElementById(pembaca.elementId)).toBeNull();
        expect(pembaca.clear).toHaveBeenCalled();
    });

    it('menolak berkas bukan gambar tanpa memuat pustaka pemindai', async () => {
        const { muatPustaka } = pustakaTiruan(async () => KODE_RESI);

        await expect(bacaQrDariGambar(berkasGambar('resi.pdf', 'application/pdf'), { muatPustaka }))
            .rejects.toMatchObject({ kode: 'bukan-gambar', message: PESAN_BUKAN_GAMBAR });
        expect(muatPustaka).not.toHaveBeenCalled();
    });

    it('memberi pesan jelas bila gambar tidak memuat QR', async () => {
        const { muatPustaka, instans } = pustakaTiruan(async () => {
            throw 'QR code parse error, error = NotFoundException: No MultiFormat Readers were able to detect the code.';
        });

        const gagal = bacaQrDariGambar(berkasGambar('pemandangan.jpg', 'image/jpeg'), { muatPustaka });

        await expect(gagal).rejects.toBeInstanceOf(GagalBacaGambarQR);
        await expect(gagal).rejects.toMatchObject({ kode: 'tanpa-qr', message: PESAN_TANPA_QR });
        // Wadah tetap dibersihkan walau gagal.
        expect(document.getElementById(instans[0].elementId)).toBeNull();
    });

    it('membedakan gambar yang gagal dibuka dari gambar tanpa QR', async () => {
        const { muatPustaka } = pustakaTiruan(async () => {
            throw new Event('error');
        });

        await expect(bacaQrDariGambar(berkasGambar(), { muatPustaka }))
            .rejects.toMatchObject({ kode: 'gambar-rusak', message: PESAN_GAMBAR_RUSAK });
    });

    it('menganggap hasil kosong sebagai tanpa QR', async () => {
        const { muatPustaka } = pustakaTiruan(async () => '   ');

        await expect(bacaQrDariGambar(berkasGambar(), { muatPustaka }))
            .rejects.toMatchObject({ kode: 'tanpa-qr' });
    });
});

describe('PilihGambarQR', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    function pilih(container, berkas) {
        const input = container.querySelector('[data-testid="input-gambar-qr"]');
        return fireEvent.change(input, { target: { files: [berkas] } });
    }

    it('menampilkan tombol dan input gambar yang membuka galeri di HP', () => {
        const { getByText, container } = render(PilihGambarQR);

        expect(getByText('Pilih gambar QR')).toBeTruthy();

        const input = container.querySelector('[data-testid="input-gambar-qr"]');
        expect(input.getAttribute('type')).toBe('file');
        expect(input.getAttribute('accept')).toBe('image/*');
        // Tanpa atribut capture supaya HP menawarkan galeri, bukan hanya kamera.
        expect(input.hasAttribute('capture')).toBe(false);
    });

    it('mengirim scanSuccess dengan detail.text seperti hasil kamera', async () => {
        const bacaGambar = vi.fn(async () => KODE_RESI);
        const { container, component } = render(PilihGambarQR, { props: { bacaGambar } });
        const terima = vi.fn();
        component.$on('scanSuccess', terima);

        const berkas = berkasGambar();
        await pilih(container, berkas);

        await waitFor(() => expect(terima).toHaveBeenCalledTimes(1));
        expect(bacaGambar).toHaveBeenCalledWith(berkas);
        expect(terima.mock.calls[0][0].detail).toMatchObject({ text: KODE_RESI, sumber: 'gambar' });
    });

    it('menampilkan pesan kegagalan dan tidak mengirim event bila QR tidak terbaca', async () => {
        const bacaGambar = vi.fn(async () => {
            throw new GagalBacaGambarQR('tanpa-qr', PESAN_TANPA_QR);
        });
        const { container, component, findByRole } = render(PilihGambarQR, { props: { bacaGambar } });
        const terima = vi.fn();
        component.$on('scanSuccess', terima);

        await pilih(container, berkasGambar('pemandangan.jpg', 'image/jpeg'));

        const pesan = await findByRole('alert');
        expect(pesan.textContent).toBe(PESAN_TANPA_QR);
        expect(terima).not.toHaveBeenCalled();
    });

    it('menghapus pesan gagal lama setelah gambar berikutnya terbaca', async () => {
        const bacaGambar = vi.fn()
            .mockRejectedValueOnce(new GagalBacaGambarQR('tanpa-qr', PESAN_TANPA_QR))
            .mockResolvedValueOnce(KODE_KERDUS);
        const { container, findByRole, queryByRole } = render(PilihGambarQR, { props: { bacaGambar } });

        await pilih(container, berkasGambar());
        await findByRole('alert');

        await pilih(container, berkasGambar());
        await waitFor(() => expect(queryByRole('alert')).toBeNull());
    });
});
