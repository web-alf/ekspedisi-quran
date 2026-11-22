/**
 * Beranda kurir "Hari Ini & Rute" — janji tampilan yang mudah rusak tanpa terasa:
 * menu sidebar, navigasi bawah khusus kurir di HP, dan tombol beranda yang membuka
 * halaman Pindai langsung pada mode yang tepat.
 *
 * Perilaku server-nya dikunci di tests/Feature/Admin/KurirBerandaTest.php.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { tujuanBelumDikunjungi, pilihDituju, kunciPenyimpananDituju } from '../../resources/js/utils/tujuanKurir.js';
import { resolve } from 'path';

const bersih = (teks) => teks.replace(/\/\*[\s\S]*?\*\//g, '').replace(/<!--[\s\S]*?-->/g, '');
const baca = (jalur) => bersih(readFileSync(resolve(__dirname, '../../resources/js', jalur), 'utf8'));

const layout = baca('Layouts/AdminLayout.svelte');
const beranda = baca('Pages/Admin/Kurir/Beranda.svelte');
const scan = baca('Pages/Admin/Kurir/Scan.svelte');

describe('Navigasi kurir', () => {
    it('sidebar punya menu Hari Ini & Rute ke beranda kurir', () => {
        expect(layout).toContain("label: 'Hari Ini & Rute'");
        expect(layout).toContain("route: '/admin/kurir',");
        expect(layout).toContain("'Hari Ini & Rute': 'map'");
    });

    it('navigasi bawah hanya untuk kurir dan hanya di bawah breakpoint lg', () => {
        expect(layout).toContain("$: isPeranKurir = peranNama.includes('courier');");
        expect(layout).toMatch(/\{#if isPeranKurir\}\s*<nav\s+class="lg:hidden fixed/);
        expect(layout).toContain("pb-20 lg:pb-0");
    });

    it('navigasi bawah memuat empat tujuan kurir', () => {
        for (const href of ["'/admin/kurir'", "'/admin/kurir#rute'", "'/admin/kurir/pindai'", "'/admin/muatan'"]) {
            expect(layout).toContain(`href: ${href}`);
        }
    });
});

describe('Beranda membuka Pindai pada mode yang tepat', () => {
    it('tombol serah terima, muat, dan tengah jalan memakai ?mode=', () => {
        expect(beranda).toContain('href="/admin/kurir/pindai?mode=tujuan"');
        expect(beranda).toContain('href="/admin/kurir/pindai?mode=box"');
        expect(beranda).toContain('href="/admin/kurir/pindai?mode=tengah_jalan"');
    });

    it('daftar rute bisa dituju lewat #rute', () => {
        expect(beranda).toContain('id="rute"');
    });

    it('halaman Pindai membaca ?mode= dan hanya menerima mode yang sah', () => {
        expect(scan).toContain("const MODE_SAH = ['pcs', 'box', 'status', 'tujuan', 'tengah_jalan'];");
        expect(scan).toContain("searchParams.get('mode')");
        expect(scan).toContain("MODE_SAH.includes(modeDariUrl) ? modeDariUrl : 'box'");
    });
});

describe('Tujuan yang dituju kurir (urutan bebas)', () => {
    const tujuan = [
        { kunci: 'mi|dramaga', status: 'tiba' },
        { kunci: 'darul|ciomas', status: 'berikutnya' },
        { kunci: 'tahfidz|ciomas', status: 'menunggu' },
        { kunci: 'lapas|paledang', status: 'di_jalan' },
    ];

    it('hanya tujuan berikutnya/menunggu yang masuk kartu geser', () => {
        expect(tujuanBelumDikunjungi(tujuan).map((t) => t.kunci)).toEqual(['darul|ciomas', 'tahfidz|ciomas']);
    });

    it('memakai pilihan tersimpan bila tujuannya masih belum dikunjungi', () => {
        expect(pilihDituju(tujuan, 'tahfidz|ciomas')).toBe('tahfidz|ciomas');
    });

    it('kembali ke saran server bila pilihan tersimpan sudah tiba, diserahkan di jalan, atau tidak ada', () => {
        expect(pilihDituju(tujuan, 'mi|dramaga')).toBe('darul|ciomas');
        expect(pilihDituju(tujuan, 'lapas|paledang')).toBe('darul|ciomas');
        expect(pilihDituju(tujuan, 'hilang|x')).toBe('darul|ciomas');
        expect(pilihDituju(tujuan, null)).toBe('darul|ciomas');
    });

    it('null bila semua tujuan sudah dikunjungi', () => {
        expect(pilihDituju([{ kunci: 'a', status: 'tiba' }], 'a')).toBeNull();
        expect(pilihDituju([], null)).toBeNull();
    });

    it('pilihan diingat per muatan', () => {
        expect(kunciPenyimpananDituju(148)).toBe('kurir-dituju-148');
    });

    it('beranda memakai kartu geser dan daftar diserahkan di jalan', () => {
        expect(beranda).toContain('snap-x snap-mandatory');
        expect(beranda).toContain('on:scroll={saatGulir}');
        expect(beranda).toContain('{#each belum as t, n (t.kunci)}');
        expect(beranda).toContain('{#if diJalan.length > 0}');
        expect(beranda).toContain("t.status === 'di_jalan'");
    });
});

describe('Halaman Pindai kurir tidak sempit di HP', () => {
    it('tidak menambah padding sendiri di atas padding AdminLayout', () => {
        expect(scan).not.toContain('class="px-6 py-8"');
        expect(scan).not.toContain('min-h-screen');
    });

    it('kotak tunggu/galat kamera tidak melebihi lebar layar', () => {
        const pemindai = baca('Components/LazyQRScanner.svelte');
        expect(pemindai).not.toMatch(/style="width: \{width\}px; height/);
        expect(pemindai.match(/width: \{width\}px; max-width: 100%;/g)).toHaveLength(2);
    });
});

describe('Desain Pindai kurir (mengikuti preview 22/11/2026)', () => {
    it('lima mode dikelompokkan jadi tiga tab dengan sub-pilihan', () => {
        expect(scan).toContain("{ id: 'muat', label: 'Muat barang', awal: 'box' }");
        expect(scan).toContain("{ id: 'serah', label: 'Serah terima', awal: 'tujuan' }");
        expect(scan).toContain("{ id: 'status', label: 'Ubah status', awal: 'status' }");
        expect(scan).toContain("muat: [{ id: 'box', label: 'Per kerdus' }, { id: 'pcs', label: 'Per mushaf' }]");
        expect(scan).toContain("serah: [{ id: 'tujuan', label: 'Di tujuan' }, { id: 'tengah_jalan', label: 'Tengah jalan' }]");
    });

    it('kamera langsung menyala di HP, kolom kode langsung aktif di laptop', () => {
        expect(scan).toContain('let kameraAktif = !layarLebar();');
        expect(scan).toContain("window.matchMedia('(min-width: 1024px)').matches");
        expect(scan).toContain('if (layarLebar()) inputEl?.focus();');
    });

    it('setiap hasil pindai memberi kilat dan getar', () => {
        expect(scan).toContain('beriUmpanBalik(data.success);');
        expect(scan).toContain('beriUmpanBalik(false);');
        expect(scan).toContain('navigator.vibrate?.(');
    });

    it('pemilih muatan hanya tampil saat memuat barang', () => {
        expect(scan).toContain("{#if grup === 'muat'}");
    });
});

