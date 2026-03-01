<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $showAllActivity = request()->query('activity') === 'all';

        $devices = Device::with('assignedUser')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $totalUsers = User::count();
        $totalDevices = Device::count();
        $onlineDevices = Device::where('status', 'online')->count();
        $authUser = User::find(request()->session()->get('auth.user_id'));
        $activeAlerts = Alert::where('status', 'open')->count();
        $alerts = Alert::with('device')
            ->where('status', 'open')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();
        $recentActivityQuery = AuditLog::with('actor')
            ->orderByDesc('occurred_at');
        if (!$showAllActivity) {
            $recentActivityQuery->limit(4);
        }
        $recentActivity = $recentActivityQuery->get();

        return view('admin_overview', [
            'devices' => $devices,
            'totalUsers' => $totalUsers,
            'totalDevices' => $totalDevices,
            'onlineDevices' => $onlineDevices,
            'activeAlerts' => $activeAlerts,
            'alerts' => $alerts,
            'recentActivity' => $recentActivity,
            'showAllActivity' => $showAllActivity,
            'authUser' => $authUser,
        ]);
    }
}
