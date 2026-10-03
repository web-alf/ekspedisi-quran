/**
 * Logika pin lokasi untuk "Peta Penyebaran Distribusi Al-Qur'an".
 *
 * Dipisah ke berkas sendiri supaya bisa diuji tanpa browser. Pengelompokan pin
 * adalah bagian peta yang kesalahannya paling halus dan paling sulit terlihat di
 * layar: pin yang saling menutupi, angka yang tidak cocok dengan jumlah
 * sebenarnya, atau ujung pin terpotong. Semua itu lebih murah ditangkap di sini.
 */

/**
 * Kategori lembaga → warna pin.
 *
 * Warna dipilih agar terbedakan di atas lapisan provinsi (kuning–merah gelap),
 * jadi tidak ada kategori yang memakai warna kekuningan.
 */
export const WARNA_KATEGORI = {
  'TPQ/TPA/Madin': '#3b82f6',
  'Sekolah/Madrasah': '#f59e0b',
  'Masjid/Mushola/Majelis Taklim/Jamaah Masjid': '#10b981',
  'Pondok Pesantren': '#8b5cf6',
  "Rumah Tahfidz/Rumah Qur'an": '#ec4899',
  Yayasan: '#06b6d4',
};

/** Nama pendek untuk legenda dan popup. */
export const LABEL_KATEGORI = {
  'TPQ/TPA/Madin': 'TPQ/TPA/Madin',
  'Sekolah/Madrasah': 'Sekolah/Madrasah',
  'Masjid/Mushola/Majelis Taklim/Jamaah Masjid': 'Masjid/Musholla',
  'Pondok Pesantren': 'Pondok Pesantren',
  "Rumah Tahfidz/Rumah Qur'an": 'Rumah Tahfidz',
  Yayasan: 'Yayasan',
};

export const WARNA_BAWAAN = '#9ca3af';

/**
 * @param {string|null|undefined} kategori
 * @returns {string} Warna heksadesimal.
 */
export function warnaKategori(kategori) {
  return WARNA_KATEGORI[kategori] || WARNA_BAWAAN;
}

/**
 * @param {string|null|undefined} kategori
 * @returns {string} Label yang enak dibaca.
 */
export function labelKategori(kategori) {
  if (!kategori) return 'Lainnya';

  return LABEL_KATEGORI[kategori] || kategori;
}

/**
 * Batas wilayah Indonesia, termasuk perairan.
 * Dipakai untuk membuang koordinat yang jelas rusak.
 */
export const BATAS_INDONESIA = {
  latMin: -11.5,
  latMaks: 7.0,
  lngMin: 94.0,
  lngMaks: 141.5,
};

/**
 * Apakah koordinat ada di luar wilayah Indonesia?
 *
 * Penjagaan ini bukan hiasan: satu titik rusak (mis. bujur yang berisi nilai
 * lintang) membuat perhitungan skala peta menghitung rentang sebesar dunia,
 * lalu dijepit ke pembesaran minimum — akibatnya SELURUH pin menumpuk jadi satu
 * gumpalan dan peta tidak terbaca.
 *
 * @param {number} lat
 * @param {number} lng
 * @returns {boolean}
 */
export function diLuarIndonesia(lat, lng) {
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return true;

  return (
    lat < BATAS_INDONESIA.latMin ||
    lat > BATAS_INDONESIA.latMaks ||
    lng < BATAS_INDONESIA.lngMin ||
    lng > BATAS_INDONESIA.lngMaks
  );
}

const angka = (nilai) => {
  const n = Number(nilai);

  return Number.isFinite(n) ? n : null;
};

/**
 * Ubah satu baris mapData menjadi titik peta yang siap digambar.
 *
 * @param {object} item
 * @returns {object|null} null bila baris tidak punya koordinat yang bisa dipakai.
 */
