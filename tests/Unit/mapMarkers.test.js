import { describe, it, expect } from 'vitest';
import {
    WARNA_KATEGORI,
    WARNA_BAWAAN,
    BATAS_INDONESIA,
    JARAK_GABUNG_PIKSEL,
    PIN_TINGGI,
    warnaKategori,
    labelKategori,
    diLuarIndonesia,
    titikDari,
    pisahkanTitikRusak,
    sebaranPerProvinsi,
    lembagaPerProvinsi,
    kelompokkanPerPiksel,
    aman,
    formatAngka,
    isiPopupTitik,
    isiPopupKelompok,
    buatIkonPin,
} from '../../resources/js/utils/mapMarkers.js';

/** Titik contoh dari data produksi. */
const titik = (over = {}) => ({
    id: 1,
    nama_lembaga: 'TPQ Al Falah Plosorejo',
    kategori_lembaga: 'TPQ/TPA/Madin',
    provinsi: 'JAWA TIMUR',
    kota_kabupaten: 'KABUPATEN BLITAR',
    kecamatan: 'GARUM',
    kelurahan_desa: 'PLOSOREJO',
    kode_pos: '66182',
    lat: -8.0868357,
    lng: 112.2396983,
    jumlah_mushaf: 100,
    jumlah_mushaf_a5: 60,
    jumlah_mushaf_a6: 40,
    jumlah_iqra: 0,
    ...over,
});

describe('mapMarkers — titik rusak', () => {
    it('menerima koordinat di dalam Indonesia', () => {
        expect(diLuarIndonesia(-8.0868357, 112.2396983)).toBe(false);
        expect(diLuarIndonesia(5.5, 95.3)).toBe(false);
        expect(diLuarIndonesia(-9.0, 141.0)).toBe(false);
    });

    it('menolak koordinat di luar Indonesia', () => {
        // Kasus nyata di produksi: SD NEGERI DEMAAN, bujurnya berisi nilai
        // lintang sehingga titiknya jatuh di Afrika.
        expect(diLuarIndonesia(-6.59958411, -6.59960511)).toBe(true);
        expect(diLuarIndonesia(51.5, -0.12)).toBe(true); // London
    });

    it('menolak nilai yang bukan angka', () => {
        expect(diLuarIndonesia(NaN, 112)).toBe(true);
        expect(diLuarIndonesia(null, 112)).toBe(true);
        expect(diLuarIndonesia(undefined, undefined)).toBe(true);
    });

    it('memisahkan titik rusak dari yang sah', () => {
        const { sah, rusak } = pisahkanTitikRusak([
            titik({ id: 1 }),
            titik({ id: 19, nama_lembaga: 'SD NEGERI DEMAAN', lat: -6.59958411, lng: -6.59960511 }),
            titik({ id: 2, lat: -6.9, lng: 110.4 }),
        ]);

        expect(sah).toHaveLength(2);
        expect(rusak).toHaveLength(1);
        expect(rusak[0].nama).toBe('SD NEGERI DEMAAN');
        expect(rusak[0].anomali).toBe(true);
    });

    it('membuang baris yang tidak punya koordinat sama sekali', () => {
        const { sah, rusak } = pisahkanTitikRusak([
            { id: 1, nama_lembaga: 'Tanpa koordinat' },
            titik({ id: 2 }),
        ]);

        expect(sah).toHaveLength(1);
        expect(rusak).toHaveLength(0);
    });

    it('selalu menandai satu titik rusak sebagai anomali', () => {
        // Bukti-bahwa-rusak: kalau penjagaan batas dilepas, titik ini lolos ke
        // peta dan merusak skala seluruh peta — semua pin menumpuk jadi satu.
        const { rusak } = pisahkanTitikRusak([
            titik({ lat: -6.59958411, lng: -6.59960511 }),
        ]);

        expect(rusak).toHaveLength(1);
        expect(diLuarIndonesia(-6.59958411, -6.59960511)).toBe(true);
        expect(BATAS_INDONESIA.lngMin).toBeGreaterThan(-6.59960511);
    });
});

