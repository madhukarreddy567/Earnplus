<?php

use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OfferwallController as AdminOfferwallController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OfferwallClickController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\PostbackController;
use App\Http\Controllers\SpinController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\Admin\WithdrawalController as AdminWithdrawalController;
use App\Http\Controllers\Admin\AdController as AdminAdController;
use App\Http\Controllers\AdVerificationController;
use App\Http\Controllers\RewardedAdController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
})->name('landing');

Route::get('/health', HealthController::class)->name('health');

/*
|--------------------------------------------------------------------------
| Remote kill switch (Phase 13)
|--------------------------------------------------------------------------
|
| A signed URL the owner can trigger from anywhere to lock the app
| instantly. Disabled by default — enable it on the admin license page,
| where the secret (and the full URL) is shown. A wrong or missing
| token gets a plain 404; the endpoint never advertises itself.
|
*/
Route::get('/license/remote-kill', [\App\Http\Controllers\Admin\LicenseController::class, 'remoteKill'])
    ->name('license.remote-kill');

/*
|--------------------------------------------------------------------------
| Public policy pages (terms, privacy, refund, about) — latest published
| version only; unpublished slugs 404.
|--------------------------------------------------------------------------
*/
Route::get('/policies/{slug}', [PolicyController::class, 'show'])
    ->name('policies.show');

/*
|--------------------------------------------------------------------------
| Offerwall provider postbacks (signed, no session auth)
|--------------------------------------------------------------------------
*/
Route::match(['GET', 'POST'], '/postback/{provider:slug}', [PostbackController::class, 'handle'])
    ->middleware('throttle:postback')
    ->name('postback.handle');

/*
|--------------------------------------------------------------------------
| Mobile ad-network server-to-server reward callbacks (AdMob SSV,
| Unity Ads S2S). Signed by the network — no session auth, CSRF-exempt.
|--------------------------------------------------------------------------
*/
Route::match(['get', 'post'], '/ads/verify/{network:slug}', [AdVerificationController::class, 'handle'])
    ->name('ads.verify');

/*
|--------------------------------------------------------------------------
| User authentication (web guard)
|--------------------------------------------------------------------------
|
| E-mail/password auth is behind the `email.auth` middleware: when the
| owner disables it (setting `email_auth_enabled`, default OFF), every
| e-mail auth route below 404s — GET and POST alike — as if e-mail auth
| never existed. GET /login always renders but becomes Google-only.
| The admin guard is unaffected.
|
| "Continue with Google" lives outside that gate: it is the default
| sign-in and works whenever Google credentials are configured.
*/
Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

    // The login page always renders — when e-mail auth is disabled it is
    // Google-only (no e-mail form, no registration link).
    Route::get('/login', [LoginController::class, 'show'])->name('login');
});

Route::middleware(['guest', 'email.auth'])->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:login');

    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:login');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'send'])
        ->middleware('throttle:login')
        ->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:login')
        ->name('password.update');
});

