/**
 * Perhitungan untuk formulir "Catat Pergerakan" stok mushaf.
 *
 * Dipisah dari halaman supaya bisa diuji tanpa merender Svelte (pola yang sama dengan
 * konfirmasiBulk.js): pratinjau "stok sekarang → sesudah" adalah penjaga kesalahan yang
 * paling sering terjadi — mencatat pengurangan melebihi stok yang ada.
 */

/** Label varian yang dipakai konsisten di formulir, tabel, dan dokumen transfer. */
export function labelVarian(terjemahan) {
    if (terjemahan === '' || terjemahan === null || terjemahan === undefined) return '';

    return Number(terjemahan) === 1 ? 'Dengan terjemahan' : 'Tanpa terjemahan';
}

/** Varian satu-satunya untuk jenis tanpa varian (IQRA): selalu disimpan tanpa terjemahan. */
export const VARIAN_JENIS_TUNGGAL = '0';

/**
 * Apakah jenis ini menawarkan pilihan terjemahan? Penandanya `ada_varian` dari server
 * (config pengiriman.stok_varian_jenis). Jenis yang belum dipilih/dikenal dianggap punya
 * varian, supaya pilihan tidak hilang sebelum jenisnya jelas.
 *
 * @param {Array<{id:number|string, ada_varian?:boolean}>} jenisList
 * @param {number|string|null} jenisId
 */
export function jenisPunyaVarian(jenisList = [], jenisId = null) {
    const jenis = jenisList.find((j) => String(j.id) === String(jenisId));

    return jenis ? jenis.ada_varian !== false : true;
}

/**
 * Varian sesudah jenis berganti: jenis tanpa varian memakai VARIAN_JENIS_TUNGGAL; kembali
 * dari jenis tanpa varian ke A5/A6 mengosongkan pilihan supaya varian dipilih sendiri,
 * bukan terbawa diam-diam dari IQRA.
 *
 * @param {Array<{id:number|string, ada_varian?:boolean}>} jenisList
 * @param {number|string} jenisBaru
 * @param {number|string} varian
 * @param {number|string} jenisLama
 * @returns {string}
 */
export function varianSesudahGantiJenis(jenisList, jenisBaru, varian, jenisLama) {
    if (jenisBaru !== '' && jenisBaru !== null && !jenisPunyaVarian(jenisList, jenisBaru)) {
        return VARIAN_JENIS_TUNGGAL;
    }
    if (jenisLama !== '' && jenisLama !== null && !jenisPunyaVarian(jenisList, jenisLama)) {
        return '';
    }

    return varian === null || varian === undefined ? '' : String(varian);
}

/**
 * Stok yang tercatat untuk kombinasi gudang × jenis × varian.
 *
 * @param {Array<{jenis_id:number|string, varian:boolean|number, gudang_id?:number|string, eks:number}>} stokList
 * @param {{jenisId?:number|string|null, terjemahan?:number|string|null, gudangId?:number|string|null}} kunci
 * @returns {number} eks (0 bila belum ada barisnya)
 */
export function stokSaatIni(stokList = [], { jenisId = null, terjemahan = null, gudangId = null } = {}) {
    if (jenisId === null || jenisId === '' || terjemahan === null || terjemahan === '') return 0;

    const baris = stokList.find((s) => String(s.jenis_id) === String(jenisId)
        && Number(s.varian) === Number(terjemahan)
        && (gudangId === null || gudangId === '' || String(s.gudang_id) === String(gudangId)));

    return baris ? Number(baris.eks) || 0 : 0;
}

/**
 * Hasil pergerakan: stok akhir, dan apakah pengurangannya masih cukup.
 *
 * @param {'masuk'|'keluar'} tipe
 * @param {number} stok
 * @param {number} jumlah
 * @returns {{akhir:number, cukup:boolean, selisih:number}}
 */
export function hitungHasil(tipe, stok, jumlah) {
    const awal = Number(stok) || 0;
    const n = Math.max(0, Number(jumlah) || 0);
    const akhir = tipe === 'keluar' ? awal - n : awal + n;

    return { akhir, cukup: akhir >= 0, selisih: Math.abs(akhir) };
}

/**
 * Apakah formulir sudah layak disimpan?
 *
 * @param {{tipe?:string, gudang?:string|number, jenis?:string|number, terjemahan?:string|number, jumlah?:number}} isian
 * @returns {{boleh:boolean, alasan:string}}
 */
export function bolehSimpan({ tipe = 'masuk', gudang = '', jenis = '', terjemahan = '', jumlah = 0 } = {}) {
    if (!gudang) return { boleh: false, alasan: 'Gudang belum ditentukan.' };
    if (!jenis) return { boleh: false, alasan: 'Pilih jenis mushaf dulu.' };
    if (terjemahan === '' || terjemahan === null) return { boleh: false, alasan: 'Pilih varian (dengan atau tanpa terjemahan).' };
    if (!(Number(jumlah) > 0)) return { boleh: false, alasan: 'Jumlah harus lebih dari 0.' };
    if (tipe !== 'masuk' && tipe !== 'keluar') return { boleh: false, alasan: 'Tipe pergerakan tidak dikenal.' };

    return { boleh: true, alasan: '' };
}
