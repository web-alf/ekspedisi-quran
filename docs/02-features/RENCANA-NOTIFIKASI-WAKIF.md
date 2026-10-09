# Rencana: Menghidupkan Kembali Notifikasi ke Wakif

**Status:** rencana saja — belum ada kode yang ditulis.
**Konteks:** fitur WhatsApp dihapus permanen Agustus 2025. Ini rencana membangunnya ulang secara sadar, bukan memulihkan.

---

## 1. Apa yang sebenarnya hilang

Penghapusan dilakukan oleh migrasi `2025_08_15_190504_remove_whatsapp_functionality.php`:

| Yang dihapus | Rincian |
|---|---|
| 4 tabel | `whatsapp_settings`, `whatsapp_notifications`, `whatsapp_templates`, `whatsapp_contacts` |
| 10 izin Spatie | `whatsapp.settings.read/write`, `whatsapp.contacts.*`, `whatsapp.templates.*`, `whatsapp.notifications.read/send/retry`, `whatsapp.system.test` |
| Kode layanan | `WhatsAppService` (tidak ada lagi di repo) |
| 13 perintah artisan | `whatsapp:test-text`, `whatsapp:test-media`, `whatsapp:test-external`, dll. |
| Webhook | `/webhook/whatsapp`, `/webhook/whatsapp/device`, `/webhook/whatsapp/test` |
| Konfigurasi | `WHATSAPP_API_KEY`, `WHATSAPP_MEDIA_BASE_URL`, rate limit, webhook secret |

`down()` migrasi itu sengaja melempar exception — jadi pengembaliannya **tidak bisa** sekadar `migrate:rollback`. Yang masih ada hanya dokumen (`docs/02-features/WHATSAPP_*.md`) sebagai rujukan desain lama.

**Catatan penting:** tabelnya sudah tidak ada di produksi, jadi tidak ada data lama yang bisa dipulihkan. Riwayat pesan yang dulu pernah terkirim **hilang permanen**.

---

## 2. Yang harus diputuskan dulu (sebelum satu baris kode)

### 2.1 Penyedia pengiriman
Dokumen lama memakai **StarSender**. Pilihannya:

| Pilihan | Kelebihan | Kekurangan |
|---|---|---|
| StarSender (seperti dulu) | Sudah pernah dipakai, dokumennya ada | Perlu akun & biaya; API mungkin sudah berubah sejak Agu 2025 |
| Penyedia lain (Fonnte, Wablas, dll.) | Mungkin lebih murah/lokal | Perlu tulis adaptor baru |
| WhatsApp Business API resmi (Meta) | Paling stabil & patuh | Mahal, perlu verifikasi bisnis |

**Perlu dari Anda:** akun penyedia + API key, dan siapa pemilik nomor pengirimnya.

### 2.2 Siapa yang dinotifikasi, dan pada peristiwa apa

| Peristiwa | Penerima | Isi pesan |
|---|---|---|
| Batch selesai (tahap 5) | Donatur | Sertifikat siap + link unduh |
| Resi diterima | Donatur | Mushaf sudah sampai |
| Permintaan mushaf disetujui | Pengurus lembaga | Rincian pengiriman |
| Kerdus disegel | Staff gudang | Ringkasan harian (opsional) |

**Perlu dari Anda:** mana yang benar-benar diinginkan? Ini menentukan jumlah template.

### 2.3 Batas privasi & laju
- Nomor donatur sudah tersimpan (`donatur.no_hp`, `whatsapp_pengurus_1/2`) — jadi sumbernya ada.
- Berapa pesan maksimum per menit agar nomor pengirim tidak diblokir?
- Boleh kirim di luar jam kerja (mis. hasil penutupan batch 02:30)? Kalau tidak, perlu penjadwalan ulang ke pagi.

---

## 3. Rancangan teknis

### 3.1 Migrasi (add-only, tidak menyentuh yang ada)

```
create_whatsapp_settings      — pengaturan + API key (terenkripsi), 1 baris aktif
create_whatsapp_templates     — template pesan per jenis peristiwa, bisa dinonaktifkan
create_whatsapp_notifications — antrean & riwayat kirim (status, percobaan, galat)
add_whatsapp_permissions      — 10 izin Spatie (hanya menambah, JANGAN syncPermissions)
```

**Pelajaran yang wajib dipatuhi:** `RolePermissionSeeder::syncPermissions` menghapus izin yang tidak ada di daftarnya. Karena itu izin baru harus lewat migrasi, bukan seeder.

**Jebakan enum yang baru saja terbukti:** migrasi `2025_08_21_004209` menghapus `cancelled` dari enum `wakaf_batches` tanpa sengaja hanya karena menulis ulang daftarnya. Setiap migrasi yang menulis ulang daftar nilai harus menyebut **seluruh** nilai lama, bukan hanya yang baru.

