<?php

namespace App\Services;

use App\Helpers\ProvinceHelper;
use App\Services\Cache\GeographicCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Melengkapi kolom wilayah permintaan mushaf (provinsi, kota/kabupaten,
 * kecamatan, kelurahan) dari data yang tersedia di berkas import.
 *
 * Latar belakang: berkas import hanya memuat satu kolom teks `alamat_lengkap`
 * plus `link_gmaps`. Akibatnya kolom provinsi..kelurahan_desa_id selalu kosong,
 * halaman detail menampilkan alamat yang tidak lengkap, dan form "Informasi
 * Lembaga" — yang mewajibkan latitude/longitude — tidak bisa disimpan.
 *
 * Cara kerjanya, dan mengapa tiap tingkat dipakai:
 *
 *   koordinat  : dari `link_gmaps`, memperluas tautan pendek (maps.app.goo.gl)
 *                 yang tidak memuat koordinat kalau tidak diperluas.
 *   provinsi    : hasil reverse geocoding koordinat — SATU-SATUNYA sumber yang
 *                 memberi nama provinsi yang bisa dicocokkan; teks alamat
 *                 jarang menyebut provinsi.
 *   kota/kab.   : idem.
 *   kecamatan   : dari TEKS alamat ("Kec. Garum") — lebih andal daripada
 *                 reverse geocoding, yang sering tidak mengembalikan kecamatan
 *                 sama sekali. Dicocokkan hanya bila utuh sebagai kata.
 *   kelurahan   : HANYA bila tepat satu kandidat. Sumber data wilayah yang
 *                 dipakai aplikasi tidak lengkap — "Bence" dan "Tanggung" di
 *                 alamat template bahkan tidak ada di dalamnya, dan "Slorok"
 *                 ada di dua kecamatan berbeda. Menebak di sini berarti
 *                 menulis kelurahan yang salah tanpa gejala apa pun, jadi
 *                 tingkat ini sengaja dibiarkan kosong bila meragukan.
 */
final class MushafAddressResolver
{
    /** Nama tempat yang dikembalikan Nominatim untuk tiap tingkat. */
    private const PETA_PROVINSI = ['state', 'region'];

    private const PETA_KOTA = ['county', 'city', 'municipality'];

    private const PETA_KELURAHAN = ['village', 'suburb', 'neighbourhood', 'city_district'];

    private const GEOCODE_TTL = 604800; // 7 hari — koordinat tidak berubah

    /** Kolom yang boleh diisi langsung dari berkas dan tidak boleh ditimpa. */
    private const PETAKOLOM = [
        'provinsi', 'kota_kabupaten', 'kecamatan', 'kelurahan_desa',
        'kode_pos', 'alamat_detail', 'latitude', 'longitude',
    ];

    public function __construct(
        private readonly GeographicCacheService $geographicCache,
    ) {}

    /**
     * Susun kolom wilayah. Tingkat yang tidak bisa dipastikan dibiarkan null.
     *
     * @return array{provinsi: ?string, provinsi_id: ?string, kota_kabupaten: ?string,
     *               kota_kabupaten_id: ?string, kecamatan: ?string, kecamatan_id: ?string,
     *               kelurahan_desa: ?string, kelurahan_desa_id: ?string, kode_pos: ?string,
     *               alamat_detail: ?string, latitude: ?float, longitude: ?float}
     */
    public function resolve(?string $alamatLengkap, ?string $linkGmaps = null, array $isian = []): array
    {
        // Baris yang sudah memuat koordinat DAN detail alamatnya tidak perlu
        // diuraikan sama sekali. Selain mubazir, penguraian selalu memanggil
        // layanan peta dan pencocokan wilayah — tidak ada gunanya dilakukan
        // untuk data yang sudah lengkap.
        $sudahLengkap = $this->adaIsi($isian['latitude'] ?? null)
            && $this->adaIsi($isian['longitude'] ?? null)
            && $this->adaIsi($isian['alamat_detail'] ?? null);

        $hasil = $sudahLengkap
            ? $this->kerangka($alamatLengkap)
            : $this->uraiOtomatis($alamatLengkap, $linkGmaps);

        // Kolom yang diisi LANGSUNG di berkas dipakai apa adanya, dan menang
        // atas hasil penguraian. Yang perlu menebak adalah berkas yang cuma
        // punya satu kolom teks alamat; kalau pengisinya sudah tahu provinsi
        // atau kelurahannya, menimpanya dengan hasil tebakan justru merusak
        // data yang benar.
        foreach (self::PETAKOLOM as $kunci) {
            $nilai = $isian[$kunci] ?? null;
            if (is_string($nilai)) {
                $nilai = trim($nilai);
            }
            if ($nilai !== null && $nilai !== '') {
                $hasil[$kunci] = $nilai;
            }
        }

        // Lengkapi ID wilayah dari nama yang diisi manual, supaya bentuk
        // datanya sama dengan hasil penguraian otomatis (bukan hanya namanya).
        $this->lengkapiId($hasil);

        return $hasil;
    }

