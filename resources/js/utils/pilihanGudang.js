/**
 * Pilihan gudang pada formulir Stok Mushaf.
 *
 * Kenapa dipisah ke sini: server SUDAH menolak gudang milik SPV lain (403), tetapi
 * formulir tetap menawarkannya — pengguna baru tahu setelah menekan Simpan. Pemilik
 * sistem menuntut opsi yang tidak sah jangan pernah ditawarkan (bukan hanya divalidasi
 * belakangan), dan aturan itu perlu diuji tanpa merender halaman.
 */

/**
 * Gudang yang boleh dipilih saat mencatat pergerakan stok.
 *
 * SPV tercakup gudang hanya boleh menyentuh gudangnya sendiri; peran lain (PJ,
 * super-admin) melihat semuanya. Bila SPV belum ditempatkan (gudangSaya null), hasilnya
 * kosong — lebih baik tidak bisa memilih daripada memilih gudang orang lain.
 *
 * @param {Array<{id:number|string, nama:string}>} gudangList
 * @param {{gudangSaya?: number|string|null, tercakupGudang?: boolean}} opsi
 * @returns {Array<{id:number|string, nama:string}>}
 */
export function gudangUntukEntri(gudangList = [], { gudangSaya = null, tercakupGudang = false } = {}) {
    if (!tercakupGudang) return gudangList;
    if (gudangSaya === null || gudangSaya === undefined || gudangSaya === '') return [];

    return gudangList.filter((g) => String(g.id) === String(gudangSaya));
}

/**
 * Gudang tujuan transfer stok: semua gudang kecuali asal.
 *
 * Berlaku untuk semua peran — memindahkan stok ke gudang yang sama tidak punya arti dan
 * ditolak server. Untuk SPV, `asalId` selalu gudangnya sendiri.
 *
 * @param {Array<{id:number|string, nama:string}>} gudangList
 * @param {number|string|null} asalId
 */
export function gudangTujuanTransfer(gudangList = [], asalId = null) {
    if (asalId === null || asalId === undefined || asalId === '') return gudangList;

    return gudangList.filter((g) => String(g.id) !== String(asalId));
}

/**
 * Nilai awal kolom gudang pada formulir: SPV terkunci ke gudangnya, peran lain mulai
 * dari kosong ("Pilih gudang...").
 *
 * @param {{gudangSaya?: number|string|null, tercakupGudang?: boolean}} opsi
 * @returns {string}
 */
export function gudangAwal({ gudangSaya = null, tercakupGudang = false } = {}) {
    if (!tercakupGudang) return '';
    if (gudangSaya === null || gudangSaya === undefined || gudangSaya === '') return '';

    return String(gudangSaya);
}
