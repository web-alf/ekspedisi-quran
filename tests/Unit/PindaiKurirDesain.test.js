import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, fireEvent, waitFor } from '@testing-library/svelte';
import { writable } from 'svelte/store';

/**
 * Halaman Pindai kurir dengan desain baru (22/11/2026): tiga tab (Muat barang, Serah
 * terima, Ubah status), sub-pilihan per tab, kartu hitungan muatan hanya saat memuat,
 * dan riwayat pindai bergaya feed.
 */

const axiosPost = vi.fn();

vi.mock('axios', () => ({
    default: { post: (...args) => axiosPost(...args), get: vi.fn(() => Promise.resolve({ data: {} })) },
}));

vi.mock('html5-qrcode', () => ({
    Html5QrcodeScanner: class {
        render() {}
        clear() {}
    },
    Html5Qrcode: class {},
}));

vi.mock('@inertiajs/svelte', () => ({
    router: { visit: vi.fn(), get: vi.fn(), post: vi.fn(), reload: vi.fn(), on: vi.fn() },
    page: writable({
        props: { auth: { user: { id: 1, name: 'Kurir', role: 'courier', roles: ['courier'], permissions: [] } }, flash: {} },
        url: '/admin/kurir/pindai',
    }),
    usePage: () => ({ props: {} }),
    inertia: () => ({}),
    Link: vi.fn(),
}));

vi.mock('../../resources/js/utils/auth.js', () => ({ logout: vi.fn(), safeLogout: vi.fn() }));

const { default: Scan } = await import('../../resources/js/Pages/Admin/Kurir/Scan.svelte');

const muatanHariIni = [{ id: 148, kode_muatan: 'MUK-2026-00148', total_resi: 16, total_mushaf: 384 }];

const tombol = (container, teks) =>
    [...container.querySelectorAll('button')].find((b) => b.textContent.trim() === teks);

describe('Pindai kurir — tab dan sub-pilihan', () => {
    beforeEach(() => axiosPost.mockReset());

    it('dibuka pada Muat barang · Per kerdus dengan hitungan muatan', () => {
        const { container } = render(Scan, { props: { muatanHariIni } });

        expect(tombol(container, 'Muat barang').getAttribute('aria-selected')).toBe('true');
        expect(tombol(container, 'Per kerdus').getAttribute('aria-pressed')).toBe('true');
        expect(container.querySelector('#muatan-tujuan')).not.toBeNull();
        expect(container.textContent).toContain('resi · 384 mushaf');
    });

    it('tab Serah terima membuka mode Di tujuan dan menyembunyikan pemilih muatan', async () => {
        const { container } = render(Scan, { props: { muatanHariIni } });

        await fireEvent.click(tombol(container, 'Serah terima'));

        expect(tombol(container, 'Di tujuan').getAttribute('aria-pressed')).toBe('true');
        expect(tombol(container, 'Tengah jalan')).toBeDefined();
        expect(container.querySelector('#muatan-tujuan')).toBeNull();
    });

    it('tab Ubah status tidak punya sub-pilihan', async () => {
        const { container } = render(Scan, { props: { muatanHariIni } });

        await fireEvent.click(tombol(container, 'Ubah status'));

        expect(tombol(container, 'Per kerdus')).toBeUndefined();
        expect(tombol(container, 'Di tujuan')).toBeUndefined();
        expect(container.querySelector('#muatan-tujuan')).toBeNull();
    });

    it('kode kerdus yang diketik dimuat dan tampil di pindaian terakhir', async () => {
        axiosPost.mockResolvedValue({
            data: {
                success: true,
                tercatat: true,
                jenis: 'box',
                message: 'Kerdus dimuat: 24 resi.',
                data: { kode_kerdus: 'KB-20261120-07-A5-011', jumlah_resi: 24 },
                total_resi: 40,
                total_mushaf: 960,
            },
        });
        const { container } = render(Scan, { props: { muatanHariIni } });

        const input = container.querySelector('#input-manual');
        await fireEvent.input(input, { target: { value: 'KB-20261120-07-A5-011' } });
        await fireEvent.click(tombol(container, 'Masukkan'));

        expect(axiosPost).toHaveBeenCalledWith('/admin/kurir/pindai/box', { kode: 'KB-20261120-07-A5-011', muatan_id: 148 });
        await waitFor(() => expect(container.textContent).toContain('resi · 960 mushaf'));
        const feed = container.querySelector('ul.divide-y');
        expect(feed.textContent).toContain('KB-20261120-07-A5-011');
        expect(feed.querySelector('.bg-green-50')).not.toBeNull();
    });

    it('pindaian yang ditolak tampil merah di pindaian terakhir', async () => {
        // Hanya pindai yang ditolak: komponen lain di layout juga memanggil axios.post.
        const ditolak = Object.assign(new Error('422'), { response: { data: { message: 'Kerdus milik muatan lain.' } } });
        axiosPost.mockImplementation((url) =>
            String(url).startsWith('/admin/kurir/pindai') ? Promise.reject(ditolak) : Promise.resolve({ data: {} }),
        );
        const { container } = render(Scan, { props: { muatanHariIni } });

        await fireEvent.input(container.querySelector('#input-manual'), { target: { value: 'KB-20261120-03-A6-002' } });
        await fireEvent.click(tombol(container, 'Masukkan'));

        await waitFor(() => expect(container.querySelector('ul.divide-y')?.textContent).toContain('Kerdus milik muatan lain.'));
        expect(container.querySelector('ul.divide-y .bg-red-50')).not.toBeNull();
    });
});