    /**
     * Susun kolom wilayah dari alamat + tautan peta saja.
     *
     * @return array<string, mixed>
     */
    private function uraiOtomatis(?string $alamatLengkap, ?string $linkGmaps): array
    {
        $kosong = $this->kerangka($alamatLengkap);

        $koordinat = $this->koordinatDariLink($linkGmaps);
        if (! $koordinat) {
            // Tanpa koordinat, provinsi dan kota/kabupaten TIDAK bisa ditentukan:
            // tidak ada teks yang bisa dicocokkan dengan murah (butuh 514+ panggilan
            // ke sumber data wilayah untuk menyisir seluruh Indonesia). Seluruh
            // kolom wilayah dibiarkan kosong — lebih baik daripada menebak.
            // Teks alamatnya sendiri tetap tersimpan utuh di alamat_detail.
            return $kosong;
        }

        $kosong['latitude'] = $koordinat['lat'];
        $kosong['longitude'] = $koordinat['lng'];

        $geo = $this->reverseGeocode($koordinat['lat'], $koordinat['lng']);
        if (! $geo) {
            return $kosong;
        }

        // --- Provinsi ---
        $namaProvinsi = $this->ambilNama($geo, self::PETA_PROVINSI);
        if ($namaProvinsi) {
            $provinsi = $this->cocokkanProvinsi($namaProvinsi);
            $kosong['provinsi'] = $provinsi['nama'] ?? $namaProvinsi;
            $kosong['provinsi_id'] = $provinsi['id'] ?? null;
        }

        // --- Kota/Kabupaten ---
        $namaKota = $this->ambilNama($geo, self::PETA_KOTA);
        if ($namaKota && $kosong['provinsi_id']) {
            $kota = $this->cocokkanKota($kosong['provinsi_id'], $namaKota);
            $kosong['kota_kabupaten'] = $kota['nama'] ?? $namaKota;
            $kosong['kota_kabupaten_id'] = $kota['id'] ?? null;
        }

        // --- Kecamatan: dari teks lebih andal daripada reverse geocoding ---
        if ($kosong['kota_kabupaten_id']) {
            $kecamatan = $this->kecamatanDariTeks($alamatLengkap, $kosong['kota_kabupaten_id'])
                ?? ['nama' => $this->ambilNama($geo, ['city_district', 'suburb', 'municipality']), 'id' => null];

            $kosong['kecamatan'] = $kecamatan['nama'] ?: null;
            $kosong['kecamatan_id'] = $kecamatan['id'] ?? null;
        }

        // --- Kelurahan: hanya bila tepat satu kandidat ---
        if ($kosong['kecamatan_id']) {
            $kelurahan = $this->kelurahanDariTeks(
                $alamatLengkap,
                $kosong['kecamatan_id'],
                $kosong['kecamatan'],
            );
            $kosong['kelurahan_desa'] = $kelurahan['nama'] ?? null;
            $kosong['kelurahan_desa_id'] = $kelurahan['id'] ?? null;
        }

        $kosong['kode_pos'] = $geo['address']['postcode'] ?? null;

        return $kosong;
    }

