/**
 * Menormalkan nama provinsi menjadi kunci kanonik.
 *
 * Kembaran dari app/Helpers/ProvinceHelper.php — KEDUA SISI WAJIB menghasilkan
 * kunci yang sama. Peta mencocokkan provinsi secara persis, jadi kalau normalisasi
 * di sini berbeda dengan di PHP, data yang sah akan hilang dari peta.
 *
 * Alasan keberadaan: nama provinsi datang dari beberapa sumber dengan ejaan
 * berbeda — input pengguna, impor, dan GeoJSON yang masih memakai ejaan era 2004
 * ("DI. ACEH", "PROBANTEN", "IRIAN JAYA BARAT"). Tanpa normalisasi, "DI YOGYAKARTA"
 * tidak akan pernah cocok dengan "DAERAH ISTIMEWA YOGYAKARTA".
 *
 * Kunci kanonik = nama provinsi Indonesia yang berlaku sekarang, huruf besar.
 */

/** @type {Record<string, string>} */
const ALIASES = {
  // Aceh
  'ACEH': 'ACEH',
  'DI ACEH': 'ACEH',
  'DAERAH ISTIMEWA ACEH': 'ACEH',
  'NANGGROE ACEH DARUSSALAM': 'ACEH',
  'NAD': 'ACEH',

  // Yogyakarta
  'DI YOGYAKARTA': 'DI YOGYAKARTA',
  'DAERAH ISTIMEWA YOGYAKARTA': 'DI YOGYAKARTA',
  'YOGYAKARTA': 'DI YOGYAKARTA',
  'DIY': 'DI YOGYAKARTA',
  'YOGYA': 'DI YOGYAKARTA',

  // Banten
  'BANTEN': 'BANTEN',
  'PROBANTEN': 'BANTEN',

  // Bangka Belitung
  'KEPULAUAN BANGKA BELITUNG': 'KEPULAUAN BANGKA BELITUNG',
  'BANGKA BELITUNG': 'KEPULAUAN BANGKA BELITUNG',
  'KEP BANGKA BELITUNG': 'KEPULAUAN BANGKA BELITUNG',

  // Nusa Tenggara
  'NUSA TENGGARA BARAT': 'NUSA TENGGARA BARAT',
  'NUSATENGGARA BARAT': 'NUSA TENGGARA BARAT',
  'NTB': 'NUSA TENGGARA BARAT',
  'NUSA TENGGARA TIMUR': 'NUSA TENGGARA TIMUR',
  'NUSATENGGARA TIMUR': 'NUSA TENGGARA TIMUR',
  'NTT': 'NUSA TENGGARA TIMUR',

  // Jakarta
  'DKI JAKARTA': 'DKI JAKARTA',
  'JAKARTA': 'DKI JAKARTA',
  'JAKARTA RAYA': 'DKI JAKARTA',
  'DAERAH KHUSUS IBUKOTA JAKARTA': 'DKI JAKARTA',

  // Papua — ejaan lama (Irian Jaya) dan pemekaran 2022
  'PAPUA': 'PAPUA',
  'IRIAN JAYA': 'PAPUA',
  'IRIAN JAYA TIMUR': 'PAPUA',
  'IRIAN JAYA TENGAH': 'PAPUA',
  'PAPUA BARAT': 'PAPUA BARAT',
  'IRIAN JAYA BARAT': 'PAPUA BARAT',
  'PAPUA SELATAN': 'PAPUA SELATAN',
  'PAPUA TENGAH': 'PAPUA TENGAH',
  'PAPUA PEGUNUNGAN': 'PAPUA PEGUNUNGAN',
  'PAPUA BARAT DAYA': 'PAPUA BARAT DAYA',

  // Kepulauan Riau (sering tertukar dengan Riau)
  'KEPULAUAN RIAU': 'KEPULAUAN RIAU',
  'KEP RIAU': 'KEPULAUAN RIAU',
  'RIAU': 'RIAU',
};

/**
 * Ubah nama provinsi apa pun menjadi kunci kanonik.
 *
 * @param {string|null|undefined} name
 * @returns {string} Nama kanonik huruf besar, atau '' bila input kosong.
 */
export function canonicalProvince(name) {
  if (name === null || name === undefined) return '';

  let key = String(name).toUpperCase().trim();
  // Buang kata administratif ("Provinsi Jawa Barat" → "Jawa Barat")
  key = key.replace(/\b(PROVINSI|PROPINSI|PROP)\b/g, ' ');
  // Sisakan huruf dan spasi saja
  key = key.replace(/[^A-Z\s]/g, ' ');
  key = key.replace(/\s+/g, ' ').trim();

  if (key === '') return '';

  return ALIASES[key] || key;
}

/**
 * Ambil nama provinsi dari properti GeoJSON apa pun bentuknya.
 *
 * @param {object} properties
 * @returns {string} Nama kanonik.
 */
export function provinceFromGeoJson(properties = {}) {
  return canonicalProvince(
    properties.name || properties.provinsi || properties.NAME ||
    properties.Propinsi || properties.PROVINSI
  );
}
