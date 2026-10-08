<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 13: license / domain-lock enforcement.
 *
 * - Development mode: everything passes (lenient), including the demo
 *   provider and test banners.
 * - Production mode: the signature must match and the request host must
 *   belong to the licensed domain (www variant + aliases allowed).
 * - Kill switch: when active, every user route shows the lock page.
 *   The admin area (so the owner can log in and unlock), the license
 *   system routes, and health checks always pass through.
 *
 * Blocked visitors see neutral pages — nothing about the license
 * internals, paths, or configuration is leaked.
 */
class EnsureLicensed
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var LicenseService $license */
        $license = app(LicenseService::class);

        // The license system must stay reachable: remote-kill URL,
        // health checks, and the whole admin area (admin login is the
        // only way to unlock the app).
        if (
            $request->is('license/*')
            || $request->is('health')
            || $request->is('up')
            || $request->is('admin*')
            || auth('admin')->check()
        ) {
            return $next($request);
        }

        // Kill switch: lock every user route.
        if ($license->isLocked()) {
            return $this->lockedResponse($request);
        }

        // Development mode is lenient by design.
        if (! $license->isProduction()) {
            return $next($request);
        }

        // Production: the build signature must be intact.
        if (! $license->signatureValid()) {
            $license->recordViolation(
                \App\Models\LicenseViolation::TYPE_SIGNATURE_TAMPER,
                $request,
                ['source' => 'middleware']
            );

            return $this->unlicensedResponse($request);
        }

        // Production: the host must belong to the licensed domain.
        // (Unconfigured domain = admin warning, not a lockout — the owner
        // must be able to reach the admin page to configure it.)
        if (! $license->hostAllowed($request->getHost())) {
            $license->recordViolation(
                \App\Models\LicenseViolation::TYPE_DOMAIN_MISMATCH,
                $request,
                ['expected' => $license->licensedDomain()]
            );

            if ($license->violationThresholdExceeded($request->ip())) {
                return $this->blockedResponse($request);
            }

            return $this->unlicensedResponse($request);
        }

        return $next($request);
    }

    protected function lockedResponse(Request $request): Response
    {
        if ($this->wantsJson($request)) {
            return response()->json([
                'message' => 'This installation is temporarily locked.',
                'locked' => true,
            ], 503);
        }

        return response()->view('license-locked', [
            'siteName' => setting('site_name', 'EarnPlus'),
            'reason' => setting('license_locked_reason', ''),
        ], 503);
    }

    protected function unlicensedResponse(Request $request): Response
    {
        if ($this->wantsJson($request)) {
            return response()->json([
                'message' => 'This installation is not licensed.',
            ], 403);
        }

        return response()->view('license-unlicensed', [
            'siteName' => setting('site_name', 'EarnPlus'),
        ], 403);
    }

    protected function blockedResponse(Request $request): Response
    {
        if ($this->wantsJson($request)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Deliberately generic: no hint about licenses, domains or limits.
        return response('Access temporarily restricted.', 403);
    }

    protected function wantsJson(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
