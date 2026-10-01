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

];
