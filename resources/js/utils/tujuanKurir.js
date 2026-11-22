/**
 * Tujuan yang dituju kurir di beranda "Hari Ini & Rute".
 *
 * Kenapa dipisah ke sini: kurir tidak selalu mengantar sesuai urutan muat (22/11/2026).
 * Server hanya memberi SARAN (`status: 'berikutnya'`); kurir memilih sendiri lewat kartu
 * geser, dan pilihannya diingat di HP. Aturan memilih dan mengingat itu perlu diuji tanpa
 * merender halaman.
 */

/** Status tujuan yang masih perlu dikunjungi. */
const BELUM_DIKUNJUNGI = ['berikutnya', 'menunggu'];

/**
 * @param {Array<{kunci:string, status:string}>} tujuan
 * @returns {Array<{kunci:string, status:string}>}
 */
export function tujuanBelumDikunjungi(tujuan = []) {
    return tujuan.filter((t) => BELUM_DIKUNJUNGI.includes(t.status));
}

/**
 * Kunci tujuan yang sedang dituju: pilihan tersimpan bila masih perlu dikunjungi,
 * selain itu saran server, selain itu tujuan pertama yang belum dikunjungi.
 *
 * @param {Array<{kunci:string, status:string}>} tujuan
 * @param {string|null} kunciTersimpan
 * @returns {string|null}
 */
export function pilihDituju(tujuan = [], kunciTersimpan = null) {
    const belum = tujuanBelumDikunjungi(tujuan);

    if (kunciTersimpan && belum.some((t) => t.kunci === kunciTersimpan)) return kunciTersimpan;

    return (belum.find((t) => t.status === 'berikutnya') || belum[0] || {}).kunci ?? null;
}

/** @param {number|string} muatanId */
export function kunciPenyimpananDituju(muatanId) {
    return `kurir-dituju-${muatanId}`;
}

/**
 * localStorage bisa kosong atau melempar galat (mode privat, data situs diblokir), jadi
 * kegagalan membaca/menulis cukup berarti "tidak ingat pilihan".
 *
 * @param {number|string} muatanId
 * @returns {string|null}
 */
export function bacaDituju(muatanId) {
    try {
        return window.localStorage.getItem(kunciPenyimpananDituju(muatanId));
    } catch {
        return null;
    }
}

/**
 * @param {number|string} muatanId
 * @param {string} kunci
 */
export function simpanDituju(muatanId, kunci) {
    try {
        window.localStorage.setItem(kunciPenyimpananDituju(muatanId), kunci);
    } catch {
        // Tidak bisa mengingat pilihan; beranda kembali ke saran server saat dibuka ulang.
    }
}
