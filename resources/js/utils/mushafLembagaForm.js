/**
 * Susun isi form "Informasi Lembaga" dari data permintaan mushaf.
 *
 * KENAPA DIPISAH: dropdown di AddressFormIndonesia memilih wilayah berdasarkan ID
 * (`provinsi_id`, `kota_kabupaten_id`, `kecamatan_id`, `kelurahan_desa_id`), bukan
 * berdasarkan namanya. Halaman detail dulu hanya mengisi NAMA-nya, sehingga
 * keempat dropdown tampil kosong ("Pilih Provinsi") padahal data wilayahnya
 * tersimpan lengkap — dan simpan berikutnya bisa mengosongkan wilayah itu.
 *
 * Dipisah ke modul sendiri supaya pemetaan ini bisa diuji langsung, tanpa perlu
 * merender halaman (Vitest di proyek ini mengompilasi Svelte untuk SSR sehingga
 * `onMount` tidak berjalan dan komponen yang memuat data saat dipasang selalu
 * tampak kosong di tes).
 */

/**
 * @param {Record<string, any>} mushafRequest - Data permintaan dari server
 * @returns {Record<string, string>} Isi form yang siap di-bind dan dikirim kembali
 */
export function buatFormLembaga(mushafRequest = {}) {
    // ID wilayah dibandingkan sebagai teks: <option value="33"> selalu berupa
    // string, sedangkan server bisa mengirim angka. Tanpa diseragamkan, opsi
    // tidak akan pernah dianggap terpilih.
    const sebagaiTeks = (nilai) => (nilai === null || nilai === undefined || nilai === '' ? '' : String(nilai));

    return {
        nama_lembaga: mushafRequest.nama_lembaga || '',
        kategori_lembaga: mushafRequest.kategori_lembaga || '',
        alamat_lengkap: mushafRequest.alamat_lengkap || '',

        provinsi: mushafRequest.provinsi || '',
        provinsi_id: sebagaiTeks(mushafRequest.provinsi_id),

        kota_kabupaten: mushafRequest.kota_kabupaten || '',
        kota_kabupaten_id: sebagaiTeks(mushafRequest.kota_kabupaten_id),

        kecamatan: mushafRequest.kecamatan || '',
        kecamatan_id: sebagaiTeks(mushafRequest.kecamatan_id),

        kelurahan_desa: mushafRequest.kelurahan_desa || '',
        kelurahan_desa_id: sebagaiTeks(mushafRequest.kelurahan_desa_id),

        kode_pos: mushafRequest.kode_pos || '',
        alamat_detail: mushafRequest.alamat_detail || '',
        latitude: mushafRequest.latitude || '',
        longitude: mushafRequest.longitude || '',
        urgensi_request: mushafRequest.urgensi_request || '',
        sumber_info: mushafRequest.sumber_info || '',
    };
}