Route::middleware('auth')->group(function () {
    // E-mail verification
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Mobile OTP verification
    Route::get('/otp/verify', [OtpController::class, 'notice'])->name('otp.notice');
    Route::post('/otp/verify', [OtpController::class, 'verify'])
        ->middleware('throttle:otp')
        ->name('otp.verify');
    Route::post('/otp/resend', [OtpController::class, 'resend'])
        ->middleware('throttle:3,1')
        ->name('otp.resend');

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth', 'verified', 'mobile.verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Daily check-in
    Route::post('/checkin', [CheckinController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('checkin');

    // Spin wheel
    Route::get('/spin', [SpinController::class, 'show'])->name('spin');
    Route::post('/spin', [SpinController::class, 'spin'])
        ->middleware('throttle:10,1')
        ->name('spin.play');

    // Earn tasks / offerwalls (module toggle)
    Route::middleware('module.enabled:offerwalls_enabled')->group(function () {
        Route::get('/tasks', [TaskController::class, 'index'])->name('tasks');
        Route::get('/tasks/out/{provider:slug}', [OfferwallClickController::class, 'redirect'])
            ->name('tasks.out');
        Route::post('/tasks/demo/{task}/complete', [TaskController::class, 'completeDemo'])
            ->middleware('throttle:10,1')
            ->name('tasks.demo.complete');
    });

    // Rewarded ads — claim endpoint (server re-verifies everything; module toggle)
    Route::post('/ads/reward/{placement:slug}', [RewardedAdController::class, 'claim'])
        ->middleware(['throttle:20,1', 'module.enabled:ads_enabled'])
        ->name('ads.reward');

    // Withdrawals
    Route::get('/withdraw', [\App\Http\Controllers\WithdrawController::class, 'index'])->name('withdraw.index');
    Route::post('/withdraw/quote', [\App\Http\Controllers\WithdrawController::class, 'quote'])
        ->middleware('throttle:30,1')
        ->name('withdraw.quote');
    Route::post('/withdraw', [\App\Http\Controllers\WithdrawController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('withdraw.store');
    Route::get('/withdraw/{withdrawal}', [\App\Http\Controllers\WithdrawController::class, 'show'])->name('withdraw.show');
});

/*
|--------------------------------------------------------------------------
| Admin authentication (separate admin guard, /admin/*)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminLoginController::class, 'show'])->name('login');
        Route::post('/login', [AdminLoginController::class, 'store'])
            ->middleware('throttle:admin-login');
    });

    Route::middleware(['auth:admin', 'throttle:admin-write'])->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Phase 10: security center (login logs, IP blocklist, lockdown).
        Route::get('/security', [\App\Http\Controllers\Admin\SecurityController::class, 'index'])->name('security.index');
        Route::post('/security/blocks', [\App\Http\Controllers\Admin\SecurityController::class, 'storeBlock'])->name('security.blocks.store');
        Route::delete('/security/blocks/{blockedIp}', [\App\Http\Controllers\Admin\SecurityController::class, 'destroyBlock'])->name('security.blocks.destroy');
        Route::post('/security/settings', [\App\Http\Controllers\Admin\SecurityController::class, 'saveSettings'])->name('security.settings');
        Route::post('/security/lockdown', [\App\Http\Controllers\Admin\SecurityController::class, 'lockdown'])->name('security.lockdown');
        Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');

        // Phase 11: scheduled jobs dashboard + manual runner.
        Route::get('/crons', [\App\Http\Controllers\Admin\CronController::class, 'index'])->name('crons.index');
        Route::post('/crons/{job}/run', [\App\Http\Controllers\Admin\CronController::class, 'run'])->name('crons.run');

        // Phase 13: license & domain lock.
        Route::get('/license', [\App\Http\Controllers\Admin\LicenseController::class, 'index'])->name('license.index');
        Route::post('/license/settings', [\App\Http\Controllers\Admin\LicenseController::class, 'update'])->name('license.settings');
        Route::post('/license/kill-secret', [\App\Http\Controllers\Admin\LicenseController::class, 'regenerateSecret'])->name('license.kill-secret');
        Route::post('/license/kill', [\App\Http\Controllers\Admin\LicenseController::class, 'kill'])
            ->middleware('admin.role:super_admin')
            ->name('license.kill');
        Route::post('/license/unlock', [\App\Http\Controllers\Admin\LicenseController::class, 'unlock'])
            ->middleware('admin.role:super_admin')
            ->name('license.unlock');

        Route::get('/admins', [AdminUserController::class, 'index'])
            ->middleware('admin.role:super_admin')
            ->name('admins.index');

        // Wallets & coin ledger
        Route::get('/wallets', [WalletController::class, 'index'])->name('wallets.index');
        Route::get('/wallets/{user}', [WalletController::class, 'show'])->name('wallets.show');
        Route::post('/wallets/{user}/adjust', [WalletController::class, 'adjust'])
            ->middleware('admin.role:super_admin')
            ->name('wallets.adjust');

        // Offerwalls & tasks
        Route::get('/offerwalls', [AdminOfferwallController::class, 'index'])->name('offerwalls.index');
        Route::get('/offerwalls/create', [AdminOfferwallController::class, 'create'])->name('offerwalls.create');
        Route::post('/offerwalls', [AdminOfferwallController::class, 'store'])->name('offerwalls.store');
        Route::get('/offerwalls/conversions', [AdminOfferwallController::class, 'conversions'])->name('offerwalls.conversions');
        Route::get('/offerwalls/{provider}', [AdminOfferwallController::class, 'edit'])->name('offerwalls.edit');
        Route::put('/offerwalls/{provider}', [AdminOfferwallController::class, 'update'])->name('offerwalls.update');
        Route::delete('/offerwalls/{provider}', [AdminOfferwallController::class, 'destroy'])->name('offerwalls.destroy');
        Route::post('/offerwalls/{provider}/regenerate-secret', [AdminOfferwallController::class, 'regenerateSecret'])
            ->name('offerwalls.regenerate-secret');
        Route::post('/offerwalls/conversions/{conversion}/credit', [AdminOfferwallController::class, 'creditConversion'])
            ->name('offerwalls.conversions.credit');
        Route::post('/offerwalls/conversions/{conversion}/reject', [AdminOfferwallController::class, 'rejectConversion'])
            ->name('offerwalls.conversions.reject');

        // Ads: networks, placements, reward log
        Route::get('/ads', [AdminAdController::class, 'index'])->name('ads.index');
        Route::get('/ads/rewards', [AdminAdController::class, 'rewards'])->name('ads.rewards');
        Route::get('/ads/networks/create', [AdminAdController::class, 'createNetwork'])->name('ads.networks.create');
        Route::post('/ads/networks', [AdminAdController::class, 'storeNetwork'])->name('ads.networks.store');
        Route::get('/ads/networks/{network}', [AdminAdController::class, 'editNetwork'])->name('ads.networks.edit');
        Route::put('/ads/networks/{network}', [AdminAdController::class, 'updateNetwork'])->name('ads.networks.update');
        Route::delete('/ads/networks/{network}', [AdminAdController::class, 'destroyNetwork'])->name('ads.networks.destroy');
        Route::get('/ads/placements/create', [AdminAdController::class, 'createPlacement'])->name('ads.placements.create');
        Route::post('/ads/placements', [AdminAdController::class, 'storePlacement'])->name('ads.placements.store');
        Route::get('/ads/placements/{placement}', [AdminAdController::class, 'editPlacement'])->name('ads.placements.edit');
        Route::put('/ads/placements/{placement}', [AdminAdController::class, 'updatePlacement'])->name('ads.placements.update');
        Route::delete('/ads/placements/{placement}', [AdminAdController::class, 'destroyPlacement'])->name('ads.placements.destroy');
        // Withdrawals: payout methods, queue, log
        // NOTE: /withdrawals/methods must be declared before /withdrawals/{withdrawal}.
        Route::get('/withdrawals', [AdminWithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::get('/withdrawals/methods', [AdminWithdrawalController::class, 'methods'])->name('withdrawals.methods');
        Route::get('/withdrawals/methods/{method}/edit', [AdminWithdrawalController::class, 'editMethod'])->name('withdrawals.methods.edit');
        Route::put('/withdrawals/methods/{method}', [AdminWithdrawalController::class, 'updateMethod'])->name('withdrawals.methods.update');
        Route::get('/withdrawals/{withdrawal}', [AdminWithdrawalController::class, 'show'])->name('withdrawals.show');
        Route::post('/withdrawals/{withdrawal}/approve', [AdminWithdrawalController::class, 'approve'])->name('withdrawals.approve');
        Route::post('/withdrawals/{withdrawal}/reject', [AdminWithdrawalController::class, 'reject'])->name('withdrawals.reject');

        // Promotions: festival multipliers
        Route::get('/promotions', [\App\Http\Controllers\Admin\PromotionController::class, 'index'])->name('promotions.index');
        Route::get('/promotions/create', [\App\Http\Controllers\Admin\PromotionController::class, 'create'])->name('promotions.create');
        Route::post('/promotions', [\App\Http\Controllers\Admin\PromotionController::class, 'store'])->name('promotions.store');
        Route::get('/promotions/{promotion}/edit', [\App\Http\Controllers\Admin\PromotionController::class, 'edit'])->name('promotions.edit');
        Route::put('/promotions/{promotion}', [\App\Http\Controllers\Admin\PromotionController::class, 'update'])->name('promotions.update');
        Route::delete('/promotions/{promotion}', [\App\Http\Controllers\Admin\PromotionController::class, 'destroy'])->name('promotions.destroy');
        Route::post('/promotions/{promotion}/toggle', [\App\Http\Controllers\Admin\PromotionController::class, 'toggle'])->name('promotions.toggle');

        // Branding, media & design settings
        Route::get('/branding', [\App\Http\Controllers\Admin\BrandingController::class, 'index'])->name('branding.index');
        Route::post('/branding/media', [\App\Http\Controllers\Admin\BrandingController::class, 'storeMedia'])->name('branding.media.store');
        Route::delete('/branding/media/{type}', [\App\Http\Controllers\Admin\BrandingController::class, 'destroyMedia'])->name('branding.media.destroy');
        Route::post('/branding/design', [\App\Http\Controllers\Admin\BrandingController::class, 'storeDesign'])->name('branding.design.store');

        // Site settings (general / feature toggles / maintenance)
        Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');

        // Policy pages (rich text, versioned)
        Route::get('/policies', [\App\Http\Controllers\Admin\PolicyPageController::class, 'index'])->name('policies.index');
        Route::get('/policies/{policy}', [\App\Http\Controllers\Admin\PolicyPageController::class, 'edit'])->name('policies.edit');
        Route::put('/policies/{policy}', [\App\Http\Controllers\Admin\PolicyPageController::class, 'update'])->name('policies.update');
        Route::post('/policies/{policy}/restore/{revision}', [\App\Http\Controllers\Admin\PolicyPageController::class, 'restore'])->name('policies.restore');
    });
});