    /**
     * Kerangka hasil: seluruh tingkat wilayah kosong, kecuali teks alamatnya
     * yang selalu disimpan utuh.
     *
     * @return array<string, mixed>
     */
    private function kerangka(?string $alamatLengkap): array
    {
        return [
            'provinsi' => null, 'provinsi_id' => null,
            'kota_kabupaten' => null, 'kota_kabupaten_id' => null,
            'kecamatan' => null, 'kecamatan_id' => null,
            'kelurahan_desa' => null, 'kelurahan_desa_id' => null,
            'kode_pos' => null,
            'alamat_detail' => $this->bersihkanDetail($alamatLengkap),
            'latitude' => null, 'longitude' => null,
        ];
    }

    /** Apakah nilainya benar-benar terisi (bukan null/string kosong)? */
    private function adaIsi(mixed $nilai): bool
    {
        return $nilai !== null && trim((string) $nilai) !== '';
    }

    /**
     * Lengkapi `*_id` dari nama wilayah yang sudah ada, tanpa mengubah namanya.
     *
     * Dipakai setelah kolom isian manual dipasang: bila pengisi berkas menulis
     * "Jawa Timur" tanpa ID, ID-nya dilengkapi di sini. Bila namanya tidak ada di
     * sumber data wilayah, ID dibiarkan kosong — nama tetap tersimpan apa adanya
     * dan tidak pernah diganti dengan tebakan.
     */
    private function lengkapiId(array &$hasil): void
    {
        if (empty($hasil['provinsi_id']) && ! empty($hasil['provinsi'])) {
            $hasil['provinsi_id'] = $this->cocokkanProvinsi($hasil['provinsi'])['id'] ?? null;
        }

        if (empty($hasil['kota_kabupaten_id']) && ! empty($hasil['kota_kabupaten']) && $hasil['provinsi_id']) {
            $hasil['kota_kabupaten_id'] = $this->cocokkanKota(
                $hasil['provinsi_id'],
                $hasil['kota_kabupaten']
            )['id'] ?? null;
        }

        if (empty($hasil['kecamatan_id']) && ! empty($hasil['kecamatan']) && $hasil['kota_kabupaten_id']) {
            $hasil['kecamatan_id'] = $this->cariDalamDaftar(
                $this->geographicCache->getDistricts($hasil['kota_kabupaten_id']) ?? [],
                $hasil['kecamatan']
            )['pertama']['id'] ?? null;
        }

        if (empty($hasil['kelurahan_desa_id']) && ! empty($hasil['kelurahan_desa']) && $hasil['kecamatan_id']) {
            $hasil['kelurahan_desa_id'] = $this->cariDalamDaftar(
                $this->geographicCache->getVillages($hasil['kecamatan_id']) ?? [],
                $hasil['kelurahan_desa']
            )['pertama']['id'] ?? null;
        }
    }

    /**
     * Ambil koordinat dari tautan Google Maps, termasuk tautan pendek.
     */
    public function koordinatDariLink(?string $url): ?array
    {
        if (empty($url)) {
            return null;
        }

        // Sudah memuat koordinat langsung.
        foreach ([
            '/@(-?\d+\.\d+),(-?\d+\.\d+)/',
            '/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/',
            '/[?&]ll=(-?\d+\.\d+),(-?\d+\.\d+)/',
            '/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/',
        ] as $pola) {
            if (preg_match($pola, $url, $m)) {
                return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
            }
        }

        // Tautan pendek (maps.app.goo.gl): harus diperluas dulu, kalau tidak
        // koordinatnya tidak pernah ada di dalam teks tautan.
        if (preg_match('#^https?://(maps\.app\.goo\.gl|goo\.gl/maps|g\.co/kgs)/#', $url)) {
            $tujuan = $this->perluasTautan($url);
            if ($tujuan) {
                return $this->koordinatDariLink($tujuan);
            }
        }

        return null;
    }

