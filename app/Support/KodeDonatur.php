<?php

namespace App\Support;

/**
 * Penyeragam kode dan nama donatur.
 *
 * Donasi rutin memakai kode donatur yang sama berulang kali, jadi kode itu yang
 * menentukan apakah sebuah isian dihitung sebagai donasi berikutnya dari orang yang
 * sama atau sebagai orang baru. Perbandingan mentah membuat "ECB 81" dan "ECB81"
 * dianggap dua orang — dan di produksi itu benar-benar terjadi.
 *
 * Semua tempat yang membandingkan kode atau nama WAJIB lewat kelas ini supaya
 * aturannya satu dan tidak berbeda antar layar.
 */
class KodeDonatur
{
    /**
     * Bentuk baku kode donatur: tanpa spasi, huruf besar, hanya huruf/angka/-/_.
     *
     * Spasi dibuang, bukan diganti tanda hubung, karena di data lama spasi muncul
     * justru karena tidak sengaja terketik — "ECB 81" adalah "ECB81" yang salah ketik.
     * Beberapa ragam tanda hubung yang sulit dibedakan juga diseragamkan.
     */
    public static function bersihkan(?string $kode): string
    {
        if ($kode === null) {
            return '';
        }

        // Buang spasi biasa, spasi tak-putus (dari salinan Excel/web), dan spasi lebar-nol.
        $kode = preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', '', $kode);

        // Ragam tanda hubung: en dash, em dash, minus, hyphen tak-putus.
        $kode = str_replace(['–', '—', '−', '‐', '‑'], '-', $kode);

        return preg_replace('/[^A-Z0-9\-_]/', '', mb_strtoupper($kode, 'UTF-8'));
    }

    /**
     * Bentuk baku nama donatur: spasi berlebih diringkas jadi satu, ujung dipangkas.
     */
    public static function nama(?string $nama): string
    {
        if ($nama === null) {
            return '';
        }

        $nama = preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', ' ', $nama);

        return trim($nama);
    }

    /**
     * Apakah dua nama merujuk orang yang sama.
     *
     * Huruf besar/kecil dan spasi berlebih diabaikan, karena itu murni salah ketik
     * dan bukan orang yang berbeda.
     */
    public static function namaSama(?string $a, ?string $b): bool
    {
        $a = self::nama($a);
        $b = self::nama($b);

        if ($a === '' || $b === '') {
            return false;
        }

        return mb_strtolower($a, 'UTF-8') === mb_strtolower($b, 'UTF-8');
    }

    /**
     * Apakah dua kode donatur merujuk kode yang sama setelah diseragamkan.
     */
    public static function kodeSama(?string $a, ?string $b): bool
    {
        $a = self::bersihkan($a);
        $b = self::bersihkan($b);

        return $a !== '' && $a === $b;
    }
}
