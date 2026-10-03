# Template import Permintaan Mushaf disamakan dengan field "Informasi Lembaga"

**Tanggal:** 2026-10-03
**Commit:** `1ed0a99`
**Status:** sudah di-push dan di-deploy ke `dash.ekspedisiquran.com`, terverifikasi di produksi

## Masalahnya

Template import hanya memuat satu kolom teks `alamat_lengkap`. Sementara kartu
**"Informasi Lembaga"** pada halaman detail memecah alamat menjadi provinsi /
kota-kabupaten / kecamatan / kelurahan / kode pos / detail alamat, dan
**mewajibkan** latitude + longitude.

Akibatnya rantainya begini: berkas diimpor → seluruh kolom wilayah kosong →
koordinat kosong → form "Informasi Lembaga" tidak bisa disimpan sama sekali.
Kolom lain yang tidak ada di template pun tidak akan pernah terisi
(`kategori_lembaga` selalu jatuh ke "Lembaga Lainnya", `sumber_info` selalu
"Import Excel", `urgensi_request` selalu hasil terkaan).

## Template baru: 20 kolom

Dipetakan ke kolom tabel yang benar-benar ada (kolom yang berbeda nama
dipetakan oleh pengimpor):

| Kolom template | Kolom tabel |
|---|---|
| `nama_lembaga`, `kategori_lembaga`, `provinsi`, `kota_kabupaten`, `kecamatan`, `kelurahan_desa`, `kode_pos`, `alamat_detail`, `alamat_lengkap`, `latitude`, `longitude`, `link_gmaps`, `jumlah_mushaf_a5`, `jumlah_mushaf_a6`, `jumlah_iqra`, `sumber_info` | sama |
| `nama_penanggung_jawab_1` | `nama_pengurus_1` |
| `nomor_hp` | `whatsapp_pengurus_1` |
| `jabatan_penanggung_jawab_1` | `jabatan_pengurus_1` |
| `urgensi` | `urgensi_request` |

Keputusan yang diambil:

- **Alamat dipecah per kolom**, plus tetap ada `alamat_lengkap` untuk yang hanya
  punya satu baris alamat. Kolom wilayah yang dikosongkan diuraikan otomatis
  dari `link_gmaps`; yang diisi langsung dipakai apa adanya.
- **Jumlah dipecah per jenis** (`jumlah_mushaf_a5` / `a6` / `iqra`) — satu kolom
  angka per jenis, sehingga pengisi tidak perlu tahu format penulisan dan
  **"50 A5, 30 A6" tidak lagi terbaca sebagai 50**.
- **Koordinat boleh ditulis langsung** tanpa perlu tautan peta.
- **Lembar kedua "Panduan Kolom"** menjelaskan tiap kolom (wajib/tidak, contoh).
  Pengimpor dibatasi pada lembar pertama; tanpa itu lembar panduan terbaca
  sebagai data dan satu berkas template menghasilkan **40 baris gagal palsu**.
- **Baris contoh memakai nama lembaga fiktif.** Tiga baris sebelumnya adalah data
  pemohon **sungguhan** — nama lembaga, alamat, dan nomor HP lengkap serta masih
  aktif (`REQ-2026-00025` s/d `00027`) — sehingga template yang diunduh dan
  diedarkan berisi data pribadi orang lain.

## Cacat lain yang ikut diperbaiki

1. **`urgensi_request` diisi TINGKAT hasil terkaan**, bukan deskripsi dari berkas.
   Tiga permintaan hasil import tercatat hanya sebagai `"sedang"`. Kini
   deskripsinya disimpan apa adanya — sama seperti yang diisi lewat halaman publik.
2. **Baris tanpa jumlah sama sekali tetap masuk** dengan nilai nol dan tanpa
   keluhan apa pun. Kini ditolak dengan pesan yang jelas.
3. **`alamat_lengkap` diakses tanpa `??`** padahal kolomnya kini opsional →
   `Undefined array key "alamat_lengkap"` menggagalkan seluruh baris.
4. **Kolom wilayah yang diisi di berkas ditimpa** hasil penguraian otomatis.
5. **Baris yang sudah lengkap tidak lagi memanggil layanan peta** sama sekali.

**Berkas format lama tetap bisa diimpor** — kolom teks `jumlah_kebutuhan_mushaf`
dan `alamat_lengkap` dibaca sebagai cadangan, karena berkas yang sudah beredar
tidak bisa ditarik kembali.

## Verifikasi produksi (permintaan HTTP nyata, di dalam transaksi yang dibatalkan)

```
Template diunduh  : status 200, application/vnd.openxmlformats-...sheet, 9.698 byte
Lembar            : [0] Data  <- dibaca saat import
                    [1] Panduan Kolom  <- tidak dibaca
Dibuka pada       : Data
20 kolom          : nama_lembaga … sumber_info
Panduan           : 20 kolom dijelaskan

Data pemohon di dalam template: TIDAK ADA

Impor template    : berhasil 1, gagal 0
  provinsi        = Jawa Timur
  kota_kabupaten  = Kabupaten Blitar
  kecamatan       = Garum
  kelurahan_desa  = Contoh Kelurahan
  latitude/longitude terisi: YA / YA
  kategori_lembaga= TPQ/TPA/Madin
  sumber_info     = WhatsApp
  jumlah a5/a6/iqra = 100 / 0 / 0, jenis [A5]

baris sebelum uji: 34  sesudah rollback: 34   (tidak ada yang tertulis)
```

## Uji

| Berkas | Hasil |
|---|---|
| `TemplateSesuaiInformasiLembagaTest` (13 tes, baru) | lulus · bukti-gagal 3/13 dan 1/13 |
| `MushafAddressAutoFillTest` (14) | lulus, termasuk penjagaan anti-data-pribadi |
| `MushafRequestImportTest` (25) | lulus |
| `DianosaImportGagalSebagianTest` (4) | lulus · bukti-gagal 2/4 |
| `UmpanBalikImportTest` (3) | lulus · bukti-gagal 2/3 |
| **Total `tests/Feature/Imports/`** | **65 lulus (301 assertion)** |
| Vitest | 89 lulus |

## Belum selesai

- **5 permintaan lama (`REQ-2026-00025` … `00029`) belum berubah.** Perbaikan ini
  hanya berlaku untuk import berikutnya; baris lama tetap tanpa kolom wilayah dan
  tanpa koordinat, sehingga "Informasi Lembaga"-nya masih harus diisi manual.
- Sumber data wilayah (`emsifa.com`) belum lengkap ("Bence", "Tanggung" tidak ada;
  "Slorok" ada di dua kecamatan), sehingga tingkat yang meragukan sengaja
  dibiarkan kosong.