### 3.2 Komponen kode

| Berkas | Tugas |
|---|---|
| `app/Services/WhatsAppService.php` | Bungkus HTTP penyedia: kirim teks, kirim gambar, cek status. Timeout + retry + backoff |
| `app/Services/WhatsAppTemplateService.php` | Isi placeholder (`{nama}`, `{batch}`, `{resi}`, `{link}`) dari data wakaf |
| `app/Jobs/WhatsApp/SendWhatsAppJob.php` | Kirim lewat antrean, `tries=3`, backoff, `ShouldQueue` |
| `app/Services/WhatsAppRateLimiter.php` | Batas per menit via cache (tanpa Redis pun jalan — cache database) |
| `app/Http/Controllers/Admin/WhatsAppController.php` | Pengaturan, daftar antrean, kirim ulang, uji kirim |
| `resources/js/Pages/Admin/WhatsApp/*.svelte` | Halaman admin (ikut gaya yang ada) |

**Pola yang sudah terbukti di repo ini:**
- Antrean pakai `database` (bukan Redis) — worker sudah jalan di server, `jobs`/`failed_jobs` sudah terpasang.
- Tandai terkirim **hanya** setelah penyedia membalas sukses — idempoten lewat kunci unik (peristiwa + id), supaya kiriman ulang tidak menggandakan.
- Kegagalan dicatat, tidak melempar ke alur utama. **Notifikasi tidak boleh pernah menggagalkan perubahan status.**

### 3.3 Titik sambung ke alur (hati-hati!)

Ini bagian paling rawan. Perubahan status resi sudah punya rantai: `Pengiriman::updateStatus()` → `PengirimanObserver` → `WakafBatch::updateStatus()`.

**Aturan:** notifikasi **tidak** dipanggil langsung di dalam transaksi status. Yang benar: masukkan ke antrean (`dispatchAfterResponse` atau job ber-`ShouldQueue`), supaya transaksi status selesai dulu. Kalau tidak, penyedia yang lambat akan menahan kunci baris `pengiriman` dan memblokir kerja gudang.

Titik sambungnya:
1. `PengirimanObserver::updated()` saat slug jadi `diterima` → antre "mushaf sudah sampai".
2. `wakaf:tutup-pengiriman` setelah batch `completed` → antre "sertifikat siap" (ini menyelesaikan butir yang sekarang masih menggantung di diagram).
3. `MushafRequestController::approve()` → antre "permintaan disetujui".

### 3.4 Webhook masuk (opsional)
Kalau ingin melacak status pengiriman pesan (terkirim/dibaca) dan jawaban wakif:
`POST /webhook/whatsapp` dengan verifikasi tanda tangan `WHATSAPP_WEBHOOK_SECRET`. **Hanya jalur yang punya verifikasi** — endpoint publik tanpa verifikasi adalah pintu terbuka.

---

## 4. Urutan pengerjaan yang saya usulkan

| Tahap | Isi | Keluaran |
|---|---|---|
| 0 | Anda putuskan penyedia + daftar peristiwa (§2) | keputusan tertulis |
| 1 | Migrasi + model + izin lewat migrasi | uji: izin bertambah, peran lain tidak berubah |
| 2 | `WhatsAppService` + rate limiter + **perintah uji kirim** | kirim 1 pesan uji ke nomor Anda sendiri |
| 3 | Template + `SendWhatsAppJob` + antrean & riwayat | uji: gagal → tercatat; ulang → tidak ganda |
| 4 | Titik sambung ke alur (3 titik di §3.3) | uji: status berubah walau penyedia mati |
| 5 | Halaman admin | pengaturan, antrean, kirim ulang |
| 6 | Webhook masuk (opsional) | tanda tangan diverifikasi |

**Tahap 2 adalah gerbang:** kalau satu pesan uji tidak benar-benar terkirim ke nomor Anda, tahap 3–6 tidak ada gunanya. Semua diuji di nomor uji + data uji dulu, baru produksi.

---

## 5. Yang perlu Anda jawab

1. **Penyedia WhatsApp & API key** — pakai StarSender seperti dulu, atau lain?
2. **Peristiwa mana** yang benar-benar dikirim (§2.2) — semua, atau hanya "sertifikat siap"?
3. **Nomor pengirim** milik siapa, dan boleh dipakai untuk ini?
4. **Jam kirim** — boleh 02:30 mengikuti jadwal penutupan, atau harus pagi?

Tanpa jawaban 1, pengerjaan tidak bisa mulai. Pertanyaan 2–4 bisa menyusul sambil tahap 1 berjalan.
