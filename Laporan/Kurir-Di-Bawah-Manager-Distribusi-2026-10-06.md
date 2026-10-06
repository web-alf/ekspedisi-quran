# Kurir di Bawah Manager Distribusi — Penindaklanjutan Temuan

**Tanggal:** 2026-10-06
**Commit:** `fa9bd14` (belum ter-deploy)
**Dasar:** temuan bahwa kurir berada di bawah role **manager distribusi** (Nalurita dkk), bukan berdiri sendiri.

---

## 1. Keadaan sebelum perubahan

Temuan itu **benar secara organisasi, tetapi belum punya wujud di sistem.**

| Diperiksa | Hasil |
|---|---|
| Kolom penanda atasan di `users` | **Tidak ada** — tak ada `manager_id`/`reports_to` |
| Peran manager distribusi | 4 orang: Nalurita Firdausyah (id 6), Nelli Agustina Siregar, Chozinatul Rohmah, Zaen |
| Peran kurir | 9 orang (id 5, 7–14) |
| Pembeda antar manager | **Tidak ada** — keempatnya identik |
| Yang menghubungkan kurir ke pekerjaan | hanya `muatan.kurir_id` (siapa yang mengantar) |
| Muatan di produksi | 0 baris |

Akibatnya: **keempat manager sama-sama melihat semua muatan dan bisa menugaskan kurir mana pun.** Pemisahan tim tidak punya arti, dan tidak ada satu pun tempat di sistem yang menyatakan siapa membawahi siapa.

Pada level izin pun tidak ada bedanya: manager memiliki izin `muatan.*` yang sama, jadi tidak ada jalan membedakan manager yang satu dari yang lain.

---

## 2. Yang diubah

Penanda `users.manager_id` (nullable, `nullOnDelete`) membuat hubungan itu **melekat pada data**. Di atasnya tiga hal:

### a. Lingkup muatan mengikuti atasan

| Peran | Muatan yang terlihat |
|---|---|
| Kurir | miliknya sendiri |
| **Manager** | **milik kurir bawahannya** |
| Gudang, supervisor, super-admin | semua (pekerjaannya memang lintas tim) |

### b. Penegakan di server, bukan hanya pada daftar yang disaring

Menyembunyikan baris di layar tidak cukup: alamat bisa diketik langsung. Setiap jalur yang menyentuh muatan diperiksa:

- membuka detail muatan
- memindai resi masuk muatan (`/admin/muatan/{id}/pindai`)
- mengubah dan menyelesaikan muatan
- memuat barang lewat halaman scan kurir

Muatan yang **belum ditugaskan** (`kurir_id` kosong) sengaja **tidak dianggap milik siapa pun** — kalau tidak, satu kurir bisa mengambil alih muatan orang lain hanya dengan menebak ID-nya.

### c. Manager boleh memakai tahap perjalanan kurir

Sebelumnya manager **diblokir** dari "Batal" (pekerjaan sebelumnya membatasi tahap kurir hanya untuk kurir). Padahal manager mengawasi perjalanan kurir bawahannya dan ikut mencatat resi yang gagal diantar. Sekarang:

| Peran | Status yang boleh dipilih |
|---|---|
| Kurir | `pengiriman`, `batal` |
| **Manager** | `selesai-packing`, `pengiriman`, `diterima`, **`batal`** |
| Gudang dkk | tanpa batas pilihan |

Tahap gudang (`pemesanan` s/d `packing`) **tetap tertutup** untuk manager.

### d. Penugasan kurir

Manager hanya menawarkan dan hanya menerima **kurir bawahannya** saat membuat muatan — baik pada daftar pilihan maupun validasi `store()`, supaya id kurir manager lain yang dikirim langsung tetap ditolak.

### e. Halaman pengguna

Form tambah/ubah pengguna dapat memilih **Manager Distribusi** untuk akun kurir. Memindahkan seseorang keluar dari peran kurir otomatis melepas keterkaitannya (mencegah penanda lama menggantung).

---

## 3. Temuan yang muncul dari tes

**Bug yang tertangkap sendiri:** rancangan pertama menerapkan dua batas (batas data manager + batas pilihan kurir) sebagai **irisan**. Hasilnya manager hanya mendapat `pengiriman` — pilihan `Diterima` dan `Selesai Packing` **hilang**, padahal justru itu pekerjaannya (verifikasi manual penyelesaian distribusi).

Perbaikannya: keduanya **digabung dalam satu predikat**, dan predikat itu yang dipakai bersama oleh daftar pilihan di layar dan penegakan di server — sehingga yang ditawarkan tidak pernah berbeda dari yang diterima.

---

## 4. Bukti

| Uji | Hasil |
|---|---|
| `KurirDiBawahManagerTest` (13 tes baru) | 13 lulus |
| Bukti-gagal: penegakan lingkup & guard dimatikan | **7 tes gagal**; dipulihkan → lulus |
| Rangkaian kurir/manager/muatan/status | 119 lulus |
| Sapuan luas | 204 lulus; **7 gagal terbukti pre-existing** (gagal juga di `761786c`) |
| `npm run build` | lulus |
| `vendor/bin/pint --dirty` | bersih |

**Verifikasi di aplikasi berjalan** (manager nyata, sesi login sungguhan):

- Halaman Muatan: hanya muatan bawahannya yang tampil (`MUK-2026-21564`, kurir "Kurir Bawahan Nalurita"); muatan kurir manager lain **0 kemunculan**.
- Halaman Pengiriman: `statusList` = `selesai-packing`, `pengiriman`, `diterima`, **`batal`** — tahap gudang tidak ada.
- Halaman pengguna: `managerList` memuat keempat manager nyata.

---

## 5. Yang perlu dilakukan sebelum/sesudah deploy

**Penugasan belum ada datanya.** Di produksi 9 kurir masih tanpa `manager_id`, dan muatan produksi masih 0 baris. Jadi:

1. Setelah deploy, tentukan **kurir mana di bawah manager mana** lewat **Kelola Pengguna → Ubah → Manager Distribusi**.
2. Sebelum itu ditegakkan, daftar pilihan kurir untuk manager akan **kosong** — bukan kerusakan, melainkan tanda penugasan belum diisi.
3. Role lain (gudang, supervisor, super-admin) tidak terpengaruh sama sekali, sehingga alur packing dan pengiriman gudang berjalan seperti sebelumnya.

**Tidak ada perubahan izin** pada perubahan ini — hanya lingkup data dan batas pilihan.