describe('mapMarkers — angka provinsi dan pin memakai daftar yang sama', () => {
    const kanonik = (n) => (n || '').toUpperCase();

    it('menghitung mushaf per provinsi', () => {
        const { sah } = pisahkanTitikRusak([
            titik({ provinsi: 'JAWA TENGAH', jumlah_mushaf: 80 }),
            titik({ provinsi: 'JAWA TENGAH', jumlah_mushaf: 100 }),
            titik({ provinsi: 'JAWA TIMUR', jumlah_mushaf: 500 }),
        ]);

        expect(sebaranPerProvinsi(sah, kanonik)).toEqual({
            'JAWA TENGAH': 180,
            'JAWA TIMUR': 500,
        });
    });

    it('menghitung lembaga per provinsi, bukan jumlah titik', () => {
        const { sah } = pisahkanTitikRusak([
            titik({ nama_lembaga: 'Testi', provinsi: 'JAWA TENGAH' }),
            titik({ nama_lembaga: 'Testi', provinsi: 'JAWA TENGAH' }),
            titik({ nama_lembaga: 'SD NEGERI 1 GLEMPANG', provinsi: 'JAWA TENGAH' }),
        ]);

        expect(lembagaPerProvinsi(sah, kanonik)['JAWA TENGAH']).toBe(2);
    });

    it('titik rusak tidak ikut dihitung di provinsi mana pun', () => {
        // Bukti-bahwa-rusak: kalau perhitungan provinsi memakai daftar mentah
        // (bukan hasil pemisahan), angka provinsi tidak akan sama dengan angka
        // pin. Titik rusak harus dikeluarkan SEKALI, di satu tempat.
        const mentah = [
            titik({ provinsi: 'JAWA TENGAH', jumlah_mushaf: 80 }),
            titik({ provinsi: 'JAWA TENGAH', lat: -6.59958411, lng: -6.59960511, jumlah_mushaf: 999 }),
        ];

        const { sah } = pisahkanTitikRusak(mentah);
        const tanpaPenyaringan = mentah.map(titikDari);

        expect(sebaranPerProvinsi(sah, kanonik)['JAWA TENGAH']).toBe(80);
        expect(sebaranPerProvinsi(tanpaPenyaringan, kanonik)['JAWA TENGAH']).toBe(1079);
    });
});

describe('mapMarkers — pengelompokan pin', () => {
    // Proyeksi sederhana: 1 derajat = 111 km, dan pada uji ini 1 derajat = 100 px.
    const proyeksi = (lat, lng) => ({ x: lng * 100, y: lat * 100 });

    it('tidak menggabungkan titik yang jauh', () => {
        const hasil = kelompokkanPerPiksel(
            [{ lat: -6.9, lng: 110.0, jumlah: 10, a5: 0, a6: 0, iqra: 0 },
             { lat: -8.0, lng: 112.0, jumlah: 20, a5: 0, a6: 0, iqra: 0 }],
            proyeksi
        );

        expect(hasil).toHaveLength(2);
    });

    it('menggabungkan titik yang berdekatan di layar', () => {
        const hasil = kelompokkanPerPiksel(
            [{ lat: -6.9, lng: 110.0, jumlah: 80, a5: 60, a6: 20, iqra: 0 },
             { lat: -6.9, lng: 110.1, jumlah: 100, a5: 0, a6: 0, iqra: 100 }],
            proyeksi
        );

        expect(hasil).toHaveLength(1);
        expect(hasil[0].anggota).toHaveLength(2);
        expect(hasil[0].jumlah).toBe(180);
        // Semua pecahan ikut dijumlahkan, bukan hanya totalnya.
        expect(hasil[0].a5).toBe(60);
        expect(hasil[0].a6).toBe(20);
        expect(hasil[0].iqra).toBe(100);
    });

    it('menghitung jarak pada LAYAR, bukan pada bumi', () => {
        // Dua titik 0,05 derajat terpisah. Pada pembesaran kecil keduanya
        // berimpit di layar (5 px) sehingga harus digabung; pada pembesaran
        // besar keduanya 100 px terpisah sehingga harus terpisah.
        const dua = [
            { lat: -6.9, lng: 110.0, jumlah: 10, a5: 0, a6: 0, iqra: 0 },
            { lat: -6.9, lng: 110.05, jumlah: 20, a5: 0, a6: 0, iqra: 0 },
        ];
        const kecil = (lat, lng) => ({ x: lng * 100, y: lat * 100 });   // 5 px
        const besar = (lat, lng) => ({ x: lng * 2000, y: lat * 2000 }); // 100 px

        expect(kelompokkanPerPiksel(dua, kecil)).toHaveLength(1);
        expect(kelompokkanPerPiksel(dua, besar)).toHaveLength(2);
    });

    it('menggeser titik wakil ke tengah kelompok', () => {
        const hasil = kelompokkanPerPiksel(
            [{ lat: -6.9, lng: 110.0, jumlah: 10, a5: 0, a6: 0, iqra: 0 },
             { lat: -6.9, lng: 110.2, jumlah: 20, a5: 0, a6: 0, iqra: 0 }],
            proyeksi
        );

        expect(hasil).toHaveLength(1);
        expect(hasil[0].lng).toBeCloseTo(110.1, 6);
    });

    it('mempertahankan urutan masukan sebagai wakil kelompok', () => {
        // Kalau urutannya tidak tetap, warna pin berubah sendiri setiap kali
        // peta digambar ulang.
        const hasil = kelompokkanPerPiksel(
            [{ lat: -6.9, lng: 110.0, nama_lembaga: 'PERTAMA', jumlah: 10, a5: 0, a6: 0, iqra: 0 },
             { lat: -6.9, lng: 110.1, nama_lembaga: 'KEDUA', jumlah: 20, a5: 0, a6: 0, iqra: 0 }],
            proyeksi
        );

        expect(hasil[0].anggota[0].nama_lembaga).toBe('PERTAMA');
    });

    it('memakai jarak gabung yang sedikit lebih besar dari lebar pin', () => {
        expect(JARAK_GABUNG_PIKSEL).toBeGreaterThan(26);
    });
});

