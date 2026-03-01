<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DebugController extends Controller
{
    public function toggleProvisioningLog(Request $request)
    {
        if ($request->has('enabled')) {
            $enabled = $request->boolean('enabled');
        } else {
            $enabled = !Cache::get('provisioning_log_enabled', false);
        }

        Cache::forever('provisioning_log_enabled', (bool) $enabled);

        $this->writeProvisioningLog(
            $enabled ? 'provisioning log enabled' : 'provisioning log disabled',
            [
                'user_id' => optional($request->user())->id,
                'actor' => optional($request->user())->name,
                'ip' => $request->ip(),
            ]
        );

        return response()->json([
            'enabled' => (bool) $enabled,
        ]);
    }

    public function viewProvisioningLog(Request $request)
    {
        $limit = (int) $request->query('limit', 200);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 2000) {
            $limit = 2000;
        }

        $path = storage_path('logs/provisioning.log');
        if (!file_exists($path) && Cache::get('provisioning_log_enabled', false)) {
            $this->writeProvisioningLog('provisioning log active; waiting for polling entries');
        }

        if (!file_exists($path)) {
            return response()->json([
                'enabled' => Cache::get('provisioning_log_enabled', false),
                'lines' => [],
            ]);
        }

        $maxBytes = 200000;
        $size = filesize($path);
        $offset = max(0, $size - $maxBytes);
        $data = file_get_contents($path, false, null, $offset);
        $lines = preg_split('/\r\n|\r|\n/', trim((string) $data));
        if ($offset > 0 && count($lines) > 1) {
            array_shift($lines);
        }
        $lines = array_slice($lines, -$limit);

        return response()->json([
            'enabled' => Cache::get('provisioning_log_enabled', false),
            'lines' => $lines,
        ]);
    }

    private function writeProvisioningLog(string $message, array $context = []): void
    {
        try {
            Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/provisioning.log'),
                'level' => 'debug',
            ])->debug($message, $context);
        } catch (\Throwable $e) {
            // ignore logging failures
        }
    }
}
