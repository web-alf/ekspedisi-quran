# Item Wakaf Ganda pada Donasi Rutin — Menunggu Keputusan

Dihasilkan dari data produksi `dash.ekspedisiquran.com`. **Hanya baca** — tidak ada baris yang diubah.

## Cara membacanya

Bukan semua nomor urut kembar berarti item palsu. Patokannya adalah **angka total yang tercatat pada data donatur** (`total_a5_count` dan seterusnya) — itulah berapa item yang seharusnya ada. Nomor urut hanyalah label, jadi bisa saja salah tanpa berarti itemnya palsu.

Akibat bug lama, setiap donasi berikutnya menyalin ulang seluruh item sebelumnya, sehingga dalam satu donatur ada beberapa item berlabel nomor yang sama. Tiga akibatnya berbeda penanganan:

| | Keadaan | Arti | Tindakan | Jumlah |
|---|---|---|---|---|
| **A** | Item lebih banyak dari total tercatat | ada item **palsu** | **buang** | 58 baris / 40 donatur |
| **B** | Item sama dengan total tercatat, tapi nomornya kembar | itemnya **sah**, hanya **nomornya salah** | **nomori ulang**, jangan dibuang | 312 item / 86 donatur |
| **C** | Item kurang dari total tercatat | itemnya **kurang** | perlu ditelusuri | 1 donatur |

Angka A (**58 baris**) konsisten dengan pemeriksaan awal ketika bug ini ditemukan, sebelum baris dikelompokkan per jenis.

## Keamanan

Semua baris yang bersangkutan **belum pernah masuk alur kerja**:

| Tempat | Baris |
|---|---|
| Packing | 0 |
| Muatan | 0 |
| Riwayat status | 0 |
| Pelacakan | 0 |
| Sertifikat (seluruh sistem) | 0 |
| Permintaan mushaf | 0 |

Semua resinya masih berstatus `pemesanan` — paling awal, belum dipindai, belum dikemas. Tidak ada rujukan dari tabel lain selain timbal balik `wakaf_items.pengiriman_id` milik baris itu sendiri.

## A. Item palsu — usul dibuang

| Donatur | Nama | Jenis | Item sekarang | Total tercatat | Lebih |
|---|---|---|---|---|---|
| HMT320 | Niko Fromusi | A5 | 3 | 2 | 1 |
| RZA162 | Rukmini | A5 | 9 | 6 | 3 |
| LII66 | Abdul Latief Danu Aji | A5 | 3 | 2 | 1 |
| HQT239 | Albert Nobel | A5 | 5 | 3 | 2 |
| MVR180 | Heru Novianto | A5 | 4 | 3 | 1 |
| FBC1802 | Ilham wisnu ricky prabowo | A5 | 3 | 2 | 1 |
| RVL1593 | puji lestari | A5 | 3 | 2 | 1 |
| QRO182 | Moh Tamin | A5 | 3 | 2 | 1 |
| OMP474 | Emnurmaini | A5 | 3 | 2 | 1 |
| HMT347 | Santi Widiawati | A5 | 3 | 2 | 1 |
| DKS198 | Fathor Rosyid | A5 | 8 | 5 | 3 |
| HKQ327 | Dii Annisaa | A5 | 3 | 2 | 1 |
| GCR135 | Nuni Maulidian | A5 | 11 | 8 | 3 |
| FBC2129 | Siti Nurlaila | A5 | 5 | 3 | 2 |
| MLD39 | Suhardi mustafa | A5 | 3 | 2 | 1 |
| AFO44 | Gema | A5 | 2 | 1 | 1 |
| AFO44 | Gema | A6 | 4 | 3 | 1 |
| NAE1019 | Sumarno | A5 | 3 | 2 | 1 |
| MDI437 | Ulul Fahmi | A5 | 3 | 2 | 1 |
| MKR549 | Reiza Dzaki | A5 | 6 | 4 | 2 |
| RRH28 | Suharsono | A5 | 3 | 2 | 1 |
| OMP631 | Bp Setiawan | A5 | 3 | 2 | 1 |
| FBC2286 | Annisa Hazeera | A5 | 3 | 2 | 1 |
| FBC2328 | Yanti Susianti | A5 | 3 | 2 | 1 |
| OMP661 | Bp Dr. Rochmat Jasin | A5 | 9 | 6 | 3 |
| TSY86 | M Widiyanto | A5 | 3 | 2 | 1 |
| LJN95 | Hamba Allah | A5 | 9 | 6 | 3 |
| OMP711 | Bp Muhammad sidiq | A5 | 3 | 2 | 1 |
| FBC2547 | Ani suriani | A5 | 3 | 2 | 1 |
| HMT422 | Hendy | A5 | 5 | 3 | 2 |
| AFO54 | Muhammad Irsyad Kamal | A5 | 3 | 2 | 1 |
| RMS352 | Dien Muvita | A5 | 3 | 2 | 1 |
| TJP87 | NIKMAH | A5 | 3 | 2 | 1 |
| QRO377 | Hamba Allah | A5 | 6 | 4 | 2 |
| OMP849 | Ibu Sri Susilowati | A5 | 3 | 2 | 1 |
| IN1407 | Rafi | A5 | 3 | 2 | 1 |
| OMP877 | Bp Zakaria alkaf | A5 | 3 | 2 | 1 |
| TNU1329 | Nabila | A5 | 3 | 2 | 1 |
| ECB 81 | NADIM MUNIR | A5 | 3 | 2 | 1 |
| BTV 656 | Donny Prasetya Manginsih | A5 | 6 | 4 | 2 |
| BTV 658 | Saanah Yulia | A5 | 6 | 4 | 2 |

