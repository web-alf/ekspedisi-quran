import { describe, it, expect } from 'vitest';

/**
 * Pemindai QR: wadah DOM dan kejujuran pesan kegagalan.
 *
 * Dua bug yang dikunci di sini, keduanya muncul sebagai satu pesan yang sama di
 * layar pengguna:
 *
 *   1. WADAH BELUM ADA. Komponen pemindai dipasang di dalam blok {#if} (tombol
 *      "Nyalakan Kamera"). Saat onMount berjalan, #qr-scanner-container belum
 *      dirender — dan html5-qrcode MELEMPAR error dari konstruktornya bila
 *      elemen tujuan tidak ada. Karena container itu sendiri berada di blok
 *      {:else} (cabang sukses), kegagalan ini membuat container tidak pernah
 *      dirender, sehingga kamera gagal SELALU dan tidak bisa pulih.
 *
 *   2. PESAN SALAH MENUDUH. Dulu setiap kegagalan berbunyi "check camera
 *      permissions", padahal untuk kasus di atas izin kamera tidak pernah
 *      diminta sama sekali. Orang lalu mengejar setelan izin yang bukan
 *      sebabnya.
 *
 * Catatan: vitest di repo ini mengompilasi Svelte ke mode SSR, sehingga onMount
 * TIDAK berjalan di tes. Karena itu yang diuji adalah kunci sumbernya:
 * urutannya di berkas, dan fungsinya secara terpisah.
 */

const fs = await import('node:fs');
const path = await import('node:path');

const sumber = fs.readFileSync(
    path.resolve(__dirname, '../../resources/js/Components/LazyQRScanner.svelte'),
    'utf8'
);

describe('LazyQRScanner — wadah pemindai', () => {
    it('menjaga wadah SELALU ada di DOM, bukan di dalam cabang if/else', () => {
        // Akar masalahnya. Svelte hanya merender satu cabang, dan dulu wadah ada
        // di cabang {:else} yang mensyaratkan loading=false — padahal loading
        // baru false SETELAH pemindai berhasil dibuat. Saling mengunci: wadah
        // tidak pernah dirender, pemindai tidak pernah bisa dibuat, dan kamera
        // gagal permanen.
        const markup = sumber.slice(sumber.indexOf('</script>'));
        const posisiWadah = markup.indexOf('id="qr-scanner-container"');

        expect(posisiWadah).toBeGreaterThan(-1);

        // Komentar dibuang lebih dulu: penjelasan di berkas ini ikut menyebut
        // "{:else}", dan itu bukan markup yang dirender.
        const sebelum = markup.slice(0, posisiWadah).replace(/<!--[\s\S]*?-->/g, '');
        expect(sebelum).not.toContain('{:else');
        expect(sebelum).not.toMatch(/\{#if loading\}/);
    });

    it('menunggu wadah ada di DOM sebelum membuat pemindai', () => {
        // Konstruktor html5-qrcode melempar bila elemennya belum ada, jadi
        // penantian ini bukan kehati-hatian berlebih — tanpa ini kamera selalu
        // gagal saat komponen dipasang di dalam {#if}.
        expect(sumber).toContain('tungguContainer');
        expect(sumber).toContain('await tick()');
    });

    it('menunggu wadah SEBELUM memanggil konstruktor pemindai', () => {
        // Urutan adalah inti perbaikannya. Kalau penantian dipindah ke bawah,
        // bug aslinya kembali tanpa tes lain menangkapnya.
        const posisiTunggu = sumber.indexOf('await tungguContainer()');
        const posisiKonstruktor = sumber.indexOf('new Html5QrcodeScanner(');

        expect(posisiTunggu).toBeGreaterThan(-1);
        expect(posisiKonstruktor).toBeGreaterThan(-1);
        expect(posisiTunggu).toBeLessThan(posisiKonstruktor);
    });

    it('menggunakan const untuk id wadah, bukan teks yang tersebar', () => {
        // Dulu id ini ditulis literal dua kali (konstruktor + markup). Kalau
        // salah satu berubah, kegagalannya sunyi dan hanya muncul di peramban.
        expect(sumber).toContain("const ELEMENT_ID = 'qr-scanner-container'");
        expect(sumber).toContain('new Html5QrcodeScanner(ELEMENT_ID');
        expect(sumber).not.toContain("new Html5QrcodeScanner('qr-scanner-container'");
    });
});

describe('LazyQRScanner — kejujuran pesan kegagalan', () => {
    it('menyertakan sebab asli, bukan selalu menuduh izin kamera', () => {
        expect(sumber).not.toContain('Failed to initialize camera. Please check camera permissions.');
        expect(sumber).toMatch(/error = `Kamera tidak dapat dinyalakan: \$\{err/);
    });

    it('menampilkan tombol minta izin HANYA bila sebabnya soal izin', () => {
        // Dulu tombol ini muncul untuk setiap kegagalan (termasuk "wadah belum
        // ada"), jadi pengguna diminta memberi izin yang tidak pernah diminta.
        expect(sumber).toContain('menyinggungIzin');
        expect(sumber).toContain('{#if menyinggungIzin}');
        expect(sumber).not.toContain("error.includes('permission')");
    });

    it('mengenali kegagalan izin dari nama error peramban', () => {
        for (const nama of ['NotAllowedError', 'NotFoundError', 'NotReadableError']) {
            expect(sumber).toContain(nama);
        }
    });

    it('melepaskan kamera setelah permintaan izin', () => {
        // Tanpa track.stop(), kamera tetap dikuasai permintaan izin sehingga
        // pemindai gagal karena perangkat "sedang dipakai".
        const blokIzin = sumber.slice(
            sumber.indexOf('function requestCameraPermission'),
            sumber.indexOf('function requestCameraPermission') + 1400
        );
        expect(blokIzin).toContain('track.stop()');
    });
});
