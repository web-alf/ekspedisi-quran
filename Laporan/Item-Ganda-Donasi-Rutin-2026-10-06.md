# Item Wakaf Berlebih pada Donasi Rutin — Rencana Terverifikasi

Dari data produksi `dash.ekspedisiquran.com`. **Hanya baca** — tidak ada baris yang diubah.

## Ringkasan

| Hal | Jumlah |
|---|---|
| Baris item berlebih (akan dibuang) | **68** |
| Kasus (pasangan donatur + jenis) | 51 |
| Resi yang ikut terbuang | 68 (semuanya status `pemesanan`) |
| Nomor urut kembar sebelum perbaikan | 191 kelompok |
| Donatur yang kolom totalnya disetel | 60 kombinasi |
| Item yang dinomori ulang | semua item tersisa, tanpa menghapus |

## Kenapa angkanya bukan 206

Aturan pertama saya salah: ia hanya membuang nomor urut yang kembar. Padahal ada **10 baris** yang nomornya sudah berbeda semua tetapi jumlahnya tetap melebihi catatan — baris itu lolos dari pemeriksaan awal. Setelah aturannya dibetulkan menjadi *"pertahankan sebanyak angka catatan, mulai dari yang paling awal dibuat"*, hasilnya 68 baris dan **tidak ada satu pun yang tersisa melebihi catatan**.

Angka 206 adalah jumlah baris yang nomornya kembar — termasuk **138 baris donasi yang sah** yang hanya salah nomor. Kalau 206 yang dibuang, 138 donasi nyata ikut terhapus.

## Keamanan (sudah diperiksa)

| Tempat | Baris |
|---|---|
| Packing | 0 |
| Muatan | 0 |
| Riwayat status | 0 |
| Pelacakan | 0 |
| Sertifikat (seluruh sistem) | 0 |
| Permintaan mushaf | 0 |

Simulasi langkah 1–4 dijalankan penuh di memori, tanpa menulis apa pun. Hasilnya: sisa baris yang melebihi catatan = 0, nomor kembar = 0, dan kunci unik bisa dipasang.

## Daftar baris yang akan dibuang