export function titikDari(item) {
  if (!item || typeof item !== 'object') return null;

  const lat = angka(item.lat ?? item.latitude);
  const lng = angka(item.lng ?? item.longitude);
  if (lat === null || lng === null) return null;

  const nama = item.nama_lembaga || item.nama_penerima || 'Tanpa nama';

  return {
    id: item.id,
    nama,
    kategori: item.kategori || item.kategori_lembaga || null,
    provinsi: item.provinsi || null,
    kota: item.kota_kabupaten || null,
    kecamatan: item.kecamatan || null,
    kelurahan: item.kelurahan_desa || null,
    kodePos: item.kode_pos || null,
    lat,
    lng,
    jumlah: Number(item.jumlah_mushaf) || 0,
    a5: Number(item.jumlah_mushaf_a5) || 0,
    a6: Number(item.jumlah_mushaf_a6) || 0,
    iqra: Number(item.jumlah_iqra) || 0,
    anomali: diLuarIndonesia(lat, lng),
  };
}

/**
 * Pisahkan titik yang koordinatnya rusak dari yang sah.
 *
 * Titik rusak dibuang SEKALI di sini, lalu semua hitungan (warna provinsi dan
 * pin) memakai daftar bersih yang sama — supaya angka di pin tidak mungkin
 * berbeda dengan angka di provinsi.
 *
 * @param {Array<object>} daftar
 * @returns {{ sah: Array<object>, rusak: Array<object> }}
 */
export function pisahkanTitikRusak(daftar) {
  const sah = [];
  const rusak = [];

  (Array.isArray(daftar) ? daftar : []).forEach((mentah) => {
    const titik = titikDari(mentah);
    if (!titik) return;

    (titik.anomali ? rusak : sah).push(titik);
  });

  return { sah, rusak };
}

/**
 * Jumlah mushaf per provinsi (kunci kanonik dari nama provinsi).
 *
 * @param {Array<object>} titik
 * @param {(nama: string) => string} kanonik
 * @returns {Record<string, number>}
 */
export function sebaranPerProvinsi(titik, kanonik) {
  const hasil = {};

  (Array.isArray(titik) ? titik : []).forEach((t) => {
    const provinsi = kanonik ? kanonik(t.provinsi) : t.provinsi;
    if (!provinsi) return;

    hasil[provinsi] = (hasil[provinsi] || 0) + t.jumlah;
  });

  return hasil;
}

/**
 * Jumlah lembaga per provinsi.
 *
 * @param {Array<object>} titik
 * @param {(nama: string) => string} kanonik
 * @returns {Record<string, number>}
 */
export function lembagaPerProvinsi(titik, kanonik) {
  const kumpulan = {};

  (Array.isArray(titik) ? titik : []).forEach((t) => {
    const provinsi = kanonik ? kanonik(t.provinsi) : t.provinsi;
    if (!provinsi) return;

    if (!kumpulan[provinsi]) kumpulan[provinsi] = new Set();
    kumpulan[provinsi].add(t.nama);
  });

  const hasil = {};
  Object.keys(kumpulan).forEach((provinsi) => {
    hasil[provinsi] = kumpulan[provinsi].size;
  });

  return hasil;
}

/** Jarak gabung bawaan, sedikit lebih besar dari lebar satu pin. */
export const JARAK_GABUNG_PIKSEL = 34;

/**
 * Gabungkan pin yang saling berdekatan DI LAYAR.
 *
 * Yang diukur adalah jarak pada layar (piksel), bukan jarak di bumi. Kalau
 * memakai derajat, titik-titik di Jawa akan tetap menumpuk saat peta
 * diperkecil — 10 derajat hanya beberapa puluh piksel di layar — sementara
 * titik di pulau terpencil ikut tergabung tanpa alasan. Dengan ukuran piksel
 * aturannya jadi satu: "pin yang tidak muat berdampingan tanpa saling menutup,
 * digabung", dan pengelompokannya mengikuti apa yang benar-benar dilihat
 * pengunjung.
 *
 * Urutan masukan menentukan titik wakil, jadi warna pin tidak berubah-ubah
 * setiap kali peta digambar ulang.
 *
 * @param {Array<object>} titik
 * @param {(lat: number, lng: number) => {x: number, y: number}} proyeksi
 * @param {number} [jarak]
 * @returns {Array<object>} Kelompok berisi { lat, lng, anggota, jumlah, a5, a6, iqra }.
 */
