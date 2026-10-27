/**
 * Batas doa wakif (keputusan pemilik 28/10/2026): doa dicetak di label resi 100×150 mm,
 * dan lebih dari 100 karakter mendorong isi label keluar kotak. Sama dengan
 * WakafItem::DOA_MAKS_KARAKTER di server.
 */
export const DOA_MAKS_KARAKTER = 100;

/** Panjang doa seperti dihitung server (spasi di ujung diabaikan). */
export function panjangDoa(teks) {
    return [...String(teks ?? '').trim()].length;
}

/** Doa lebih panjang dari batas (biasanya doa lama yang tersimpan sebelum batas berlaku). */
export function doaTerlaluPanjang(teks) {
    return panjangDoa(teks) > DOA_MAKS_KARAKTER;
}
