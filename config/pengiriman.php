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

];