describe('mapMarkers — pin tidak terpotong', () => {
    it('selalu menyertakan viewBox', () => {
        // Bukti-bahwa-rusak: tanpa viewBox, gambar 26x34 diperlakukan sebagai
        // piksel apa adanya; di dalam kotak yang lebih kecil ujung bawah pin
        // terpotong dan pinnya terlihat seperti bulatan.
        const ikon = buatIkonPin('#3b82f6', 0.78);

        expect(ikon.html).toContain(`viewBox="0 0 ${26} ${34}"`);
    });

    it('memakai kanvas gambar yang sama untuk semua skala', () => {
        const penuh = buatIkonPin('#3b82f6', 1);
        const kecil = buatIkonPin('#3b82f6', 0.78);

        [penuh, kecil].forEach((ikon) => {
            expect(ikon.html).toMatch(/viewBox="0 0 \d+ \d+"/);
        });

        // Kotak penampung tidak boleh lebih pendek dari gambar pin.
        expect(kecil.tinggi).toBeGreaterThanOrEqual(PIN_TINGGI * 0.78);
    });

    it('menaruh jangkar tepat di ujung bawah gambar', () => {
        const ikon = buatIkonPin('#3b82f6', 1);
        expect(ikon.jangkar[0]).toBe(ikon.lebar / 2);
        expect(ikon.jangkar[1]).toBe(PIN_TINGGI);
    });

    it('memberi angka hanya pada pin gabungan', () => {
        // Bukti-bahwa-rusak: tanpa angka, pin yang mewakili 14 lembaga terlihat
        // persis seperti pin 1 lembaga dan peta jadi menyesatkan.
        expect(buatIkonPin('#3b82f6', 1, 1).html).toContain('lubang');
        expect(buatIkonPin('#3b82f6', 1, 0).html).toContain('lubang');
        expect(buatIkonPin('#3b82f6', 1, 14).html).toContain('angka');
        expect(buatIkonPin('#3b82f6', 1, 14).html).toContain('>14<');
    });
});

describe('mapMarkers — keamanan popup', () => {
    it('meloloskan tag HTML dari isian pengguna', () => {
        expect(aman('<script>alert(1)</script>'))
            .toBe('&lt;script&gt;alert(1)&lt;/script&gt;');
        expect(aman('" onmouseover="alert(1)')).toContain('&quot;');
    });

    it('tidak menjalankan skrip dari nama lembaga', () => {
        // Nama lembaga datang dari formulir publik permintaan mushaf.
        const html = isiPopupTitik(titikDari(titik({
            nama_lembaga: '<img src=x onerror=alert(1)>',
            kelurahan_desa: '<b>PLOSOREJO</b>',
        })));

        // Yang berbahaya adalah TAG yang terbentuk, bukan kata "onerror" yang
        // muncul sebagai teks biasa. Jadi yang diperiksa: tidak ada tag asli,
        // dan teksnya benar-benar sudah diloloskan.
        expect(html).not.toContain('<img');
        expect(html).not.toContain('<script');
        expect(html).toContain('&lt;img src=x onerror=alert(1)&gt;');
        expect(html).toContain('&lt;b&gt;PLOSOREJO&lt;/b&gt;');
    });

    it('meloloskan tanda kutip agar tidak bisa keluar dari atribut', () => {
        // Tanpa pelolosan kutip, nilai ini bisa keluar dari atribut dan
        // menambahkan penanganan kejadian pada elemen.
        expect(aman('" onmouseover="alert(1)')).toBe('&quot; onmouseover=&quot;alert(1)');
    });

    it('meloloskan nama lembaga pada popup kelompok juga', () => {
        const html = isiPopupKelompok({
            anggota: [
                titikDari(titik({ nama_lembaga: '<script>x</script>' })),
                titikDari(titik({ nama_lembaga: 'Aman' })),
            ],
            jumlah: 200,
        });

        expect(html).not.toContain('<script>');
        expect(html).toContain('&lt;script&gt;');
    });
});

