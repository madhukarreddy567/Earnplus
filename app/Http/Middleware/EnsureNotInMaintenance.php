<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maintenance mode (Phase 9): when the `maintenance_mode` setting is on,
 * visitors see the maintenance page — admins (signed in on the admin
 * guard) and anything under /admin/* pass straight through.
 */
class EnsureNotInMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! setting_bool('maintenance_mode', false)) {
            return $next($request);
        }

        // Admins are never locked out by maintenance mode.
        if ($request->is('admin*') || auth('admin')->check()) {
            return $next($request);
        }

        return response()->view('maintenance', [
            'message' => setting(
                'maintenance_message',
                'We are doing scheduled maintenance. Please check back soon.'
            ),
            'siteName' => setting('site_name', 'EarnPlus'),
        ], 503);
    }
}