    /** Ikuti pengalihan tautan pendek; kembalikan URL akhir atau null. */
    private function perluasTautan(string $url): ?string
    {
        try {
            $respons = Http::timeout(8)->withOptions(['allow_redirects' => false])->get($url);
            $lokasi = $respons->header('Location');

            if ($lokasi) {
                return $lokasi;
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal memperluas tautan peta', ['url' => $url, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Reverse geocoding lewat Nominatim (OpenStreetMap) — tanpa kunci API.
     * Hasilnya di-cache per koordinat supaya import banyak baris tidak
     * memanggil layanan berulang kali.
     */
    private function reverseGeocode(float $lat, float $lng): ?array
    {
        $kunci = 'geocode_'.round($lat, 4).'_'.round($lng, 4);

        return Cache::remember($kunci, self::GEOCODE_TTL, function () use ($lat, $lng) {
            try {
                $respons = Http::timeout(10)
                    ->withHeaders(['User-Agent' => 'EkspedisiQ/1.0 (distribusi wakaf)'])
                    ->get('https://nominatim.openstreetmap.org/reverse', [
                        'format' => 'jsonv2',
                        'lat' => $lat,
                        'lon' => $lng,
                        'addressdetails' => 1,
                    ]);

                if ($respons->failed()) {
                    return null;
                }

                return $respons->json();
            } catch (\Throwable $e) {
                Log::warning('Gagal reverse geocoding', ['lat' => $lat, 'lng' => $lng, 'error' => $e->getMessage()]);

                return null;
            }
        });
    }

    /** Ambil nama tempat pertama yang ada dari hasil geocoding. */
    private function ambilNama(?array $geo, array $kandidat): ?string
    {
        foreach ($kandidat as $kunci) {
            if (! empty($geo['address'][$kunci])) {
                return (string) $geo['address'][$kunci];
            }
        }

        return null;
    }

    /** Cocokkan nama provinsi ke daftar wilayah aplikasi (pakai ProvinceHelper). */
    private function cocokkanProvinsi(string $nama): ?array
    {
        $target = ProvinceHelper::canonical($nama);

        foreach ($this->geographicCache->getProvinces() ?? [] as $provinsi) {
            if (ProvinceHelper::canonical($provinsi['name'] ?? '') === $target) {
                return ['nama' => $provinsi['name'], 'id' => (string) $provinsi['id']];
            }
        }

        return null;
    }

    /** Cocokkan nama kota/kabupaten di dalam provinsi tertentu. */
    private function cocokkanKota(string $provinsiId, string $nama): ?array
    {
        return $this->cariDalamDaftar(
            $this->geographicCache->getRegencies($provinsiId) ?? [],
            $nama
        )['pertama'];
    }

    /**
     * Tebak kecamatan dari teks alamat — hanya bila namanya muncul utuh.
     *
     * Kandidat yang PERTAMA muncul di dalam teks dianggap yang benar, bukan yang
     * pertama di dalam daftar: alamat lazim berbunyi "... Kec. Garum, Blitar",
     * dan tanpa ini kecamatan yang kebetulan namanya muncul sebagai nama
     * kelurahan di tempat lain bisa terpilih.
     */
    private function kecamatanDariTeks(?string $alamat, ?string $kotaId = null): ?array
    {
        if (empty($alamat) || empty($kotaId)) {
            return null;
        }

        $daftar = $this->geographicCache->getDistricts($kotaId) ?? [];
        $kandidat = $this->cariDalamDaftar($daftar, $alamat)['semua'];
        $bersih = $this->buangNamaJalan($alamat);

        $terpilih = null;
        $posisiTerpilih = PHP_INT_MAX;

        foreach ($kandidat as $k) {
            $posisi = mb_strpos($bersih, mb_strtoupper($k['nama']));
            if ($posisi !== false && $posisi < $posisiTerpilih) {
                $posisiTerpilih = $posisi;
                $terpilih = $k;
            }
        }

        return $terpilih;
    }

    /**
     * Tebak kelurahan dari teks alamat. Sengaja mengembalikan null kecuali
     * tepat SATU kandidat — lihat penjelasan di kepala kelas.
     *
     * `$namaKecamatan` adalah nama kecamatan yang sudah dipilih, dan wajib
     * dikecualikan: alamat lazim menulis "Kec. Garum", dan bila tidak dibuang,
     * kelurahan yang kebetulan bernama sama dengan kecamatannya akan tertangkap
     * dan menghasilkan baris seperti kecamatan GARUM dengan kelurahan GARUM —
     * padahal alamat tidak menyebut kelurahan sama sekali.
     */
    private function kelurahanDariTeks(?string $alamat, string $kecamatanId, ?string $namaKecamatan = null): ?array
    {
        if (empty($alamat)) {
            return null;
        }

        $semua = $this->cariDalamDaftar(
            $this->geographicCache->getVillages($kecamatanId) ?? [],
            $alamat,
            kecualikan: $namaKecamatan,
        )['semua'] ?? [];

        return count($semua) === 1 ? $semua[0] : null;
    }

    /**
     * Cari entri pada daftar wilayah berdasarkan kecocokan utuh.
     *
     * @param  array<int, array{id: mixed, name: string}>  $daftar
     * @param  ?string  $kecualikan  nama yang tidak boleh dianggap kecocokan
     * @return array{pertama: ?array, semua: array<int, array{nama: string, id: string}>}
     */
    private function cariDalamDaftar(array $daftar, string $teks, ?string $kecualikan = null): array
    {
        $bersih = $this->buangNamaJalan($teks);
        $dibuang = $kecualikan ? mb_strtoupper(trim($kecualikan)) : null;

        // Jenis tempat yang disebut di teks ("Kota Jayapura" / "Kabupaten
        // Jayapura"). Dipakai untuk memilih di antara nama kembar.
        $jenisDiminta = $this->jenisTempat($bersih);

        $cocok = [];
        $cocokSesuaiJenis = [];

        foreach ($daftar as $entri) {
            $nama = mb_strtoupper(trim($entri['name'] ?? ''));
            if ($nama === '') {
                continue;
            }

            if ($dibuang !== null && ($nama === $dibuang
                || preg_replace('/^(KABUPATEN|KAB\.?|KOTA|KECAMATAN|KEC\.?)\s+/', '', $nama) === $dibuang)) {
                continue;
            }

            if (preg_match('/\b'.preg_quote($nama, '/').'\b/', $bersih)
                || $this->cocokkanPecahan($bersih, $nama)) {
                $hasil = ['nama' => $entri['name'], 'id' => (string) $entri['id']];
                $cocok[] = $hasil;

                // Jenis pada daftar harus sama dengan yang diminta
                // ("KOTA" vs "KABUPATEN"). Tanpa ini, "Kota Jayapura" bisa
                // memilih KABUPATEN JAYAPURA hanya karena entri itu muncul
                // lebih dulu di daftar.
                if ($jenisDiminta !== null && $this->jenisTempat($nama) === $jenisDiminta) {
                    $cocokSesuaiJenis[] = $hasil;
                }
            }
        }

        // Bila teks menyebut jenisnya dengan jelas, utamakan entri yang
        // jenisnya cocok. Bila tidak ada yang cocok, jatuh ke hasil seperti semula
        // supaya teks yang tidak menyebut jenis tetap bisa dicocokkan.
        if ($jenisDiminta !== null && $cocokSesuaiJenis !== []) {
            return [
                'pertama' => $cocokSesuaiJenis[0],
                'semua' => $cocokSesuaiJenis,
            ];
        }

        return [
            'pertama' => $cocok[0] ?? null,
            'semua' => $cocok,
        ];
    }

    /**
     * Jenis tempat yang disebut di teks atau nama wilayah.
     *
     * Mengembalikan "KOTA", "KABUPATEN", "KECAMATAN", atau null bila tidak
     * disebut. Dipakai untuk membedakan nama kembar — banyak kabupaten dan kota
     * memakai nama yang sama persis (Jayapura, Blitar, Tangerang, Malang, ...).
     */
    private function jenisTempat(?string $teks): ?string
    {
        if (empty($teks)) {
            return null;
        }

        $atas = mb_strtoupper($teks);

        return match (true) {
            (bool) preg_match('/\b(KOTA|KOTAMADYA)\b/', $atas) => 'KOTA',
            (bool) preg_match('/\b(KABUPATEN|KAB)\b/', $atas) => 'KABUPATEN',
            (bool) preg_match('/\b(KECAMATAN|KEC)\b/', $atas) => 'KECAMATAN',
            default => null,
        };
    }

    /**
     * Nama wilayah di daftar sering memakai awalan jenis ("KABUPATEN BLITAR",
     * "KOTA BLITAR") yang tidak ditulis di alamat. Cocokkan tanpa awalan itu,
     * selama sisanya cukup panjang supaya tidak salah tangkap.
     */
    private function cocokkanPecahan(string $bersih, string $nama): bool
    {
        $tanpaAwalan = preg_replace('/^(KABUPATEN|KAB\.?|KOTA|KECAMATAN|KEC\.?)\s+/', '', $nama) ?? $nama;
        $tanpaAwalan = trim($tanpaAwalan);

        if ($tanpaAwalan === '' || mb_strlen($tanpaAwalan) < 4) {
            return false;
        }

        return (bool) preg_match('/\b'.preg_quote($tanpaAwalan, '/').'\b/', $bersih);
    }

    /** Buang bagian alamat yang sudah menjadi kolom tersendiri. */
    private function bersihkanDetail(?string $alamat): ?string
    {
        if (empty($alamat)) {
            return null;
        }

        return mb_substr(trim($alamat), 0, 500);
    }

    /**
     * Siapkan teks alamat untuk pencocokan nama wilayah, dengan membuang
     * penanda NAMA JALAN terlebih dahulu.
     *
     * Alamat Indonesia lazim berbentuk "Jl. Slorok, ... Kec. Garum, Blitar", dan
     * "Slorok" juga nama sebuah kelurahan di kecamatan Garum. Tanpa membuang
     * penanda jalannya, nama jalan akan terbaca sebagai kelurahan — padahal
     * alamat itu tidak menyebut kelurahan mana pun. Menulis data yang salah
     * tanpa gejala apa pun lebih buruk daripada membiarkannya kosong.
     */
    private function buangNamaJalan(?string $teks): string
    {
        if (empty($teks)) {
            return '';
        }

        // Normalisasi: buang tanda baca, rapikan spasi, jadikan huruf besar.
        // KOMA DIPERTAHANKAN di tahap ini karena dipakai sebagai pemisah bagian
        // alamat pada langkah berikutnya; kalau dibuang lebih dulu, penanda jalan
        // akan menelan seluruh sisa alamat.
        $bersih = preg_replace('/[^A-Z0-9, ]/', ' ', mb_strtoupper($teks)) ?? '';
        $bersih = preg_replace('/\s+/', ' ', $bersih) ?? $bersih;

        // Penanda jalan: JL, JALAN, GG, GANG, PERUM, KOMPLEK, BLOK, DUSUN, DS.
        // Bila ditemukan, bagian itu dibuang sampai koma berikutnya.
        $penanda = '(?:JL|JALAN|GG|GANG|PERUM|PERUMAHAN|KOMPLEK|KOMPL|BLOK|DUSUN|DS)';
        $bersih = preg_replace('/\b'.$penanda.'\b[^,]*(?:,|$)/', ' ', $bersih) ?? $bersih;

        // Baru sekarang koma dibuang, supaya pencocokan nama memakai kata utuh.
        $bersih = str_replace(',', ' ', $bersih);

        return preg_replace('/\s+/', ' ', $bersih) ?? $bersih;
    }
}
