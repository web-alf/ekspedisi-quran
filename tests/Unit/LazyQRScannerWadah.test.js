import { describe, it, expect } from 'vitest';

/**
 * Pemindai QR: wadah DOM, langsungnya kamera, dan kejujuran pesan kegagalan.
 *
 * Semua bug di bawah ini muncul sebagai SATU pesan yang sama di layar pengguna
 * ("camera permission"), sehingga akar aslinya mudah tertukar. Tiap bagian
 * mengunci satu akar:
 *
 *   1. WADAH BELUM ADA. Komponen pemindai dipasang di dalam blok {#if} (tombol
 *      "Nyalakan Kamera"), sementara #qr-scanner-container dulu berada di cabang
 *      {:else} yang mensyaratkan loading=false — padahal loading baru false
 *      SETELAH pemindai dibuat. Saling mengunci: wadah tidak pernah dirender dan
 *      html5-qrcode melempar error bila elemennya tidak ada.
 *
 *   2. SATU KLIK TAMBAHAN DI HP. Html5QrcodeScanner merender UI sendiri berisi
 *      tautan "Request Camera Permissions". Di HP, menekan tombol kamera tidak
 *      langsung membuka kamera, hanya memunculkan tautan itu.
 *
 *   3. PESAN SALAH MENUDUH. Setiap kegagalan dulu berbunyi "check camera
 *      permissions", padahal izin tidak pernah diminta sama sekali.
 *
 * Catatan: vitest di repo ini mengompilasi Svelte ke mode SSR, sehingga onMount
 * TIDAK berjalan di tes. Karena itu yang diuji adalah kunci-kunci di berkas —
 * urutan panggilan dan API yang dipakai.
 */

const fs = await import('node:fs');
const path = await import('node:path');

const sumber = fs.readFileSync(
    path.resolve(__dirname, '../../resources/js/Components/LazyQRScanner.svelte'),
    'utf8'
);

describe('LazyQRScanner — wadah pemindai', () => {
    it('menjaga wadah SELALU ada di DOM, bukan di dalam cabang if/else', () => {
        // Svelte hanya merender satu cabang. Dulu wadah ada di cabang {:else}
        // yang mensyaratkan loading=false, padahal loading baru false SETELAH
        // pemindai dibuat — kamera gagal permanen.
        const markup = sumber.slice(sumber.indexOf('</script>'));
        const posisiWadah = markup.indexOf('id="qr-scanner-container"');

        expect(posisiWadah).toBeGreaterThan(-1);

        // Komentar dibuang lebih dulu: penjelasan di berkas ini ikut menyebut
        // "{:else}", dan itu bukan markup yang dirender.
        const sebelum = markup.slice(0, posisiWadah).replace(/<!--[\s\S]*?-->/g, '');
        expect(sebelum).not.toContain('{:else');
    });

    it('menunggu wadah ada di DOM sebelum membuat pemindai', () => {
        expect(sumber).toContain('tungguContainer');
        expect(sumber).toContain('await tick()');
    });

    it('menunggu wadah SEBELUM memanggil API pemindai', () => {
        const posisiTunggu = sumber.indexOf('await tungguContainer()');
        const posisiKonstruktor = sumber.indexOf('new Html5Qrcode(');

        expect(posisiTunggu).toBeGreaterThan(-1);
        expect(posisiKonstruktor).toBeGreaterThan(-1);
        expect(posisiTunggu).toBeLessThan(posisiKonstruktor);
    });
});

describe('LazyQRScanner — kamera menyala langsung', () => {
    it('memakai Html5Qrcode, bukan Html5QrcodeScanner', () => {
        // Html5QrcodeScanner merender UI sendiri ("Request Camera Permissions" /
        // "Scan an Image File"), dan di HP itulah yang membuat kamera tidak
        // langsung terbuka. Html5Qrcode tidak merender UI apa pun.
        expect(sumber).toContain('new Html5Qrcode(');
        expect(sumber).not.toContain('Html5QrcodeScanner');
    });

    it('memanggil start() supaya kamera langsung menyala', () => {
        expect(sumber).toMatch(/html5QrCode\.start\(/);
        expect(sumber).toContain('facingMode');
    });

    it('meminta izin lebih dulu agar sebab kegagalan bisa dibedakan', () => {
        // Izin diminta eksplisit; hasilnya menentukan pesan yang tampil. Tanpa
        // ini, kegagalan izin dan kegagalan lain tampak sama persis.
        expect(sumber).toContain('pastikanIzinKamera');
        expect(sumber).toMatch(/navigator\.mediaDevices\.getUserMedia\(/);

        const posisiIzin = sumber.indexOf('await pastikanIzinKamera()');
        const posisiStart = sumber.indexOf('html5QrCode.start(');
        expect(posisiIzin).toBeLessThan(posisiStart);
    });

    it('melepaskan kamera setelah permintaan izin', () => {
        // Tanpa track.stop(), kamera tetap dikuasai permintaan izin sehingga
        // pemindai gagal karena perangkat "sedang dipakai".
        const blok = sumber.slice(
            sumber.indexOf('async function pastikanIzinKamera'),
            sumber.indexOf('function pilihKamera')
        );
        expect(blok).toContain('track.stop()');
    });

    it('membersihkan pemindai saat gagal maupun saat komponen dilepas', () => {
        // Pemindai yang gagal tapi tidak dibersihkan menahan kamera, sehingga
        // percobaan berikutnya ikut gagal.
        expect(sumber).toMatch(/await html5QrCode\.stop\(\)/);
        expect(sumber).toMatch(/onDestroy\(\(\) => \{\s*bersihkanPemindai\(\)/);
    });
});

describe('LazyQRScanner — kejujuran pesan kegagalan', () => {
    it('menyertakan sebab asli, bukan selalu menuduh izin kamera', () => {
        expect(sumber).not.toContain('Failed to initialize camera. Please check camera permissions.');
        expect(sumber).toContain('terjemahkanGagal');
    });

    it('membedakan izin ditolak, kamera tidak ada, dan kamera terpakai', () => {
        expect(sumber).toContain('NotAllowedError');
        expect(sumber).toContain('NotFoundError');
        expect(sumber).toContain('NotReadableError');
    });

    it('menyebut HTTPS pada kegagalan izin', () => {
        // Penyebab izin yang paling sering di HP adalah halaman dibuka lewat
        // koneksi tidak aman — kamera memang diblokir di sana. Pesan tanpa
        // petunjuk ini membuat orang mencari setelan yang salah.
        const blok = sumber.slice(sumber.indexOf('NotAllowedError'), sumber.indexOf('NotFoundError'));
        expect(blok).toContain('HTTPS');
    });

    it('tidak menyisakan mekanisme "minta izin" terpisah', () => {
        // Izin kini diminta di awal proses nyala kamera, jadi tombol izin
        // tersendiri tidak diperlukan lagi. Yang penting: tidak kembali ke pola
        // lama yang memunculkan tombol itu untuk SETIAP kegagalan.
        expect(sumber).toContain('pastikanIzinKamera');
        expect(sumber).not.toContain('menyinggungIzin');
        expect(sumber).not.toContain("error.includes('permission')");
        expect(sumber).not.toContain('Grant Camera Permission');
    });
});
