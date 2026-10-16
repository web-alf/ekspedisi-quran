<?php

namespace App\Helpers;

/**
 * Menormalkan nama provinsi dari berbagai sumber menjadi satu kunci kanonik.
 *
 * Latar belakang: peta sebaran mencocokkan provinsi secara persis
 * (`item.provinsi === provinceName`). Sumber namanya bermacam-macam — hasil
 * input pengguna, impor, dan GeoJSON yang masih memakai ejaan era 2004
 * ("DI. ACEH", "PROBANTEN", "NUSATENGGARA BARAT"). Tanpa normalisasi, data
 * yang sah tidak pernah muncul di peta — mis. "DI YOGYAKARTA" tidak cocok
 * dengan "DAERAH ISTIMEWA YOGYAKARTA".
 *
 * Kunci kanonik = nama provinsi Indonesia yang berlaku sekarang, huruf besar.
 * Sumber kanonik ada di canonical() agar dipakai bersama oleh JS
 * (resources/js/utils/provinceName.js) — kedua sisi WAJIB menghasilkan kunci
 * yang sama, kalau tidak data akan hilang lagi.
 */
class ProvinceHelper
{
    /**
     * Alias nama provinsi → nama kanonik.
     *
     * Kunci sudah dinormalkan (huruf besar, tanpa tanda baca) sehingga
     * pencocokan cukup satu kali lihat.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        // Aceh
        'ACEH' => 'ACEH',
        'DI ACEH' => 'ACEH',
        'DAERAH ISTIMEWA ACEH' => 'ACEH',
        'NANGGROE ACEH DARUSSALAM' => 'ACEH',
        'NAD' => 'ACEH',

        // Yogyakarta
        'DI YOGYAKARTA' => 'DI YOGYAKARTA',
        'DAERAH ISTIMEWA YOGYAKARTA' => 'DI YOGYAKARTA',
        'YOGYAKARTA' => 'DI YOGYAKARTA',
        'DIY' => 'DI YOGYAKARTA',
        'YOGYA' => 'DI YOGYAKARTA',

        // Banten
        'BANTEN' => 'BANTEN',
        'PROBANTEN' => 'BANTEN',

        // Bangka Belitung
        'KEPULAUAN BANGKA BELITUNG' => 'KEPULAUAN BANGKA BELITUNG',
        'BANGKA BELITUNG' => 'KEPULAUAN BANGKA BELITUNG',
        'KEP BANGKA BELITUNG' => 'KEPULAUAN BANGKA BELITUNG',

        // Nusa Tenggara
        'NUSA TENGGARA BARAT' => 'NUSA TENGGARA BARAT',
        'NUSATENGGARA BARAT' => 'NUSA TENGGARA BARAT',
        'NTB' => 'NUSA TENGGARA BARAT',
        'NUSA TENGGARA TIMUR' => 'NUSA TENGGARA TIMUR',
        'NUSATENGGARA TIMUR' => 'NUSA TENGGARA TIMUR',
        'NTT' => 'NUSA TENGGARA TIMUR',

        // Jakarta
        'DKI JAKARTA' => 'DKI JAKARTA',
        'JAKARTA' => 'DKI JAKARTA',
        'JAKARTA RAYA' => 'DKI JAKARTA',
        'DAERAH KHUSUS IBUKOTA JAKARTA' => 'DKI JAKARTA',

        // Papua — ejaan lama (Irian Jaya) dan pemekaran 2022
        'PAPUA' => 'PAPUA',
        'IRIAN JAYA' => 'PAPUA',
        'IRIAN JAYA TIMUR' => 'PAPUA',
        'IRIAN JAYA TENGAH' => 'PAPUA',
        'PAPUA BARAT' => 'PAPUA BARAT',
        'IRIAN JAYA BARAT' => 'PAPUA BARAT',
        'PAPUA SELATAN' => 'PAPUA SELATAN',
        'PAPUA TENGAH' => 'PAPUA TENGAH',
        'PAPUA PEGUNUNGAN' => 'PAPUA PEGUNUNGAN',
        'PAPUA BARAT DAYA' => 'PAPUA BARAT DAYA',

        // Kepulauan Riau (sering tertukar dengan Riau)
        'KEPULAUAN RIAU' => 'KEPULAUAN RIAU',
        'KEP RIAU' => 'KEPULAUAN RIAU',
        'RIAU' => 'RIAU',
    ];

    /**
     * Ubah nama provinsi apa pun menjadi kunci kanonik.
     *
     * @return string Nama kanonik huruf besar, atau string kosong bila input kosong.
     */
    public static function canonical(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        // Buang kata administratif ("Provinsi Jawa Barat" cukup menjadi "Jawa Barat"),
        // tanda baca, lalu rapikan spasi.
        $key = mb_strtoupper(trim($name));
        $key = preg_replace('/\b(PROVINSI|PROPINSI|PROP)\b/', ' ', $key) ?? $key;
        $key = preg_replace('/[^A-Z\s]/', ' ', $key) ?? $key;
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;
        $key = trim($key);

        if ($key === '') {
            return '';
        }

        return self::ALIASES[$key] ?? $key;
    }

    /**
     * Apakah dua nama provinsi menunjuk provinsi yang sama.
     */
    public static function isSame(?string $a, ?string $b): bool
    {
        $ca = self::canonical($a);
        $cb = self::canonical($b);

        return $ca !== '' && $ca === $cb;
    }
}
