<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\LoginLog;
use App\Services\AdminLockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminLoginController extends Controller
{
    /**
     * Show the admin login form (separate from the user login).
     */
    public function show(): View
    {
        return view('admin.login');
    }

    /**
     * Handle an admin login request. Failed attempts are logged and
     * the account locks for 15 minutes after 8 failures.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = (string) $request->input('email');

        if (AdminLockout::isLocked($email)) {
            LoginLog::record($email, $request, false, 'admin');

            $minutes = (int) ceil(AdminLockout::lockedSecondsRemaining($email) / 60);

            return back()
                ->onlyInput('email')
                ->withErrors(['email' => "Account locked due to too many failed attempts. Try again in {$minutes} minutes."]);
        }

        $admin = Admin::where('email', $email)->first();

        $valid = $admin !== null
            && $admin->is_active
            && Hash::check((string) $request->input('password'), $admin->password);

        if (! $valid) {
            AdminLockout::recordFailure($email);
            LoginLog::record($email, $request, false, 'admin');

            return back()
                ->onlyInput('email')
                ->withErrors(['email' => 'These credentials do not match our records.']);
        }

        AdminLockout::clear($email);
        Auth::guard('admin')->login($admin, $request->boolean('remember'));
        $request->session()->regenerate();
        LoginLog::record($email, $request, true, 'admin');

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Log the admin out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
