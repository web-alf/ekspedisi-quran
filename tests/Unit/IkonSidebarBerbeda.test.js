import { describe, it, expect, vi } from 'vitest';
import { render, fireEvent } from '@testing-library/svelte';
import { writable } from 'svelte/store';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { page } from '@inertiajs/svelte';
import AdminLayout from '../../resources/js/Layouts/AdminLayout.svelte';

/**
 * Ikon sidebar tidak boleh kembar (03/11/2026).
 *
 * Sebelumnya 7 dari 9 menu peran gudang jatuh ke ikon cadangan dokumen, dan di menu
 * admin beberapa halaman berbeda berbagi ikon (Kelola Donatur & Kelola Pengguna;
 * Media Sosial, Testimonial & Notifikasi WhatsApp). Aturannya: halaman berbeda =
 * ikon berbeda; dua label yang membuka halaman yang sama boleh berikon sama.
 *
 * Ikon dibandingkan lewat kelas `lucide-<nama>` pada SVG hasil render, bukan nama
 * HeroIcon, karena dua nama HeroIcon bisa menunjuk ikon Lucide yang sama
 * (mis. 'user-group' dan 'users').
 */

vi.mock('@inertiajs/svelte', () => ({
    router: { visit: vi.fn(), get: vi.fn(), post: vi.fn(), on: vi.fn() },
    page: writable({ props: {}, url: '/' }),
    usePage: () => ({ props: {} }),
    useForm: (data = {}) => {
        const store = writable(data);
        return Object.assign(store, {
            errors: {}, processing: false, reset: vi.fn(), patch: vi.fn(), post: vi.fn()
        });
    }
}));

vi.mock('../../resources/js/utils/auth.js', () => ({
    logout: vi.fn(),
    safeLogout: vi.fn()
}));

const sumberLayout = readFileSync(resolve(__dirname, '../../resources/js/Layouts/AdminLayout.svelte'), 'utf8');

/** Semua izin yang disebut menu, supaya setiap menu ikut tampil. */
const semuaIzin = [...new Set(
    [...sumberLayout.matchAll(/requiredPermissions:\s*\[([^\]]*)\]/g)]
        .flatMap((m) => [...m[1].matchAll(/'([^']+)'/g)].map((x) => x[1]))
)];

function tampilkanSidebar(roles) {
    page.set({
        props: { auth: { user: { id: 1, name: 'Uji Ikon', roles, permissions: semuaIzin } }, settings: {}, flash: {} },
        url: '/admin/dashboard'
    });

    return render(AdminLayout);
}

/**
 * Ikon setiap menu di <nav>: tujuan (href untuk tautan, label untuk induk dropdown) → ikon Lucide.
 *
 * @returns {Array<{ label: string, tujuan: string, ikon: string|null }>}
 */
async function ikonMenu(container) {
    const nav = container.querySelector('nav');

    for (const tombol of [...nav.querySelectorAll('button')]) {
        await fireEvent.click(tombol);
    }

    return [...nav.querySelectorAll('a, button')]
        .filter((el) => el.textContent.trim())
        .map((el) => {
            const kelas = el.querySelector('svg')?.getAttribute('class') ?? '';
            const ikon = kelas.split(/\s+/).find((k) => k.startsWith('lucide-') && k !== 'lucide-icon');

            return {
                label: el.textContent.trim(),
                tujuan: el.tagName === 'A' ? el.getAttribute('href') : `dropdown:${el.textContent.trim()}`,
                ikon: ikon ?? null
            };
        });
}

/** Ikon yang dipakai lebih dari satu halaman: ikon → daftar label. */
function ikonKembar(menu) {
    const perIkon = new Map();

    for (const { label, tujuan, ikon } of menu) {
        const isi = perIkon.get(ikon) ?? new Map();
        isi.set(tujuan, label);
        perIkon.set(ikon, isi);
    }

    return Object.fromEntries(
        [...perIkon].filter(([, tujuan]) => tujuan.size > 1).map(([ikon, tujuan]) => [ikon, [...tujuan.values()]])
    );
}

describe('Ikon sidebar', () => {
    it('menu peran gudang: setiap halaman punya ikon sendiri, tanpa ikon cadangan dokumen', async () => {
        const { container } = tampilkanSidebar(['warehouse', 'manager-repacking']);
        const menu = await ikonMenu(container);

        expect(menu.map((m) => m.label)).toEqual([
            'Dashboard Gudang', 'Proses Packing', 'Stok Mushaf', 'Perpindahan Mushaf', 'Penempatan Gudang',
            'Daftar Kerdus', 'Monitor & Penugasan', 'Data Resi', 'Performa Kerja'
        ]);
        expect(menu.filter((m) => !m.ikon).map((m) => m.label), 'menu tanpa ikon').toEqual([]);
        expect(menu.filter((m) => m.ikon === 'lucide-file-text').map((m) => m.label)).toEqual([]);
        expect(ikonKembar(menu)).toEqual({});
    });

    it('menu admin: halaman berbeda tidak berbagi ikon', async () => {
        const { container } = tampilkanSidebar(['super-admin']);
        const menu = await ikonMenu(container);

        expect(menu.length, 'menu admin tidak ter-render lengkap').toBeGreaterThan(30);
        expect(menu.filter((m) => !m.ikon).map((m) => m.label), 'menu tanpa ikon').toEqual([]);
        expect(ikonKembar(menu)).toEqual({});
    });

    it('label gudang dan label admin untuk halaman yang sama memakai ikon yang sama', async () => {
        const gudang = await ikonMenu(tampilkanSidebar(['warehouse', 'manager-repacking']).container);
        const admin = await ikonMenu(tampilkanSidebar(['super-admin']).container);
        const ikonGudang = Object.fromEntries(gudang.map((m) => [m.tujuan, m.ikon]));

        const berbeda = admin
            .filter((m) => ikonGudang[m.tujuan] && ikonGudang[m.tujuan] !== m.ikon)
            .map((m) => `${m.label} (${m.tujuan})`);

        expect(berbeda).toEqual([]);
    });

    it('setiap ikon di peta getMenuIcon terdaftar di HeroIcon', () => {
        const heroIcon = readFileSync(resolve(__dirname, '../../resources/js/Components/UI/HeroIcon.svelte'), 'utf8');
        const terdaftar = new Set([...heroIcon.matchAll(/'([a-z0-9-]+)':\s*Icon/g)].map((m) => m[1]));
        const peta = sumberLayout.match(/const iconMapping = \{([\s\S]*?)\};/)[1];
        const ikonPeta = [...peta.matchAll(/^\s*'[^']+':\s*'([a-z0-9-]+)'/gm)].map((m) => m[1]);

        expect(ikonPeta.length).toBeGreaterThan(40);
        expect(ikonPeta.filter((ikon) => !terdaftar.has(ikon))).toEqual([]);
    });
});
