<?php

namespace App\Providers;

use App\Services\MailSettings;
use App\Services\Otp\LogOtpDriver;
use App\Services\Otp\OtpDriver;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // OTP delivery driver: log-based by default. Swap this binding
        // for a real SMS gateway driver later without touching callers.
        $this->app->bind(OtpDriver::class, LogOtpDriver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Already-authenticated visitors hitting a guest-only page land on
        // the right dashboard: admins -> admin area, users -> user dashboard.
        RedirectIfAuthenticated::redirectUsing(
            fn (Request $request) => $request->is('admin*')
                ? route('admin.dashboard')
                : route('dashboard')
        );

        // Bottom navigation needs today's check-in state on every page.
        // Phase 13 also shares the app mode so the layout can show the
        // "development mode" ribbon.
        View::composer('layouts.app', function ($view) {
            $checkedIn = false;
            if (auth()->check() && ! auth('admin')->check()) {
                try {
                    $checkedIn = auth()->user()->dailyCheckins()
                        ->whereDate('checked_in_on', today())
                        ->exists();
                } catch (\Throwable) {
                    $checkedIn = false;
                }
            }
            $view->with('navCheckedInToday', $checkedIn);

            try {
                $view->with('epAppMode', app(\App\Services\LicenseService::class)->mode());
            } catch (\Throwable) {
                $view->with('epAppMode', 'development');
            }
        });

        // Mailer comes from DB settings (admin pastes Gmail credentials
        // in settings later). Skipped in tests so the array mailer stays.
        if (! $this->app->runningUnitTests()) {
            try {
                if (Schema::hasTable('settings')) {
                    MailSettings::apply();
                }
            } catch (\Throwable $e) {
                // Database not ready yet (fresh install / migrate) —
                // keep the .env mail defaults.
            }
        }

        // Phase 13: license signature verification on boot. In production
        // mode a tampered signature is a violation (recorded, throttled —
        // never fatal to boot). Development mode stays lenient.
        if (! $this->app->runningUnitTests()) {
            try {
                if (Schema::hasTable('settings') && Schema::hasTable('license_violations')) {
                    app(\App\Services\LicenseService::class)->verifyBootSignature();
                }
            } catch (\Throwable $e) {
                // License logging must never break boot.
            }
        }

        $this->configureRateLimiters();
    }

    /**
     * Phase 10: named rate limiters. Auth limiters key on IP + the account
     * being attacked, so a botnet IP can't be throttled as one bucket and
     * one e-mail can't be hammered from many IPs without per-IP limits.
     */
    protected function configureRateLimiters(): void
    {
        // User login: 30/min per IP + e-mail as a middleware backstop.
        // The controller adds its own strict 5-attempt per-account throttle
        // with a friendly "try again" message; this layer stops floods.
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(30)->by($request->ip() . '|' . $email);
        });

        // Admin login: 30/min per IP + e-mail backstop (the AdminLockout
        // service handles the strict 8-fail/15-minute account lockout).
        RateLimiter::for('admin-login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(30)->by($request->ip() . '|' . $email);
        });

        // Admin panel writes: generous per-session bucket, still stops floods.
        RateLimiter::for('admin-write', function (Request $request) {
            $key = $request->user('admin')?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(120)->by('admin:' . $key);
        });

        // Offerwall postbacks: servers retry, so allow bursts but cap floods.
        RateLimiter::for('postback', function (Request $request) {
            return Limit::perMinute(300)->by('postback:' . $request->ip());
        });

        // OTP verification attempts: 10/min per IP + e-mail.
        RateLimiter::for('otp', function (Request $request) {
            $email = strtolower((string) ($request->user()?->email ?? ''));

            return Limit::perMinute(10)->by($request->ip() . '|' . $email);
        });
    }
}
