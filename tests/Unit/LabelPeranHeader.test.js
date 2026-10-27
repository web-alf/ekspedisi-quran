/**
 * Label peran di header AdminLayout.
 *
 * Kejadian 27/10/2026: header memakai `roles_display[0]` — elemen pertama daftar role
 * mengikuti urutan pivot, bukan wewenang. Akun gudang@ (Manager Gudang) memegang
 * `warehouse` + `manager-repacking`, sehingga header menulis "Freelancer Gudang"
 * padahal akun itu manager gudang. Header harus memakai `role_display` yang dihitung
 * server dari role UTAMA (User::roleUtama()).
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const layout = readFileSync(
    resolve(__dirname, '../../resources/js/Layouts/AdminLayout.svelte'),
    'utf8',
).replace(/<!--[\s\S]*?-->/g, '');

describe('Label peran di header', () => {
    it('memakai role utama dari server, bukan elemen pertama roles_display', () => {
        expect(layout).toContain('primaryRoleDisplay = currentUser.role_display || userRolesDisplay[0]');
        expect(layout).not.toMatch(/primaryRoleDisplay\s*=\s*userRolesDisplay\[0\]\s*\|\|/);
    });

    it('menampilkan role utama itu di blok identitas pengguna', () => {
        expect(layout).toContain('<p class="text-gray-500">{primaryRoleDisplay}</p>');
    });
});
