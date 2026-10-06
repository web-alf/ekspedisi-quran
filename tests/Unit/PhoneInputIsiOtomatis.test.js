/**
 * Memilih kode donatur di formulir mengisi nama, telepon, email, dan alamat sekaligus.
 * Kolom telepon memakai pustaka `svelte-tel-input`, dan pemasangannya di sini punya
 * cacat: prop `value`-nya hanya dibaca SEKALI saat komponen dipasang (lewat onMount),
 * sehingga nilai yang datang belakangan — persis kasus pengisian otomatis — tidak pernah
 * muncul di kotak isian walau data formulirnya sudah terisi.
 *
 * Tes ini memakai TelInput yang asli, bukan tiruan, karena justru perilaku pustakanya
 * yang jadi pokok persoalan: tiruan akan selalu lolos dan tidak membuktikan apa pun.
 *
 * Cara menjalankan: npx vitest run tests/Unit/PhoneInputIsiOtomatis.test.js
 */
import { describe, it, expect, afterEach } from 'vitest';
import { render, cleanup } from '@testing-library/svelte';
import PhoneInput from '../../resources/js/Components/PhoneInput.svelte';

afterEach(() => {
    cleanup();
});

/** Nilai yang tersimpan di elemen input telepon (yang benar-benar dilihat pengguna). */
function nilaiTelepon(container) {
    return container.querySelector('input[type="tel"]')?.value ?? null;
}

describe('PhoneInput menerima nilai yang datang belakangan', () => {
    it('menampilkan nilai yang diberikan saat komponen dipasang', () => {
        const { container } = render(PhoneInput, {
            props: { value: '+6281234568523' },
        });

        expect(nilaiTelepon(container)).toBeTruthy();
        expect(nilaiTelepon(container)).toContain('812');
    });

    it('menampilkan nilai yang berubah SETELAH komponen dipasang (kasus pengisian otomatis)', async () => {
        // Inilah persoalan yang dilaporkan: kolom lain terisi, kolom telepon tidak.
        const { component, container } = render(PhoneInput, {
            props: { value: '' },
        });

        // Sebelumnya kosong.
        expect(nilaiTelepon(container)).toBe('');

        // Pengisian otomatis datang belakangan, seperti saat kode donatur dipilih.
        await component.$set({ value: '+6281234568523' });

        expect(nilaiTelepon(container)).not.toBe('');
        expect(nilaiTelepon(container)).toContain('812');
    });

    it('mengosongkan kotak isian ketika nilainya dikosongkan dari luar', async () => {
        const { component, container } = render(PhoneInput, {
            props: { value: '+6281234568523' },
        });

        expect(nilaiTelepon(container)).not.toBe('');

        await component.$set({ value: '' });

        expect(nilaiTelepon(container)).toBe('');
    });

    it('mengganti isi kotak isian ketika nilainya diganti dari luar', async () => {
        // Kasus nyata: staf memilih donatur A, lalu mengganti ke donatur B.
        const { component, container } = render(PhoneInput, {
            props: { value: '+628111111111' },
        });

        expect(nilaiTelepon(container)).toContain('811');

        await component.$set({ value: '+628222222222' });

        expect(nilaiTelepon(container)).toContain('822');
        expect(nilaiTelepon(container)).not.toContain('811');
    });

    it('menerima nilai yang datang tanpa awalan +62 (data lama)', async () => {
        const { component, container } = render(PhoneInput, {
            props: { value: '' },
        });

        await component.$set({ value: '085176861983' });

        expect(nilaiTelepon(container)).not.toBe('');
    });
});
