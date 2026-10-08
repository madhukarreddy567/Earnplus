<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as BaseAuthenticate;
use Illuminate\Http\Request;

/**
 * Guard-aware authentication redirect: guests hitting /admin/*
 * go to the admin login page, everyone else to the user login page.
 */
class Authenticate extends BaseAuthenticate
{
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        return $request->is('admin*')
            ? route('admin.login')
            : route('login');
    }
}
