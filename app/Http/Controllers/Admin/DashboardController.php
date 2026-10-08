<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CronJobRegistry;
use App\Services\LicenseService;
use App\Services\StatisticsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * The admin dashboard: live statistics, the pending-withdrawal
     * queue, cron health alerts, license alerts, and links to every
     * admin module.
     */
    public function index(StatisticsService $stats, LicenseService $license): View
    {
        return view('admin.dashboard', [
            'stats' => $stats->overview(),
            'cronAlerts' => CronJobRegistry::alerts(),
            'licenseAlerts' => $license->adminAlerts(),
        ]);
    }
}
