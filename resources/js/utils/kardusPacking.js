/**
 * Ringkasan hasil "Catat Kerdus" — alur packing kardus, tempat mushaf datang
 * SUDAH dalam kardus/dos dari produksi sehingga tiap mushaf tidak dipindai.
 *
 * Dipisah dari halaman supaya bisa diuji tanpa merender Svelte: vitest di repo
 * ini mengompilasi komponen ke SSR sehingga onMount tidak berjalan.
 *
 * Aturan yang dijaga di sini:
 * - kekurangan eks DILAPORKAN (kerdus "terisi", bukan penuh) — jangan sampai
 *   kerdus separuh tampak seperti kerdus penuh;
 * - resi yang tidak cukup ruang (mis. 60 eks pada sisa 8) dilaporkan sebagai
 *   penghalang, bukan diam-diam dilewati (urutan FIFO tidak dilanggar);
 * - pelampauan target harian dilaporkan, karena satu kerdus fisik tidak bisa
 *   dicatat separuh.
 */

export function ringkasHasilKardus(hasil) {
    if (!hasil || typeof hasil !== 'object') {
        return null;
    }

    const jenis = hasil.jenis || {};
    const kode = jenis.kode_jenis || '';
    const jumlahKerdus = Number(hasil.jumlah_kerdus || 0);
    const totalMushaf = Number(hasil.total_mushaf || 0);

    const kerdus = (hasil.kerdus || []).map((box) => ({
        kode: box.kode_kerdus,
        terisi: Number(box.jumlah_terisi || 0),
        kapasitas: Number(box.kapasitas || 0),
        tersegel: box.status === 'sealed',
        keterangan: `${Number(box.jumlah_terisi || 0)}/${Number(box.kapasitas || 0)} eks · ${box.status === 'sealed' ? 'tersegel' : 'terisi'}`,
    }));

    const peringatan = [];

    if (Number(hasil.kekurangan || 0) > 0) {
        peringatan.push(
            `Kurang ${hasil.kekurangan} eks dari ${hasil.diminta} yang diminta — stok resi berstatus packing tidak cukup, kerdus tetap "terisi" (bukan penuh).`,
        );
    }

    if (hasil.tertahan) {
        peringatan.push(
            `Resi ${hasil.tertahan.no_resi} butuh ${hasil.tertahan.butuh} eks, sisa ruang hanya ${hasil.tertahan.tersedia} — kerdus ditutup apa adanya, urutan FIFO tidak dilanggar.`,
        );
    }

    if (hasil.melebihi_target) {
        peringatan.push(
            `Progres tugas kini ${hasil.selesai}/${hasil.target} — melewati target hari ini, laporkan ke supervisor.`,
        );
    }

    if (hasil.tugas_selesai) {
        peringatan.push('Tugas hari ini sudah selesai. Minta supervisor bila masih ada kerdus yang perlu dicatat.');
    }

    return {
        judul: `${jumlahKerdus} kerdus ${kode} · ${totalMushaf} mushaf tercatat`,
        kerdus,
        peringatan,
    };
}

/**
 * Keterangan pilihan: "2 kerdus A5 = 40 eks" — bantu gudang memastikan jumlah
 * eks sebelum menekan tombol.
 */
export function labelKerdus(jenis, jumlah = 1) {
    if (!jenis) {
        return '';
    }

    const kapasitas = Number(jenis.kapasitas || 0);
    const jumlahKerdus = Math.max(1, Number(jumlah) || 1);

    return `${jumlahKerdus} kerdus ${jenis.kode_jenis || ''} = ${kapasitas * jumlahKerdus} eks`.replace(/\s+/g, ' ');
}
