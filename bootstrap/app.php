<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'mobile.verified' => \App\Http\Middleware\EnsureMobileVerified::class,
            'admin.role' => \App\Http\Middleware\EnsureAdminRole::class,
            'email.auth' => \App\Http\Middleware\EnsureEmailAuthEnabled::class,
            'module.enabled' => \App\Http\Middleware\EnsureModuleEnabled::class,
        ]);

        // Phase 10 security stack (order matters):
        // - Laravel's own TrustProxies middleware (default global stack)
        //   resets trusted proxies first; TrustConfiguredProxies is appended
        //   AFTER it so the DB-configured ranges are the ones that stick.
        // - EnsureNotBlocked runs at the start of the web group, after the
        //   real client IP is resolved, so the blocklist sees the visitor.
        // - SecurityHeaders wraps every response (CSP nonce etc.).
        $middleware->append(\App\Http\Middleware\TrustConfiguredProxies::class);
        $middleware->web(prepend: \App\Http\Middleware\EnsureNotBlocked::class);
        $middleware->api(prepend: \App\Http\Middleware\EnsureNotBlocked::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Maintenance mode: visitors see the maintenance page, admins pass.
        $middleware->web(append: [\App\Http\Middleware\EnsureNotInMaintenance::class]);

        // Phase 13: license / domain-lock enforcement. Runs after the
        // maintenance check; the admin area, the license system routes
        // and health checks always pass through (admin login is the only
        // way to unlock the app). JSON for API consumers (Flutter app).
        $middleware->web(append: [\App\Http\Middleware\EnsureLicensed::class]);
        $middleware->api(append: [\App\Http\Middleware\EnsureLicensed::class]);

        // Provider postbacks are signed (HMAC) server-to-server calls and
        // mobile ad-network reward callbacks are signature-verified too —
        // CSRF tokens don't apply; the signature is the authentication.
        $middleware->validateCsrfTokens(except: ['postback/*', 'ads/verify/*']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
