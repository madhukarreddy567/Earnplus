<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * E-mail/password authentication gate.
 *
 * The owner can disable e-mail auth entirely from the admin panel
 * (setting `email_auth_enabled`, default OFF — Google is the only
 * sign-in). When disabled, every e-mail auth route — GET and POST —
 * returns 404, as if the pages never existed.
 *
 * The admin guard (/admin/login) is intentionally unaffected: staff
 * always sign in with e-mail + password.
 */
class EnsureEmailAuthEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! setting_bool('email_auth_enabled', false)) {
            abort(404);
        }

        return $next($request);
    }
}
