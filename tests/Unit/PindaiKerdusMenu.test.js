/**
 * Panel Pindai Kerdus (sisi distribusi) — di mana ia tinggal dan apa janjinya.
 *
 * Keputusan pemilik sistem: panel ini MENUMPANG halaman kerja Muatan & Distribusi
 * (`/admin/muatan`), BUKAN menu sidebar tersendiri — sisi distribusi bekerja di satu
 * halaman, dan navigasi kembar dihindari. Izinnya tetap `distribusi.pindai-kerdus`
 * (SENGAJA bukan muatan.scan, yang dipegang kurir).
 *
 * Yang diuji di sini adalah hal-hal yang mudah rusak tanpa terasa: menu kembar yang
 * muncul kembali, panel yang lupa dirender, jalur input manual yang hilang, dan tabel
 * riwayat yang tidak ikut menyegar setelah memindai.
 *
 * Kenapa tes berkas-bukan-browser: aplikasi ini tanpa SSR, jadi menu/markup hanya ada di
 * JavaScript. Membaca berkas sumber adalah cara termurah mengunci janji tampilannya;
 * perilaku server-nya dikunci di tests/Feature/Admin/PindaiKerdusDistribusiTest.php.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const bersih = (teks) =>
    teks
        // Komentar diabaikan supaya tes tidak lulus karena komentar.
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/<!--[\s\S]*?-->/g, '');

const navigasi = bersih(
    readFileSync(resolve(__dirname, '../../resources/js/Layouts/AdminLayout.svelte'), 'utf8'),
);

const halamanMuatan = bersih(
    readFileSync(resolve(__dirname, '../../resources/js/Pages/Admin/Muatan/Index.svelte'), 'utf8'),
);

const panel = bersih(
    readFileSync(resolve(__dirname, '../../resources/js/Components/PindaiKerdusPanel.svelte'), 'utf8'),
);

describe('Panel Pindai Kerdus menumpang halaman muatan', () => {
    it('dirender di halaman muatan, hanya bila server mengirim propnya', () => {
        expect(halamanMuatan).toContain("import PindaiKerdusPanel from '../../../Components/PindaiKerdusPanel.svelte'");
        expect(halamanMuatan).toContain('{#if pindaiKerdus}');
        expect(halamanMuatan).toContain('<PindaiKerdusPanel pindai={pindaiKerdus}');
    });

    it('tidak lagi punya menu sidebar sendiri (tidak ada navigasi kembar)', () => {
        expect(navigasi).not.toContain("label: 'Pindai Kerdus'");
        expect(navigasi).not.toContain("route: '/admin/pindai-kerdus'");
    });

    it('kirimannya ke server memakai endpoint pencatat, bukan halaman', () => {
        expect(panel).toContain("axios.post('/admin/pindai-kerdus'");
        expect(panel).not.toContain("route: '/admin/pindai-kerdus'");
    });
});

describe('Janji tampilan panel', () => {
    it('menyediakan kamera dan jalur ketik manual, keduanya dicatat', () => {
        expect(panel).toContain('Pindai QR Kerdus');
        expect(panel).toContain('id="kode-manual"');
        expect(panel).toContain("catat(kode, 'manual')");
        expect(panel).toContain('LazyQRScanner');
    });

    it('menampilkan kerdus belum terdaftar dengan isian jenis & jumlah eks', () => {
        expect(panel).toContain('{#if perluIsian}');
        expect(panel).toContain('id="isian-jenis"');
        expect(panel).toContain('id="isian-jumlah"');
    });

    it('tidak meminta ketik ulang jumlah untuk kerdus terdaftar (isi diambil dari data)', () => {
        expect(panel).not.toContain('perluHitung');
        expect(panel).not.toContain('id="hitung-jumlah"');
        expect(panel).not.toContain('jumlah_fisik:');
    });

    it('tidak ada tabel riwayat terpisah yang menduplikasi daftar kerdus', () => {
        expect(panel).not.toContain('{#each log.data');
        expect(panel).not.toContain('pindai_tanggal');
    });

    it('daftar kerdus siap meniru kolom Tracking Kerdus gudang, tanpa status verifikasi', () => {
        for (const kolom of ['Kode Kerdus', 'Status', 'Jenis Quran', 'Progress', 'Isi (Satuan)', 'QR Kerdus', 'User', 'Seal Code', 'Tanggal']) {
            expect(panel).toContain(`>${kolom}</th>`);
        }
        // Pindai terakhir diadopsi dari tabel riwayat lama.
        for (const kolom of ['Dipindai', 'Metode', 'Dipindai oleh']) {
            expect(panel).toContain(`>${kolom}</th>`);
        }
        expect(panel).toContain('{box.qr_code_base64}');
        expect(panel).not.toContain('>Verifikasi</th>');
        expect(panel).not.toContain('siap_status');
    });

    it('menyegarkan prop panelnya dari server setelah memindai', () => {
        // Tanpa ini tabelnya masih menampilkan keadaan sebelum pindai sampai halaman dimuat
        // ulang manual. Hanya prop panel yang dimuat ulang — tabel muatan tidak diganggu.
        expect(panel).toContain("router.reload({ only: ['pindaiKerdus'] })");
    });

    it('filter daftar kerdus memakai awalan siap_ supaya tidak menimpa filter tabel muatan', () => {
        for (const kunci of ['siap_gudang', 'siap_cari', 'siap_page']) {
            expect(panel).toContain(kunci);
        }
    });

    it('kode kerdus bisa diklik untuk melihat mushaf di dalamnya', () => {
        expect(panel).toContain('on:click={() => bukaDetail(box)}');
        expect(panel).toContain('/admin/pindai-kerdus/kerdus/${box.id}');
        expect(panel).toContain('{#each detail.mushaf as m');
        expect(panel).toContain('{#each detail.riwayat_pindai as r}');
    });

    it('jendela detail responsif: kartu di ponsel/tablet, tabel di layar lebar', () => {
        expect(panel).toContain('<ul class="lg:hidden divide-y divide-gray-100">');
        expect(panel).toContain('<table class="hidden lg:table');
        expect(panel).toContain('max-h-[92dvh]');
        // Escape menutup, dan halaman di belakang tidak ikut tergulir.
        expect(panel).toContain("e.key === 'Escape' && tutupDetail()");
        expect(panel).toContain("document.body.style.overflow = detailKode ? 'hidden' : ''");
    });
});
