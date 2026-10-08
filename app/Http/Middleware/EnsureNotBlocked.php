<?php

namespace App\Http\Middleware;

use App\Models\SecurityEvent;
use App\Services\IpBlockService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 10: blocklisted IPs get a neutral 403 on every route (web and
 * postback). The page reveals nothing about why the request was refused.
 */
class EnsureNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = (string) $request->ip();

        if (IpBlockService::isBlocked($ip)) {
            SecurityEvent::record(
                SecurityEvent::TYPE_IP_BLOCKED,
                $ip,
                substr($request->path(), 0, 255),
                'Blocklist hit'
            );

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            return response()->view('errors.403', [], 403);
        }

        return $next($request);
    }
}
