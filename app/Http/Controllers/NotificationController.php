<?php

namespace App\Http\Controllers;

use App\Models\Alert;
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

        return back();
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
