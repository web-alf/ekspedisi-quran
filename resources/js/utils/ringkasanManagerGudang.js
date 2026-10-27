/**
 * Turunan data dashboard Manager Gudang (PJ Semarang & Boyolali) — desain 27/10/2026.
 *
 * Dashboard Manager memakai satu filter gudang ("Semua gudang" / satu gudang) yang
 * menggantikan seksi stok berulang per gudang. Semua penyaringan dan penurunan angka
 * ada di sini supaya bisa diuji tanpa merender halaman, dan supaya angka di setiap
 * seksi berasal dari baris `per_gudang` yang sama dari RingkasanGudangService.
 */

export const SEMUA_GUDANG = 'semua';

/**
 * Baris `per_gudang` yang tampil untuk pilihan filter.
 *
 * @template {{id: number|string}} G
 * @param {G[]} perGudang
 * @param {string} aktif 'semua' atau id gudang (string)
 * @returns {G[]}
 */
export function gudangTampil(perGudang = [], aktif = SEMUA_GUDANG) {
    if (aktif === SEMUA_GUDANG) return perGudang;

    return perGudang.filter((g) => String(g.id) === String(aktif));
}

/**
 * Jumlah mushaf siap di gudang yang belum ada wakifnya (kelima jenis/varian).
 *
 * @param {{muwakif?: Array<{eks: number}>}} stok
 */
export function totalBelumAdaWakif(stok) {
    return (stok?.muwakif ?? []).reduce((n, baris) => n + (Number(baris.eks) || 0), 0);
}

/**
 * Angka "Packing Hari Ini" untuk cakupan filter. "Semua gudang" memakai angka gabungan
 * server (yang memang = Semarang + Boyolali); satu gudang memakai barisnya sendiri.
 *
 * @param {{gabungan?: object, per_gudang?: object[]}|null} ringkasan
 * @param {string} aktif
 * @returns {{target: number, selesai: number, kerdus: number, staf: number, belumMulai: number, persen: number}}
 */
export function packingCakupan(ringkasan, aktif = SEMUA_GUDANG) {
    const sumber = aktif === SEMUA_GUDANG
        ? ringkasan?.gabungan
        : gudangTampil(ringkasan?.per_gudang ?? [], aktif)[0];

    const target = Number(sumber?.target) || 0;
    const selesai = Number(sumber?.selesai) || 0;

    return {
        target,
        selesai,
        kerdus: Number(sumber?.kerdus_tersegel) || 0,
        staf: Number(sumber?.staf_bertugas ?? sumber?.staf_total) || 0,
        belumMulai: Number(sumber?.staf_belum_mulai) || 0,
        persen: target > 0 ? Math.round((selesai / target) * 100) : 0,
    };
}

/**
 * Stok belum ada wakif dikelompokkan per jenis, dengan rincian tiap gudang — supaya
 * Semarang dan Boyolali bisa dibandingkan berdampingan untuk jenis yang sama.
 *
 * @param {Array<{id: number|string, nama: string, stok?: {muwakif?: Array<{label: string, eks: number, status?: string}>}}>} gudang
 * @returns {Array<{label: string, total: number, gudang: Array<{id: number|string, nama: string, eks: number, status: string}>}>}
 */
export function stokPerJenis(gudang = []) {
    const urutan = [];
    const perLabel = new Map();

    for (const g of gudang) {
        for (const baris of g.stok?.muwakif ?? []) {
            if (!perLabel.has(baris.label)) {
                perLabel.set(baris.label, { label: baris.label, total: 0, gudang: [] });
                urutan.push(baris.label);
            }

            const jenis = perLabel.get(baris.label);
            const eks = Number(baris.eks) || 0;
            jenis.total += eks;
            jenis.gudang.push({ id: g.id, nama: g.nama, eks, status: baris.status ?? (eks > 0 ? 'normal' : 'habis') });
        }
    }

    return urutan.map((label) => perLabel.get(label));
}

/**
 * Jenis yang habis di satu gudang padahal gudang lain masih punya — keputusan yang
 * bisa langsung diambil Manager lewat transfer stok. Sumber = gudang dengan stok
 * terbanyak untuk jenis itu, dan hanya bila stoknya normal (minimal satu dus):
 * menyarankan memindahkan stok yang sudah "rendah" hanya memindahkan masalahnya.
 *
 * @param {Array<{id: number|string, nama: string, stok?: {muwakif?: Array<{label: string, eks: number}>}}>} perGudang
 * @returns {Array<{label: string, gudang: string, sumber: string, eksSumber: number}>}
 */
export function stokHabisDiSatuGudang(perGudang = []) {
    if (perGudang.length < 2) return [];

    return stokPerJenis(perGudang).flatMap((jenis) => {
        const sumber = [...jenis.gudang].sort((a, b) => b.eks - a.eks)[0];
        if (!sumber || sumber.eks <= 0 || sumber.status !== 'normal') return [];

        return jenis.gudang
            .filter((g) => g.eks <= 0)
            .map((g) => ({ label: jenis.label, gudang: g.nama, sumber: sumber.nama, eksSumber: sumber.eks }));
    });
}

const URUTAN_STATUS_STAF = { tidak_mulai: 0, mulai: 1, selesai: 2 };

/**
 * Freelancer yang ditugaskan hari ini: yang belum mulai paling atas, karena itulah
 * yang perlu ditindaklanjuti. Status lain dari server dianggap belum mulai, sama
 * dengan tampilan pil di FreelancerKerjaHariIni.
 *
 * @template {{status?: string}} S
 * @param {S[]} staf
 * @returns {S[]}
 */
export function urutkanStafBelumMulaiDulu(staf = []) {
    const peringkat = (s) => URUTAN_STATUS_STAF[s.status] ?? 0;

    return staf
        .map((s, i) => ({ s, i }))
        .sort((a, b) => (peringkat(a.s) - peringkat(b.s)) || (a.i - b.i))
        .map(({ s }) => s);
}

/**
 * Dokumen perpindahan yang menyangkut gudang yang tampil (asal ATAU tujuan).
 *
 * @template {{asal?: string|null, tujuan?: string|null}} D
 * @param {D[]} dokumen
 * @param {Array<{nama: string}>} gudang
 * @param {string} aktif
 * @returns {D[]}
 */
export function dokumenTerkait(dokumen = [], gudang = [], aktif = SEMUA_GUDANG) {
    if (aktif === SEMUA_GUDANG) return dokumen;

    const nama = new Set(gudang.map((g) => g.nama));

    return dokumen.filter((d) => nama.has(d.asal) || nama.has(d.tujuan));
}
