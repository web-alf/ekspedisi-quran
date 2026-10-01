import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, fireEvent } from '@testing-library/svelte';
import { writable } from 'svelte/store';
import { page } from '@inertiajs/svelte';
import AdminLayout from '../../resources/js/Layouts/AdminLayout.svelte';

/**
 * Label menu gudang memakai istilah baku Indonesia: "Pengemasan" (dari kata
 * dasar "kemas"), bukan "Proses Packing".
 *
 * Dua hal dikunci di sini:
 *   1. label menunya, dan
 *   2. IKON-nya. Peta ikon di AdminLayout di-key pakai TEKS LABEL, jadi
 *      mengganti label tanpa mengganti key membuat ikonnya hilang tanpa error
 *      apa pun - kesalahan yang hanya terlihat kalau ada yang menguji ikonnya.
 */

vi.mock('@inertiajs/svelte', () => ({
    router: { visit: vi.fn(), get: vi.fn(), post: vi.fn(), on: vi.fn() },
    // AdminLayout membaca user dari STORE page, bukan dari props komponen.
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

/** User yang boleh membuka seluruh menu gudang. */
const userGudang = {
    id: 6,
    name: 'Nalurita Firdausyah',
    role: 'manager',
    permissions: [
        'dashboard.view',
        'warehouse.dashboard',
        'warehouse.packing.view',
        'warehouse.boxes.view',
        'warehouse.performance.view',
        'supervisor.warehouse.monitor'
    ]
};

beforeEach(() => {
    page.set({
        props: {
            auth: { user: userGudang },
            settings: {},
            flash: {}
        },
        url: '/admin/warehouse/packing'
    });
});

/** Buka dropdown sidebar supaya menu anaknya ikut ter-render. */
async function bukaMenuGudang(container) {
    const tombol = [...container.querySelectorAll('button')]
        .find((b) => b.textContent.includes('Manajemen Gudang'));

    expect(tombol, 'tombol dropdown Manajemen Gudang tidak ditemukan').toBeTruthy();

    await fireEvent.click(tombol);

    return container;
}

describe('Label menu gudang', () => {
    it('memakai "Pengemasan", bukan "Proses Packing"', async () => {
        const { container } = render(AdminLayout);
        await bukaMenuGudang(container);

        expect(container.textContent).toContain('Pengemasan');
        expect(container.textContent).not.toContain('Proses Packing');
    });

    it('tetap memakai ikon kotak (cube), bukan ikon cadangan', async () => {
        // Ganti label tanpa ganti kunci peta ikon membuat menu ini diam-diam
        // memakai ikon cadangan (document-text) - halaman tetap jalan, jadi
        // tidak ada yang sadar sampai ada yang memeriksa ikonnya.
        // HeroIcon dan getMenuIcon SAMA-SAMA punya fallback, jadi memeriksa
        // "ada svg" saja tidak cukup: ikon mana yang muncul yang menentukan.
        const { container } = render(AdminLayout);
        await bukaMenuGudang(container);

        const item = [...container.querySelectorAll('a')]
            .find((a) => a.textContent.trim() === 'Pengemasan');

        expect(item, 'menu Pengemasan tidak ditemukan').toBeTruthy();

        const kelasIkon = item.querySelector('svg')?.getAttribute('class') ?? '';

        expect(kelasIkon, 'ikon menu Pengemasan hilang').not.toBe('');
        expect(kelasIkon).toContain('lucide-box');
        expect(kelasIkon, 'menu ini memakai ikon cadangan').not.toContain('lucide-file-text');
    });
});
