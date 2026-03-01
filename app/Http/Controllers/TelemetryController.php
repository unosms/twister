<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\TelemetryLog;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    public function index(Request $request)
    {
        $deviceId = (int) $request->query('device', 0);
        $selectedDevice = $deviceId > 0 ? Device::findOrFail($deviceId) : null;

        $telemetryQuery = TelemetryLog::with('device')
            ->orderByDesc('recorded_at');

        if ($selectedDevice) {
            $telemetryQuery->where('device_id', $selectedDevice->id);
        }

        $telemetryLogs = $telemetryQuery
            ->limit(25)
            ->get();

        if (!$selectedDevice && $telemetryLogs->isEmpty()) {
            $telemetryLogs = Device::orderByDesc('last_seen_at')
                ->orderByDesc('updated_at')
                ->limit(25)
                ->get()
                ->map(function (Device $device) {
                    $status = strtolower((string) ($device->status ?? 'offline'));
                    $level = $status === 'online' ? 'info' : ($status === 'warning' ? 'warning' : 'debug');
                    $recordedAt = $device->last_seen_at ?? $device->updated_at ?? now();

                    return (object) [
                        'device' => $device,
                        'device_id' => $device->id,
                        'level' => $level,
                        'message' => "Current device status snapshot: {$status}.",
                        'recorded_at' => $recordedAt,
                    ];
                })
                ->values();
        }

        $auditLogs = $selectedDevice
            ? collect()
            : AuditLog::with('actor')
                ->orderByDesc('occurred_at')
                ->limit(20)
                ->get();

        return view('telemetry_logs', [
            'selectedDevice' => $selectedDevice,
            'telemetryLogs' => $telemetryLogs,
            'auditLogs' => $auditLogs,
        ]);
    }
}
