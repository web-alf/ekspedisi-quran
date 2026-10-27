<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Penugasan packing harian OTOMATIS DIHAPUS (keputusan pemilik, 27/10/2026).
//
// Pekerjaan sekarang dibagi manusia, bukan cron: PJ (manager gudang) memberi beban kerja
// ke SPV gudangnya, lalu SPV menugasi freelancer-nya dari halaman Monitor & Penugasan.
// Cron pukul 06:00 dulu menugasi semua pemegang izin memindai — termasuk akun PJ/SPV —
// sehingga target gudang menggelembung oleh tugas yang tak dikerjakan sebagai pekerja
// packing, dan SPV kehilangan kendali atas pembagian kerja.
//
// Perintah `php artisan packing:daily-assignment` SENGAJA tetap ada untuk dijalankan
// manual bila suatu hari dibutuhkan (mis. menyiapkan data uji), tetapi tidak lagi
// dijadwalkan.

Schedule::command('packing:expire-tasks')->dailyAt('23:59')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/packing-expire.log'));

// Batas akhir jendela HARUS '17:59', bukan '17:00'.
// between('08:00', '17:00') hanya mencakup sampai 17:00:00.000, sementara cron
// memanggil schedule:run beberapa ratus milidetik SETELAH batas menit itu —
// jadi run pukul 17:00 selalu dilewati dan checkpoint 5pm (level critical,
// target 80%) tidak pernah dikirim. Terbukti di produksi: log berhenti di 16:00
// dan hanya checkpoint 10am/12pm/3pm yang pernah terbuat.
Schedule::command('packing:check-progress')->hourly()->between('08:00', '17:59')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/packing-progress.log'));

// Rapikan notifikasi packing lama supaya lonceng tidak menumpuk.
Schedule::command('packing:prune-notifications')->dailyAt('23:45')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/packing-prune.log'));

// Performance commands will be auto-discovered by Laravel

// Performance monitoring scheduled tasks
Schedule::command('performance:benchmark --output=json --iterations=3')
    ->weekly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/performance-benchmark.log'));

Schedule::command('performance:check-budgets --output=json')
    ->daily()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/performance-budgets.log'));

// Tutup tahap akhir distribusi: batch yang seluruh resinya sudah diterima
// ditandai selesai dan catatan sertifikatnya dibuat. Tanpa ini alur berhenti di
// "diterima" — lihat TutupPengirimanSelesaiCommand untuk alasan kenapa ini
// perintah terjadwal, bukan hook saat status berubah.
Schedule::command('wakaf:tutup-pengiriman')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/wakaf-tutup.log'));

// Kuras notifikasi WhatsApp yang tertahan: yang sengaja ditahan karena jam
// tenang, dan yang jobnya hilang (worker mati sebelum sempat jalan). Tiap jam
// supaya pesan yang ditahan pukul 02:30 tetap terkirim pagi itu juga, begitu
// jam tenangnya berakhir.
Schedule::command('whatsapp:kirim-tertunda')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/whatsapp-tertunda.log'));

Schedule::command('cache:clear')->weekly();
Schedule::command('optimize:clear')->weekly();
