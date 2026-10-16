<?php

namespace App\Providers;

use App\Listeners\LogQueueJobFailure;
use App\Models\CertificateTemplate;
use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\MushafRequest;
use App\Models\Pengiriman;
use App\Models\Sertifikat;
use App\Models\StatusHistory;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Observers\CacheInvalidationObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\JobFailed;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        JobFailed::class => [
            LogQueueJobFailure::class,
        ],
    ];

    /**
     * The model observers to register.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $observers = [
        Pengiriman::class => [CacheInvalidationObserver::class],
        Donatur::class => [CacheInvalidationObserver::class],
        MushafRequest::class => [CacheInvalidationObserver::class],
        StatusPengiriman::class => [CacheInvalidationObserver::class],
        JenisQuran::class => [CacheInvalidationObserver::class],
        CertificateTemplate::class => [CacheInvalidationObserver::class],
        User::class => [CacheInvalidationObserver::class],
        Sertifikat::class => [CacheInvalidationObserver::class],
        StatusHistory::class => [CacheInvalidationObserver::class],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Register model observers
        foreach ($this->observers as $model => $observers) {
            foreach ($observers as $observer) {
                $model::observe($observer);
            }
        }
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
