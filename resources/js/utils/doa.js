/**
 * Batas doa wakif (keputusan pemilik 28/10/2026): doa dicetak di label resi 100×150 mm
 * dengan font yang mengecil bertingkat sampai 6pt, dan lebih dari 250 karakter tidak lagi
 * muat terbaca. Sama dengan WakafItem::DOA_MAKS_KARAKTER di server.
 */
export const DOA_MAKS_KARAKTER = 250;

/** Panjang doa seperti dihitung server (spasi di ujung diabaikan). */
export function panjangDoa(teks) {
    return [...String(teks ?? '').trim()].length;
}

/** Doa lebih panjang dari batas (biasanya doa lama yang tersimpan sebelum batas berlaku). */
export function doaTerlaluPanjang(teks) {
    return panjangDoa(teks) > DOA_MAKS_KARAKTER;
}
