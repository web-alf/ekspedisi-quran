import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, fireEvent } from '@testing-library/svelte';
import { writable } from 'svelte/store';
import { page } from '@inertiajs/svelte';
import AdminLayout from '../../resources/js/Layouts/AdminLayout.svelte';

/**
 * Setiap menu di sidebar WAJIB punya ikon di peta ikon AdminLayout.
 *
 * Peta itu di-key pakai TEKS LABEL, bukan route atau id. Jadi mengganti label
 * menu tanpa mengganti kuncinya tidak memunculkan error apa pun: HeroIcon dan
 * getMenuIcon sama-sama punya fallback, sehingga ikonnya diam-diam berubah jadi
 * ikon dokumen. Tes ini membandingkan daftar label pada navigasi dengan kunci
 * yang ada di peta, supaya label baru yang belum berikon langsung ketahuan -
 * tanpa perlu memperbarui tes tiap kali label diganti.
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

/** Super-admin: paling sedikit izin terpotong, jadi paling banyak menu tampil. */
const userLengkap = {
    id: 1,
    name: 'Uji Izin',
    role: 'super-admin',
    permissions: [
        'dashboard.view', 'donatur.read', 'shipments.read',
        'certificates.read', 'templates.read', 'mushaf-requests.read',
        'warehouse.dashboard', 'warehouse.packing.view', 'warehouse.boxes.view',
        'warehouse.performance.view', 'supervisor.warehouse.monitor',
        'supervisor.performance.reports', 'users.read', 'roles.read',
        'permissions.read', 'settings.read'
    ]
};

beforeEach(() => {
    page.set({
        props: { auth: { user: userLengkap }, settings: {}, flash: {} },
        url: '/admin/dashboard'
    });
});

/** Buka semua dropdown supaya menu anaknya ikut ter-render. */
async function bukaSemuaDropdown(container) {
    const tombol = [...container.querySelectorAll('button')];

    for (const t of tombol) {
        await fireEvent.click(t);
    }

    return container;
}

/** Label menu yang muncul di sidebar (item anak dropdown dibedakan bentuknya). */
function labelTerlihat(container) {
    const label = new Set();

    for (const a of container.querySelectorAll('a')) {
        const teks = a.textContent.trim();

        if (teks) {
            label.add(teks);
        }
    }

    return label;
}

describe('Sidebar admin', () => {
    it('setiap label menu punya ikon tersendiri, bukan ikon cadangan', async () => {
        const { container } = render(AdminLayout);
        await bukaSemuaDropdown(container);

        const label = labelTerlihat(container);

        expect(label.size, 'tidak ada menu yang ter-render').toBeGreaterThan(5);

        // Setiap menu harus merender ikon, dan ikonnya tidak boleh jatuh ke
        // cadangan document-text kecuali memang itu ikonnya.
        const pakaiCadangan = [];

        for (const a of container.querySelectorAll('a')) {
            const teks = a.textContent.trim();

            if (!teks) {
                continue;
            }

            const kelas = a.querySelector('svg')?.getAttribute('class') ?? '';

            expect(kelas, `menu "${teks}" tidak punya ikon sama sekali`).not.toBe('');

            // 'document-text' adalah cadangan getMenuIcon sekaligus HeroIcon.
            // Menu yang memang memakainya akan terdaftar di pengecualian ini.
            const memangIkonDokumen = ['Manajemen Sertifikat', 'Legal & Kebijakan', 'FAQ'];

            if (kelas.includes('lucide-file-text') && !memangIkonDokumen.includes(teks)) {
                pakaiCadangan.push(teks);
            }
        }

        expect(pakaiCadangan, 'menu ini memakai ikon cadangan, kuncinya belum diisi').toEqual([]);
    });

    it('menu gudang memakai ikon kotak (cube)', async () => {
        // Dulu dijaga eksplisit karena labelnya pernah diganti.
        const { container } = render(AdminLayout);

        const tombol = [...container.querySelectorAll('button')]
            .find((b) => b.textContent.includes('Manajemen Gudang'));
        await fireEvent.click(tombol);

        const item = [...container.querySelectorAll('a')]
            .find((a) => a.textContent.trim() === 'Proses Packing');

        expect(item, 'menu Proses Packing tidak ditemukan').toBeTruthy();
        expect(item.querySelector('svg')?.getAttribute('class') ?? '').toContain('lucide-box');
    });

    it('menu Pengemasan memakai ikon kardus, bukan truck', async () => {
        // Ikon truk adalah sisa dari waktu label menu ini masih "Pengiriman".
        // Setelah labelnya jadi "Pengemasan", truk tidak nyambung - ikon adalah
        // hal pertama yang terlihat sebelum labelnya dibaca. Dikunci eksplisit
        // supaya sisa lama itu tidak kembali tanpa ketahuan.
        const { container } = render(AdminLayout);

        const item = [...container.querySelectorAll('a')]
            .find((a) => a.textContent.trim() === 'Pengemasan');

        expect(item, 'menu Pengemasan tidak ditemukan').toBeTruthy();

        const kelas = item.querySelector('svg')?.getAttribute('class') ?? '';

        expect(kelas).toContain('lucide-package-check');
        expect(kelas, 'ikon truck tidak boleh dipakai lagi di menu Pengemasan').not.toContain('lucide-truck');
    });

    it('menu Pengemasan tetap mengarah ke halaman pengiriman', async () => {
        // Hanya label dan ikon yang berubah; route dan izinnya tidak disentuh.
        const { container } = render(AdminLayout);

        const item = [...container.querySelectorAll('a')]
            .find((a) => a.textContent.trim() === 'Pengemasan');

        expect(item.getAttribute('href')).toContain('/admin/pengiriman');
    });
});
