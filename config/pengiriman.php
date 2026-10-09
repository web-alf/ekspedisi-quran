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
        'diterima',
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
    | Tahap awal distribusi — Pemesanan, Produksi, Kedatangan/Penurunan, dan
    | Packing — adalah ranah gudang: mereka yang menerima kiriman mushaf,
    | memantau produksi, mencatat penurunan, dan mengerjakan packing. Karena itu
    | hanya role `warehouse` yang boleh memindahkan status ke tahap-tahap ini.
    |
    | Mengapa `packing` ikut ada: alur packing dimulai dari resi yang SUDAH
    | berstatus packing (PackingAssignmentService::assignPengirimanOnScan
    | menolak resi yang belum packing), jadi tanpa `packing` di daftar ini gudang
    | tidak akan pernah bisa memulai pekerjaan packing-nya sendiri dan tiap
    | pemindahan tetap harus lewat super-admin.
    |
    | Yang TIDAK termasuk: pengiriman, diterima, batal, dan selesai-packing.
    | Selesai-packing dihasilkan otomatis saat kerdus disegel (bukan pilihan
    | manual); pengiriman/diterima/batal adalah wewenang kurir, manager, dan
    | role distribusi.
    |
    | Batas ini berlaku MUTLAK untuk gudang — bukan sekadar pilihan yang
    | disembunyikan. Semua jalur pemindahan status (ubah status, batch, bulk,
    | form edit, scan QR gudang) menolak tahap di luar daftar ini dan menolak
    | memindahkan resi yang sudah lewat tahap awal, walau ID-nya dikirim
    | langsung. Tanpa batas itu, izin `shipments.update-status` yang mereka
    | pegang akan membuka pengiriman/diterima lewat endpoint yang tidak dijaga.
    |
    | Mengosongkan daftar = gudang tidak bisa memindahkan status apa pun
    | (gagal-tertutup), bukan "boleh semua".
    |
    */

    'warehouse_stage_slugs' => [
        'pemesanan',
        'produksi',
        'kedatangan',
        'packing',
    ],

];
