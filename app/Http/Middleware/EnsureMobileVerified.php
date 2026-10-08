<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects users who must verify their mobile number (OTP) before
 * continuing. Only applies when the otp_enabled setting is on AND the
 * user actually has a mobile number that is still unverified.
 */
class EnsureMobileVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null
            && setting_bool('otp_enabled')
            && ! empty($user->mobile)
            && $user->mobile_verified_at === null
        ) {
            return redirect()->route('otp.notice');
        }

        return $next($request);
    }
}
