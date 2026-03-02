<?php

namespace App\Http\Controllers;

use App\Models\CommandTemplate;
use App\Models\Device;
use App\Models\DevicePermission;
use App\Support\ProvisioningTrace;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class ScriptController extends Controller
{
    private const EXEC_COMMANDS = [
        'showlog' => ['script' => 'showlog.sh'],
        'showmac' => ['script' => 'showmac.sh', 'needs' => ['interface']],
        'showint' => ['script' => 'showint.sh', 'needs' => ['interface'], 'env' => ['CMD' => 'showint']],
        'showintstatus' => ['script' => 'showintstatus.sh'],
        'restartint' => ['script' => 'restartint.sh', 'needs' => ['interface']],
        'disableint' => ['script' => 'disableint.sh', 'needs' => ['interface']],
        'enableint' => ['script' => 'enableint.sh', 'needs' => ['interface']],
        'shspantree' => ['script' => 'shspantree.sh', 'needs' => ['interface']],
        'renameint' => ['script' => 'renameint.sh', 'needs' => ['interface', 'description']],
        'shtransceiver' => ['script' => 'shtransceiver_cat.sh', 'needs' => ['interface']],
    ];

    private const NEXUS_COMMANDS = [
        'showlog' => ['script' => 'showlog.sh'],
        'showmac' => ['script' => 'showmacnexus.sh', 'needs' => ['interface']],
        'showint' => ['script' => 'showintnexus.sh', 'needs' => ['interface']],
        'showintstatus' => ['script' => 'showintstatusnexus.sh'],
        'restartint' => ['script' => 'restartintnexus.sh', 'needs' => ['interface']],
        'disableint' => ['script' => 'disableintnexus.sh', 'needs' => ['interface']],
        'enableint' => ['script' => 'enableintnexus.sh', 'needs' => ['interface']],
        'shspantree' => ['script' => 'shspantreenexus.sh', 'needs' => ['interface']],
        'shtransceiver' => ['script' => 'shtransceiver_nexus.sh', 'needs' => ['interface']],
    ];

    public function execLegacy(Request $request)
    {
        $name = trim((string) $request->query('name'));
        $cmd = trim((string) $request->query('cmd'));

        if ($cmd === '') {
            return $this->plainError('Missing cmd parameter.', 400);
        }

        $device = $this->resolveTargetDevice($request, $name !== '' ? $name : null);
        if (!$device) {
            if ($name === '' && !$request->filled('id') && !$request->filled('device')) {
                return $this->plainError('Missing name or id parameter.', 400);
            }

            return $this->plainError('Device not found.', 404);
        }

        $authorizationError = $this->authorizeCommandExecution($request, $device, $cmd);
        if ($authorizationError) {
            return $authorizationError;
        }

        $result = $this->runExec($device, $cmd, $request);
        if ($result instanceof \Symfony\Component\HttpFoundation\Response) {
            return $result;
        }

        $status = (int) ($result['status'] ?? (($result['ok'] ?? false) ? 200 : 500));
        $output = (string) ($result['output'] ?? '');
        if ($output === '') {
            $output = ($result['ok'] ?? false) ? 'OK' : (string) ($result['message'] ?? 'Command failed.');
        }

        return response()
            ->view('script_result', [
                'device' => $device,
                'command' => $cmd,
                'statusCode' => $status,
                'ok' => (bool) ($result['ok'] ?? false),
                'output' => $output,
            ], $status)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function backupLegacy(Request $request)
    {
        $name = trim((string) $request->query('name'));
        $device = $this->resolveTargetDevice($request, $name !== '' ? $name : null);

        if (!$device) {
            if ($name === '' && !$request->filled('id') && !$request->filled('device')) {
                return $this->plainError('Missing name or id parameter.', 400);
            }

            return $this->plainError('Device not found.', 404);
        }

        $result = $this->executeBackup($device, $request);
        $status = $result['status'] ?? ($result['ok'] ? 200 : 500);

        return response($result['output'] ?: ($result['ok'] ? 'OK' : 'Backup failed.'), $status)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function showBackupsPage(Device $device)
    {
        $backupData = $this->buildBackupFilePayload($device);

        return view('device_backups', [
            'device' => $device,
            'backupFiles' => $backupData['files'],
            'backupFolder' => $backupData['folder'],
        ]);
    }

    public function runBackupNow(Request $request, Device $device)
    {
        $result = $this->executeBackup($device, $request);

        $redirect = redirect()->route('devices.backups.show', ['device' => $device->id]);
        if ($result['ok']) {
            return $redirect->with('status', 'Backup completed successfully.');
        }

        $message = $result['message'] ?? 'Backup failed.';
        return $redirect->with('error', $message)->with('backup_output', $result['output']);
    }

    public function listBackups(Device $device)
    {
        return response()->json($this->buildBackupFilePayload($device));
    }

    public function downloadBackup(Device $device, string $file)
    {
        $resolved = $this->resolveBackupDirectory($device);
        if (!$resolved) {
            return $this->plainError('Backup folder not found.', 404);
        }

        $absolute = $resolved['absolute'];
        $file = basename(urldecode($file));
        if ($file === '') {
            return $this->plainError('Backup file not found.', 404);
        }

        $target = $absolute . DIRECTORY_SEPARATOR . $file;
        if (!is_file($target)) {
            return $this->plainError('Backup file not found.', 404);
        }

        $baseReal = realpath($absolute);
        $targetReal = realpath($target);
        if (!$baseReal || !$targetReal || !str_starts_with($targetReal, $baseReal . DIRECTORY_SEPARATOR)) {
            return $this->plainError('Invalid backup file path.', 400);
        }

        return response()->download($targetReal, $file);
    }

    public function showEventsPage(Request $request, Device $device)
    {
        // Render quickly from stored DB events by default.
        // Pass poll=1/true/yes/on only when an immediate manual poll is needed.
        $runPoller = in_array(
            strtolower((string) $request->query('poll', '0')),
            ['1', 'true', 'yes', 'on'],
            true
        );

        $pollerResult = null;
        if ($runPoller) {
            $pollerResult = $this->runPollerScript();
        }

        $eventsResult = $this->runEventsScript($device, $request);
        if (!$eventsResult['ok']) {
            $message = $eventsResult['output'] ?: ($eventsResult['message'] ?? 'Failed to load events.');

            return response($message, (int) ($eventsResult['status'] ?? 500))
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $html = (string) ($eventsResult['output'] ?? '');
        if ($pollerResult && !$pollerResult['ok']) {
            Log::warning('poller.php execution failed before events view render.', [
                'device_id' => $device->id,
                'message' => $pollerResult['message'] ?? null,
                'output' => $pollerResult['output'] ?? null,
            ]);
        }

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function showGraphsPage(Request $request)
    {
        $unit = strtolower(trim((string) $request->query('unit', 'mbit')));
        $unitMap = [
            'kbit' => ['label' => 'kbit/s', 'divisor' => 1000],
            'mbit' => ['label' => 'Mbit/s', 'divisor' => 1000000],
            'gbit' => ['label' => 'Gbit/s', 'divisor' => 1000000000],
        ];
        if (!isset($unitMap[$unit])) {
            $unit = 'mbit';
        }

        $trendModes = [
            'weekly' => [
                'label' => 'Weekly',
                'days' => 7,
                'aggregation' => 'interval',
                'bucket_seconds' => 3600,
                'description' => 'Hourly trend across the selected week.',
            ],
            'monthly' => [
                'label' => 'Monthly',
                'days' => 30,
                'aggregation' => 'interval',
                'bucket_seconds' => 86400,
                'description' => 'Daily trend across the selected month.',
            ],
            'yearly' => [
                'label' => 'Yearly',
                'days' => 365,
                'aggregation' => 'month',
                'bucket_seconds' => null,
                'description' => 'Month-over-month trend across the selected year.',
            ],
        ];
        $trendMode = strtolower(trim((string) $request->query('trend_mode', 'weekly')));
        if (!isset($trendModes[$trendMode])) {
            $trendMode = 'weekly';
        }

        $name = trim((string) $request->query('device', ''));
        $device = $this->resolveTargetDevice($request, $name !== '' ? $name : null);
        if (!$device) {
            return $this->plainError('Device not found.', 404);
        }

        $deviceOptions = Device::query()
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        // Render quickly from stored samples by default.
        // Pass poll=1/true/yes/on for an immediate manual poll before rendering.
        $poll = strtolower((string) $request->query('poll', '0'));
        $shouldPoll = in_array($poll, ['1', 'true', 'yes', 'on'], true);
        $pollMessage = null;
        if ($shouldPoll) {
            $pollResult = $this->runPollerScript();
            if (!$pollResult['ok']) {
                $pollMessage = 'poller.php failed before graph render; showing stored data only.';
                Log::warning('poller.php failed before graph render.', [
                    'device_id' => $device->id,
                    'message' => $pollResult['message'] ?? null,
                    'output' => $pollResult['output'] ?? null,
                ]);
            }
        }

        $schema = DB::getSchemaBuilder();
        if (!$schema->hasTable('interfaces') || !$schema->hasTable('interface_samples')) {
            return view('device_graphs', [
                'device' => $device,
                'unit' => $unit,
                'unitLabel' => $unitMap[$unit]['label'],
                'unitOptions' => $unitMap,
                'deviceOptions' => $deviceOptions,
                'interfaces' => [],
                'selectedInterface' => null,
                'selectedIfaceId' => null,
                'fixedGraphs' => [],
                'trendGraph' => $this->emptyTrafficGraph(
                    'Custom Trend',
                    'No graph data available.',
                    0,
                    0,
                    $unitMap[$unit]['label']
                ),
                'error' => 'Missing interfaces/interface_samples tables.',
                'note' => $pollMessage,
                'trendMode' => $trendMode,
                'trendModes' => $trendModes,
                'trendStartDate' => '',
                'trendEndDate' => '',
                'chartPayload' => [],
            ]);
        }

        $interfaces = DB::table('interfaces')
            ->where('device_id', $device->id)
            ->orderBy('ifIndex')
            ->get(['id', 'ifIndex', 'ifName', 'ifDescr', 'ifAlias', 'speed_bps', 'is_up']);

        if ($interfaces->isEmpty()) {
            return view('device_graphs', [
                'device' => $device,
                'unit' => $unit,
                'unitLabel' => $unitMap[$unit]['label'],
                'unitOptions' => $unitMap,
                'deviceOptions' => $deviceOptions,
                'interfaces' => [],
                'selectedInterface' => null,
                'selectedIfaceId' => null,
                'fixedGraphs' => [],
                'trendGraph' => $this->emptyTrafficGraph(
                    'Custom Trend',
                    'No graph data available.',
                    0,
                    0,
                    $unitMap[$unit]['label']
                ),
                'error' => null,
                'note' => 'No SNMP interfaces discovered yet for this device. Check SNMP community and poller.',
                'trendMode' => $trendMode,
                'trendModes' => $trendModes,
                'trendStartDate' => '',
                'trendEndDate' => '',
                'chartPayload' => [],
            ]);
        }

        $selectedIfaceId = (int) $request->query('iface', 0);
        $selectedIfIndex = (int) $request->query('ifIndex', 0);

        $selectedInterface = null;
        if ($selectedIfaceId > 0) {
            $selectedInterface = $interfaces->firstWhere('id', $selectedIfaceId);
        }
        if (!$selectedInterface && $selectedIfIndex > 0) {
            $selectedInterface = $interfaces->firstWhere('ifIndex', $selectedIfIndex);
        }
        if (!$selectedInterface) {
            $ifaceIds = $interfaces->pluck('id')->all();
            $ifaceWithSamples = DB::table('interface_samples')
                ->whereIn('interface_id', $ifaceIds)
                ->orderByDesc('ts')
                ->value('interface_id');

            if ($ifaceWithSamples) {
                $selectedInterface = $interfaces->firstWhere('id', (int) $ifaceWithSamples);
            }
        }
        if (!$selectedInterface) {
            $selectedInterface = $interfaces->first();
        }

        $selectedIfaceId = (int) $selectedInterface->id;
        $divisor = (float) $unitMap[$unit]['divisor'];
        $fixedRangeConfigs = [
            [
                'key' => 'five_minutes',
                'title' => '5 Minutes',
                'subtitle' => 'Immediate burst visibility.',
                'seconds' => 300,
                'aggregation' => 'raw',
                'bucket_seconds' => null,
                'max_points' => 300,
            ],
            [
                'key' => 'one_hour',
                'title' => '1 Hour',
                'subtitle' => 'Short-term traffic behavior.',
                'seconds' => 3600,
                'aggregation' => 'raw',
                'bucket_seconds' => null,
                'max_points' => 360,
            ],
            [
                'key' => 'twenty_four_hours',
                'title' => '24 Hours',
                'subtitle' => 'Intraday trend with 5-minute buckets.',
                'seconds' => 86400,
                'aggregation' => 'interval',
                'bucket_seconds' => 300,
                'max_points' => 288,
            ],
            [
                'key' => 'seven_days',
                'title' => '7 Days',
                'subtitle' => 'Multi-day trend with hourly buckets.',
                'seconds' => 604800,
                'aggregation' => 'interval',
                'bucket_seconds' => 3600,
                'max_points' => 240,
            ],
        ];

        $fixedGraphs = [];
        $now = time();
        foreach ($fixedRangeConfigs as $config) {
            $rangeEnd = $now;
            $rangeStart = max(0, $rangeEnd - (int) $config['seconds'] + 1);
            $fixedGraphs[] = $this->buildTrafficGraph(
                $selectedIfaceId,
                $rangeStart,
                $rangeEnd,
                $divisor,
                $unitMap[$unit]['label'],
                $config
            );
        }

        $defaultTrendEndDate = date('Y-m-d');
        $defaultTrendStartDate = date('Y-m-d', strtotime('-' . ($trendModes[$trendMode]['days'] - 1) . ' days'));
        $trendStartDate = $this->normalizeGraphDateInput((string) $request->query('trend_start', ''), $defaultTrendStartDate);
        $trendEndDate = $this->normalizeGraphDateInput((string) $request->query('trend_end', ''), $defaultTrendEndDate);

        if ($trendStartDate > $trendEndDate) {
            [$trendStartDate, $trendEndDate] = [$trendEndDate, $trendStartDate];
        }

        $trendStartTs = strtotime($trendStartDate . ' 00:00:00') ?: strtotime($defaultTrendStartDate . ' 00:00:00');
        $trendEndTs = strtotime($trendEndDate . ' 23:59:59') ?: strtotime($defaultTrendEndDate . ' 23:59:59');
        $trendEndTs = min($trendEndTs, $now);

        if ($trendStartTs > $trendEndTs) {
            $trendStartTs = max(0, $trendEndTs - (($trendModes[$trendMode]['days'] * 86400) - 1));
            $trendStartDate = date('Y-m-d', $trendStartTs);
        }

        $trendGraph = $this->buildTrafficGraph(
            $selectedIfaceId,
            $trendStartTs,
            $trendEndTs,
            $divisor,
            $unitMap[$unit]['label'],
            [
                'key' => 'custom_trend',
                'title' => $trendModes[$trendMode]['label'] . ' Trend',
                'subtitle' => $trendModes[$trendMode]['description'],
                'aggregation' => $trendModes[$trendMode]['aggregation'],
                'bucket_seconds' => $trendModes[$trendMode]['bucket_seconds'],
                'max_points' => 366,
                'empty_message' => 'No graph data found in the selected custom date range.',
            ]
        );

        $chartPayload = [];
        foreach (array_merge($fixedGraphs, [$trendGraph]) as $graph) {
            $chartPayload[] = [
                'domId' => $graph['dom_id'],
                'labels' => $graph['labels'],
                'inSeries' => $graph['in_series'],
                'outSeries' => $graph['out_series'],
                'unitLabel' => $unitMap[$unit]['label'],
                'hasData' => $graph['has_data'],
            ];
        }

        return view('device_graphs', [
            'device' => $device,
            'unit' => $unit,
            'unitLabel' => $unitMap[$unit]['label'],
            'unitOptions' => $unitMap,
            'deviceOptions' => $deviceOptions,
            'interfaces' => $interfaces,
            'selectedInterface' => $selectedInterface,
            'selectedIfaceId' => $selectedIfaceId,
            'fixedGraphs' => $fixedGraphs,
            'trendGraph' => $trendGraph,
            'error' => null,
            'note' => $pollMessage,
            'trendMode' => $trendMode,
            'trendModes' => $trendModes,
            'trendStartDate' => $trendStartDate,
            'trendEndDate' => $trendEndDate,
            'chartPayload' => $chartPayload,
        ]);
    }

    private function buildTrafficGraph(
        int $interfaceId,
        int $rangeStart,
        int $rangeEnd,
        float $divisor,
        string $unitLabel,
        array $config
    ): array {
        $title = (string) ($config['title'] ?? 'Traffic');
        $subtitle = (string) ($config['subtitle'] ?? '');
        $aggregation = (string) ($config['aggregation'] ?? 'raw');
        $bucketSeconds = isset($config['bucket_seconds']) && is_numeric($config['bucket_seconds'])
            ? (int) $config['bucket_seconds']
            : null;
        $maxPoints = isset($config['max_points']) && is_numeric($config['max_points'])
            ? max(1, (int) $config['max_points'])
            : 480;
        $emptyMessage = (string) ($config['empty_message'] ?? 'No graph data available for this range.');
        $domId = 'graph-' . preg_replace('/[^a-z0-9]+/i', '-', (string) ($config['key'] ?? $title));

        $rows = DB::table('interface_samples')
            ->where('interface_id', $interfaceId)
            ->whereBetween('ts', [$rangeStart, $rangeEnd])
            ->orderBy('ts')
            ->get(['ts', 'in_bps', 'out_bps']);

        if ($rows->isEmpty()) {
            return $this->emptyTrafficGraph($title, $emptyMessage, $rangeStart, $rangeEnd, $unitLabel, $domId, $subtitle);
        }

        $stats = [
            'in' => [
                'current' => round(((float) (($rows->last()->in_bps ?? 0))) / $divisor, 3),
                'average' => round((float) $rows->avg(static fn ($row) => ((float) ($row->in_bps ?? 0)) / $divisor), 3),
                'maximum' => round(((float) $rows->max('in_bps')) / $divisor, 3),
            ],
            'out' => [
                'current' => round(((float) (($rows->last()->out_bps ?? 0))) / $divisor, 3),
                'average' => round((float) $rows->avg(static fn ($row) => ((float) ($row->out_bps ?? 0)) / $divisor), 3),
                'maximum' => round(((float) $rows->max('out_bps')) / $divisor, 3),
            ],
        ];

        $preparedRows = $this->aggregateTrafficRows($rows, $aggregation, $bucketSeconds);
        if ($preparedRows->count() > $maxPoints) {
            $step = (int) ceil($preparedRows->count() / $maxPoints);
            $preparedRows = $preparedRows->values()->filter(static function ($row, $index) use ($step) {
                return ($index % $step) === 0;
            })->values();
        }

        $span = max(1, $rangeEnd - $rangeStart);
        $labels = [];
        $inSeries = [];
        $outSeries = [];
        foreach ($preparedRows as $row) {
            $ts = (int) ($row['ts'] ?? 0);
            $labels[] = $this->formatTrafficAxisLabel($ts, $aggregation, $span);
            $inSeries[] = round(((float) ($row['in_bps'] ?? 0)) / $divisor, 3);
            $outSeries[] = round(((float) ($row['out_bps'] ?? 0)) / $divisor, 3);
        }

        $actualStartTs = (int) (($rows->first()->ts ?? $rangeStart));
        $actualEndTs = (int) (($rows->last()->ts ?? $rangeEnd));

        return [
            'dom_id' => $domId,
            'title' => $title,
            'subtitle' => $subtitle,
            'labels' => $labels,
            'in_series' => $inSeries,
            'out_series' => $outSeries,
            'has_data' => !empty($labels),
            'unit_label' => $unitLabel,
            'stats' => $stats,
            'sample_count' => $rows->count(),
            'from_text' => date('Y-m-d H:i:s', $actualStartTs),
            'to_text' => date('Y-m-d H:i:s', $actualEndTs),
            'empty_message' => $emptyMessage,
        ];
    }

    private function emptyTrafficGraph(
        string $title,
        string $emptyMessage,
        int $rangeStart,
        int $rangeEnd,
        string $unitLabel,
        string $domId = 'graph-empty',
        string $subtitle = ''
    ): array {
        return [
            'dom_id' => $domId,
            'title' => $title,
            'subtitle' => $subtitle,
            'labels' => [],
            'in_series' => [],
            'out_series' => [],
            'has_data' => false,
            'unit_label' => $unitLabel,
            'stats' => [
                'in' => ['current' => 0, 'average' => 0, 'maximum' => 0],
                'out' => ['current' => 0, 'average' => 0, 'maximum' => 0],
            ],
            'sample_count' => 0,
            'from_text' => $rangeStart > 0 ? date('Y-m-d H:i:s', $rangeStart) : '-',
            'to_text' => $rangeEnd > 0 ? date('Y-m-d H:i:s', $rangeEnd) : '-',
            'empty_message' => $emptyMessage,
        ];
    }

    private function aggregateTrafficRows(Collection $rows, string $aggregation, ?int $bucketSeconds = null): Collection
    {
        if ($aggregation === 'month') {
            return $rows
                ->groupBy(static fn ($row) => date('Y-m', (int) ($row->ts ?? 0)))
                ->map(static function (Collection $group, string $monthKey): array {
                    $monthTs = strtotime($monthKey . '-01 00:00:00') ?: (int) ($group->first()->ts ?? 0);

                    return [
                        'ts' => $monthTs,
                        'in_bps' => (float) $group->avg(static fn ($row) => (float) ($row->in_bps ?? 0)),
                        'out_bps' => (float) $group->avg(static fn ($row) => (float) ($row->out_bps ?? 0)),
                    ];
                })
                ->sortBy('ts')
                ->values();
        }

        if ($aggregation === 'interval' && $bucketSeconds && $bucketSeconds > 0) {
            return $rows
                ->groupBy(static fn ($row) => (int) floor(((int) ($row->ts ?? 0)) / $bucketSeconds))
                ->map(static function (Collection $group, int $bucketKey) use ($bucketSeconds): array {
                    return [
                        'ts' => $bucketKey * $bucketSeconds,
                        'in_bps' => (float) $group->avg(static fn ($row) => (float) ($row->in_bps ?? 0)),
                        'out_bps' => (float) $group->avg(static fn ($row) => (float) ($row->out_bps ?? 0)),
                    ];
                })
                ->sortBy('ts')
                ->values();
        }

        return $rows->map(static function ($row): array {
            return [
                'ts' => (int) ($row->ts ?? 0),
                'in_bps' => (float) ($row->in_bps ?? 0),
                'out_bps' => (float) ($row->out_bps ?? 0),
            ];
        })->values();
    }

    private function formatTrafficAxisLabel(int $ts, string $aggregation, int $span): string
    {
        if ($ts <= 0) {
            return '-';
        }

        if ($aggregation === 'month') {
            return date('M Y', $ts);
        }

        if ($aggregation === 'interval' && $span >= 86400 * 20) {
            return date('M j', $ts);
        }

        if ($aggregation === 'interval' && $span >= 86400 * 2) {
            return date('M j H:i', $ts);
        }

        if ($span >= 86400) {
            return date('M j H:i', $ts);
        }

        return date('H:i', $ts);
    }

    private function normalizeGraphDateInput(string $value, string $fallback): string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return $fallback;
        }

        return $value;
    }

    private function runExec(Device $device, string $cmd, Request $request)
    {
        $cmdKey = strtolower(trim($cmd));
        $cisco = data_get($device->metadata ?? [], 'cisco', []);
        $switchModel = $this->resolveSwitchModel($device, $cisco);
        $isNexus = $this->shouldUseNexus($device, $cisco, $switchModel);
        $traceContext = $this->deviceTraceContext($device, [
            'trace' => 'command execution',
            'trigger' => $request->route()?->getName() ?: 'scripts.exec',
            'request_method' => $request->getMethod(),
            'request_path' => $request->path(),
            'request_ip' => $request->ip(),
            'actor_id' => optional($request->user())->id,
            'actor' => optional($request->user())->name,
            'command_key' => $cmdKey,
            'switch_model' => $switchModel !== '' ? $switchModel : null,
            'is_nexus' => $isNexus,
        ]);

        ProvisioningTrace::log('command trace: request received', $traceContext);

        $commandMap = $isNexus ? self::NEXUS_COMMANDS : self::EXEC_COMMANDS;
        $command = $commandMap[$cmdKey] ?? self::EXEC_COMMANDS[$cmdKey] ?? null;
        if (!$command) {
            $template = CommandTemplate::where('action_key', $cmdKey)
                ->where('active', true)
                ->first();

            if ($template) {
                ProvisioningTrace::log('command trace: using custom command template', $traceContext + [
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                ]);

                $scriptCode = trim((string) ($template->script_code ?? ''));
                if ($scriptCode !== '') {
                    return $this->runCustomTemplate($device, $cmdKey, $template, $request);
                }

                $scriptName = trim((string) ($template->script_name ?? ''));
                if ($scriptName !== '') {
                    $customScriptPath = $this->resolveCustomScriptPath($scriptName);
                    if ($customScriptPath) {
                        return $this->runCustomTemplateScript($device, $cmdKey, $template, $request, $customScriptPath);
                    }
                }

                $derivedName = preg_replace('/^custom_command_/', '', $cmdKey);
                if (is_string($derivedName) && $derivedName !== '') {
                    $derivedScriptPath = $this->resolveCustomScriptPath($derivedName);
                    if ($derivedScriptPath) {
                        return $this->runCustomTemplateScript($device, $cmdKey, $template, $request, $derivedScriptPath);
                    }
                }

                ProvisioningTrace::log('command trace: custom template has no runnable script', $traceContext + [
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                ]);

                return $this->plainError(
                    "Custom command '{$cmdKey}' is configured but has no runnable script content. Edit it and add script code.",
                    400
                );
            }

            ProvisioningTrace::log('command trace: unsupported command requested', $traceContext);
            return $this->plainError("Unsupported command: {$cmdKey}", 400);
        }

        $interface = $this->firstNonEmpty(
            $request->query('iface'),
            $request->query('interface')
        );
        $description = $this->firstNonEmpty(
            $request->query('description'),
            $request->query('desc')
        );

        $missing = [];
        foreach ($command['needs'] ?? [] as $need) {
            if ($need === 'interface' && !$interface) {
                $missing[] = 'interface';
            }
            if ($need === 'description' && !$description) {
                $missing[] = 'description';
            }
        }
        if ($missing) {
            ProvisioningTrace::log('command trace: validation failed - missing parameters', $traceContext + [
                'missing' => $missing,
            ]);
            return $this->plainError('Missing parameters: ' . implode(', ', $missing), 400);
        }

        $scriptPath = $this->scriptPath($command['script']);
        if (!$scriptPath) {
            ProvisioningTrace::log('command trace: script missing', $traceContext + [
                'script_name' => $command['script'],
            ]);
            return $this->plainError('Script not found: ' . $command['script'], 404);
        }

        $ip = $this->firstNonEmpty(
            data_get($cisco, 'ip_address'),
            $device->ip_address
        );
        $password = $this->decryptValue(data_get($cisco, 'password'));
        $enablePassword = $this->decryptValue(data_get($cisco, 'enable_password'));
        $username = $this->firstNonEmpty(
            $request->query('username'),
            data_get($cisco, 'username'),
            data_get($cisco, 'user')
        );

        if (!$ip || !$password) {
            ProvisioningTrace::log('command trace: validation failed - missing device IP or password', $traceContext);
            return $this->plainError('Missing device IP or password.', 400);
        }

        if (!$isNexus && !$enablePassword) {
            ProvisioningTrace::log('command trace: validation failed - missing enable password', $traceContext);
            return $this->plainError('Missing enable password for this device.', 400);
        }

        if ($isNexus && !$username) {
            ProvisioningTrace::log('command trace: validation failed - missing Nexus username', $traceContext);
            return $this->plainError('Missing switch username for Nexus device.', 400);
        }

        $env = array_filter([
            'SWITCH_IP' => $ip,
            'SWITCH_NAME' => data_get($cisco, 'name') ?? $device->name,
            'SWITCH_PASS' => $password,
            'SWITCH_ENA' => $enablePassword,
            'SWITCH_USERNAME' => $username,
            'INTERFACE' => $interface,
            'DESCRIPTION' => $description,
            'CMD' => $cmdKey,
        ], static fn ($value) => $value !== null && $value !== '');

        $env = array_merge($env, $command['env'] ?? []);

        ProvisioningTrace::log('command trace: execution prepared', $traceContext + [
            'script_name' => $command['script'],
            'script_path' => $scriptPath,
            'switch_ip' => $ip,
            'interface' => $interface,
            'description_present' => $description !== null && $description !== '',
            'env_keys' => array_values(array_keys($env)),
        ]);

        $result = $this->runProcess(['bash', $scriptPath], $env, $traceContext + [
            'label' => 'command trace',
            'line_prefix' => 'command trace',
            'script_name' => $command['script'],
            'script_path' => $scriptPath,
            'switch_ip' => $ip,
            'command' => ['bash', $scriptPath],
            'secret_values' => array_values(array_filter([$password, $enablePassword])),
        ]);
        if (isset($result['output']) && is_string($result['output']) && $cmdKey !== 'showlog') {
            $result['output'] = $this->cleanupTransportNoise($result['output']);
        }

        return $result;
    }

    private function runCustomTemplate(Device $device, string $cmdKey, CommandTemplate $template, Request $request): array
    {
        $scriptCode = trim((string) ($template->script_code ?? ''));
        if ($scriptCode === '') {
            ProvisioningTrace::log('command trace: custom inline script is empty', $this->deviceTraceContext($device, [
                'trace' => 'command execution',
                'command_key' => $cmdKey,
                'template_id' => $template->id,
            ]));

            return [
                'ok' => false,
                'status' => 400,
                'message' => 'Custom script code is empty.',
                'output' => '',
            ];
        }

        $env = $this->buildCustomCommandEnv($device, $cmdKey, $template, $request);

        return $this->runProcess(['bash', '-lc', $scriptCode], $env, $this->deviceTraceContext($device, [
            'trace' => 'command execution',
            'trigger' => $request->route()?->getName() ?: 'scripts.exec',
            'command_key' => $cmdKey,
            'template_id' => $template->id,
            'template_name' => $template->name,
            'script_source' => 'inline_code',
            'command' => ['bash', '-lc', '[INLINE SCRIPT REDACTED]'],
            'label' => 'command trace',
            'line_prefix' => 'command trace',
            'secret_values' => array_values(array_filter([
                $env['SWITCH_PASS'] ?? null,
                $env['SWITCH_ENA'] ?? null,
            ])),
        ]));
    }

    private function runCustomTemplateScript(
        Device $device,
        string $cmdKey,
        CommandTemplate $template,
        Request $request,
        string $scriptPath
    ): array {
        $env = $this->buildCustomCommandEnv($device, $cmdKey, $template, $request);
        return $this->runProcess(['bash', $scriptPath], $env, $this->deviceTraceContext($device, [
            'trace' => 'command execution',
            'trigger' => $request->route()?->getName() ?: 'scripts.exec',
            'command_key' => $cmdKey,
            'template_id' => $template->id,
            'template_name' => $template->name,
            'script_source' => 'custom_script',
            'script_path' => $scriptPath,
            'command' => ['bash', $scriptPath],
            'label' => 'command trace',
            'line_prefix' => 'command trace',
            'secret_values' => array_values(array_filter([
                $env['SWITCH_PASS'] ?? null,
                $env['SWITCH_ENA'] ?? null,
            ])),
        ]));
    }

    private function buildCustomCommandEnv(Device $device, string $cmdKey, CommandTemplate $template, Request $request): array
    {
        $cisco = data_get($device->metadata ?? [], 'cisco', []);
        $interface = $this->firstNonEmpty(
            $request->query('iface'),
            $request->query('interface')
        );
        $description = $this->firstNonEmpty(
            $request->query('description'),
            $request->query('desc')
        );

        return array_filter([
            'SWITCH_IP' => $this->firstNonEmpty(data_get($cisco, 'ip_address'), $device->ip_address),
            'SWITCH_NAME' => data_get($cisco, 'name') ?? $device->name,
            'SWITCH_PASS' => $this->decryptValue(data_get($cisco, 'password')),
            'SWITCH_ENA' => $this->decryptValue(data_get($cisco, 'enable_password')),
            'SWITCH_USERNAME' => $this->firstNonEmpty(
                $request->query('username'),
                data_get($cisco, 'username'),
                data_get($cisco, 'user')
            ),
            'INTERFACE' => $interface,
            'DESCRIPTION' => $description,
            'CMD' => $cmdKey,
            'CUSTOM_SCRIPT_NAME' => trim((string) ($template->script_name ?? '')),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function resolveCustomScriptPath(string $rawName): ?string
    {
        $base = trim((string) $rawName);
        if ($base === '') {
            return null;
        }

        $base = basename($base);
        $candidates = [$base];
        if (!str_contains($base, '.')) {
            $candidates[] = $base . '.sh';
            $candidates[] = $base . '.bash';
        }

        foreach ($candidates as $candidate) {
            $path = $this->scriptPath($candidate);
            if ($path) {
                return $path;
            }
        }

        return null;
    }

    private function executeBackup(Device $device, Request $request): array
    {
        $cisco = data_get($device->metadata ?? [], 'cisco', []);
        $switchModel = $this->resolveSwitchModel($device, $cisco);
        $isNexus = $this->shouldUseNexus($device, $cisco, $switchModel);
        $scriptName = $this->resolveBackupScriptName($device, $cisco, $switchModel);
        $is3560 = $scriptName === '3560_backup.sh';
        $traceContext = $this->deviceTraceContext($device, [
            'trace' => 'backup execution',
            'trigger' => $request->route()?->getName() ?: 'backup',
            'request_method' => $request->getMethod(),
            'request_path' => $request->path(),
            'request_ip' => $request->ip(),
            'actor_id' => optional($request->user())->id,
            'actor' => optional($request->user())->name,
        ]);

        ProvisioningTrace::log('backup trace: request received', $traceContext + [
            'switch_model' => $switchModel !== '' ? $switchModel : null,
            'is_nexus' => $isNexus,
            'is_3560' => $is3560,
            'script_name' => $scriptName,
        ]);

        $scriptPath = $this->scriptPath($scriptName);
        if (!$scriptPath) {
            ProvisioningTrace::log('backup trace: backup script missing', $traceContext + [
                'script_name' => $scriptName,
            ]);

            return [
                'ok' => false,
                'status' => 404,
                'message' => 'Script not found: ' . $scriptName,
                'output' => '',
            ];
        }

        $ip = $this->firstNonEmpty(
            data_get($cisco, 'ip_address'),
            $device->ip_address
        );
        $password = $this->decryptValue(data_get($cisco, 'password'));
        $enablePassword = $this->decryptValue(data_get($cisco, 'enable_password'));
        $username = $this->firstNonEmpty(
            $request->query('username'),
            data_get($cisco, 'username'),
            data_get($cisco, 'user')
        );
        $location = $this->firstNonEmpty(
            data_get($cisco, 'folder_location'),
            data_get($device->metadata ?? [], 'folder_location')
        );
        $usedFallbackLocation = false;

        if (!$location) {
            $fallbackName = data_get($cisco, 'name') ?? $device->name ?? 'device';
            $fallbackSlug = preg_replace('/\s+/', '_', trim((string) $fallbackName));
            $location = 'uno/' . ($fallbackSlug !== '' ? $fallbackSlug : 'device');
            $usedFallbackLocation = true;
        }
        $resolvedBackupDirectory = $this->prepareBackupDirectory($location);
        if (!$resolvedBackupDirectory) {
            ProvisioningTrace::log('backup trace: failed to prepare backup directory', $traceContext + [
                'switch_model' => $switchModel !== '' ? $switchModel : null,
                'is_nexus' => $isNexus,
                'is_3560' => $is3560,
                'switch_ip' => $ip,
                'location' => $location,
            ]);

            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Backup folder could not be prepared.',
                'output' => 'Backup folder could not be prepared.',
            ];
        }
        $backupSnapshot = $this->snapshotBackupFiles($resolvedBackupDirectory);

        ProvisioningTrace::log('backup trace: resolved backup inputs', $traceContext + [
            'script_name' => $scriptName,
            'script_path' => $scriptPath,
            'switch_model' => $switchModel !== '' ? $switchModel : null,
            'is_nexus' => $isNexus,
            'is_3560' => $is3560,
            'switch_ip' => $ip,
            'location' => $location,
            'location_source' => $usedFallbackLocation ? 'fallback' : 'configured',
            'password_present' => $password !== null && $password !== '',
            'enable_password_present' => $enablePassword !== null && $enablePassword !== '',
            'username_present' => $username !== null && $username !== '',
        ]);

        if (!$ip || !$password) {
            ProvisioningTrace::log('backup trace: validation failed - missing IP or password', $traceContext);

            return [
                'ok' => false,
                'status' => 400,
                'message' => 'Missing device IP or password.',
                'output' => '',
            ];
        }

        if ($isNexus) {
            if (!$username) {
                ProvisioningTrace::log('backup trace: validation failed - missing Nexus username', $traceContext);

                return [
                    'ok' => false,
                    'status' => 400,
                    'message' => 'Missing switch username for Nexus backup.',
                    'output' => '',
                ];
            }

            $command = ['bash', $scriptPath, $ip, $username, $password, $location];

            $result = $this->runProcess($command, [], $traceContext + [
                'label' => 'backup trace',
                'line_prefix' => 'backup trace',
                'script_name' => $scriptName,
                'script_path' => $scriptPath,
                'switch_model' => $switchModel !== '' ? $switchModel : null,
                'is_nexus' => true,
                'switch_ip' => $ip,
                'location' => $location,
                'command' => $this->redactBackupCommand($command, true),
                'secret_values' => array_values(array_filter([$password])),
            ]);

            return $this->verifyBackupArtifact($device, $result, $backupSnapshot, $traceContext + [
                'script_name' => $scriptName,
                'script_path' => $scriptPath,
                'switch_model' => $switchModel !== '' ? $switchModel : null,
                'is_nexus' => true,
                'is_3560' => false,
                'switch_ip' => $ip,
                'location' => $location,
            ]);
        }

        if (!$enablePassword) {
            ProvisioningTrace::log('backup trace: validation failed - missing enable password', $traceContext);

            return [
                'ok' => false,
                'status' => 400,
                'message' => 'Missing enable password for backup.',
                'output' => '',
            ];
        }

        if ($is3560) {
            $command = ['bash', $scriptPath, $ip, $password, $enablePassword, $location];
            if ($username) {
                $command[] = $username;
            }

            $result = $this->runProcess($command, [], $traceContext + [
                'label' => 'backup trace',
                'line_prefix' => 'backup trace',
                'script_name' => $scriptName,
                'script_path' => $scriptPath,
                'switch_model' => $switchModel !== '' ? $switchModel : null,
                'is_nexus' => false,
                'is_3560' => true,
                'switch_ip' => $ip,
                'location' => $location,
                'command' => $this->redactBackupCommand($command, false),
                'secret_values' => array_values(array_filter([$password, $enablePassword])),
            ]);

            return $this->verifyBackupArtifact($device, $result, $backupSnapshot, $traceContext + [
                'script_name' => $scriptName,
                'script_path' => $scriptPath,
                'switch_model' => $switchModel !== '' ? $switchModel : null,
                'is_nexus' => false,
                'is_3560' => true,
                'switch_ip' => $ip,
                'location' => $location,
            ]);
        }

        $command = ['bash', $scriptPath, $ip, $password, $enablePassword, $location];

        $result = $this->runProcess($command, [], $traceContext + [
            'label' => 'backup trace',
            'line_prefix' => 'backup trace',
            'script_name' => $scriptName,
            'script_path' => $scriptPath,
            'switch_model' => $switchModel !== '' ? $switchModel : null,
            'is_nexus' => false,
            'is_3560' => false,
            'switch_ip' => $ip,
            'location' => $location,
            'command' => $this->redactBackupCommand($command, false),
            'secret_values' => array_values(array_filter([$password, $enablePassword])),
        ]);

        return $this->verifyBackupArtifact($device, $result, $backupSnapshot, $traceContext + [
            'script_name' => $scriptName,
            'script_path' => $scriptPath,
            'switch_model' => $switchModel !== '' ? $switchModel : null,
            'is_nexus' => false,
            'is_3560' => false,
            'switch_ip' => $ip,
            'location' => $location,
        ]);
    }

    private function runProcess(array $command, array $env = [], ?array $provisioningTrace = null): array
    {
        $process = new Process($command, base_path());
        $process->setTimeout(180);
        $baseEnv = array_filter(
            array_merge($_ENV, $_SERVER),
            static fn ($value) => is_scalar($value) || $value === null
        );
        $process->setEnv(array_merge($baseEnv, $env));
        $trace = $provisioningTrace ?? [];
        $traceResult = ProvisioningTrace::runProcess($process, [
            'label' => $trace['label'] ?? 'process trace',
            'line_prefix' => $trace['line_prefix'] ?? ($trace['label'] ?? 'process trace'),
            'context' => $trace,
            'secret_values' => $trace['secret_values'] ?? [],
            'env_keys' => array_values(array_keys($env)),
            'log_output' => $trace['log_output'] ?? true,
        ]);

        return [
            'ok' => $traceResult['ok'],
            'status' => $traceResult['ok'] ? 200 : 500,
            'message' => $traceResult['ok'] ? 'Process completed.' : 'Script execution failed.',
            'output' => $traceResult['output'],
        ];
    }

    private function snapshotBackupFiles(array $resolved): array
    {
        return [
            'resolved' => $resolved,
            'files' => $this->backupFileMap($resolved['absolute']),
        ];
    }

    private function backupFileMap(string $absolutePath): array
    {
        if (!is_dir($absolutePath)) {
            return [];
        }

        $files = [];
        foreach (File::files($absolutePath) as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $files[$file->getFilename()] = $file->getMTime();
        }

        return $files;
    }

    private function verifyBackupArtifact(Device $device, array $processResult, array $snapshot, array $traceContext): array
    {
        if (!($processResult['ok'] ?? false)) {
            return $processResult;
        }

        $resolved = $snapshot['resolved'] ?? $this->resolveBackupDirectory($device);
        if (!$resolved) {
            ProvisioningTrace::log('backup trace: verification failed - backup folder could not be resolved', $traceContext);

            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Backup folder could not be resolved after the script finished.',
                'output' => trim(($processResult['output'] ?? '') . "\nBackup verification failed: backup folder could not be resolved."),
            ];
        }

        $beforeFiles = $snapshot['files'] ?? [];
        $afterFiles = $this->backupFileMap($resolved['absolute']);
        $createdFile = null;
        $createdMtime = null;

        foreach ($afterFiles as $name => $mtime) {
            $previousMtime = $beforeFiles[$name] ?? null;
            if ($previousMtime === null || $mtime > $previousMtime) {
                if ($createdMtime === null || $mtime > $createdMtime) {
                    $createdFile = $name;
                    $createdMtime = $mtime;
                }
            }
        }

        if ($createdFile !== null) {
            ProvisioningTrace::log('backup trace: verification succeeded - backup file detected', $traceContext + [
                'backup_file' => $createdFile,
                'backup_modified_at' => date('Y-m-d H:i:s', $createdMtime),
                'backup_folder' => $resolved['relative'],
            ]);

            return $processResult;
        }

        ProvisioningTrace::log('backup trace: verification failed - no new backup file detected', $traceContext + [
            'backup_folder' => $resolved['relative'],
            'backup_path' => $resolved['absolute'],
            'existing_file_count_before' => count($beforeFiles),
            'existing_file_count_after' => count($afterFiles),
        ]);

        return [
            'ok' => false,
            'status' => 500,
            'message' => 'Backup script finished, but no new backup file was created.',
            'output' => trim(($processResult['output'] ?? '') . "\nBackup verification failed: no new backup file was created in {$resolved['relative']}."),
        ];
    }

    private function prepareBackupDirectory(string $relative): ?array
    {
        $resolved = $this->resolveBackupDirectoryFromRelative($relative);
        if (!$resolved) {
            return null;
        }

        try {
            if (!is_dir($resolved['absolute'])) {
                File::ensureDirectoryExists($resolved['absolute']);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return $resolved;
    }

    private function deviceTraceContext(Device $device, array $context = []): array
    {
        return $context + [
            'device_id' => $device->id,
            'device_name' => $device->name,
            'device_type' => $device->type,
        ];
    }

    private function redactBackupCommand(array $command, bool $isNexus): array
    {
        $redacted = $command;

        if ($isNexus) {
            if (isset($redacted[4])) {
                $redacted[4] = '[REDACTED]';
            }
        } else {
            if (isset($redacted[3])) {
                $redacted[3] = '[REDACTED]';
            }

            if (isset($redacted[4])) {
                $redacted[4] = '[REDACTED]';
            }
        }

        return $redacted;
    }

    private function runPollerScript(): array
    {
        $scriptPath = $this->scriptPath('poller.php');
        if (!$scriptPath) {
            ProvisioningTrace::log('poller trace: script missing', [
                'trace' => 'poller execution',
                'script_name' => 'poller.php',
            ]);

            return [
                'ok' => false,
                'status' => 404,
                'message' => 'poller.php not found in scripts folder.',
                'output' => '',
            ];
        }

        $phpBinary = PHP_BINARY ?: 'php';
        $command = [$phpBinary, $scriptPath];
        $process = new Process($command, base_path());
        $process->setTimeout(180);
        $result = ProvisioningTrace::runProcess($process, [
            'label' => 'poller trace',
            'line_prefix' => 'poller trace',
            'context' => [
                'trace' => 'poller execution',
                'script_name' => 'poller.php',
                'script_path' => $scriptPath,
                'command' => $command,
            ],
        ]);

        if (!$result['ok']) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'poller.php execution failed.',
                'output' => $result['output'],
            ];
        }

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'poller.php executed.',
            'output' => $result['output'],
        ];
    }

    private function runEventsScript(Device $device, Request $request): array
    {
        $scriptPath = $this->scriptPath('events.php');
        if (!$scriptPath) {
            ProvisioningTrace::log('events trace: script missing', $this->deviceTraceContext($device, [
                'trace' => 'events rendering',
                'script_name' => 'events.php',
            ]));

            return [
                'ok' => false,
                'status' => 404,
                'message' => 'events.php not found in scripts folder.',
                'output' => '',
            ];
        }

        $hours = 1;

        $type = strtolower(trim((string) $request->query('type', '')));
        if (!in_array($type, ['', 'iface', 'device'], true)) {
            $type = '';
        }

        $deviceName = $this->firstNonEmpty(
            data_get($device->metadata ?? [], 'cisco.name'),
            $device->name
        );

        $query = [
            'hours' => $hours,
            'device' => $deviceName,
        ];
        if ($type !== '') {
            $query['type'] = $type;
        }

        $phpBinary = PHP_BINARY ?: 'php';
        $bootstrap = 'parse_str($argv[1] ?? "", $_GET); $_SERVER["REQUEST_METHOD"] = "GET"; include $argv[2];';
        $command = [$phpBinary, '-d', 'display_errors=1', '-r', $bootstrap, http_build_query($query), $scriptPath];
        $process = new Process($command, base_path());
        $process->setTimeout(120);
        $result = ProvisioningTrace::runProcess($process, [
            'label' => 'events trace',
            'line_prefix' => 'events trace',
            'log_output' => false,
            'context' => $this->deviceTraceContext($device, [
                'trace' => 'events rendering',
                'trigger' => $request->route()?->getName() ?: 'devices.events.show',
                'script_name' => 'events.php',
                'script_path' => $scriptPath,
                'command' => [$phpBinary, '-d', 'display_errors=1', '-r', '[INLINE BOOTSTRAP]', http_build_query($query), $scriptPath],
                'query' => $query,
            ]),
        ]);

        if (!$result['ok']) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'events.php execution failed.',
                'output' => $result['output'],
            ];
        }

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'events.php executed.',
            'output' => $result['stdout'] !== '' ? $result['stdout'] : $result['output'],
        ];
    }

    private function buildBackupFilePayload(Device $device): array
    {
        $resolved = $this->resolveBackupDirectory($device);
        if (!$resolved) {
            return [
                'files' => [],
                'folder' => null,
            ];
        }

        $relative = $resolved['relative'];
        $absolute = $resolved['absolute'];

        if (!File::isDirectory($absolute)) {
            return [
                'files' => [],
                'folder' => $relative,
            ];
        }

        $files = collect(File::files($absolute))
            ->filter(static fn ($file) => $file->isFile())
            ->sortByDesc(static fn ($file) => $file->getMTime())
            ->values()
            ->take(100)
            ->map(function ($file) use ($device) {
                $name = $file->getFilename();
                $size = $file->getSize();
                $modifiedTs = $file->getMTime();

        return [
                    'name' => $name,
                    'size_bytes' => $size,
                    'size_human' => $this->formatBytes($size),
                    'modified_at' => date('Y-m-d H:i:s', $modifiedTs),
                    'download_url' => route('devices.backups.download', ['device' => $device->id, 'file' => $name]),
                ];
            })
            ->all();

        return [
            'files' => $files,
            'folder' => $relative,
        ];
    }

    private function resolveBackupDirectory(Device $device): ?array
    {
        $meta = $device->metadata ?? [];
        $cisco = data_get($meta, 'cisco', []);
        $relative = $this->firstNonEmpty(
            data_get($cisco, 'folder_location'),
            data_get($meta, 'folder_location')
        );

        if (!$relative) {
            $fallbackName = $this->firstNonEmpty(data_get($cisco, 'name'), $device->name, 'device');
            $fallbackSlug = preg_replace('/\s+/', '_', (string) $fallbackName);
            $relative = 'uno/' . ($fallbackSlug !== '' ? $fallbackSlug : 'device');
        }

        return $this->resolveBackupDirectoryFromRelative($relative);
    }

    private function resolveBackupDirectoryFromRelative(string $relative): ?array
    {
        $relative = trim(str_replace('\\', '/', (string) $relative), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $roots = $this->backupRoots();
        $firstCandidate = null;
        foreach ($roots as $root) {
            $candidate = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if ($firstCandidate === null) {
                $firstCandidate = $candidate;
            }
            if (is_dir($candidate)) {
        return [
                    'relative' => $relative,
                    'absolute' => $candidate,
                ];
            }
        }

        return [
            'relative' => $relative,
            'absolute' => $firstCandidate ?: base_path($relative),
        ];
    }

    private function backupRoots(): array
    {
        $roots = [];

        $configuredRoots = (string) env('BACKUP_ROOTS', '');
        if ($configuredRoots !== '') {
            foreach (explode(',', $configuredRoots) as $root) {
                $root = trim($root);
                if ($root !== '') {
                    $roots[] = $root;
                }
            }
        }

        $singleRoot = trim((string) env('BACKUP_ROOT', ''));
        if ($singleRoot !== '') {
            $roots[] = $singleRoot;
        }

        $roots = array_merge($roots, [
            base_path(),
            '/var/www/html',
            '/srv/tftp',
            '/srv/tftpboot',
            '/var/lib/tftpboot',
            '/var/tftpboot',
            '/tftpboot',
        ]);

        $normalized = [];
        foreach ($roots as $root) {
            $root = rtrim(str_replace('\\', '/', (string) $root), '/');
            if ($root === '') {
                continue;
            }
            if (!in_array($root, $normalized, true)) {
                $normalized[] = $root;
            }
        }

        return $normalized;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'TB') {
                return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . ' ' . $unit;
            }
            $value /= 1024;
        }

        return $bytes . ' B';
    }

    private function scriptPath(string $scriptName): ?string
    {
        $scriptName = basename($scriptName);
        $path = base_path('scripts/' . $scriptName);
        return is_file($path) ? $path : null;
    }

    private function resolveTargetDevice(Request $request, ?string $name = null): ?Device
    {
        $id = $request->query('id', $request->query('device'));
        if (is_scalar($id) && is_numeric((string) $id)) {
            $device = Device::find((int) $id);
            if ($device) {
                return $device;
            }
        }

        if ($name !== null && trim($name) !== '') {
            return $this->findDeviceByName($name);
        }

        return null;
    }

    private function authorizeCommandExecution(Request $request, Device $device, string $cmd): ?\Symfony\Component\HttpFoundation\Response
    {
        $role = (string) $request->session()->get('auth.role', '');
        if ($role === 'admin') {
            return null;
        }

        $userId = (int) $request->session()->get('auth.user_id', 0);
        if ($userId <= 0) {
            return $this->plainError('Unauthorized.', 401);
        }

        $actionKey = strtolower(trim($cmd));
        $templateId = DB::table('command_templates')
            ->where('action_key', $actionKey)
            ->where('active', true)
            ->value('id');

        if (!$templateId) {
            return $this->plainError('Forbidden: command is not allowed.', 403);
        }

        $permissionColumns = ['allowed_ports'];
        if (DevicePermission::supportsAllowedCommandTemplateIds()) {
            $permissionColumns[] = 'allowed_command_template_ids';
        }

        $permissionRow = DB::table('device_permissions')
            ->where('device_id', $device->id)
            ->where('user_id', $userId)
            ->first($permissionColumns);

        $deviceAssigned = ((int) ($device->assigned_user_id ?? 0) === $userId)
            || DB::table('device_assignments')
                ->where('device_id', $device->id)
                ->where('user_id', $userId)
                ->whereNull('unassigned_at')
                ->exists()
            || ($permissionRow !== null);

        if (!$deviceAssigned) {
            return $this->plainError('Forbidden: device is not assigned.', 403);
        }

        $allowedCommandTemplateIds = DevicePermission::supportsAllowedCommandTemplateIds()
            ? DevicePermission::decodeAllowedCommandTemplateIds($permissionRow?->allowed_command_template_ids ?? null)
            : [];

        if (!empty($allowedCommandTemplateIds) && !in_array((int) $templateId, $allowedCommandTemplateIds, true)) {
            return $this->plainError('Forbidden: command is not permitted for this device.', 403);
        }

        if (empty($allowedCommandTemplateIds)) {
            $commandAssigned = DB::table('command_template_user')
                ->where('command_template_id', $templateId)
                ->where('user_id', $userId)
                ->exists();

            if (!$commandAssigned) {
                return $this->plainError('Forbidden: command is not assigned.', 403);
            }
        }

        $allowedPorts = trim((string) ($permissionRow->allowed_ports ?? ''));
        if ($allowedPorts !== '' && $this->commandNeedsInterface($actionKey)) {
            $interface = $this->firstNonEmpty(
                $request->query('iface'),
                $request->query('interface')
            );

            if (!$interface) {
                return $this->plainError('Forbidden: interface is required by your device port permissions.', 403);
            }

            if (!$this->interfaceMatchesAllowedList($interface, $allowedPorts)) {
                return $this->plainError('Forbidden: interface is not permitted for this user.', 403);
            }
        }

        return null;
    }

    private function commandNeedsInterface(string $cmd): bool
    {
        $cmdKey = strtolower(trim($cmd));
        $command = self::EXEC_COMMANDS[$cmdKey] ?? self::NEXUS_COMMANDS[$cmdKey] ?? null;
        if (!is_array($command)) {
            return false;
        }

        $needs = $command['needs'] ?? [];
        return is_array($needs) && in_array('interface', $needs, true);
    }

    private function interfaceMatchesAllowedList(string $interface, string $allowedPorts): bool
    {
        $interfaceToken = $this->normalizeInterfaceToken($interface);
        if ($interfaceToken === '') {
            return false;
        }

        $patterns = preg_split('/\s*,\s*/', trim($allowedPorts)) ?: [];
        foreach ($patterns as $pattern) {
            $pattern = $this->normalizeInterfaceToken((string) $pattern);
            if ($pattern === '') {
                continue;
            }

            if ($pattern === '*') {
                return true;
            }

            if (str_contains($pattern, '*')) {
                $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/i';
                if (preg_match($regex, $interfaceToken) === 1) {
                    return true;
                }
                continue;
            }

            if (strcasecmp($pattern, $interfaceToken) === 0) {
                return true;
            }
        }

        return false;
    }

    private function normalizeInterfaceToken(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/', '', $value) ?? '';
        return strtolower($value);
    }

    private function findDeviceByName(string $name): ?Device
    {
        $name = trim($name);
        $device = Device::where('metadata->cisco->name', $name)->first()
            ?? Device::where('name', $name)->first();

        if ($device) {
            return $device;
        }

        $normalized = strtolower(str_replace(' ', '_', $name));
        foreach (Device::orderByDesc('id')->get(['id', 'name', 'metadata']) as $candidate) {
            $ciscoName = (string) data_get($candidate->metadata ?? [], 'cisco.name', '');
            $candidateNames = [
                strtolower(str_replace(' ', '_', trim($candidate->name ?? ''))),
                strtolower(str_replace(' ', '_', trim($ciscoName))),
            ];
            if (in_array($normalized, $candidateNames, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveSwitchModel(Device $device, array $cisco): string
    {
        return (string) $this->firstNonEmpty(
            data_get($cisco, 'switch_model'),
            data_get($device->metadata ?? [], 'subtype'),
            $device->model,
            ''
        );
    }

    private function decryptValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return decrypt($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    private function isNexusModel(string $model): bool
    {
        return str_contains(strtolower($model), 'nexus');
    }

    private function is3560Model(string $model): bool
    {
        return str_contains(strtolower($model), '3560');
    }

    private function shouldUseNexus(Device $device, array $cisco, string $switchModel): bool
    {
        if ($this->isNexusModel($switchModel)) {
            return true;
        }

        $username = $this->firstNonEmpty(
            data_get($cisco, 'username'),
            data_get($cisco, 'user')
        );
        $enablePassword = $this->decryptValue(data_get($cisco, 'enable_password'));

        if ($username && !$enablePassword) {
            return true;
        }

        $nameHint = strtolower((string) $this->firstNonEmpty(
            data_get($cisco, 'name'),
            $device->name,
            $device->model,
            data_get($device->metadata ?? [], 'subtype')
        ));

        return str_contains($nameHint, 'nexus');
    }

    private function resolveBackupScriptName(Device $device, array $cisco, string $switchModel): string
    {
        if ($this->isNexusModel($switchModel)) {
            return 'nexus_backup.sh';
        }

        if ($this->is3560Model($switchModel)) {
            return '3560_backup.sh';
        }

        $subtype = (string) data_get($device->metadata ?? [], 'subtype', '');
        if ($this->is3560Model($subtype)) {
            return '3560_backup.sh';
        }

        return '4948_backup.sh';
    }
    private function cleanupTransportNoise(string $output): string
    {
        $output = str_replace("\r", '', $output);
        $lines = preg_split('/\n/', $output) ?: [];
        if (!$lines) {
            return trim($output);
        }

        $noisePattern = '/^\s*(?:spawn\s+telnet|Trying\s+\d|Connected to |Escape character is|User Access Verification|Password:|Connection closed by foreign host\.?|Running\s+\w+)\s*$/i';

        $clean = [];
        foreach ($lines as $line) {
            if (preg_match($noisePattern, (string) $line)) {
                continue;
            }
            $clean[] = $line;
        }

        while ($clean && trim((string) $clean[0]) === '') {
            array_shift($clean);
        }
        while ($clean && trim((string) end($clean)) === '') {
            array_pop($clean);
        }

        $normalized = trim(implode("\n", $clean));
        return $normalized !== '' ? $normalized : trim($output);
    }
    private function firstNonEmpty(...$values): ?string
    {
        foreach ($values as $value) {
            $value = is_string($value) ? trim($value) : $value;
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    private function plainError(string $message, int $status)
    {
        return response($message, $status)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}