describe('mapMarkers — isi popup', () => {
    it('menampilkan detail sampai kelurahan/desa', () => {
        const html = isiPopupTitik(titikDari(titik()));

        expect(html).toContain('PLOSOREJO');
        expect(html).toContain('GARUM');
        expect(html).toContain('KABUPATEN BLITAR');
        expect(html).toContain('JAWA TIMUR');
        expect(html).toContain('100 mushaf');
    });

    it('memakai tanda pisah untuk kolom yang kosong, bukan "null"', () => {
        const html = isiPopupTitik(titikDari(titik({ kelurahan_desa: null, kode_pos: null })));

        expect(html).not.toContain('null');
        expect(html).not.toContain('undefined');
        expect(html).toContain('—');
    });

    it('menjumlahkan pecahan jenis mushaf', () => {
        const html = isiPopupTitik(titikDari(titik({ jumlah_mushaf_a5: 60, jumlah_mushaf_a6: 40, jumlah_iqra: 5 })));

        expect(html).toContain('A5: 60');
        expect(html).toContain('A6: 40');
        expect(html).toContain('Iqra: 5');
    });

    it('memakai popup satu lembaga saat kelompok hanya berisi satu', () => {
        const html = isiPopupKelompok({ anggota: [titikDari(titik())], jumlah: 100 });

        expect(html).toContain('pop-judul');
        expect(html).not.toContain('lembaga berdekatan');
    });

    it('mendaftar setiap lembaga pada kelompok', () => {
        const html = isiPopupKelompok({
            anggota: [
                titikDari(titik({ nama_lembaga: 'SD NEGERI 1 GLEMPANG', jumlah_mushaf: 80 })),
                titikDari(titik({ nama_lembaga: 'SEKOLAH DASAR NEGERI 2 PETAHUNAN', jumlah_mushaf: 100 })),
            ],
            jumlah: 180,
        });

        expect(html).toContain('2 lembaga berdekatan');
        expect(html).toContain('1. SD NEGERI 1 GLEMPANG');
        expect(html).toContain('2. SEKOLAH DASAR NEGERI 2 PETAHUNAN');
        expect(html).toContain('180 mushaf');
    });
});

describe('mapMarkers — kategori', () => {
    it('memberi warna berbeda untuk tiap kategori yang dikenal', () => {
        const warna = Object.keys(WARNA_KATEGORI).map((k) => warnaKategori(k));
        expect(new Set(warna).size).toBe(Object.keys(WARNA_KATEGORI).length);
    });

    it('memakai warna bawaan untuk kategori yang tidak dikenal', () => {
        expect(warnaKategori('Kategori Baru')).toBe(WARNA_BAWAAN);
        expect(warnaKategori(null)).toBe(WARNA_BAWAAN);
        expect(labelKategori(null)).toBe('Lainnya');
    });

    it('tidak memakai warna yang sama dengan warna provinsi', () => {
        // Kalau warna pin sama dengan warna salah satu tingkat provinsi, pinnya
        // menyatu dengan latar dan tidak terlihat sama sekali.
        const warnaProvinsi = [
            '#FFEDA0', '#FED976', '#FEB24C', '#FD8D3C',
            '#FC4E2A', '#E31A1C', '#BD0026', '#800026',
        ];

        Object.values(WARNA_KATEGORI).forEach((warnaPin) => {
            expect(warnaProvinsi).not.toContain(warnaPin.toUpperCase());
        });
    });

    it('memformat angka dengan pemisah ribuan Indonesia', () => {
        expect(formatAngka(2691)).toBe('2.691');
    });
});