export function kelompokkanPerPiksel(titik, proyeksi, jarak = JARAK_GABUNG_PIKSEL) {
  const kelompok = [];

  (Array.isArray(titik) ? titik : []).forEach((t) => {
    const pos = proyeksi(t.lat, t.lng);

    let cocok = null;
    for (const k of kelompok) {
      const dx = k.x - pos.x;
      const dy = k.y - pos.y;
      if (Math.sqrt(dx * dx + dy * dy) <= jarak) {
        cocok = k;
        break;
      }
    }

    if (!cocok) {
      kelompok.push({
        x: pos.x,
        y: pos.y,
        lat: t.lat,
        lng: t.lng,
        anggota: [t],
        jumlah: t.jumlah,
        a5: t.a5,
        a6: t.a6,
        iqra: t.iqra,
      });

      return;
    }

    const n = cocok.anggota.length;
    cocok.anggota.push(t);
    cocok.jumlah += t.jumlah;
    cocok.a5 += t.a5;
    cocok.a6 += t.a6;
    cocok.iqra += t.iqra;
    // Titik wakil digeser ke tengah kelompok supaya pin tidak menempel di
    // lokasi salah satu lembaga saja.
    cocok.x = (cocok.x * n + pos.x) / (n + 1);
    cocok.y = (cocok.y * n + pos.y) / (n + 1);
    cocok.lat = (cocok.lat * n + t.lat) / (n + 1);
    cocok.lng = (cocok.lng * n + t.lng) / (n + 1);
  });

  return kelompok;
}

/**
 * Lolos-keluar HTML.
 *
 * WAJIB dipakai untuk SEMUA nilai yang berasal dari isian pengguna sebelum
 * dimasukkan ke popup. Nama lembaga, kelurahan, dan kecamatan datang dari
 * formulir publik permintaan mushaf — tanpa pelolosan, satu kiriman berisi
 * tag skrip akan dijalankan di halaman depan.
 *
 * @param {unknown} nilai
 * @returns {string}
 */
