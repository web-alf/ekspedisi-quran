/**
 * Modal "Assign Target Harian" di Monitor Gudang (28/10/2026).
 *
 * Jenis mushaf wajib dipilih (A5/A6 dengan atau tanpa terjemahan, atau Iqro), dan gudang
 * tugas tidak lagi dipilih: mengikuti penempatan staf yang ditugasi (`gudang_tugas` dari
 * server). Dipisah dari halaman supaya bisa diuji tanpa merender Svelte.
 */

/**
 * Isian jenis untuk POST assign-target dari nilai pilihan ("1:0", "5:-").
 *
 * @param {Array<{nilai:string, jenis_quran_id:number, terjemahan:boolean|null}>} pilihanJenis
 * @param {string} nilai
 * @returns {{jenis_quran_id:number, terjemahan:boolean|null}|null}
 */
export function isianJenis(pilihanJenis = [], nilai = '') {
    const pilihan = pilihanJenis.find((p) => p.nilai === nilai);

    return pilihan ? { jenis_quran_id: pilihan.jenis_quran_id, terjemahan: pilihan.terjemahan } : null;
}

/**
 * Nama gudang tempat tugas staf ini akan tercatat; null bila staf belum ditempatkan.
 *
 * @param {Array<{id:number|string, gudang_tugas?:string|null}>} kandidat
 * @param {number|string} userId
 * @returns {string|null}
 */
export function gudangTugas(kandidat = [], userId = '') {
    return kandidat.find((u) => String(u.id) === String(userId))?.gudang_tugas ?? null;
}

/**
 * Apakah modal sudah layak disimpan? Alasannya ditampilkan di bawah tombol.
 *
 * @returns {{boleh:boolean, alasan:string}}
 */
export function bolehSimpanTarget({ userId = '', target = 0, jenis = '' } = {}, pilihanJenis = [], kandidat = []) {
    if (!userId) return { boleh: false, alasan: 'Pilih staf lebih dulu.' };
    if (gudangTugas(kandidat, userId) === null) {
        return { boleh: false, alasan: 'Staf ini belum ditempatkan di gudang. Tempatkan dulu lewat menu Penempatan Gudang.' };
    }
    if (!(Number(target) >= 1 && Number(target) <= 200)) return { boleh: false, alasan: 'Target harus antara 1 dan 200 mushaf.' };
    if (!isianJenis(pilihanJenis, jenis)) return { boleh: false, alasan: 'Pilih jenis mushaf.' };

    return { boleh: true, alasan: '' };
}