| Donatur | Nama | Jenis | Punya | Catatan | Lebih | Mode | Simpan (id) | Buang (id) |
|---|---|---|---|---|---|---|---|---|
| HMT319 | Rika Aulia J | A5 | 2 | 1 | 1 | customize_individual | 370 | 998 |
| HMT320 | Niko Fromusi | A5 | 3 | 2 | 1 | semua_donatur | 388,3833 | 3834 |
| RZA162 | Rukmini | A5 | 9 | 6 | 3 | semua_donatur | 644,645,646,947,948,949 | 950,951,952 |
| LII66 | Abdul Latief Danu Aji | A5 | 3 | 2 | 1 | semua_donatur | 1066,1140 | 1141 |
| HQT239 | Albert Nobel | A5 | 5 | 3 | 2 | semua_donatur | 1425,9441,18582 | 18583,18584 |
| MVR180 | Heru Novianto | A5 | 4 | 3 | 1 | semua_donatur | 1811,9596,9597 | 9598 |
| VOC2120 | Nahrawi Entengo | A5 | 2 | 1 | 1 | customize_individual | 1850 | 2171 |
| FBC1802 | Ilham wisnu ricky prabowo | A5 | 3 | 2 | 1 | semua_donatur | 1851,10602 | 10603 |
| SVF312 | Raden Kris Nugroho | A5 | 2 | 1 | 1 | customize_individual | 2142 | 5607 |
| LGO1659 | Lili Haryanti | A5 | 2 | 1 | 1 | customize_individual | 2156 | 3425 |
| IPG154 | Kiki nadya | A6 | 1 | 0 | 1 | semua_donatur |  | 2321 |
| RVL1593 | puji lestari | A5 | 3 | 2 | 1 | semua_donatur | 2398,2609 | 2610 |
| QRO182 | Moh Tamin | A5 | 3 | 2 | 1 | semua_donatur | 2598,3596 | 3597 |
| OMP474 | Emnurmaini | A5 | 3 | 2 | 1 | semua_donatur | 3450,3706 | 3707 |
| ASA166 | Iradi Tauzuri | A5 | 2 | 1 | 1 | semua_donatur | 4036 | 6553 |
| HMT347 | Santi Widiawati | A5 | 3 | 2 | 1 | semua_donatur | 4842,21312 | 21313 |
| DKS198 | Fathor Rosyid | A5 | 8 | 5 | 3 | semua_donatur | 4864,4865,4866,20560,20561 | 20562,20563,20564 |
| HKQ327 | Dii Annisaa | A5 | 3 | 2 | 1 | semua_donatur | 5812,6647 | 6648 |
| GCR135 | Nuni Maulidian | A5 | 11 | 8 | 3 | semua_donatur | 5944,5945,5946,8696,8697,8698,8699,8700 | 8701,8702,8703 |
| HMT360 | Dino Dasril | A5 | 2 | 1 | 1 | semua_donatur | 6599 | 6730 |
| FBC2129 | Siti Nurlaila | A5 | 5 | 3 | 2 | semua_donatur | 7136,7137,23504 | 23505,23506 |
| MLD39 | Suhardi mustafa | A5 | 3 | 2 | 1 | semua_donatur | 7923,10573 | 10574 |
| AFO44 | Gema | A5 | 2 | 1 | 1 | customize_individual | 8317 | 8335 |
| NAE1019 | Sumarno | A5 | 3 | 2 | 1 | semua_donatur | 8318,8319 | 8320 |
| AFO44 | Gema | A6 | 4 | 3 | 1 | customize_individual | 8332,8336,8337 | 8339 |
| MDI437 | Ulul Fahmi | A5 | 3 | 2 | 1 | semua_donatur | 9456,9458 | 9459 |
| MKR549 | Reiza Dzaki | A5 | 6 | 4 | 2 | semua_donatur | 9559,9560,18146,18147 | 18148,18149 |
| RRH28 | Suharsono | A5 | 3 | 2 | 1 | semua_donatur | 9727,18274 | 18275 |
| OMP631 | Bp Setiawan | A5 | 3 | 2 | 1 | semua_donatur | 9916,9924 | 9925 |
| FBC2286 | Annisa Hazeera | A5 | 3 | 2 | 1 | semua_donatur | 10024,10029 | 10030 |
| GWS85 | Fuad Teguh | A5 | 2 | 1 | 1 | customize_individual | 10108 | 11735 |
| FBC2328 | Yanti Susianti | A5 | 3 | 2 | 1 | semua_donatur | 10423,18891 | 18892 |
| OMP661 | Bp Dr. Rochmat Jasin | A5 | 9 | 6 | 3 | semua_donatur | 10837,10838,10839,13957,13958,13959 | 13960,13961,13962 |
| JM134 | Karsidi | A5 | 2 | 1 | 1 | customize_individual | 11339 | 12055 |
| TSY86 | M Widiyanto | A5 | 3 | 2 | 1 | semua_donatur | 12249,12815 | 12816 |
| LJN95 | Hamba Allah | A5 | 9 | 6 | 3 | semua_donatur | 12250,12251,12252,12817,12818,12819 | 12820,12821,12822 |
| JM150 | Aah Jamilah | A5 | 2 | 1 | 1 | customize_individual | 13700 | 14484 |
| OMP711 | Bp Muhammad sidiq | A5 | 3 | 2 | 1 | semua_donatur | 14128,14129 | 14130 |
| FBC2547 | Ani suriani | A5 | 3 | 2 | 1 | semua_donatur | 14232,14233 | 14234 |
| HMT422 | Hendy | A5 | 5 | 3 | 2 | semua_donatur | 14252,14253,25226 | 25227,25228 |
| AFO54 | Muhammad Irsyad Kamal | A5 | 3 | 2 | 1 | semua_donatur | 17413,17414 | 17415 |
| RMS352 | Dien Muvita | A5 | 3 | 2 | 1 | semua_donatur | 19433,19435 | 19436 |
| TJP87 | NIKMAH | A5 | 3 | 2 | 1 | semua_donatur | 19804,22379 | 22380 |
| QRO377 | Hamba Allah | A5 | 6 | 4 | 2 | semua_donatur | 19906,19907,19911,19912 | 19913,19914 |
| OMP849 | Ibu Sri Susilowati | A5 | 3 | 2 | 1 | semua_donatur | 20160,20161 | 20162 |
| IN1407 | Rafi | A5 | 3 | 2 | 1 | semua_donatur | 22099,22100 | 22101 |
| OMP877 | Bp Zakaria alkaf | A5 | 3 | 2 | 1 | semua_donatur | 22194,22243 | 22244 |
| TNU1329 | Nabila | A5 | 3 | 2 | 1 | semua_donatur | 22259,22819 | 22820 |
| ECB 81 | NADIM MUNIR | A5 | 3 | 2 | 1 | semua_donatur | 24639,25137 | 25138 |
| BTV 656 | Donny Prasetya Manginsih | A5 | 6 | 4 | 2 | semua_donatur | 24645,24646,25139,25140 | 25141,25142 |
| BTV 658 | Saanah Yulia | A5 | 6 | 4 | 2 | semua_donatur | 24648,24649,25145,25146 | 25147,25148 |

## Butuh keputusan: item yang justru KURANG

Ini kebalikannya — jumlahnya kurang dari catatan, jadi bukan kasus yang dibuang. Perlu keputusan Anda.

| Donatur | Nama | Jenis | Punya | Catatan |
|---|---|---|---|---|
| BOZ1246 | Hamba Allah | A5 | 1 | 2 |
| CAI122 | sjaechu | A5 | 2 | 3 |
| AYA26 | Sentot Sunarso | A5 | 5 | 10 |
| RZA337 | Amad dawam | A5 | 1 | 2 |
| ASC622 | Muhammad Nuruddin Utomo | A5 | 1 | 2 |
| MYJ37 | M. Hudan | A5 | 2 | 4 |
| CFW12 | Cicin Lenia | A5 | 1 | 2 |
| RZA338 | Arifprasetiyo | A5 | 1 | 2 |
| BTV 657 | Tonny | A5 | 1 | 2 |
