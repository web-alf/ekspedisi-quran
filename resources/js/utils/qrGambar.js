/**
 * Membaca QR dari gambar tersimpan (galeri HP / berkas komputer).
 *
 * Cadangan untuk pemindai kamera: bila kamera rusak, izin ditolak, atau QR
 * hanya ada sebagai berkas (mis. QR-EQ-2026-00001.svg hasil unduhan), pengguna
 * tetap bisa memindai dengan memilih gambarnya.
 *
 * Dipisah dari komponen supaya aturan pesan kegagalannya bisa diuji tanpa
 * merender Svelte dan tanpa pustaka pemindai sungguhan.
 */

const ID_WADAH = 'qr-gambar-pembaca';

export const PESAN_BUKAN_GAMBAR = 'Berkas yang dipilih bukan gambar. Pilih foto atau gambar QR (JPG, PNG, SVG, WEBP).';
export const PESAN_GAMBAR_RUSAK = 'Gambar tidak dapat dibuka. Coba pilih gambar lain.';
export const PESAN_TANPA_QR = 'Tidak ada QR yang terbaca di gambar ini. Pastikan QR terlihat utuh, tidak buram, dan tidak terpotong, lalu coba lagi.';

/**
 * Kegagalan dengan pesan yang siap ditampilkan ke pengguna.
 */
export class GagalBacaGambarQR extends Error {
    constructor(kode, pesan) {
        super(pesan);
        this.name = 'GagalBacaGambarQR';
        this.kode = kode;
    }
}

/**
 * Berkas dianggap gambar bila MIME-nya image/*. Sebagian peramban HP tidak
 * mengisi MIME, jadi ekstensi nama berkas dipakai sebagai cadangan.
 */
export function adalahGambar(berkas) {
    if (!berkas) {
        return false;
    }

    if (typeof berkas.type === 'string' && berkas.type.startsWith('image/')) {
        return true;
    }

    return /\.(jpe?g|png|gif|webp|bmp|svg|heic|heif)$/i.test(berkas.name || '');
}

/**
 * Pustaka melempar Event bila gambarnya sendiri gagal dimuat, dan string/Error
 * berisi "NotFoundException" bila gambarnya terbuka tetapi QR tidak ditemukan.
 */
function terjemahkanGagal(err) {
    if (typeof Event !== 'undefined' && err instanceof Event) {
        return new GagalBacaGambarQR('gambar-rusak', PESAN_GAMBAR_RUSAK);
    }

    return new GagalBacaGambarQR('tanpa-qr', PESAN_TANPA_QR);
}

async function muatPustakaBawaan() {
    return import('html5-qrcode');
}

/**
 * Mengembalikan teks QR di dalam gambar.
 *
 * Html5Qrcode memakai wadah tersembunyi sendiri — bukan wadah kamera — karena
 * scanFile() mengosongkan isi wadahnya dan menolak bekerja bila instans yang
 * sama sedang memakai kamera. Dengan wadah terpisah, gambar tetap bisa dibaca
 * walau kamera sedang menyala.
 *
 * @param {File} berkas
 * @param {{ muatPustaka?: () => Promise<{ Html5Qrcode: any, Html5QrcodeSupportedFormats?: any }> }} [opsi]
 * @returns {Promise<string>}
 */
export async function bacaQrDariGambar(berkas, { muatPustaka = muatPustakaBawaan } = {}) {
    if (!adalahGambar(berkas)) {
        throw new GagalBacaGambarQR('bukan-gambar', PESAN_BUKAN_GAMBAR);
    }

    const { Html5Qrcode, Html5QrcodeSupportedFormats } = await muatPustaka();

    const wadah = document.createElement('div');
    wadah.id = `${ID_WADAH}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    wadah.style.display = 'none';
    document.body.appendChild(wadah);

    const pembaca = new Html5Qrcode(wadah.id, {
        verbose: false,
        formatsToSupport: Html5QrcodeSupportedFormats ? [Html5QrcodeSupportedFormats.QR_CODE] : undefined,
        useBarCodeDetectorIfSupported: true,
    });

    try {
        const teks = await pembaca.scanFile(berkas, false);

        if (typeof teks !== 'string' || teks.trim() === '') {
            throw new GagalBacaGambarQR('tanpa-qr', PESAN_TANPA_QR);
        }

        return teks;
    } catch (err) {
        if (err instanceof GagalBacaGambarQR) {
            throw err;
        }

        throw terjemahkanGagal(err);
    } finally {
        try {
            pembaca.clear();
        } catch {
            // Wadah sudah kosong — tidak masalah.
        }
        wadah.remove();
    }
}
