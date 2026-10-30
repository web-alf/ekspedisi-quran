/**
 * Target periode per gudang (30/10/2026): pilihan minggu/bulan di modal dan teks laju.
 *
 * Rentang yang sebenarnya dihitung server (TargetPeriodeGudang::rentang); di sini hanya
 * pilihan yang ditawarkan: periode berjalan + beberapa ke depan, tidak ada yang sudah lewat.
 */

const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const BULAN_PENDEK = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

const duaDigit = (n) => String(n).padStart(2, '0');
const isoTanggal = (d) => `${d.getFullYear()}-${duaDigit(d.getMonth() + 1)}-${duaDigit(d.getDate())}`;

/** Senin di minggu yang memuat tanggal ini. */
export function seninMinggu(tanggal) {
    const d = new Date(tanggal.getFullYear(), tanggal.getMonth(), tanggal.getDate());
    const geser = (d.getDay() + 6) % 7; // Minggu = 6, Senin = 0
    d.setDate(d.getDate() - geser);
    return d;
}

/** Jumlah hari dalam rentang tanggal_acuan untuk tipe ini. */
export function jumlahHariPeriode(tipe, nilai) {
    if (!nilai) return 0;
    if (tipe === 'mingguan') return 7;
    const [tahun, bulan] = nilai.split('-').map(Number);
    return new Date(tahun, bulan, 0).getDate();
}

/**
 * Pilihan periode: nilai = tanggal acuan (Senin atau tanggal 1), label untuk dropdown.
 *
 * @returns {Array<{nilai: string, label: string}>}
 */
export function opsiPeriode(tipe, hariIni = new Date(), jumlah = tipe === 'mingguan' ? 8 : 6) {
    const opsi = [];

    if (tipe === 'mingguan') {
        const senin = seninMinggu(hariIni);
        for (let i = 0; i < jumlah; i++) {
            const mulai = new Date(senin.getFullYear(), senin.getMonth(), senin.getDate() + i * 7);
            const akhir = new Date(mulai.getFullYear(), mulai.getMonth(), mulai.getDate() + 6);
            const rentang = mulai.getMonth() === akhir.getMonth()
                ? `${mulai.getDate()}–${akhir.getDate()} ${BULAN_PENDEK[akhir.getMonth()]} ${akhir.getFullYear()}`
                : `${mulai.getDate()} ${BULAN_PENDEK[mulai.getMonth()]} – ${akhir.getDate()} ${BULAN_PENDEK[akhir.getMonth()]} ${akhir.getFullYear()}`;
            opsi.push({ nilai: isoTanggal(mulai), label: (i === 0 ? 'Minggu ini · ' : '') + rentang });
        }
        return opsi;
    }

    for (let i = 0; i < jumlah; i++) {
        const awal = new Date(hariIni.getFullYear(), hariIni.getMonth() + i, 1);
        const hari = new Date(awal.getFullYear(), awal.getMonth() + 1, 0).getDate();
        opsi.push({
            nilai: isoTanggal(awal),
            label: `${BULAN[awal.getMonth()]} ${awal.getFullYear()} (1–${hari} ${BULAN_PENDEK[awal.getMonth()]})`,
        });
    }
    return opsi;
}

/**
 * Teks dan warna pil laju untuk satu target dari TargetPeriodeService::ringkas().
 *
 * @returns {{teks: string, warna: 'green'|'amber'|'gray'}}
 */
export function statusLaju(target) {
    if (target.status === 'akan_datang') {
        return { teks: 'Belum dimulai', warna: 'gray' };
    }
    if (target.dikemas >= target.target) {
        return { teks: 'Target tercapai', warna: 'green' };
    }
    if (target.selisih_laju >= 0) {
        return { teks: target.selisih_laju === 0 ? 'Sesuai laju' : `Di depan ${target.selisih_laju} dari laju`, warna: 'green' };
    }
    return { teks: `Tertinggal ${Math.abs(target.selisih_laju)} dari laju`, warna: 'amber' };
}
