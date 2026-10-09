<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Critical scheduled tasks for warehouse automation
Schedule::command('packing:daily-assignment')->dailyAt('06:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/packing-assignment.log'));

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

Schedule::command('cache:clear')->weekly();
Schedule::command('optimize:clear')->weekly();
