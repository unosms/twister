<?php

namespace App\Http\Controllers;

use App\Support\ProvisioningLogFeed;
use App\Support\ProvisioningTrace;
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

        return $this->jsonResponse([
            'enabled' => (bool) $enabled,
        ]);
    }

    public function viewProvisioningLog(Request $request)
    {
        $limit = max(1, min((int) $request->query('limit', ProvisioningTrace::tailLimit()), 2000));
        $eventLimit = max(1, min((int) $request->query('event_limit', ProvisioningTrace::eventLimit()), 500));

        $path = ProvisioningTrace::rawLogPath();
        if (!file_exists($path) && ProvisioningTrace::enabled()) {
            $this->writeProvisioningLog('provisioning log active; waiting for polling entries');
        }

        return $this->jsonResponse($this->buildProvisioningPayload($limit, $eventLimit));
    }

    public function streamProvisioningLog(Request $request)
    {
        $limit = max(1, min((int) $request->query('limit', ProvisioningTrace::tailLimit()), 2000));
        $eventLimit = max(1, min((int) $request->query('event_limit', ProvisioningTrace::eventLimit()), 500));
        $heartbeatMs = max(250, (int) config('provisioning.stream_heartbeat_ms', 1000));
        $lifetimeSeconds = max(5, (int) config('provisioning.stream_lifetime_seconds', 25));

        return response()->stream(function () use ($limit, $eventLimit, $heartbeatMs, $lifetimeSeconds): void {
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            @set_time_limit(0);

            $deadline = microtime(true) + $lifetimeSeconds;
            $lastSignature = null;

            while (microtime(true) < $deadline && !connection_aborted()) {
                $payload = $this->buildProvisioningPayload($limit, $eventLimit);
                $signature = sha1(json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE) ?: '');

                if ($signature !== $lastSignature) {
                    $lastSignature = $signature;
                    echo "event: snapshot\n";
                    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n\n";
                } else {
                    echo ": heartbeat\n\n";
                }

                @ob_flush();
                @flush();
                usleep($heartbeatMs * 1000);
            }
        }, 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function buildProvisioningPayload(int $limit, int $eventLimit): array
    {
        $linePath = ProvisioningTrace::rawLogPath();
        $eventPath = ProvisioningTrace::eventLogPath();
        $lines = file_exists($linePath) ? ProvisioningLogFeed::readLines($linePath, $limit) : [];
        $lines = array_values(array_filter(array_map([$this, 'sanitizeLogLine'], $lines), static fn ($line): bool => $line !== ''));
        $events = ProvisioningLogFeed::readEvents($eventPath, $eventLimit);

        return [
            'enabled' => ProvisioningTrace::enabled(),
            'stream_enabled' => ProvisioningTrace::streamEnabled(),
            'lines' => $lines,
            'events' => $events,
            'progress' => ProvisioningLogFeed::summarize($lines, $events),
            'raw_log_path' => $linePath,
            'event_log_path' => $eventPath,
        ];
    }

    private function jsonResponse(array $payload)
    {
        return response()->json($payload, 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function sanitizeLogLine(string $line): string
    {
        if ($line === '') {
            return '';
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($line, 'UTF-8')) {
            return $line;
        }

        $sanitized = @iconv('UTF-8', 'UTF-8//IGNORE', $line);
        if (is_string($sanitized) && $sanitized !== '') {
            return $sanitized;
        }

        if (function_exists('mb_convert_encoding')) {
            $sanitized = @mb_convert_encoding($line, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252, ASCII');
            if (is_string($sanitized)) {
                return $sanitized;
            }
        }

        return '';
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
