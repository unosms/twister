<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Device;
use App\Models\TelemetryLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        $authUser = User::find($request->session()->get('auth.user_id'));
        $provisioningLogPath = storage_path('logs/provisioning.log');
        $provisioningLogEnabled = (bool) Cache::get('provisioning_log_enabled', false);

        $totalDevices = Device::count();
        $onlineDevices = Device::where('status', 'online')->count();
        $openAlertCount = Alert::where('status', 'open')->count();
        $telemetryEventCount = TelemetryLog::where('recorded_at', '>=', now()->subDay())->count();
        $latestTelemetry = TelemetryLog::orderByDesc('recorded_at')->first(['recorded_at']);

        return view('support_debug_diagnostics', [
            'authUser' => $authUser,
            'provisioningLogEnabled' => $provisioningLogEnabled,
            'provisioningLogPath' => $provisioningLogPath,
            'provisioningLogExists' => file_exists($provisioningLogPath),
            'provisioningLogLines' => $this->readProvisioningLogLines($provisioningLogPath, 120),
            'totalDevices' => $totalDevices,
            'onlineDevices' => $onlineDevices,
            'offlineDevices' => max(0, $totalDevices - $onlineDevices),
            'openAlertCount' => $openAlertCount,
            'telemetryEventCount' => $telemetryEventCount,
            'latestTelemetryAt' => $latestTelemetry?->recorded_at,
            'supportStatus' => $request->session()->get('support_status'),
            'supportResult' => $request->session()->get('support_result'),
        ]);
    }

    public function autoDebug(Request $request)
    {
        Cache::forever('provisioning_log_enabled', true);

        $targetIds = array_values(array_unique(array_merge(
            Device::query()
                ->whereIn('status', ['offline', 'warning', 'error'])
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all(),
            Alert::query()
                ->where('status', 'open')
                ->whereNotNull('device_id')
                ->pluck('device_id')
                ->map(static fn ($id) => (int) $id)
                ->all()
        )));

        if (!$targetIds) {
            $targetIds = Device::query()
                ->latest('id')
                ->limit(10)
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();
        }

        $summary = $this->runProbeSnapshot($targetIds);

        return redirect()
            ->route('support.index')
            ->with('support_status', 'Auto debug completed. Provisioning capture is enabled.')
            ->with('support_result', [
                'mode' => 'Auto Debug',
                'scope' => 'Priority devices',
                'ran_at' => now()->toDateTimeString(),
                'target_count' => count($targetIds),
                'probed_count' => $summary['probed_count'],
                'online_count' => $summary['online_count'],
                'offline_count' => $summary['offline_count'],
                'warning_count' => $summary['warning_count'],
                'error_count' => $summary['error_count'],
            ]);
    }

    public function runDiagnostic(Request $request)
    {
        $targetIds = Device::query()
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $summary = $this->runProbeSnapshot($targetIds);

        return redirect()
            ->route('support.index')
            ->with('support_status', 'Fleet diagnostic completed.')
            ->with('support_result', [
                'mode' => 'Run Diagnostic',
                'scope' => 'All devices',
                'ran_at' => now()->toDateTimeString(),
                'target_count' => count($targetIds),
                'probed_count' => $summary['probed_count'],
                'online_count' => $summary['online_count'],
                'offline_count' => $summary['offline_count'],
                'warning_count' => $summary['warning_count'],
                'error_count' => $summary['error_count'],
            ]);
    }

    private function readProvisioningLogLines(string $path, int $limit): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $maxBytes = 200000;
        $size = filesize($path);
        $offset = max(0, $size - $maxBytes);
        $data = file_get_contents($path, false, null, $offset);
        $lines = preg_split('/\r\n|\r|\n/', trim((string) $data));

        if ($offset > 0 && count($lines) > 1) {
            array_shift($lines);
        }

        $lines = array_values(array_filter($lines, static fn ($line): bool => trim((string) $line) !== ''));

        return array_slice($lines, -$limit);
    }

    private function runProbeSnapshot(array $deviceIds): array
    {
        if (!$deviceIds) {
            return [
                'probed_count' => 0,
                'online_count' => 0,
                'offline_count' => 0,
                'warning_count' => 0,
                'error_count' => 0,
            ];
        }

        try {
            $snapshotRequest = Request::create('/devices/status-snapshot', 'GET', [
                'ids' => implode(',', $deviceIds),
                'probe' => '1',
                't' => (string) now()->timestamp,
            ]);

            $response = app(DeviceController::class)->statusSnapshot($snapshotRequest);
            $payload = $response->getData(true);
            $devices = collect($payload['devices'] ?? []);

            return [
                'probed_count' => $devices->count(),
                'online_count' => $devices->where('status', 'online')->count(),
                'offline_count' => $devices->where('status', 'offline')->count(),
                'warning_count' => $devices->where('status', 'warning')->count(),
                'error_count' => $devices->where('status', 'error')->count(),
            ];
        } catch (\Throwable $exception) {
            Log::warning('Support diagnostic probe failed.', [
                'device_ids' => $deviceIds,
                'message' => $exception->getMessage(),
            ]);

            return [
                'probed_count' => 0,
                'online_count' => 0,
                'offline_count' => 0,
                'warning_count' => 0,
                'error_count' => 0,
            ];
        }
    }
}
