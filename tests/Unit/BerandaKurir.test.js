/**
 * Beranda kurir "Hari Ini & Rute" — janji tampilan yang mudah rusak tanpa terasa:
 * menu sidebar, navigasi bawah khusus kurir di HP, dan tombol beranda yang membuka
 * halaman Pindai langsung pada mode yang tepat.
 *
 * Perilaku server-nya dikunci di tests/Feature/Admin/KurirBerandaTest.php.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
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
        expect(scan).toContain("MODE_SAH.includes(modeDariUrl) ? modeDariUrl : 'pcs'");
    });
});
