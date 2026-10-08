<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts admin routes to a specific admin role, e.g.
 * ->middleware('admin.role:super_admin'). Anything else gets a 403.
 */
class EnsureAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $admin = $request->user('admin');

        if ($admin === null || $admin->role !== $role) {
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}
