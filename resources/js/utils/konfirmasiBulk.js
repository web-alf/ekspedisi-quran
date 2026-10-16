/**
 * Pembentuk pesan konfirmasi untuk panel "Update Status Massal".
 *
 * Kenapa dipisah dari halaman: pesan modal sebelumnya hanya menyebut JUMLAH
 * (`mengupdate status 1 pengiriman`), sehingga pengguna tidak pernah melihat
 * status tujuan yang akan ditulis — padahal itu satu-satunya hal yang perlu
 * diperiksa ulang sebelum menekan konfirmasi. Sebagai modul biasa, aturan
 * pesannya bisa diuji tanpa merender komponen Svelte.
 */

/**
 * Nama status dari daftar `statusList` (prop halaman) berdasarkan id terpilih.
 * Id dari `<select bind:value>` bisa berupa angka maupun string, jadi
 * perbandingannya disamakan dulu.
 */
export function namaStatusTerpilih(daftarStatus, statusId) {
    if (statusId === '' || statusId === null || statusId === undefined) {
        return '';
    }

    const cocok = (daftarStatus || []).find((s) => String(s?.id) === String(statusId));

    return cocok?.nama ?? '';
}

/**
 * "Ubah status 3 resi menjadi "Proses Produksi"?"
 *
 * Bila statusnya tidak ketemu di daftar, pesannya menyatakannya terang-terangan
 * alih-alih mencetak tanda kutip kosong — pesan konfirmasi harus bisa dipercaya.
 */
export function pesanKonfirmasiUpdateStatus(jumlahResi, daftarStatus, statusId) {
    const nama = namaStatusTerpilih(daftarStatus, statusId);
    const tujuan = nama === '' ? 'status yang tidak dikenal' : `"${nama}"`;

    return `Ubah status ${jumlahResi} resi menjadi ${tujuan}?`;
}
