<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generic module toggle gate: `module.enabled:offerwalls_enabled` —
 * the route returns 404 when the named feature toggle is off,
 * exactly like the e-mail auth gate does.
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $settingKey): Response
    {
        if (! setting_bool($settingKey, true)) {
            abort(404);
        }

        return $next($request);
    }
}
