<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tahap pengiriman yang boleh dilihat role manager
    |--------------------------------------------------------------------------
    |
    | Role manager (mis. Nalurita Firdausyah) hanya menangani pengiriman yang
    | SUDAH selesai dikerjakan gudang: Selesai Packing, Proses Pengiriman, dan
    | Diterima Penerima. Pengiriman yang masih di tahap awal (Proses Pemesanan,
    | Proses Produksi, Proses Kedatangan/Penurunan, Proses Packing) adalah ranah
    | gudang/kustomer servis dan TIDAK boleh muncul di halaman Pengiriman mereka.
    |
    | Pembatasan ini ditegakkan di sisi server (query + penjagaan per-baris),
    | bukan sekadar disembunyikan di tampilan: URL langsung, pencarian, ekspor,
    | dan penomoran baris semuanya tunduk pada daftar ini.
    |
    | Mengosongkan daftar = manager tidak melihat apa pun (gagal-tertutup).
    | Menghapus role manager dari sini (lihat PengirimanStageVisibility) =
    | tanpa pembatasan.
    |
    */

    'manager_visible_stages' => [
        'selesai-packing',
        'pengiriman',
        // Kurir sudah menyerahkan di lokasi; manager yang mengonfirmasi "Diterima",
        // jadi tahap ini wajib terlihat olehnya.
        'tiba',
        'diterima',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tahap yang tampil di riwayat PUBLIK (halaman lacak resi & API lacak)
    |--------------------------------------------------------------------------
    |
    | Riwayat publik hanya memuat tahap penting, satu entri per tahap, tanpa catatan
    | petugas dan nama petugas (lihat App\Services\RiwayatPublikService). Tahap kerja
    | internal — Kedatangan/Penurunan dan Antrian — sengaja tidak dimasukkan, demikian
    | pula `produksi`: status itu dinonaktifkan 27/10/2026 atas keputusan pemilik
    | (proses cetak di luar gudang, tidak pernah dipakai di alur nyata).
    |
    */

    'riwayat_publik_tahap' => [
        'pemesanan',
        'packing',
        'selesai-packing',
        'pengiriman',
        'tiba',
        'diterima',
        'batal',
    ],

    /*
    |--------------------------------------------------------------------------
    | Status yang boleh dipilih KURIR saat memindahkan status
    |--------------------------------------------------------------------------
    |
    | Ini BATAS PILIHAN, bukan batas data — bedanya penting.
    |
    | Kurir tetap perlu MELIHAT resi lintas status di halaman Pengiriman (ada yang
    | masih pemesanan, packing, selesai packing); membatasi daftarnya akan membuat
    | pekerjaan mereka tidak bisa dilihat sama sekali. Yang dibatasi adalah status
    | yang boleh mereka PILIH. Karena itu daftar ini dipakai oleh
    | PengirimanStageVisibility::visibleProgressStatuses() dan
    | MuatanController::statusPerjalanan(), dan SENGAJA tidak dipakai oleh
    | applyToQuery()/isVisible().
    |
    | Isinya: kurir mengantar barang dan mengabarkan PERJALANANNYA. Tahap gudang
    | (pemesanan, produksi, kedatangan, packing, selesai packing) bukan wewenang
    | mereka, dan "Diterima Penerima" punya jalur tersendiri yang hanya boleh
    | diselesaikan role distribusi/manager (lihat MuatanController::
    | selesaikanDistribusi). "Batal" selalu ikut: kurir yang menemukan alamat tidak
    | ada atau penerima menolak harus bisa mengabarkannya.
    |
    | Sebelum ini daftar tersebut tidak dibatasi sama sekali — kurir melihat 8
    | status, termasuk seluruh tahap gudang.
    |
    | Mengosongkan daftar = kurir tidak bisa memindahkan status apa pun
    | (gagal-tertutup), bukan "boleh semua".
    |
    */

    'courier_progress_stages' => [
        'pengiriman',
        'batal',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tahap yang boleh dipindahkan STAFF GUDANG
    |--------------------------------------------------------------------------
    |
    | Rombakan 2026-10-13 (keputusan pemilik sistem): tahap gudang kini
    | Pemesanan → Antrian → Packing → Selesai Packing.
    |
    | - `antrian` = status BARU: mushaf sudah di gudang, mengantre packing.
    | - `produksi` KELUAR dari ranah gudang (proses cetak di luar gudang; status
    |   master tetap ada untuk kebutuhan lain).
    | - `kedatangan` DIHAPUS dari alur (status master dinonaktifkan — lihat
    |   StatusPengiriman::getDefaultStatuses()); mushaf datang langsung masuk
    |   Antrian.
    |
    | Mengapa `packing` ikut ada: alur packing dimulai dari resi yang SUDAH
    | berstatus packing (PackingAssignmentService::assignPengirimanOnScan
    | menolak resi yang belum packing), jadi tanpa `packing` di daftar ini gudang
    | tidak akan pernah bisa memulai pekerjaan packing-nya sendiri dan tiap
    | pemindahan tetap harus lewat super-admin.
    |
    | Yang TIDAK termasuk: pengiriman, diterima, dan batal — semuanya wewenang
    | kurir, manager, dan role distribusi.
    |
    | `selesai-packing` TERMASUK sebagai tahap pamungkas gudang: itulah titik
    | serah-terima ke distribusi. MuatanController menganggap resi pada
    | `selesai-packing`/`pengiriman` sebagai "siap dimuat", dan manager hanya
    | melihat tahap akhir — jadi tanpa status ini gudang tidak punya cara
    | menyatakan bahwa packing-nya rampung, dan distribusi tidak pernah melihat
    | datanya.
    |
    | Perpindahan di DALAM keempat tahap ini dibebaskan MAJU dan MUNDUR (lihat
    | PengirimanStageVisibility::bebasDalamTahapGudang() yang dipakai kedua
    | validator transisi) — pengguna bisa salah memindah dan harus bisa
    | mengembalikannya tanpa meminta admin.
    |
    | Dua jalur sampai ke `selesai-packing`, keduanya sah:
    |   1. OTOMATIS saat kerdus disegel (`PackingBox::seal()` →
    |      advancePackedShipmentsToReady()), dan
    |   2. MANUAL oleh gudang lewat ubah status (bulk/per-resi), untuk pekerjaan
    |      yang tidak lewat fitur kerdus.
    |
    | Mengosongkan daftar = gudang tidak bisa memindahkan status apa pun
    | (gagal-tertutup), bukan "boleh semua".
    |
    */

    'warehouse_stage_slugs' => [
        'pemesanan',
        'antrian',
        'packing',
        'selesai-packing',
    ],

    /*
    |--------------------------------------------------------------------------
    | Jenis mushaf yang punya varian terjemahan (stok tanpa muwakif)
    |--------------------------------------------------------------------------
    |
    | Temuan lapangan 19/10/2026: A5/A6 dicetak dengan dan tanpa terjemahan, jadi
    | stoknya dua tumpukan terpisah dan varian WAJIB dipilih di setiap pergerakan.
    | Jenis lain (IQRA/IQRO) tidak punya varian: form stok dan transfer tidak
    | menawarkan pilihannya, dan server selalu menyimpannya sebagai "tanpa
    | terjemahan" supaya stoknya tidak pernah terpecah dua.
    |
    */

    'stok_varian_jenis' => ['A5', 'A6'],

    /*
    |--------------------------------------------------------------------------
    | Jenis mushaf yang memakai label resi kecil 9 × 13 cm
    |--------------------------------------------------------------------------
    |
    | Ukuran sampul diukur pemilik 28/10/2026: A5 21,4 × 14,8 cm, A6 14 × 10 cm.
    | Resi 10 × 15 cm muat di A5 tetapi tidak di A6, jadi jenis di sini dicetak
    | dengan label 9 × 13 cm (App\Enums\UkuranLabelResi). Jenis lain tetap
    | 10 × 15 cm. Cetak massal campuran dipisah per ukuran karena printer thermal
    | hanya memuat satu ukuran rol.
    |
    */

    'jenis_label_resi_kecil' => ['A6'],

];
