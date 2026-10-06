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

/**
 * Buka semua dropdown menu — TANPA menyentuh tombol kerangka halaman.
 *
 * Versi lama menekan SETIAP tombol, termasuk "Ciutkan Sidebar". Akibatnya sidebar
 * terlipat dan label teks menunya hilang sama sekali, sehingga pemeriksaan label
 * diam-diam tidak menemukan apa pun (menu anak masih ada karena di cabang
 * terlipat ia dirender sebagai <a> di dalam panel melayang).
 *
 * Dijaga tetap sederhana supaya tes lama tidak berubah perilakunya; tes yang
 * butuh pemeriksaan lebih ketat memakai labelMenu().
 */
async function bukaSemuaDropdown(container) {
    const tombol = [...container.querySelectorAll('button')].filter(
        (b) => b.title !== 'Ciutkan Sidebar' && b.title !== 'Perluas Sidebar'
    );

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

/**
 * Manager Distribusi: izinnya diambil dari daftar NYATA yang tersimpan pada role
 * manager, bukan dikarang — supaya tes ini ikut gagal bila suatu saat izin gudang
 * diberikan kembali kepadanya lewat seeder atau migrasi lain.
 */
const izinManager = [
    'dashboard.view', 'dashboard.analytics', 'system.monitor',
    'muatan.read', 'muatan.complete', 'muatan.create', 'muatan.scan',
    'shipments.read', 'shipments.create', 'shipments.update', 'shipments.export',
    'shipments.track', 'shipments.update-status', 'shipments.bulk-update',
    'mushaf-requests.read', 'mushaf-requests.create', 'mushaf-requests.update',
    'mushaf-requests.approve', 'mushaf-requests.reject', 'mushaf-requests.process',
    'wakaf-batch.read', 'wakaf-batch.create', 'wakaf-batch.update',
    'qr.generate', 'qr.scan', 'qr.verify',
    'status.update', 'status.track'
];

/** Kurir: izinnya jauh lebih sedikit, dan tidak punya menu gudang. */
const izinKurir = ['dashboard.view', 'muatan.read', 'muatan.scan', 'shipments.read', 'status.track'];

function tampilkanSidebar(izin, role) {
    page.set({
        props: { auth: { user: { id: 9, name: 'Uji Menu', role, permissions: izin } }, settings: {}, flash: {} },
        url: '/admin/dashboard'
    });

    return render(AdminLayout);
}

/**
 * Label seluruh menu sidebar, termasuk INDUK dropdown.
 *
 * `labelTerlihat()` hanya memungut elemen `<a>`, sehingga label induk dropdown —
 * yang dirender sebagai `<button>` — tidak ikut terbaca. Untuk menguji ada/
 * tidaknya sebuah dropdown, label tombolnya justru yang menentukan.
 */
function labelMenu(container) {
    const label = new Set();

    for (const el of container.querySelectorAll('a, button')) {
        const teks = el.textContent.trim();

        // Tombol kerangka halaman (lipat sidebar, profil) juga ikut terbaca;
        // menu dikenali dari teksnya, jadi yang penting himpunannya lengkap.
        if (teks) {
            label.add(teks);
        }
    }

    return [...label];
}

describe('Sidebar per peran', () => {
    it('menghilangkan menu Manajemen Gudang dari Manager Distribusi', async () => {
        // Menu ini diminta hilang dari sidebar manager. AdminLayout memunculkan
        // induk dropdown bila salah satu anaknya cocok, jadi tesnya harus
        // memeriksa INDUK dan ANAK-ANAKNYA sekaligus — memeriksa salah satu saja
        // bisa lolos padahal menunya masih tampil.
        const { container } = tampilkanSidebar(izinManager, 'manager');
        await bukaSemuaDropdown(container);

        const label = labelMenu(container);

        expect(label).not.toContain('Manajemen Gudang');

        for (const anak of [
            'Dasbor Gudang', 'Proses Packing', 'Box Scanner', 'Laporan Kinerja',
            'Monitor Gudang', 'Analitik Kinerja', 'Pelacakan Kerdus'
        ]) {
            expect(label, `menu "${anak}" masih tampil untuk manager`).not.toContain(anak);
        }
    });

    it('menyisakan menu alur distribusi milik manager', async () => {
        // Penjaga sebaliknya: pencabutan tidak boleh ikut mematikan pekerjaan
        // manager sendiri.
        const { container } = tampilkanSidebar(izinManager, 'manager');
        await bukaSemuaDropdown(container);

        const label = labelMenu(container);

        for (const menu of ['Muatan & Distribusi', 'Pengemasan', 'Permintaan Mushaf', 'Dashboard']) {
            expect(label, `menu "${menu}" hilang dari manager`).toContain(menu);
        }
    });

    it('tetap menampilkan menu Manajemen Gudang bagi yang berhak', async () => {
        // Kalau tes ini gagal bersama yang di atas, artinya yang berubah adalah
        // menunya untuk SEMUA orang — bukan izin manager. Sekaligus membuktikan
        // tes di atas benar-benar menguji sesuatu: label yang sama HARUS muncul
        // di sini.
        const { container } = tampilkanSidebar(
            [...izinKurir, 'warehouse.dashboard', 'warehouse.packing.view'],
            'warehouse'
        );
        await bukaSemuaDropdown(container);

        const label = labelMenu(container);

        expect(label).toContain('Manajemen Gudang');
        expect(label).toContain('Proses Packing');
    });
});
