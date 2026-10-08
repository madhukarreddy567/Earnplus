<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 10: Cloudflare / reverse-proxy compatibility.
 *
 * When `trust_proxy_headers` is on and the direct peer is inside one of the
 * configured `trusted_proxy_cidrs` ranges, the real client IP / scheme are
 * read from X-Forwarded-For / X-Forwarded-Proto — so rate limiting and the
 * IP blocklist see the actual visitor, not the proxy.
 *
 * `trusted_proxy_cidrs` is seeded with Cloudflare's published ranges; the
 * admin can edit them on the security page. Untrusted peers are ignored —
 * a random client can never spoof its IP through these headers.
 *
 * Runs after Laravel's own TrustProxies middleware (which resets the static
 * trusted-proxy list), so the configured ranges are the ones that stick.
 */
class TrustConfiguredProxies
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $enabled = setting_bool('trust_proxy_headers', false);
            $cidrs = $enabled
                ? array_filter(array_map(
                    'trim',
                    preg_split('/[\r\n,]+/', (string) setting('trusted_proxy_cidrs', ''))
                ))
                : [];
        } catch (\Throwable) {
            // Database not ready (fresh install mid-migrate) — skip.
            $cidrs = [];
        }

        if ($cidrs !== []) {
            $peer = (string) $request->server('REMOTE_ADDR');

            foreach ($cidrs as $cidr) {
                try {
                    if (IpUtils::checkIp($peer, $cidr)) {
                        $request->setTrustedProxies(
                            [$peer],
                            Request::HEADER_X_FORWARDED_FOR
                            | Request::HEADER_X_FORWARDED_HOST
                            | Request::HEADER_X_FORWARDED_PORT
                            | Request::HEADER_X_FORWARDED_PROTO
                        );
                        break;
                    }
                } catch (\Throwable) {
                    // A malformed configured range must never break requests.
                }
            }
        }

        return $next($request);
    }
}
