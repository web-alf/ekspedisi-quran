<?php

namespace App\Providers;

use App\Enums\PermissionEnum;
use App\Models\CertificateTemplate;
use App\Models\Donatur;
use App\Models\Faq;
use App\Models\Gallery;
use App\Models\MushafRequest;
use App\Models\Pengiriman;
use App\Models\Sertifikat;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Video;
use App\Observers\PengirimanObserver;
use App\Policies\CertificateTemplatePolicy;
use App\Policies\DonaturPolicy;
use App\Policies\FaqPolicy;
use App\Policies\GalleryPolicy;
use App\Policies\MushafRequestPolicy;
use App\Policies\PengirimanPolicy;
use App\Policies\SertifikatPolicy;
use App\Policies\TestimonialPolicy;
use App\Policies\VideoPolicy;
use App\Policies\WarehousePolicy;
use App\Services\Cache\StatusPengirimanCache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 🚀 Load No Resi Helper
        if (file_exists(app_path('Helpers/no_resi_helper.php'))) {
            require_once app_path('Helpers/no_resi_helper.php');

            // Log helper loading only if we're not in console or running tests
            if (! app()->runningInConsole() && ! app()->runningUnitTests()) {
                logNoResiHelper();
            }
        }

        // ⚡ Warm up StatusPengiriman cache untuk eliminasi N+1 queries
        // Skip during unit tests (tables may not exist yet) and only run in non-console or when explicitly needed
        if (! app()->runningInConsole() && ! app()->runningUnitTests()) {
            StatusPengirimanCache::warmUp();
        }

        // 🛡️ Configure Rate Limiters for API Protection
        $this->configureRateLimiters();

        // 🤖 Register Observers
        Pengiriman::observe(PengirimanObserver::class);

        // 🔐 Register Policies
        Gate::policy(CertificateTemplate::class, CertificateTemplatePolicy::class);
        Gate::policy(Donatur::class, DonaturPolicy::class);
        Gate::policy(Faq::class, FaqPolicy::class);
        Gate::policy(Gallery::class, GalleryPolicy::class);
        Gate::policy(MushafRequest::class, MushafRequestPolicy::class);
        Gate::policy(Pengiriman::class, PengirimanPolicy::class);
        Gate::policy(Sertifikat::class, SertifikatPolicy::class);
        Gate::policy(Testimonial::class, TestimonialPolicy::class);
        Gate::policy(Video::class, VideoPolicy::class);

        // 🔐 Register Warehouse Security Gates
        Gate::define('accessWarehouse', [WarehousePolicy::class, 'accessWarehouse']);
        Gate::define('viewPacking', [WarehousePolicy::class, 'viewPacking']);
        Gate::define('scanItems', [WarehousePolicy::class, 'scanItems']);
        Gate::define('viewScanner', [WarehousePolicy::class, 'viewScanner']);
        Gate::define('uploadDocumentation', [WarehousePolicy::class, 'uploadDocumentation']);
        Gate::define('viewOwnHistory', [WarehousePolicy::class, 'viewOwnHistory']);
        Gate::define('viewAllHistory', [WarehousePolicy::class, 'viewAllHistory']);
        Gate::define('assignTasks', [WarehousePolicy::class, 'assignTasks']);
        Gate::define('viewJobMonitor', fn (User $user): bool => $user->can(PermissionEnum::WAREHOUSE_TASKS_VIEW->value));
        Gate::define('viewWarehouseStats', fn (User $user): bool => $user->can(PermissionEnum::WAREHOUSE_PERFORMANCE_VIEW->value));
        Gate::define('manageWarehouse', fn (User $user): bool => $user->can(PermissionEnum::SUPERVISOR_WAREHOUSE_MONITOR->value) || $user->can(PermissionEnum::WAREHOUSE_BOX_UPDATE_ANY->value));

        // 🔐 Model-specific warehouse policies
        Gate::define('accessBox', [WarehousePolicy::class, 'accessBox']);
        Gate::define('updateBoxStatus', [WarehousePolicy::class, 'updateBoxStatus']);
        Gate::define('sealBox', [WarehousePolicy::class, 'sealBox']);
    }

    /**
     * Configure comprehensive rate limiting for API protection
     */
    private function configureRateLimiters(): void
    {
        // 🔒 Authentication Rate Limiters - Prevent brute force attacks
        RateLimiter::for('login', function (Request $request) {
            $key = strtolower($request->input('email')).'|'.$request->ip();

            return [
                Limit::perMinute(50)->by($key),        // 50 attempts per minute per email/IP (increased from 30)
                Limit::perDay(1000)->by($key),         // 1000 attempts per day per email/IP (increased from 500)
            ];
        });

        // 🔒 General Auth Protection
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());  // Increased from 10
        });

        // 🌐 Public API Rate Limiters - Protect geographic data
        RateLimiter::for('wilayah-public', function (Request $request) {
            return [
                Limit::perMinute(120)->by($request->ip()),     // Anonymous users: 120/min (increased from 60)
                Limit::perDay(5000)->by($request->ip()),       // Anonymous users: 5000/day (increased from 1000)
            ];
        });

        // 🔍 Tracking API Rate Limiters - Protect tracking endpoints
        RateLimiter::for('tracking-public', function (Request $request) {
            return [
                Limit::perMinute(60)->by($request->ip()),      // 60 lookups per minute per IP (increased from 30)
                Limit::perDay(1000)->by($request->ip()),       // 1000 lookups per day per IP (increased from 300)
            ];
        });

        // 📋 Mushaf Request Rate Limiters - Prevent spam submissions
        RateLimiter::for('mushaf-request', function (Request $request) {
            return [
                Limit::perHour(60)->by($request->ip()),        // Max 60 requests per hour per IP
                Limit::perDay(100)->by($request->ip()),        // Max 100 requests per day per IP
            ];
        });

        // 📄 Certificate Download Rate Limiters
        RateLimiter::for('certificate-download', function (Request $request) {
            return [
                Limit::perMinute(20)->by($request->ip()),      // 20 downloads per minute (increased from 5)
                Limit::perHour(100)->by($request->ip()),       // 100 downloads per hour (increased from 20)
            ];
        });

        // Internal/admin rate limiters removed — admin team operates behind auth and permissions.
    }
}
