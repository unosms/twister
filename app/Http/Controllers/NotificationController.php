<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    public function menu()
    {
        $openAlerts = Alert::with('device')
            ->where('status', 'open')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json([
            'open_count' => Alert::where('status', 'open')->count(),
            'items' => $openAlerts->map(function (Alert $alert): array {
                return [
                    'id' => $alert->id,
                    'title' => (string) ($alert->title ?: 'Alert'),
                    'message' => Str::limit((string) ($alert->message ?: 'No details available.'), 140),
                    'severity' => strtolower((string) ($alert->severity ?: 'info')),
                    'device_name' => $alert->device?->name,
                    'created_at_human' => $alert->created_at?->diffForHumans(),
                ];
            })->values(),
        ]);
    }

    public function index(Request $request)
    {
        $severity = $request->query('severity');
        $status = $request->query('status');
        $deviceIdsRaw = $request->query('device_ids', []);
        if (!is_array($deviceIdsRaw)) {
            $deviceIdsRaw = [$deviceIdsRaw];
        }
        $selectedDeviceIds = array_values(array_unique(array_filter(array_map(
            static fn ($value) => is_numeric($value) ? (int) $value : null,
            $deviceIdsRaw
        ), static fn ($id) => is_int($id) && $id > 0)));
        $selectedDeviceIdSet = array_fill_keys(
            array_map(static fn ($id) => (string) $id, $selectedDeviceIds),
            true
        );

        $alertQuery = Alert::with('device')
            ->orderByDesc('created_at');

        if (!empty($severity) && $severity !== 'all') {
            $alertQuery->where('severity', $severity);
        }

        if (!empty($status) && $status !== 'all') {
            $alertQuery->where('status', $status);
        }
        if (!empty($selectedDeviceIds)) {
            $alertQuery->whereIn('device_id', $selectedDeviceIds);
        }

        $alerts = $alertQuery->paginate(12)->withQueryString();
        $devices = Device::orderBy('name')->get(['id', 'name']);

        $severityCounts = [
            'all' => Alert::count(),
            'critical' => Alert::where('severity', 'critical')->count(),
            'warning' => Alert::where('severity', 'warning')->count(),
            'info' => Alert::where('severity', 'info')->count(),
        ];

        $statusCounts = [
            'open' => Alert::where('status', 'open')->count(),
            'closed' => Alert::where('status', 'closed')->count(),
        ];

        return view('notification_alert', [
            'alerts' => $alerts,
            'filters' => [
                'severity' => $severity,
                'status' => $status,
                'device_ids' => $selectedDeviceIds,
            ],
            'severityCounts' => $severityCounts,
            'statusCounts' => $statusCounts,
            'devices' => $devices,
            'selectedDeviceIds' => $selectedDeviceIds,
            'selectedDeviceIdSet' => $selectedDeviceIdSet,
        ]);
    }

    public function markAllRead(Request $request)
    {
        Alert::where('status', 'open')->update([
            'status' => 'closed',
            'acknowledged_by' => $request->session()->get('auth.user_id'),
            'acknowledged_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'action' => 'notifications.mark_all_read',
            ]);
        }

        return back()->with('status', 'All alerts marked as read.');
    }

    public function filter(Request $request)
    {
        $filters = array_filter([
            'severity' => $request->input('severity'),
            'status' => $request->input('status'),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'action' => 'notifications.filter',
                'filters' => $filters,
            ]);
        }

        return redirect()->route('notifications.index', $filters);
    }

    public function archive(Request $request)
    {
        $alertId = $request->input('alert_id');
        if ($alertId) {
            Alert::where('id', $alertId)->update([
                'status' => 'closed',
                'resolved_at' => now(),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'action' => 'notifications.archive',
            ]);
        }

        return back()->with('status', 'Alert archived.');
    }

    public function dismiss(Request $request)
    {
        $alertId = $request->input('alert_id');
        if ($alertId) {
            Alert::where('id', $alertId)->update([
                'status' => 'closed',
                'acknowledged_by' => $request->session()->get('auth.user_id'),
                'acknowledged_at' => now(),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'action' => 'notifications.dismiss',
            ]);
        }

        return back()->with('status', 'Alert dismissed.');
    }

    public function investigate(Request $request)
    {
        $alertId = $request->input('alert_id');
        if ($alertId) {
            Alert::where('id', $alertId)->update([
                'acknowledged_by' => $request->session()->get('auth.user_id'),
                'acknowledged_at' => now(),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'action' => 'notifications.investigate',
            ]);
        }

        return back()->with('status', 'Alert marked for investigation.');
    }
}