## B. Item sah dengan nomor kembar — usul dinomori ulang saja

Ini donasi yang benar-benar diterima. Jumlahnya sudah sesuai catatan; hanya labelnya yang salah. **Tidak boleh dibuang.**

| Donatur | Nama | Jenis | Item | Nomor berbeda | Total tercatat |
|---|---|---|---|---|---|
| HMT318 | Hamba Allah | A5 | 2 | 1 | 2 |
| RRH21 | Abdul Qolik | A5 | 4 | 2 | 4 |
| FBC1401 | Mia Prihatini | A5 | 6 | 2 | 6 |
| HQT223 | Damad | A5 | 4 | 1 | 4 |
| MKR360 | Eliza Mulyani | A5 | 4 | 2 | 4 |
| MMA8 | Novi | A5 | 2 | 1 | 2 |
| FBC1852 | Lily Meilia | A5 | 6 | 3 | 6 |
| BRQ444 | Dwi Ari Wahyuni | A5 | 10 | 5 | 10 |
| DQQ35 | Amrozi | A5 | 3 | 1 | 3 |
| TFI994 | Achmas M Diyah | A5 | 7 | 5 | 7 |
| NTE138 | Ratih Hasnoeddin | A5 | 2 | 1 | 2 |
| BOZ1298 | Wahyu hendrastomo | A5 | 5 | 3 | 5 |
| HQT318 | Bu Nurul Jamilah | A5 | 2 | 1 | 2 |
| HQT322 | Siska | A5 | 2 | 1 | 2 |
| RVL1646 | Rosana Iksan Kusuma | A5 | 2 | 1 | 2 |
| MM2008 | Ofi Eka Novyanti | A5 | 3 | 2 | 3 |
| TNU761 | Rishar | A5 | 3 | 2 | 3 |
| HQT126 | Ayu | A5 | 2 | 1 | 2 |
| AFT860 | Hendrik Cahyo | A5 | 4 | 2 | 4 |
| MM2012 | Sabir ahmad | A5 | 2 | 1 | 2 |
| OQC58 | Pramudya Noor Widiarto | A5 | 2 | 1 | 2 |
| RVL1680 | ANI DIANA | A5 | 2 | 1 | 2 |
| FBC2053 | Shinta wulandari | A5 | 2 | 1 | 2 |
| FBC2056 | Ashady | A5 | 4 | 2 | 4 |
| DFI689 | Jumadiyono | A5 | 2 | 1 | 2 |
| FJI674 | Sapto Ariyono | A5 | 3 | 1 | 3 |
| HKQ353 | Taufiq Qurrohman | A5 | 2 | 1 | 2 |
| MM2035 | hamba Allah | A5 | 2 | 1 | 2 |
| MZN166 | Lilik | A5 | 2 | 1 | 2 |
| HVJ680 | Didit septa ardiyan | A5 | 2 | 1 | 2 |
| FBC2219 | Parti Mukinah | A5 | 5 | 3 | 5 |
| MM4047 | Nurmah | A5 | 6 | 3 | 6 |
| HKQ363 | Alham Ramadhan Alfarisi | A5 | 3 | 2 | 3 |
| ASA192 | Nurkadri | A5 | 2 | 1 | 2 |
| GWL822 | Sri Suwandhari | A5 | 4 | 2 | 4 |
| HKQ369 | Gilang Purnama | A5 | 2 | 1 | 2 |
| FBC2259 | SUHAERLIN | A5 | 5 | 3 | 5 |
| MKR552 | Siti Kholija | A5 | 4 | 2 | 4 |
| VOC1832 | Rio Saputra | A5 | 6 | 3 | 6 |
| SHM1595 | Nunung N | A5 | 2 | 1 | 2 |
| DKS216 | Herry lswanto | A5 | 4 | 2 | 4 |
| RZA260 | Agung semiharto | A5 | 6 | 3 | 6 |
| MMA58 | Robi Arga Prawira | A5 | 2 | 1 | 2 |
| SAF340 | Ariyani | A5 | 2 | 1 | 2 |
| TNU903 | Novi Andriani | A5 | 4 | 2 | 4 |
| TNU917 | Selvi Rizani | A5 | 2 | 1 | 2 |
| FBC2347 | Muhammad Rio | A5 | 2 | 1 | 2 |
| MM2068 | Masrokhan | A5 | 2 | 1 | 2 |
| MZL174 | Nissa | A5 | 2 | 1 | 2 |
| HQT384 | Bu Dewi | A5 | 7 | 5 | 7 |
| FBC2404 | Diana | A5 | 2 | 1 | 2 |
| NVL72 | Muhammad Husain Lovagnes | A5 | 2 | 1 | 2 |
| TNU968 | Maryati | A5 | 2 | 1 | 2 |
| RVL1863 | Giyono | A5 | 4 | 2 | 4 |
| RVL1864 | Hamba Allah | A5 | 2 | 1 | 2 |
| OMP668 | Esje | A5 | 2 | 1 | 2 |
| ASC578 | Iwan beni | A5 | 2 | 1 | 2 |
| ASC582 | Hifzul fikri | A5 | 18 | 15 | 18 |
| ECB41 | megananda HP | A5 | 8 | 5 | 8 |
| ISF151 | Andri Murbianto | A5 | 6 | 3 | 6 |
| SVF434 | Hamba Allah | A5 | 2 | 1 | 2 |
| TNU1015 | Hamba Allah | A5 | 2 | 1 | 2 |
| TQI110 | Bapak Jijim | A5 | 6 | 3 | 6 |
| AFT556 | Fahri | A5 | 2 | 1 | 2 |
| BOZ1463 | Hermawan | A6 | 4 | 2 | 4 |
| LGO2016 | Hari yanto | A5 | 4 | 2 | 4 |
| TNU1118 | Djamaludin | A5 | 3 | 1 | 3 |
| VEG1130 | Khasanuri | A5 | 4 | 2 | 4 |
| BOZ1489 | SUWARSIH | A5 | 10 | 5 | 10 |
| NTE311 | Nulias | A5 | 4 | 2 | 4 |
| HQT410 | Heny | A5 | 2 | 1 | 2 |
| MFF100 | Yono basuki hariyanto | A5 | 3 | 2 | 3 |
| ASC635 | arliansyah achik | A5 | 2 | 1 | 2 |
| HKQ247 | Teguh Wijanarko | A5 | 2 | 1 | 2 |
| TJP78 | Farista Lucy Nanda Kusuma | A5 | 2 | 1 | 2 |
| LGO2089 | heri | A5 | 2 | 1 | 2 |
| SBQ41 | Samsul Hadi | A5 | 4 | 2 | 4 |
| LII99 | Falid Lazuardy | A5 | 2 | 1 | 2 |
| ISF192 | Sulastri | A5 | 10 | 5 | 10 |
| GCR191 | E Bastian | A5 | 2 | 1 | 2 |
| LGO2152 | Sri Haryanti | A5 | 6 | 1 | 6 |
| LGO2193 | Sugiyono | A5 | 2 | 1 | 2 |
| MDI498 | Annisah Sitompul | A5 | 2 | 1 | 2 |
| LGO2245 | Umar | A5 | 7 | 6 | 7 |
| LGO2250 | Hamba Allah | A5 | 3 | 1 | 3 |
| TRJ322 | Noorida UmmJA | A5 | 2 | 1 | 2 |

## C. Item kurang — perlu ditelusuri

| Donatur | Nama | Jenis | Item sekarang | Total tercatat |
|---|---|---|---|---|
| CAI122 | sjaechu | A5 | 2 | 3 |