export function aman(nilai) {
  if (nilai === null || nilai === undefined) return '';

  return String(nilai)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

const angkaID = new Intl.NumberFormat('id-ID');

/**
 * @param {number} nilai
 * @returns {string}
 */
export function formatAngka(nilai) {
  return angkaID.format(Number(nilai) || 0);
}

/**
 * Isi popup untuk satu lembaga — sedetail mungkin, sampai kelurahan/desa.
 *
 * @param {object} t Titik peta.
 * @returns {string} HTML.
 */
export function isiPopupTitik(t) {
  const baris = (label, nilai) =>
    `<div class="pop-b"><span class="pop-l">${aman(label)}</span><span>${nilai}</span></div>`;

  let pecahan = '';
  if (t.a5) pecahan += `<span>A5: ${formatAngka(t.a5)}</span>`;
  if (t.a6) pecahan += `<span>A6: ${formatAngka(t.a6)}</span>`;
  if (t.iqra) pecahan += `<span>Iqra: ${formatAngka(t.iqra)}</span>`;

  const alamat = [
    baris('Kelurahan/Desa', `<b>${aman(t.kelurahan) || '—'}</b>`),
    baris('Kecamatan', aman(t.kecamatan) || '—'),
    baris('Kab./Kota', aman(t.kota) || '—'),
    baris('Provinsi', aman(t.provinsi) || '—'),
    t.kodePos ? baris('Kode pos', aman(t.kodePos)) : '',
  ].join('');

  return (
    `<div class="pop-kat" style="background:${warnaKategori(t.kategori)}">` +
    `${aman(labelKategori(t.kategori))}</div>` +
    `<div class="pop-judul">${aman(t.nama)}</div>` +
    alamat +
    '<div class="pop-pisah"></div>' +
    baris('Disalurkan', `<span class="pop-total">${formatAngka(t.jumlah)} mushaf</span>`) +
    (pecahan ? `<div class="pop-pecah">${pecahan}</div>` : '')
  );
}

/**
 * Isi popup untuk satu kelompok pin.
 *
 * @param {object} kelompok
 * @returns {string} HTML.
 */
export function isiPopupKelompok(kelompok) {
  if (kelompok.anggota.length === 1) return isiPopupTitik(kelompok.anggota[0]);

  const daftar = kelompok.anggota
    .map((a, i) => {
      const alamat = [a.kelurahan, a.kecamatan, a.provinsi].filter(Boolean).map(aman).join(', ');

      return (
        `<div class="pop-lembaga"><b>${i + 1}. ${aman(a.nama)}</b><br>` +
        `<span class="alamat">${alamat} · ${formatAngka(a.jumlah)} mushaf</span></div>`
      );
    })
    .join('');

  return (
    `<div class="pop-kat" style="background:${warnaKategori(kelompok.anggota[0].kategori)}">` +
    `${kelompok.anggota.length} lembaga berdekatan</div>` +
    daftar +
    '<div class="pop-pisah"></div>' +
    `<div class="pop-b"><span class="pop-l">Total</span>` +
    `<span class="pop-total">${formatAngka(kelompok.jumlah)} mushaf</span></div>` +
    '<div class="pop-koord">Perbesar peta untuk memisahkan pin ini.</div>'
  );
}

/** Ukuran gambar pin pada kanvas aslinya. */
export const PIN_LEBAR = 26;
export const PIN_TINGGI = 34;

/**
 * Ikon pin berbentuk tetes air (gaya Google Maps).
 *
 * `viewBox` WAJIB ada. Tanpa itu koordinat gambar diperlakukan sebagai piksel
 * apa adanya, sehingga gambar 26x34 dipaksa masuk kotak yang lebih kecil dan
 * ujung bawah pin terpotong — pinnya terlihat seperti bulatan, bukan pin.
 *
 * @param {string} warna
 * @param {number} [skala]
 * @param {number} [jumlah] Jumlah lembaga; di atas 1 pin diberi angka, karena
 *   tanpa angka pin berisi banyak lembaga terlihat persis seperti pin satu
 *   lembaga dan peta jadi menyesatkan.
 * @returns {{ html: string, lebar: number, tinggi: number, jangkar: [number, number] }}
 */
export function buatIkonPin(warna, skala = 1, jumlah = 0) {
  const lebar = PIN_LEBAR * skala;
  const tinggiGambar = PIN_TINGGI * skala;
  // Kotak sedikit lebih tinggi dari gambar supaya ada ruang untuk ekor pin.
  const tinggi = (PIN_TINGGI + 4) * skala;

  const isi =
    jumlah > 1
      ? `<div class="angka" style="top:${6 * skala}px;font-size:${Math.max(9, 11 * skala)}px">${aman(jumlah)}</div>`
      : `<div class="lubang" style="top:${8 * skala}px;width:${10 * skala}px;height:${10 * skala}px"></div>`;

  const html =
    `<div class="pin" style="width:${lebar}px;height:${tinggi}px">` +
    `<svg viewBox="0 0 ${PIN_LEBAR} ${PIN_TINGGI}" xmlns="http://www.w3.org/2000/svg" ` +
    `style="display:block;width:${lebar}px;height:${tinggiGambar}px">` +
    '<path d="M13 .6C6.4 .6 1 6 1 12.6c0 8.7 10.7 19.9 11.2 20.4a1.1 1.1 0 0 0 1.6 0' +
    'C14.3 32.5 25 21.3 25 12.6 25 6 19.6 .6 13 .6z" ' +
    `fill="${warna}" stroke="#fff" stroke-width="1.4"/></svg>` +
    isi +
    '</div>';

  return {
    html,
    lebar,
    tinggi,
    // Ujung bawah pin menunjuk tepat ke koordinatnya.
    jangkar: [lebar / 2, tinggiGambar],
  };
}
