/**
 * Ringkasan status resi yang sedang dipilih di panel "Update Status Massal".
 *
 * Kenapa perlu: field "Status Baru" hanya menampilkan placeholder
 * ("Pilih Status Baru...") sehingga pengguna tidak tahu resi yang dipilih
 * sekarang ada di tahap mana. Padahal status tujuan yang benar ditentukan oleh
 * status sekarang — dan server MENOLAK/melompati transisi ke status yang sama
 * (`validateStatusTransition`), jadi menawarkan status sekarang tanpa tanda
 * apa pun adalah jebakan: pengguna memilihnya, menekan Update, lalu tidak
 * terjadi apa-apa.
 *
 * Dipisah dari halaman supaya bisa diuji tanpa merender Svelte (vitest di repo
 * ini mengompilasi ke SSR sehingga onMount tidak berjalan).
 */

/**
 * @param {Array<{id: number, status?: {id: number, nama: string}}>} daftarBaris
 * @returns {{kosong: boolean, beragam: boolean, nama: string, id: number|null, jumlahStatus: number}}
 */
export function ringkasStatusTerpilih(daftarBaris) {
    const baris = Array.isArray(daftarBaris) ? daftarBaris : [];

    if (baris.length === 0) {
        return { kosong: true, beragam: false, nama: '', id: null, jumlahStatus: 0 };
    }

    const unik = [];
    for (const b of baris) {
        const nama = b?.status?.nama;
        if (nama && !unik.some((u) => u.nama === nama)) {
            unik.push({ nama, id: b.status.id ?? null });
        }
    }

    if (unik.length === 0) {
        return { kosong: false, beragam: false, nama: '', id: null, jumlahStatus: 0 };
    }

    if (unik.length === 1) {
        return { kosong: false, beragam: false, nama: unik[0].nama, id: unik[0].id, jumlahStatus: 1 };
    }

    return { kosong: false, beragam: true, nama: '', id: null, jumlahStatus: unik.length };
}

/**
 * Teks placeholder field: tetap menyuruh memilih, tetapi menyebut status
 * sekarang supaya tidak perlu menebak.
 */
export function labelPilihStatus(ringkas) {
    if (!ringkas || ringkas.kosong) {
        return 'Pilih Status Baru...';
    }

    if (ringkas.beragam) {
        return `Pilih Status Baru... (${ringkas.jumlahStatus} status berbeda)`;
    }

    return ringkas.nama === '' ? 'Pilih Status Baru...' : `Pilih Status Baru... (sekarang: ${ringkas.nama})`;
}

/**
 * Apakah opsi status ini adalah status yang sedang dipegang resi terpilih?
 * Dipakai untuk menandai + menonaktifkan opsi itu di dropdown.
 */
export function adalahStatusSekarang(ringkas, statusId) {
    if (!ringkas || ringkas.id === null || ringkas.id === undefined) {
        return false;
    }

    return String(ringkas.id) === String(statusId);
}
