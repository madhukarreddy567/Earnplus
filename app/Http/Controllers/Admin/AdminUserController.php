<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\View\View;

/**
 * Admin account management — super_admin role only
 * (enforced by the admin.role middleware on the route).
 */
class AdminUserController extends Controller
{
    /**
     * List all admin accounts.
     */
    public function index(): View
    {
        $admins = Admin::orderBy('id')->get();

        return view('admin.admins.index', compact('admins'));
    }
}
