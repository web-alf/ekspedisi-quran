/**
 * Batas doa 250 karakter agar muat di label resi (28/10/2026): setiap isian doa membatasi
 * ketikan (maxlength) dan menampilkan penghitung karakter yang sama.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';
import { DOA_MAKS_KARAKTER, panjangDoa, doaTerlaluPanjang } from '../../resources/js/utils/doa.js';

const baca = (rel) => readFileSync(resolve(__dirname, '../../resources/js', rel), 'utf8');

describe('utils/doa', () => {
    it('memakai batas yang sama dengan server', () => {
        const model = readFileSync(resolve(__dirname, '../../app/Models/WakafItem.php'), 'utf8');
        expect(model).toContain(`DOA_MAKS_KARAKTER = ${DOA_MAKS_KARAKTER};`);
        expect(DOA_MAKS_KARAKTER).toBe(250);
    });

    it('menghitung karakter seperti server: spasi di ujung diabaikan, huruf non-Latin dihitung satu', () => {
        expect(panjangDoa('  Semoga berkah  ')).toBe(13);
        expect(panjangDoa('اللهم')).toBe(5);
        expect(panjangDoa(null)).toBe(0);
    });

    it('menandai doa yang lebih dari batas', () => {
        expect(doaTerlaluPanjang('a'.repeat(250))).toBe(false);
        expect(doaTerlaluPanjang('a'.repeat(251))).toBe(true);
    });
});

describe('isian doa di form', () => {
    const isian = [
        ['Pages/Admin/Donatur/Create.svelte', 'bind:value={$form.doa_untuk_semua}', '<PenghitungDoa teks={$form.doa_untuk_semua} />'],
        ['Pages/Admin/Donatur/Create.svelte', 'bind:value={detail.doa_request}', '<PenghitungDoa teks={detail.doa_request} />'],
        ['Pages/Admin/Donatur/Edit.svelte', 'bind:value={$form.doa_untuk_semua}', '<PenghitungDoa teks={$form.doa_untuk_semua} />'],
        ['Pages/Admin/Donatur/WakafItems/Index.svelte', 'bind:value={$editForm.doa_request}', '<PenghitungDoa teks={$editForm.doa_request} />'],
    ];

    it.each(isian)('%s (%s) dibatasi maxlength dan punya penghitung', (berkas, ikatan, penghitung) => {
        const sumber = baca(berkas);
        expect(sumber).toContain(`${ikatan}\n`);
        expect(sumber).toMatch(new RegExp(`${ikatan.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*maxlength=\\{DOA_MAKS_KARAKTER\\}`));
        expect(sumber).toContain(penghitung);
        expect(sumber).toContain("import { DOA_MAKS_KARAKTER } from '");
    });

    it('Detail per Al-Qur\'an: doa selebar penuh tiga baris di bawah Atas Nama dan Hubungan', () => {
        const sumber = baca('Pages/Admin/Donatur/Create.svelte');
        const bagian = sumber.slice(sumber.indexOf("Detail per Al-Qur'an"), sumber.indexOf('{/each}', sumber.indexOf("Detail per Al-Qur'an")));

        expect(bagian).toContain('grid grid-cols-1 md:grid-cols-2 gap-4');
        expect(bagian.indexOf('wakif-name-')).toBeLessThan(bagian.indexOf('wakif-rel-'));
        expect(bagian.indexOf('wakif-rel-')).toBeLessThan(bagian.indexOf('wakif-doa-'));
        expect(bagian).toMatch(/<div class="md:col-span-2">[\s\S]*?<textarea\s+id=\{`wakif-doa-\$\{index\}`\}[\s\S]*?rows="3"/);
    });

    it('penghitung memperingatkan doa lama yang lebih dari batas', () => {
        const komponen = baca('Components/PenghitungDoa.svelte');
        expect(komponen).toContain('{panjang}/{DOA_MAKS_KARAKTER}');
        expect(komponen).toMatch(/\{#if kelebihan\}[\s\S]*tercetak terpotong di resi/);
    });
});
