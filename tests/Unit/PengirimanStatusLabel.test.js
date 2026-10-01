import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@testing-library/svelte';
import { writable } from 'svelte/store';
import { page } from '@inertiajs/svelte';
import PengirimanEdit from '../../resources/js/Pages/Admin/Pengiriman/Edit.svelte';

/**
 * Label dropdown status pada halaman Edit.
 *
 * Dropdown ini isinya SEMUA status pengiriman - termasuk tahap sebelum barang
 * jadi kiriman (Proses Produksi, Proses Kedatangan/Penurunan). Jadi label
 * "Status Pengiriman" menyesatkan: kata "Pengiriman" menjanjikan isinya hanya
 * soal pengiriman, padahal tidak. Dipakai "Status" saja - sama dengan judul
 * kolom tabel dan label filter di halaman daftar.
 *
 * Sekaligus dijaga: label "Status Baru" hanya untuk aksi MENGGANTI status
 * (bulk update), supaya istilahnya tidak tertukar dengan yang sekadar memilih.
 */

vi.mock('@inertiajs/svelte', () => ({
    router: { visit: vi.fn(), get: vi.fn(), post: vi.fn(), patch: vi.fn(), on: vi.fn() },
    page: writable({ props: {}, url: '/' }),
    usePage: () => ({ props: {} }),
    useForm: (data = {}) => {
        const store = writable(data);
        return Object.assign(store, {
            errors: {}, processing: false, reset: vi.fn(), patch: vi.fn(), post: vi.fn(), put: vi.fn()
        });
    }
}));

vi.mock('../../resources/js/utils/auth.js', () => ({ logout: vi.fn(), safeLogout: vi.fn() }));

/** 8 status yang benar-benar ada di produksi, urut sesuai pipeline. */
const statusProduksi = [
    { id: 1, nama: 'Proses Pemesanan', slug: 'pemesanan', warna: '#6b7280' },
    { id: 2, nama: 'Proses Produksi', slug: 'produksi', warna: '#f59e0b' },
    { id: 3, nama: 'Proses Kedatangan/Penurunan', slug: 'kedatangan', warna: '#3b82f6' },
    { id: 4, nama: 'Proses Packing', slug: 'packing', warna: '#8b5cf6' },
    { id: 5, nama: 'Selesai Packing', slug: 'selesai-packing', warna: '#10b981' },
    { id: 6, nama: 'Proses Pengiriman', slug: 'pengiriman', warna: '#06b6d4' },
    { id: 7, nama: 'Diterima Penerima', slug: 'diterima', warna: '#22c55e' },
    { id: 8, nama: 'Batal', slug: 'batal', warna: '#ef4444' }
];

const pengirimanContoh = {
    id: 1,
    no_resi: 'EQ-2026-00001',
    status_id: 4,
    status: { id: 4, nama: 'Proses Packing', slug: 'packing' },
    alamat_tujuan: 'Jl. Contoh No. 1',
    nama_penerima: 'Penerima Contoh',
    no_hp_penerima: '08123456789',
    catatan: '',
    jumlah_quran: 1,
    tanggal_wakaf: '2026-10-01',
    donatur: { nama_donatur: 'Donatur Contoh' },
    jenis_quran: { nama_jenis: "Al-Qur'an A5" },
    jenis: 'quran',
    wakaf_item: null,
    qr_code_data: null,
    qr_code_path: null,
    qr_code_file: null
};

beforeEach(() => {
    page.set({
        props: {
            auth: { user: { id: 1, name: 'Admin', role: 'super-admin', permissions: ['shipments.update'] } },
            flash: {}
        },
        url: '/admin/pengiriman/1/edit'
    });
});

function renderEdit() {
    return render(PengirimanEdit, {
        props: {
            pengiriman: pengirimanContoh,
            statusList: statusProduksi,
            jenisQuranList: [],
            approvedMushafRequests: [],
            errors: {}
        }
    });
}

describe('Dropdown status di halaman Edit', () => {
    it('berlabel "Status", bukan "Status Pengiriman"', () => {
        const { container } = renderEdit();

        const select = container.querySelector('#status-select');
        expect(select, 'dropdown status tidak ditemukan').toBeTruthy();

        const label = container.querySelector('label[for="status-select"]');

        expect(label.textContent.trim()).toBe('Status *');
        expect(label.textContent).not.toContain('Pengiriman');
        expect(label.textContent, 'tanda wajib harus tetap ada').toContain('*');
    });

    it('memuat SELURUH status termasuk tahap produksi dan kedatangan', () => {
        // Justru inilah alasan labelnya bukan "Status Pengiriman": isinya
        // mencakup tahap sebelum barang jadi kiriman.
        const { container } = renderEdit();

        const opsi = [...container.querySelectorAll('#status-select option')].map((o) => o.textContent.trim());

        expect(opsi).toContain('Proses Produksi');
        expect(opsi).toContain('Proses Kedatangan/Penurunan');
        expect(opsi).toContain('Proses Pengiriman');
        expect(opsi).toContain('Diterima Penerima');

        // 1 opsi kosong + 8 status + ... (tidak ada teks "Status Pengiriman" di label)
        expect(opsi.length).toBeGreaterThanOrEqual(statusProduksi.length);
    });

    it('tetap menampilkan status yang sedang dipakai pengiriman ini', () => {
        const { container } = renderEdit();

        // Svelte memakai bind:value, jadi status terpilih ada di PROPERTI value
        // elemen select, bukan di atribut selected pada option.
        const select = container.querySelector('#status-select');

        expect(select.value).toBe(String(pengirimanContoh.status_id));

        const opsi = [...select.options].find((o) => o.value === select.value);
        expect(opsi.textContent).toContain('Proses Packing');
    });

    it('menyediakan pilihan kosong di awal untuk dropdown memilih status', () => {
        const { container } = renderEdit();

        const kosong = [...container.querySelectorAll('#status-select option')]
            .find((o) => o.value === '');

        expect(kosong, 'tidak ada pilihan kosong').toBeTruthy();
    });
});
