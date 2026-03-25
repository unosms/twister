<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\TelemetryLog;
use App\Models\User;
use App\Support\ProvisioningTrace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    private const OFFLINE_FAILURE_THRESHOLD = 2;
    private const PROBE_FAILURE_TTL_SECONDS = 600;
    private const STATUS_SNAPSHOT_PROBE_TTL_SECONDS = 20;
    private const TEMP_POLL_MINUTES_DEFAULT = 1;
    private const SERVER_SERVICE_OPTIONS = [
        'web',
        'astra',
        'hls_restream',
        'xtream',
        'log',
        'middleware',
        'radius',
        'vertiofiber',
        'netplay',
        'speedtest',
        'tftp',
        'storage',
        'rsyslog',
        'dns',
        'voip',
        'stock_management',
        'crm',
        'vmware',
        'vnc',
    ];
    private const SERVER_WEB_AUTH_SERVICE_OPTIONS = [
        'web',
        'log',
        'middleware',
        'radius',
        'vertiofiber',
        'netplay',
        'hls_restream',
        'xtream',
        'voip',
        'stock_management',
        'crm',
        'vmware',
    ];
    private const SERVER_WEB_ADDRESS_SERVICE_OPTIONS = [
        'web',
        'log',
        'middleware',
        'radius',
        'vertiofiber',
        'netplay',
        'hls_restream',
        'xtream',
        'voip',
        'stock_management',
        'crm',
        'vmware',
        'speedtest',
    ];
    private const SERVER_SERVICE_LABELS = [
        'web' => 'Web',
        'astra' => 'Astra',
        'hls_restream' => 'Hls Restream',
        'xtream' => 'Xtream',
        'log' => 'Log',
        'middleware' => 'Middleware',
        'radius' => 'Radius',
        'vertiofiber' => 'Vertiofiber',
        'netplay' => 'Netplay',
        'speedtest' => 'Speedtest',
        'tftp' => 'TFTP',
        'storage' => 'Storage',
        'rsyslog' => 'Rsyslog',
        'dns' => 'DNS',
        'voip' => 'VoIP',
        'stock_management' => 'Stock Management',
        'crm' => 'CRM',
        'vmware' => 'VMware',
        'vnc' => 'VNC',
    ];
    private const CISCO_MODELS_WITHOUT_USERNAME = [
        '3560',
        '4948',
    ];
    private const SERVER_TYPE_OPTIONS = [
        'virtual_server',
        'stand_alone_server',
    ];
    private const MIMOSA_MODEL_OPTIONS = [
        'C5C',
        'C5X',
        'B11',
    ];
    private const OLT_DEVICE_TYPE_OPTIONS = [
        'HUAWEI',
        'VSOL',
        'HIOSO',
    ];
    private const MIKROTIK_PORT_KEYS = [
        'winbox_port',
        'ssh_port',
        'telnet_port',
        'api_port',
        'api_ssl_port',
        'ftp_port',
        'http_port',
        'snmp_port',
    ];

    public function create()
    {
        return view('devices_create');
    }

    public function index(Request $request)
    {
        if ($request->boolean('unplaced')) {
            return $this->unplacedDevicesResponse($request);
        }

        $statusFilter = strtolower(trim((string) $request->query('status', 'all')));
        $allowedStatuses = ['all', 'online', 'warning', 'offline', 'error'];
        if (!in_array($statusFilter, $allowedStatuses, true)) {
            $statusFilter = 'all';
        }

        $typeFilter = strtoupper(trim((string) $request->query('type', 'all')));
        $allowedTypes = ['ALL', 'CISCO', 'MIMOSA', 'OLT', 'SERVER', 'MIKROTIK'];
        if (!in_array($typeFilter, $allowedTypes, true)) {
            $typeFilter = 'ALL';
        }

        $firmwareFilter = trim((string) $request->query('firmware', 'all'));
        if ($firmwareFilter === '') {
            $firmwareFilter = 'all';
        }

        $filters = [
            'status' => $statusFilter,
            'type' => $typeFilter === 'ALL' ? 'all' : $typeFilter,
            'firmware' => $firmwareFilter,
            'search' => trim((string) $request->query('search', '')),
        ];

        $query = Device::with('assignedUser')->orderByDesc('id');

        if ($filters['status'] !== '' && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['type'] !== '' && $filters['type'] !== 'all') {
            $query->where('type', $filters['type']);
        }

        if ($filters['firmware'] !== '' && $filters['firmware'] !== 'all') {
            $query->where('firmware_version', $filters['firmware']);
        }

        if ($filters['search'] !== '') {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($sub) use ($search) {
                $sub->where('name', 'like', $search)
                    ->orWhere('serial_number', 'like', $search)
                    ->orWhere('ip_address', 'like', $search)
                    ->orWhere('model', 'like', $search)
                    ->orWhere('type', 'like', $search);
            });
        }

        $devices = $query->paginate(10)->withQueryString();

        $selectedDeviceId = (int) $request->query('device', 0);
        $selectedDevice = $selectedDeviceId > 0
            ? Device::with('assignedUser')->find($selectedDeviceId)
            : null;

        if (!$selectedDevice && $devices->count() > 0) {
            $selectedDevice = $devices->first();
        }

        $selectedSignal = '-';
        $selectedBattery = '-';
        if ($selectedDevice) {
            $selectedMeta = $this->normalizeMetadata($selectedDevice->metadata);
            ['signal' => $selectedSignal, 'battery' => $selectedBattery] = $this->resolveSignalAndBattery(
                $selectedMeta,
                (string) ($selectedDevice->type ?? '')
            );
        }

        $totalDevices = Device::count();
        $activeDevices = Device::where('status', 'online')->count();
        $users = User::orderBy('name')->get();
        $firmwareOptions = Device::query()
            ->whereNotNull('firmware_version')
            ->pluck('firmware_version')
            ->map(static fn ($version) => trim((string) $version))
            ->filter(static fn ($version) => $version !== '')
            ->unique()
            ->values()
            ->all();

        usort($firmwareOptions, static fn ($a, $b) => version_compare($b, $a));

        if ($filters['firmware'] !== 'all' && !in_array($filters['firmware'], $firmwareOptions, true)) {
            $firmwareOptions[] = $filters['firmware'];
        }

        return view('device_management', [
            'devices' => $devices,
            'users' => $users,
            'selectedDevice' => $selectedDevice,
            'selectedSignal' => $selectedSignal,
            'selectedBattery' => $selectedBattery,
            'filters' => $filters,
            'totalDevices' => $totalDevices,
            'activeDevices' => $activeDevices,
            'firmwareOptions' => $firmwareOptions,
        ]);
    }

    public function details(Request $request)
    {
        $devices = Device::orderByDesc('id')->paginate(10)->withQueryString();
        $totalDevices = Device::count();
        $activeDevices = Device::where('status', 'online')->count();
        $authUser = User::find($request->session()->get('auth.user_id'));

        return view('device_details', [
            'devices' => $devices,
            'totalDevices' => $totalDevices,
            'activeDevices' => $activeDevices,
            'authUser' => $authUser,
        ]);
    }

    public function eventsIndex(Request $request)
    {
        $authUser = User::find($request->session()->get('auth.user_id'));
        $devices = Device::query()
            ->orderByRaw('LOWER(COALESCE(type, \'\')) ASC')
            ->orderByRaw('LOWER(COALESCE(name, \'\')) ASC')
            ->orderBy('id')
            ->get();

        $deviceGroups = [
            'router_board' => collect(),
            'switches' => collect(),
            'fiber_optic' => collect(),
            'wireless' => collect(),
            'servers_standalone' => collect(),
            'servers_virtual' => collect(),
            'other' => collect(),
        ];

        foreach ($devices as $device) {
            $type = strtoupper((string) ($device->type ?? ''));

            if ($type === 'MIKROTIK') {
                $deviceGroups['router_board']->push($device);
                continue;
            }

            if ($type === 'CISCO') {
                $deviceGroups['switches']->push($device);
                continue;
            }

            if ($type === 'OLT') {
                $deviceGroups['fiber_optic']->push($device);
                continue;
            }

            if ($type === 'MIMOSA') {
                $deviceGroups['wireless']->push($device);
                continue;
            }

            if ($type === 'SERVER') {
                $serverType = strtolower((string) data_get($device->metadata, 'server.server_type', 'virtual_server'));
                if ($serverType === 'stand_alone_server') {
                    $deviceGroups['servers_standalone']->push($device);
                } else {
                    $deviceGroups['servers_virtual']->push($device);
                }
                continue;
            }

            $deviceGroups['other']->push($device);
        }

        return view('device_events_index', [
            'deviceGroups' => $deviceGroups,
            'totalDevices' => $devices->count(),
            'authUser' => $authUser,
        ]);
    }

    public function statusSnapshot(Request $request)
    {
        $idsRaw = trim((string) $request->query('ids', ''));
        if ($idsRaw === '') {
            return response()->json(['devices' => []])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($value) => is_numeric($value) ? (int) $value : null,
            preg_split('/\s*,\s*/', $idsRaw) ?: []
        ), static fn ($value) => is_int($value) && $value > 0)));

        if (!$ids) {
            return response()->json(['devices' => []])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        $devices = Device::whereIn('id', $ids)->get();

        $shouldWrite = $request->isMethod('post');
        $probe = $shouldWrite || $request->boolean('probe');
        if ($probe) {
            ProvisioningTrace::log('device snapshot trace: probe requested', [
                'trace' => 'device snapshot',
                'trigger' => $request->route()?->getName() ?: 'devices.statusSnapshot',
                'request_method' => $request->getMethod(),
                'request_path' => $request->path(),
                'request_ip' => $request->ip(),
                'device_ids' => $ids,
                'device_count' => count($ids),
            ]);
        }

        $payload = [];
        foreach ($devices as $device) {
            if ($probe) {
                $probeKey = 'status_snapshot_probe:' . $device->id;
                if (Cache::add($probeKey, true, now()->addSeconds(self::STATUS_SNAPSHOT_PROBE_TTL_SECONDS))) {
                    ProvisioningTrace::log('device snapshot trace: probing device', $this->deviceProvisioningContext($device, [
                        'trace' => 'device snapshot',
                        'trigger' => $request->route()?->getName() ?: 'devices.statusSnapshot',
                    ]));
                    $this->probeDeviceStatus($device);
                    $device->refresh();
                }
            }

            $meta = $this->normalizeMetadata($device->metadata);
            $signalBattery = $this->resolveSignalAndBattery($meta, (string) ($device->type ?? ''));
            $payload[] = [
                'id' => $device->id,
                'status' => $device->status ?? 'offline',
                'last_seen_at' => $device->last_seen_at?->toDateTimeString(),
                'last_seen_formatted' => $device->last_seen_at?->format('Y-m-d H:i:s'),
                'last_seen_human' => $device->last_seen_at?->diffForHumans(),
                'temperature' => data_get($meta, 'temperature'),
                'uptime' => data_get($meta, 'uptime'),
                'signal' => $signalBattery['signal'],
                'battery' => $signalBattery['battery'],
            ];
        }

        if ($probe) {
            ProvisioningTrace::log('device snapshot trace: probe completed', [
                'trace' => 'device snapshot',
                'trigger' => $request->route()?->getName() ?: 'devices.statusSnapshot',
                'device_count' => count($payload),
            ]);
        }

        return response()->json(['devices' => $payload])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    private function unplacedDevicesResponse(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $limit = max(0, min((int) $request->query('limit', 0), 1000));

        $query = Device::query()
            ->whereDoesntHave('cabinetPlacement')
            ->orderBy('name')
            ->orderBy('id');

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($subQuery) use ($like) {
                $subQuery->where('name', 'like', $like)
                    ->orWhere('ip_address', 'like', $like)
                    ->orWhere('serial_number', 'like', $like)
                    ->orWhere('model', 'like', $like)
                    ->orWhere('type', 'like', $like)
                    ->orWhere('location', 'like', $like);
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $devices = $query->get()->map(function (Device $device): array {
            $meta = $this->normalizeMetadata($device->metadata);

            return [
                'id' => $device->id,
                'name' => $device->name,
                'type' => $device->type,
                'model' => $device->model,
                'status' => $device->status ?? 'offline',
                'ip_address' => $device->ip_address ?: $this->normalizeOptionalString(
                    data_get($meta, 'cisco.ip_address')
                    ?? data_get($meta, 'server.ip_address')
                    ?? data_get($meta, 'mikrotik.ip_address')
                    ?? data_get($meta, 'olt.ip_address')
                    ?? data_get($meta, 'mimosa.ip')
                ),
                'serial_number' => $device->serial_number,
                'location' => $device->location,
                'default_height_u' => $this->resolveRackHeight($meta),
            ];
        })->values();

        return response()->json(['devices' => $devices])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function probeDevice(Device $device)
    {
        ProvisioningTrace::log('device probe trace: probe requested', $this->deviceProvisioningContext($device, [
            'trace' => 'device probe',
            'trigger' => 'devices.probe',
        ]));
        $this->probeDeviceStatus($device);
        $device->refresh();
        $meta = $this->normalizeMetadata($device->metadata);
        $signalBattery = $this->resolveSignalAndBattery($meta, (string) ($device->type ?? ''));

        return response()->json([
            'id' => $device->id,
            'status' => $device->status ?? 'offline',
            'last_seen_at' => $device->last_seen_at?->toDateTimeString(),
            'temperature' => data_get($meta, 'temperature'),
            'uptime' => data_get($meta, 'uptime'),
            'signal' => $signalBattery['signal'],
            'battery' => $signalBattery['battery'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
            'ip_address' => ['nullable', 'string', 'max:255'],
            'cisco_name' => ['nullable', 'string', 'max:255'],
            'switch_model' => ['nullable', 'string', 'max:255'],
            'mimosa_model' => ['nullable', 'string', Rule::in(self::MIMOSA_MODEL_OPTIONS)],
            'mimosa_c5c_name' => ['nullable', 'string', 'max:255'],
            'mimosa_c5c_ip' => ['nullable', 'string', 'max:255'],
            'mimosa_c5c_username' => ['nullable', 'string', 'max:255'],
            'mimosa_c5c_password' => ['nullable', 'string', 'max:255'],
            'mimosa_c5c_mac_address' => ['nullable', 'string', 'max:255'],
            'mimosa_c5c_url' => ['nullable', 'string', 'max:500'],
            'mimosa_c5c_station' => ['nullable', 'string', 'max:255'],
            'mimosa_c5c_switch_id' => ['nullable', 'string', 'max:255'],
            'mimosa_c5c_switch_port' => ['nullable', 'string', 'max:255'],
            'mimosa_c5c_vlan' => ['nullable', 'string', 'max:255'],
            'mimosa_c5x_name' => ['nullable', 'string', 'max:255'],
            'mimosa_c5x_ip' => ['nullable', 'string', 'max:255'],
            'mimosa_c5x_username' => ['nullable', 'string', 'max:255'],
            'mimosa_c5x_password' => ['nullable', 'string', 'max:255'],
            'mimosa_c5x_mac_address' => ['nullable', 'string', 'max:255'],
            'mimosa_c5x_url' => ['nullable', 'string', 'max:500'],
            'mimosa_c5x_station' => ['nullable', 'string', 'max:255'],
            'mimosa_c5x_switch_id' => ['nullable', 'string', 'max:255'],
            'mimosa_c5x_switch_port' => ['nullable', 'string', 'max:255'],
            'mimosa_c5x_vlan' => ['nullable', 'string', 'max:255'],
            'mimosa_b11_name' => ['nullable', 'string', 'max:255'],
            'mimosa_b11_ip' => ['nullable', 'string', 'max:255'],
            'mimosa_b11_username' => ['nullable', 'string', 'max:255'],
            'mimosa_b11_password' => ['nullable', 'string', 'max:255'],
            'mimosa_b11_mac_address' => ['nullable', 'string', 'max:255'],
            'mimosa_b11_url' => ['nullable', 'string', 'max:500'],
            'mimosa_b11_station' => ['nullable', 'string', 'max:255'],
            'mimosa_b11_switch_id' => ['nullable', 'string', 'max:255'],
            'mimosa_b11_switch_port' => ['nullable', 'string', 'max:255'],
            'mimosa_b11_vlan' => ['nullable', 'string', 'max:255'],
            'cisco_username' => ['nullable', 'string', 'max:255'],
            'cisco_password' => ['nullable', 'string', 'max:255'],
            'enable_password' => ['nullable', 'string', 'max:255'],
            'temp_poll_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'shbackup' => ['nullable', 'string', 'max:500'],
            'exec_cmd' => ['nullable', 'string', 'max:500'],
            'folder_location' => ['nullable', 'string', 'max:500'],
            'snmp_version' => ['nullable', 'string', 'max:20'],
            'snmp_community' => ['nullable', 'string', 'max:255'],
            'snmp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'server_type' => ['nullable', 'string', Rule::in(self::SERVER_TYPE_OPTIONS)],
            'server_hardware_specs' => ['nullable', 'string', 'max:500'],
            'server_name' => ['nullable', 'string', 'max:255'],
            'server_username' => ['nullable', 'string', 'max:255'],
            'server_password' => ['nullable', 'string', 'max:255'],
            'server_service' => ['nullable', 'array', 'min:1'],
            'server_service.*' => ['string', 'max:100', Rule::in(self::SERVER_SERVICE_OPTIONS)],
            'server_service_access' => ['nullable', 'array'],
            'server_service_access.*' => ['nullable', 'array'],
            'server_service_access.*.address_port' => ['nullable', 'string', 'max:500'],
            'server_service_access.*.username' => ['nullable', 'string', 'max:255'],
            'server_service_access.*.password' => ['nullable', 'string', 'max:255'],
            'server_service_access.*.vnc_ip' => ['nullable', 'string', 'max:500'],
            'server_service_access.*.vnc_password' => ['nullable', 'string', 'max:255'],
            'server_ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'server_cabinet_id' => ['nullable', 'string', 'max:255'],
            'server_rack_uid' => ['nullable', 'string', 'max:255'],
            'olt_ip_address' => ['nullable', 'string', 'max:255'],
            'olt_password' => ['nullable', 'string', 'max:255'],
            'olt_web_address' => ['nullable', 'string', 'max:500'],
            'olt_username' => ['nullable', 'string', 'max:255'],
            'olt_snmp_community' => ['nullable', 'string', 'max:255'],
            'olt_model' => ['nullable', 'string', 'max:255'],
            'olt_number_of_ports' => ['nullable', 'integer', 'min:1', 'max:4096'],
            'olt_device_type' => ['nullable', 'string', Rule::in(self::OLT_DEVICE_TYPE_OPTIONS)],
            'olt_folder_location' => ['nullable', 'string', 'max:500'],
            'mikrotik_ip_address' => ['nullable', 'string', 'max:255'],
            'mikrotik_username' => ['nullable', 'string', 'max:255'],
            'mikrotik_password' => ['nullable', 'string', 'max:255'],
            'mikrotik_location' => ['nullable', 'string', 'max:255'],
            'mikrotik_cabinet_id' => ['nullable', 'string', 'max:255'],
            'mikrotik_rack_uid' => ['nullable', 'string', 'max:255'],
            'mikrotik_snmp_community' => ['nullable', 'string', 'max:255'],
            'mikrotik_snmp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mikrotik_winbox_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mikrotik_ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mikrotik_telnet_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mikrotik_api_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mikrotik_api_ssl_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mikrotik_ftp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mikrotik_http_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mikrotik_web_address' => ['nullable', 'string', 'max:500'],
            'mikrotik_winbox_address' => ['nullable', 'string', 'max:500'],
        ]);

        $type = strtoupper((string) ($data['type'] ?? ''));
        $missingFieldErrors = $this->validateCreateRequiredFields($type, $data);
        if ($missingFieldErrors !== []) {
            return back()
                ->withInput()
                ->withErrors($missingFieldErrors);
        }
        $serverServiceFieldErrors = $type === 'SERVER'
            ? $this->validateServerServiceAccess($data)
            : [];
        if ($serverServiceFieldErrors !== []) {
            return back()
                ->withInput()
                ->withErrors($serverServiceFieldErrors);
        }

        $meta = [];
        $ipAddress = $this->normalizeOptionalString($data['ip_address'] ?? null);
        $snmpCommunity = $this->normalizeOptionalString($data['snmp_community'] ?? null);
        $snmpPort = isset($data['snmp_port']) && is_numeric($data['snmp_port']) ? (int) $data['snmp_port'] : null;
        if ($type === 'MIKROTIK') {
            $ipAddress = $this->normalizeOptionalString($data['mikrotik_ip_address'] ?? $ipAddress);
            $snmpCommunity = $this->normalizeOptionalString($data['mikrotik_snmp_community'] ?? $snmpCommunity);
            $snmpPort = isset($data['mikrotik_snmp_port']) && is_numeric($data['mikrotik_snmp_port'])
                ? (int) $data['mikrotik_snmp_port']
                : $snmpPort;
        }
        if ($type === 'OLT') {
            $ipAddress = $this->normalizeOptionalString($data['olt_ip_address'] ?? $ipAddress);
            $snmpCommunity = $this->normalizeOptionalString($data['olt_snmp_community'] ?? $snmpCommunity);
        }
        $location = null;
        $model = null;

        if ($snmpCommunity !== null) {
            $meta['snmp_community'] = $snmpCommunity;
        }
        if ($snmpPort !== null) {
            $meta['snmp_port'] = $snmpPort;
        }

        if ($type === 'CISCO') {
            $switchName = $this->normalizeOptionalString($data['cisco_name'] ?? null)
                ?? $this->normalizeOptionalString($data['name'] ?? null);
            $switchSlug = preg_replace('/\s+/', '_', (string) $switchName);
            $switchModel = $data['switch_model'] ?? null;
            $model = $switchModel ?: null;

            $meta['cisco'] = [
                'name' => $switchName,
                'switch_model' => $switchModel,
                'ip_address' => $ipAddress,
                'username' => $this->ciscoModelUsesUsername($switchModel)
                    ? $this->normalizeOptionalString($data['cisco_username'] ?? null)
                    : null,
                'password' => !empty($data['cisco_password']) ? encrypt($data['cisco_password']) : null,
                'enable_password' => !empty($data['enable_password']) ? encrypt($data['enable_password']) : null,
                'temp_poll_minutes' => (int) ($data['temp_poll_minutes'] ?? self::TEMP_POLL_MINUTES_DEFAULT),
                'shbackup' => $data['shbackup'] ?? ($switchSlug !== '' ? 'showbackup.php?name=' . $switchSlug : null),
                'exec_cmd' => $data['exec_cmd'] ?? ($switchSlug !== '' ? 'exec.php?name=' . $switchSlug : null),
                'folder_location' => $data['folder_location'] ?? ($switchSlug !== '' ? $switchSlug : null),
                'snmp_version' => $data['snmp_version'] ?? '2c',
                'snmp_community' => $snmpCommunity,
            ];
            if ($snmpPort !== null) {
                $meta['cisco']['snmp_port'] = $snmpPort;
            }
            if (!$this->ciscoModelUsesUsername($switchModel)) {
                unset($meta['cisco']['username']);
            }

            if ($switchModel) {
                $meta['subtype'] = $switchModel;
            }
        }

        if ($type === 'MIMOSA') {
            $mimosa = [];
            $mimosaKey = $this->resolveMimosaFormKey($data['mimosa_model'] ?? null);
            $mimosaModel = strtoupper($mimosaKey);
            if ($mimosaModel !== '') {
                $meta['mimosa_model'] = $mimosaModel;
                $model = $mimosaModel;
            }
            $mimosa['name'] = $this->normalizeOptionalString($data["mimosa_{$mimosaKey}_name"] ?? null);
            $mimosa['ip'] = $this->normalizeOptionalString($data["mimosa_{$mimosaKey}_ip"] ?? null);
            $mimosa['username'] = $this->normalizeOptionalString($data["mimosa_{$mimosaKey}_username"] ?? null);
            $mimosa['mac_address'] = $this->normalizeOptionalString($data["mimosa_{$mimosaKey}_mac_address"] ?? null);
            $mimosa['url'] = $this->normalizeOptionalString($data["mimosa_{$mimosaKey}_url"] ?? null);
            $mimosa['station'] = $this->normalizeOptionalString($data["mimosa_{$mimosaKey}_station"] ?? null);
            $mimosa['switch_id'] = $this->normalizeOptionalString($data["mimosa_{$mimosaKey}_switch_id"] ?? null);
            $mimosa['switch_port'] = $this->normalizeOptionalString($data["mimosa_{$mimosaKey}_switch_port"] ?? null);
            $mimosa['vlan'] = $this->normalizeOptionalString($data["mimosa_{$mimosaKey}_vlan"] ?? null);
            $mimosaPassword = $this->normalizeOptionalString($data["mimosa_{$mimosaKey}_password"] ?? null);
            if ($mimosaPassword !== null) {
                $mimosa['password'] = encrypt($mimosaPassword);
            }
            if (!$ipAddress) {
                $ipAddress = $mimosa['ip'] ?? null;
            }
            if (!empty($mimosa)) {
                $meta['mimosa'] = array_filter($mimosa, static fn ($value) => $value !== null && $value !== '');
            }
        }

        if ($type === 'SERVER') {
            $serverType = $this->normalizeServerType($data['server_type'] ?? null);
            $serverName = $this->normalizeOptionalString($data['server_name'] ?? null)
                ?: $this->normalizeOptionalString($data['name'] ?? null);
            $serverUsername = $this->normalizeOptionalString($data['server_username'] ?? null);
            $serverPassword = $this->normalizeOptionalString($data['server_password'] ?? null);
            $serverServices = $this->normalizeServerServices($data['server_service'] ?? []);
            $serverPrimaryService = $serverServices[0] ?? null;
            $serverSshPort = isset($data['server_ssh_port']) && is_numeric($data['server_ssh_port'])
                ? (int) $data['server_ssh_port']
                : null;
            $serviceAccess = $this->buildServerServiceAccess($serverServices, $data['server_service_access'] ?? [], []);

            $serverMeta = [
                'server_type' => $serverType,
                'hardware_specs' => $this->normalizeOptionalString($data['server_hardware_specs'] ?? null),
                'server_name' => $serverName,
                'username' => $serverUsername,
                'service' => $serverPrimaryService,
                'services' => $serverServices,
                'ssh_port' => $serverSshPort,
                'ip_address' => $ipAddress,
                'snmp_community' => $snmpCommunity,
                'snmp_port' => $snmpPort,
            ];

            if ($serverPassword !== null) {
                $serverMeta['password'] = encrypt($serverPassword);
            }
            if ($serviceAccess !== []) {
                $serverMeta['service_access'] = $serviceAccess;
            }

            if ($serverType === 'stand_alone_server') {
                $serverMeta['cabinet_id'] = $this->normalizeOptionalString($data['server_cabinet_id'] ?? null);
                $serverMeta['rack_uid'] = $this->normalizeOptionalString($data['server_rack_uid'] ?? null);
            }

            $meta['server'] = array_filter($serverMeta, static fn ($value) => $value !== null && $value !== '');
            $model = $serverType === 'stand_alone_server' ? 'Stand Alone Server' : 'Virtual Server';
        }

        if ($type === 'OLT') {
            $oltModel = $this->normalizeOptionalString($data['olt_model'] ?? null);
            $oltDeviceType = $this->normalizeOltDeviceType($data['olt_device_type'] ?? null) ?? 'HUAWEI';
            $oltNumberOfPorts = isset($data['olt_number_of_ports']) && is_numeric($data['olt_number_of_ports'])
                ? (int) $data['olt_number_of_ports']
                : null;
            $oltFolderLocation = $this->normalizeOptionalString($data['olt_folder_location'] ?? null);
            if ($oltFolderLocation === null) {
                $oltName = $this->normalizeOptionalString($data['name'] ?? null);
                if ($oltName !== null) {
                    $oltSlug = preg_replace('/\s+/', '_', trim($oltName));
                    $oltFolderLocation = $oltSlug !== '' ? $oltSlug : null;
                }
            }
            $olt = [
                'ip_address' => $ipAddress,
                'model' => $oltModel,
                'device_type' => $oltDeviceType,
                'number_of_ports' => $oltNumberOfPorts,
                'web_address' => $this->normalizeOptionalString($data['olt_web_address'] ?? null),
                'username' => $this->normalizeOptionalString($data['olt_username'] ?? null),
                'snmp_community' => $snmpCommunity,
                'folder_location' => $oltFolderLocation,
            ];

            if (!empty($data['olt_password'])) {
                $olt['password'] = encrypt($data['olt_password']);
            }

            $meta['olt'] = array_filter($olt, static fn ($value) => $value !== null && $value !== '');
            $model = $oltModel ?: 'OLT';
        }

        if ($type === 'MIKROTIK') {
            $location = $this->normalizeOptionalString($data['mikrotik_location'] ?? null);
            $mikrotik = [
                'ip_address' => $ipAddress,
                'username' => $this->normalizeOptionalString($data['mikrotik_username'] ?? null),
                'location' => $location,
                'cabinet_id' => $this->normalizeOptionalString($data['mikrotik_cabinet_id'] ?? null),
                'rack_uid' => $this->normalizeOptionalString($data['mikrotik_rack_uid'] ?? null),
                'snmp_community' => $snmpCommunity,
                'snmp_port' => $snmpPort,
                'winbox_port' => isset($data['mikrotik_winbox_port']) ? (int) $data['mikrotik_winbox_port'] : null,
                'ssh_port' => isset($data['mikrotik_ssh_port']) ? (int) $data['mikrotik_ssh_port'] : null,
                'telnet_port' => isset($data['mikrotik_telnet_port']) ? (int) $data['mikrotik_telnet_port'] : null,
                'api_port' => isset($data['mikrotik_api_port']) ? (int) $data['mikrotik_api_port'] : null,
                'api_ssl_port' => isset($data['mikrotik_api_ssl_port']) ? (int) $data['mikrotik_api_ssl_port'] : null,
                'ftp_port' => isset($data['mikrotik_ftp_port']) ? (int) $data['mikrotik_ftp_port'] : null,
                'http_port' => isset($data['mikrotik_http_port']) ? (int) $data['mikrotik_http_port'] : null,
                'web_address' => $this->normalizeOptionalString($data['mikrotik_web_address'] ?? null),
                'winbox_address' => $this->normalizeOptionalString($data['mikrotik_winbox_address'] ?? null),
            ];

            if (!empty($data['mikrotik_password'])) {
                $mikrotik['password'] = encrypt($data['mikrotik_password']);
            }

            $meta['mikrotik'] = array_filter($mikrotik, static fn ($value) => $value !== null && $value !== '');
            $model = 'MikroTik';
        }

        $resolvedName = $this->resolveSubmittedDeviceName($type, $data, $model, $ipAddress);
        if ($resolvedName === null) {
            return back()
                ->withInput()
                ->withErrors(['name' => 'A device name could not be resolved. Please provide the required identity fields.']);
        }
        $serialNumber = $this->typeUsesSerialNumber($type)
            ? $this->normalizeOptionalString($data['serial_number'] ?? null)
            : null;

        try {
            $device = Device::create([
                'uuid' => (string) Str::uuid(),
                'name' => $resolvedName,
                'type' => $type,
                'model' => $model,
                'serial_number' => $serialNumber,
                'status' => 'offline',
                'ip_address' => $ipAddress,
                'location' => $location,
                'metadata' => $meta,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Device creation failed.', [
                'name' => $resolvedName,
                'type' => $type,
                'ip_address' => $ipAddress,
                'message' => $exception->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['device' => 'Failed to create device. Please review the input and server logs.']);
        }

        $this->kickoffPoll($device);
        $this->writeTelemetryLog(
            $device,
            'info',
            'Device created and initial poll triggered.',
            ['action' => 'device.create']
        );

        return redirect()
            ->route('devices.index', ['device' => $device->id])
            ->with('status', "Device {$device->name} created.");
    }

    public function update(Request $request, Device $device)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
            'ip_address' => ['nullable', 'string', 'max:255', 'required_if:type,SERVER'],
            'cisco_username' => ['nullable', 'string', 'max:255'],
            'cisco_password' => ['nullable', 'string', 'max:255'],
            'enable_password' => ['nullable', 'string', 'max:255'],
            'snmp_community' => ['nullable', 'string', 'max:255', 'required_if:type,SERVER'],
            'snmp_port' => ['nullable', 'integer', 'min:1', 'max:65535', 'required_if:type,SERVER'],
            'server_type' => ['nullable', 'string', 'required_if:type,SERVER', Rule::in(self::SERVER_TYPE_OPTIONS)],
            'server_hardware_specs' => ['nullable', 'string', 'max:500', 'required_if:type,SERVER'],
            'server_name' => ['nullable', 'string', 'max:255', 'required_if:type,SERVER'],
            'server_service' => ['nullable', 'array', 'required_if:type,SERVER', 'min:1'],
            'server_service.*' => ['string', 'max:100', Rule::in(self::SERVER_SERVICE_OPTIONS)],
            'server_service_access' => ['nullable', 'array'],
            'server_service_access.*' => ['nullable', 'array'],
            'server_service_access.*.address_port' => ['nullable', 'string', 'max:500'],
            'server_service_access.*.username' => ['nullable', 'string', 'max:255'],
            'server_service_access.*.password' => ['nullable', 'string', 'max:255'],
            'server_service_access.*.vnc_ip' => ['nullable', 'string', 'max:500'],
            'server_service_access.*.vnc_password' => ['nullable', 'string', 'max:255'],
            'server_ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535', 'required_if:type,SERVER'],
            'server_cabinet_id' => ['nullable', 'string', 'max:255', 'required_if:server_type,stand_alone_server'],
            'server_rack_uid' => ['nullable', 'string', 'max:255', 'required_if:server_type,stand_alone_server'],
            'olt_ip_address' => ['nullable', 'string', 'max:255', 'required_if:type,OLT'],
            'olt_password' => ['nullable', 'string', 'max:255'],
            'olt_web_address' => ['nullable', 'string', 'max:500', 'required_if:type,OLT'],
            'olt_username' => ['nullable', 'string', 'max:255', 'required_if:type,OLT'],
            'olt_snmp_community' => ['nullable', 'string', 'max:255', 'required_if:type,OLT'],
            'olt_model' => ['nullable', 'string', 'max:255', 'required_if:type,OLT'],
            'olt_number_of_ports' => ['nullable', 'integer', 'min:1', 'max:4096', 'required_if:type,OLT'],
            'olt_device_type' => ['nullable', 'string', Rule::in(self::OLT_DEVICE_TYPE_OPTIONS)],
            'olt_folder_location' => ['nullable', 'string', 'max:500'],
            'mikrotik_ip_address' => ['nullable', 'string', 'max:255', 'required_if:type,MIKROTIK'],
            'mikrotik_username' => ['nullable', 'string', 'max:255', 'required_if:type,MIKROTIK'],
            'mikrotik_password' => ['nullable', 'string', 'max:255'],
            'mikrotik_location' => ['nullable', 'string', 'max:255', 'required_if:type,MIKROTIK'],
            'mikrotik_cabinet_id' => ['nullable', 'string', 'max:255', 'required_if:type,MIKROTIK'],
            'mikrotik_rack_uid' => ['nullable', 'string', 'max:255', 'required_if:type,MIKROTIK'],
            'mikrotik_snmp_community' => ['nullable', 'string', 'max:255', 'required_if:type,MIKROTIK'],
            'mikrotik_snmp_port' => ['nullable', 'integer', 'min:1', 'max:65535', 'required_if:type,MIKROTIK'],
            'mikrotik_winbox_port' => ['nullable', 'integer', 'min:1', 'max:65535', 'required_if:type,MIKROTIK'],
            'mikrotik_ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535', 'required_if:type,MIKROTIK'],
            'mikrotik_telnet_port' => ['nullable', 'integer', 'min:1', 'max:65535', 'required_if:type,MIKROTIK'],
            'mikrotik_api_port' => ['nullable', 'integer', 'min:1', 'max:65535', 'required_if:type,MIKROTIK'],
            'mikrotik_api_ssl_port' => ['nullable', 'integer', 'min:1', 'max:65535', 'required_if:type,MIKROTIK'],
            'mikrotik_ftp_port' => ['nullable', 'integer', 'min:1', 'max:65535', 'required_if:type,MIKROTIK'],
            'mikrotik_http_port' => ['nullable', 'integer', 'min:1', 'max:65535', 'required_if:type,MIKROTIK'],
            'mikrotik_web_address' => ['nullable', 'string', 'max:500', 'required_if:type,MIKROTIK'],
            'mikrotik_winbox_address' => ['nullable', 'string', 'max:500', 'required_if:type,MIKROTIK'],
        ]);

        $meta = $this->normalizeMetadata($device->metadata);
        $type = strtoupper((string) ($data['type'] ?? $device->type ?? ''));
        $serverServiceFieldErrors = $type === 'SERVER'
            ? $this->validateServerServiceAccess($data)
            : [];
        if ($serverServiceFieldErrors !== []) {
            return back()
                ->withInput()
                ->withErrors($serverServiceFieldErrors);
        }
        $snmpCommunity = $this->normalizeOptionalString($data['snmp_community'] ?? null);
        $snmpPort = isset($data['snmp_port']) && is_numeric($data['snmp_port']) ? (int) $data['snmp_port'] : null;
        $ipAddress = $this->normalizeOptionalString($data['ip_address'] ?? null);
        if ($type === 'MIKROTIK') {
            $ipAddress = $this->normalizeOptionalString($data['mikrotik_ip_address'] ?? $ipAddress);
            $snmpCommunity = $this->normalizeOptionalString($data['mikrotik_snmp_community'] ?? $snmpCommunity);
            $snmpPort = isset($data['mikrotik_snmp_port']) && is_numeric($data['mikrotik_snmp_port'])
                ? (int) $data['mikrotik_snmp_port']
                : $snmpPort;
        }
        if ($type === 'OLT') {
            $ipAddress = $this->normalizeOptionalString($data['olt_ip_address'] ?? $ipAddress);
            $snmpCommunity = $this->normalizeOptionalString($data['olt_snmp_community'] ?? $snmpCommunity);
        }
        $updatedLocation = $device->location;
        $updatedModel = $device->model;

        if ($snmpCommunity !== null) {
            $meta['snmp_community'] = $snmpCommunity;
        } else {
            unset($meta['snmp_community']);
        }
        if ($snmpPort !== null) {
            $meta['snmp_port'] = $snmpPort;
        } else {
            unset($meta['snmp_port']);
        }

        if ($type === 'CISCO') {
            $cisco = data_get($meta, 'cisco', []);
            $cisco['ip_address'] = $ipAddress ?? ($cisco['ip_address'] ?? null);
            $ciscoModel = $this->normalizeOptionalString($cisco['switch_model'] ?? $updatedModel);
            if ($this->ciscoModelUsesUsername($ciscoModel)) {
                $cisco['username'] = $this->normalizeOptionalString($data['cisco_username'] ?? null) ?? ($cisco['username'] ?? null);
            } else {
                unset($cisco['username']);
            }
            if ($snmpCommunity !== null) {
                $cisco['snmp_community'] = $snmpCommunity;
            } else {
                unset($cisco['snmp_community']);
            }
            if ($snmpPort !== null) {
                $cisco['snmp_port'] = $snmpPort;
            } else {
                unset($cisco['snmp_port']);
            }
            if (!empty($data['cisco_password'])) {
                $cisco['password'] = encrypt($data['cisco_password']);
            }
            if (!empty($data['enable_password'])) {
                $cisco['enable_password'] = encrypt($data['enable_password']);
            }
            $meta['cisco'] = $cisco;
            $updatedModel = $this->normalizeOptionalString($cisco['switch_model'] ?? null) ?? $updatedModel;
        } else {
            unset($meta['cisco']);
        }

        if ($type === 'SERVER') {
            $server = data_get($meta, 'server', []);
            if (!is_array($server)) {
                $server = [];
            }

            $serverType = $this->normalizeServerType($data['server_type'] ?? ($server['server_type'] ?? null));
            $serverName = $this->normalizeOptionalString($data['server_name'] ?? null)
                ?: $this->normalizeOptionalString($data['name'] ?? null)
                ?: ($server['server_name'] ?? null);
            $serverServices = $this->normalizeServerServices($data['server_service'] ?? []);
            $serverPrimaryService = $serverServices[0] ?? null;
            $serviceAccess = $this->buildServerServiceAccess($serverServices, $data['server_service_access'] ?? [], $server);
            $server['server_type'] = $serverType;
            $server['hardware_specs'] = $this->normalizeOptionalString($data['server_hardware_specs'] ?? null);
            $server['server_name'] = $serverName;
            $server['service'] = $serverPrimaryService;
            $server['services'] = $serverServices;
            $server['ip_address'] = $ipAddress;
            if ($serviceAccess !== []) {
                $server['service_access'] = $serviceAccess;
            } else {
                unset($server['service_access']);
            }
            unset(
                $server['web_address_port'],
                $server['web_username'],
                $server['web_password'],
                $server['vnc_address_port'],
                $server['vnc_password']
            );
            if (isset($data['server_ssh_port']) && is_numeric($data['server_ssh_port'])) {
                $server['ssh_port'] = (int) $data['server_ssh_port'];
            } else {
                unset($server['ssh_port']);
            }
            if ($snmpCommunity !== null) {
                $server['snmp_community'] = $snmpCommunity;
            } else {
                unset($server['snmp_community']);
            }
            if ($snmpPort !== null) {
                $server['snmp_port'] = $snmpPort;
            } else {
                unset($server['snmp_port']);
            }
            if ($serverType === 'stand_alone_server') {
                $server['cabinet_id'] = $this->normalizeOptionalString($data['server_cabinet_id'] ?? null);
                $server['rack_uid'] = $this->normalizeOptionalString($data['server_rack_uid'] ?? null);
            } else {
                unset($server['cabinet_id'], $server['rack_uid']);
            }
            $meta['server'] = array_filter($server, static fn ($value) => $value !== null && $value !== '');
            $updatedModel = $serverType === 'stand_alone_server' ? 'Stand Alone Server' : 'Virtual Server';
        } else {
            unset($meta['server']);
        }

        if ($type === 'OLT') {
            $olt = data_get($meta, 'olt', []);
            if (!is_array($olt)) {
                $olt = [];
            }

            $oltFolderLocation = $this->normalizeOptionalString($data['olt_folder_location'] ?? null);
            if ($oltFolderLocation === null) {
                $oltName = $this->normalizeOptionalString($data['name'] ?? $device->name);
                if ($oltName !== null) {
                    $oltSlug = preg_replace('/\s+/', '_', trim($oltName));
                    $oltFolderLocation = $oltSlug !== '' ? $oltSlug : null;
                }
            }

            $olt['ip_address'] = $ipAddress;
            $olt['model'] = $this->normalizeOptionalString($data['olt_model'] ?? null);
            $olt['device_type'] = $this->normalizeOltDeviceType($data['olt_device_type'] ?? null) ?? 'HUAWEI';
            $olt['number_of_ports'] = isset($data['olt_number_of_ports']) && is_numeric($data['olt_number_of_ports'])
                ? (int) $data['olt_number_of_ports']
                : null;
            $olt['web_address'] = $this->normalizeOptionalString($data['olt_web_address'] ?? null);
            $olt['username'] = $this->normalizeOptionalString($data['olt_username'] ?? null);
            $olt['snmp_community'] = $snmpCommunity;
            $olt['folder_location'] = $oltFolderLocation;
            if (!empty($data['olt_password'])) {
                $olt['password'] = encrypt($data['olt_password']);
            }

            $meta['olt'] = array_filter($olt, static fn ($value) => $value !== null && $value !== '');
            $updatedModel = $this->normalizeOptionalString($olt['model'] ?? null) ?: 'OLT';
        } else {
            unset($meta['olt']);
        }

        if ($type === 'MIKROTIK') {
            $mikrotik = data_get($meta, 'mikrotik', []);
            if (!is_array($mikrotik)) {
                $mikrotik = [];
            }

            $updatedLocation = $this->normalizeOptionalString($data['mikrotik_location'] ?? null);
            $mikrotik['ip_address'] = $ipAddress;
            $mikrotik['username'] = $this->normalizeOptionalString($data['mikrotik_username'] ?? null);
            $mikrotik['location'] = $updatedLocation;
            $mikrotik['cabinet_id'] = $this->normalizeOptionalString($data['mikrotik_cabinet_id'] ?? null);
            $mikrotik['rack_uid'] = $this->normalizeOptionalString($data['mikrotik_rack_uid'] ?? null);
            $mikrotik['snmp_community'] = $snmpCommunity;
            $mikrotik['snmp_port'] = $snmpPort;
            $mikrotik['winbox_port'] = isset($data['mikrotik_winbox_port']) ? (int) $data['mikrotik_winbox_port'] : null;
            $mikrotik['ssh_port'] = isset($data['mikrotik_ssh_port']) ? (int) $data['mikrotik_ssh_port'] : null;
            $mikrotik['telnet_port'] = isset($data['mikrotik_telnet_port']) ? (int) $data['mikrotik_telnet_port'] : null;
            $mikrotik['api_port'] = isset($data['mikrotik_api_port']) ? (int) $data['mikrotik_api_port'] : null;
            $mikrotik['api_ssl_port'] = isset($data['mikrotik_api_ssl_port']) ? (int) $data['mikrotik_api_ssl_port'] : null;
            $mikrotik['ftp_port'] = isset($data['mikrotik_ftp_port']) ? (int) $data['mikrotik_ftp_port'] : null;
            $mikrotik['http_port'] = isset($data['mikrotik_http_port']) ? (int) $data['mikrotik_http_port'] : null;
            $mikrotik['web_address'] = $this->normalizeOptionalString($data['mikrotik_web_address'] ?? null);
            $mikrotik['winbox_address'] = $this->normalizeOptionalString($data['mikrotik_winbox_address'] ?? null);
            if (!empty($data['mikrotik_password'])) {
                $mikrotik['password'] = encrypt($data['mikrotik_password']);
            }
            $meta['mikrotik'] = array_filter($mikrotik, static fn ($value) => $value !== null && $value !== '');
            $updatedModel = 'MikroTik';
        } else {
            unset($meta['mikrotik']);
        }

        $updatedIp = $ipAddress ?? $device->ip_address;
        $updatedName = $this->resolveSubmittedDeviceName($type, $data, $updatedModel, $updatedIp, $device);
        if ($updatedName === null) {
            return back()
                ->withInput()
                ->withErrors(['name' => 'A device name could not be resolved. Please provide the required identity fields.']);
        }
        $updatedSerial = $this->typeUsesSerialNumber($type)
            ? $this->normalizeOptionalString($data['serial_number'] ?? $device->serial_number)
            : $device->serial_number;

        try {
            $device->update([
                'name' => $updatedName,
                'serial_number' => $updatedSerial,
                'type' => $type,
                'model' => $updatedModel,
                'ip_address' => $updatedIp,
                'location' => $updatedLocation,
                'metadata' => $meta,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Device update failed.', [
                'device_id' => $device->id,
                'name' => $updatedName,
                'type' => $type,
                'message' => $exception->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['device' => 'Failed to update device. Please review the input and server logs.']);
        }
        $this->writeTelemetryLog(
            $device,
            'info',
            'Device configuration updated.',
            ['action' => 'device.update']
        );

        return back()->with('status', "Device {$device->name} updated.");
    }

    public function refreshStatus(Request $request, Device $device)
    {
        ProvisioningTrace::log('device refresh trace: manual refresh requested', $this->deviceProvisioningContext($device, [
            'trace' => 'device refresh',
            'trigger' => $request->route()?->getName() ?: 'devices.refresh',
            'request_method' => $request->getMethod(),
            'request_path' => $request->path(),
            'request_ip' => $request->ip(),
        ]));
        $this->kickoffPoll($device);
        $this->writeTelemetryLog(
            $device,
            'debug',
            'Manual refresh requested.',
            ['action' => 'device.refresh']
        );
        return back()->with('status', "Device {$device->name} refreshed.");
    }

    public function activate(Request $request, Device $device)
    {
        ProvisioningTrace::log('device activation trace: activation requested', $this->deviceProvisioningContext($device, [
            'trace' => 'device activation',
            'trigger' => $request->route()?->getName() ?: 'devices.activate',
            'request_ip' => $request->ip(),
        ]));
        $meta = $this->normalizeMetadata($device->metadata);
        if (array_key_exists('monitoring_disabled', $meta)) {
            unset($meta['monitoring_disabled']);
        }
        $cisco = data_get($meta, 'cisco', []);
        if (is_array($cisco) && array_key_exists('monitoring_disabled', $cisco)) {
            unset($cisco['monitoring_disabled']);
            $meta['cisco'] = $cisco;
        }
        $server = data_get($meta, 'server', []);
        if (is_array($server) && array_key_exists('monitoring_disabled', $server)) {
            unset($server['monitoring_disabled']);
            $meta['server'] = $server;
        }
        $mikrotik = data_get($meta, 'mikrotik', []);
        if (is_array($mikrotik) && array_key_exists('monitoring_disabled', $mikrotik)) {
            unset($mikrotik['monitoring_disabled']);
            $meta['mikrotik'] = $mikrotik;
        }

        $device->update([
            'metadata' => $meta,
        ]);

        Cache::forget('device_probe_failures:' . $device->id);

        $this->kickoffPoll($device);
        $this->writeTelemetryLog(
            $device,
            'info',
            'Device activated. Monitoring resumed.',
            ['action' => 'device.activate']
        );

        ProvisioningTrace::log('device activation trace: activation completed', $this->deviceProvisioningContext($device, [
            'trace' => 'device activation',
            'trigger' => $request->route()?->getName() ?: 'devices.activate',
        ]));

        return back()->with('status', "Device {$device->name} activated.");
    }

    public function deactivate(Request $request, Device $device)
    {
        ProvisioningTrace::log('device deactivation trace: deactivation requested', $this->deviceProvisioningContext($device, [
            'trace' => 'device deactivation',
            'trigger' => $request->route()?->getName() ?: 'devices.deactivate',
            'request_ip' => $request->ip(),
        ]));
        $meta = $this->normalizeMetadata($device->metadata);
        $meta['monitoring_disabled'] = true;
        $meta['temperature'] = null;
        $meta['uptime'] = null;

        $cisco = data_get($meta, 'cisco', []);
        if (is_array($cisco)) {
            $cisco['monitoring_disabled'] = true;
            $meta['cisco'] = $cisco;
        }
        $server = data_get($meta, 'server', []);
        if (is_array($server)) {
            $server['monitoring_disabled'] = true;
            $meta['server'] = $server;
        }
        $mikrotik = data_get($meta, 'mikrotik', []);
        if (is_array($mikrotik)) {
            $mikrotik['monitoring_disabled'] = true;
            $meta['mikrotik'] = $mikrotik;
        }

        $device->update([
            'metadata' => $meta,
            'status' => 'offline',
        ]);

        Cache::forget('device_probe_failures:' . $device->id);
        Cache::forget('status_snapshot_probe:' . $device->id);
        $this->writeTelemetryLog(
            $device,
            'warning',
            'Device deactivated. Monitoring paused and status set offline.',
            ['action' => 'device.deactivate']
        );

        ProvisioningTrace::log('device deactivation trace: deactivation completed', $this->deviceProvisioningContext($device, [
            'trace' => 'device deactivation',
            'trigger' => $request->route()?->getName() ?: 'devices.deactivate',
        ]));

        return back()->with('status', "Device {$device->name} deactivated.");
    }

    public function destroy(Request $request, Device $device)
    {
        DeviceAssignment::where('device_id', $device->id)
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);

        $name = $device->name;
        $device->delete();

        return back()->with('status', "Device {$name} deleted.");
    }

    public function export(Request $request)
    {
        $filename = 'devices_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $columns = ['id', 'name', 'type', 'model', 'status', 'ip_address', 'last_seen_at'];

        $callback = function () use ($columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            Device::orderBy('id')->chunk(500, function ($devices) use ($handle, $columns) {
                foreach ($devices as $device) {
                    $row = [];
                    foreach ($columns as $column) {
                        $row[] = $device->{$column};
                    }
                    fputcsv($handle, $row);
                }
            });
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function filter(Request $request)
    {
        return response()->json([
            'status' => 'ok',
            'filters' => $request->all(),
        ]);
    }

    public function history(Request $request)
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'History not implemented yet.',
        ]);
    }

    private function kickoffPoll(Device $device): void
    {
        try {
            ProvisioningTrace::log('device refresh trace: dispatching poll command', $this->deviceProvisioningContext($device, [
                'trace' => 'device refresh',
                'trigger' => 'devices:poll-status',
                'command' => ['php artisan', 'devices:poll-status', '--device=' . $device->id],
            ]));
            $exitCode = Artisan::call('devices:poll-status', ['--device' => $device->id]);
            ProvisioningTrace::log('device refresh trace: poll command completed', $this->deviceProvisioningContext($device, [
                'trace' => 'device refresh',
                'trigger' => 'devices:poll-status',
                'exit_code' => $exitCode,
            ]));
        } catch (\Throwable $e) {
            Log::warning('device poll failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
            ProvisioningTrace::log('device refresh trace: poll command failed', $this->deviceProvisioningContext($device, [
                'trace' => 'device refresh',
                'trigger' => 'devices:poll-status',
                'error' => $e->getMessage(),
            ]));
        }
    }

    private function probeDeviceStatus(Device $device): void
    {
        $meta = $this->normalizeMetadata($device->metadata);
        if ($this->isMonitoringDisabled($meta)) {
            Cache::forget('device_probe_failures:' . $device->id);
            ProvisioningTrace::communication('device probe trace: monitoring disabled', $this->deviceProvisioningContext($device, [
                'trace' => 'device probe',
                'layer' => 'network_discovery',
                'protocol' => 'INTERNAL',
                'state' => 'warning',
                'reason' => 'Monitoring is disabled for this device.',
                'response' => [
                    'status' => 'skipped',
                    'summary' => 'Monitoring disabled; probe stopped before network discovery.',
                ],
            ]));
            $device->update(['status' => 'offline']);
            return;
        }

        $ip = $this->resolveDeviceIp($device, $meta);
        if (!$ip) {
            Cache::forget('device_probe_failures:' . $device->id);
            ProvisioningTrace::communication('device probe trace: missing device IP', $this->deviceProvisioningContext($device, [
                'trace' => 'device probe',
                'layer' => 'network_discovery',
                'protocol' => 'INTERNAL',
                'state' => 'failure',
                'reason' => 'No device IP or hostname is configured.',
                'response' => [
                    'status' => 'skipped',
                    'summary' => 'Probe could not start because no target address was resolved.',
                ],
            ]));
            $device->update(['status' => 'offline']);
            return;
        }

        $ports = $this->resolveProbePorts($meta);
        $probeOnline = $this->tcpProbe($device, $ip, $ports);
        $probeMethod = 'tcp';
        if (!$probeOnline) {
            $probeMethod = 'icmp';
            $probeOnline = $this->pingHost($device, $ip);
        }

        $isOnline = $this->resolveEffectiveOnlineState($device, $probeOnline, $ip, $probeMethod);
        $updates = ['status' => $isOnline ? 'online' : 'offline'];
        if ($isOnline) {
            $updates['last_seen_at'] = now();
        }

        $device->update($updates);
        ProvisioningTrace::communication('device probe trace: probe completed', $this->deviceProvisioningContext($device, [
            'trace' => 'device probe',
            'layer' => 'status_transition',
            'protocol' => strtoupper($probeMethod === 'icmp' ? 'ICMP' : 'TCP'),
            'state' => $isOnline ? 'success' : 'failure',
            'device_ip' => $ip,
            'request' => [
                'operation' => 'status_snapshot_probe',
                'target' => $ip,
                'summary' => 'Device status snapshot probe completed.',
            ],
            'response' => [
                'status' => $isOnline ? 'online' : 'offline',
                'summary' => $isOnline ? 'Device responded to probe.' : 'Device did not respond to TCP or ICMP probe.',
            ],
        ]));
    }

    private function resolveEffectiveOnlineState(Device $device, bool $probeOnline, string $ip, string $probeMethod): bool
    {
        $failureKey = 'device_probe_failures:' . $device->id;
        $wasOnline = strtolower((string) ($device->status ?? 'offline')) === 'online';

        if ($probeOnline) {
            Cache::forget($failureKey);
            ProvisioningTrace::communication('device probe trace: probe succeeded', $this->deviceProvisioningContext($device, [
                'trace' => 'device probe',
                'layer' => 'error_handling',
                'protocol' => strtoupper($probeMethod === 'icmp' ? 'ICMP' : 'TCP'),
                'state' => 'success',
                'device_ip' => $ip,
                'response' => [
                    'status' => 'reachable',
                    'summary' => 'Failure counter cleared after a successful probe.',
                ],
            ]));
            return true;
        }

        $failures = (int) Cache::get($failureKey, 0) + 1;
        Cache::put($failureKey, $failures, now()->addSeconds(self::PROBE_FAILURE_TTL_SECONDS));

        if ($wasOnline && $failures < self::OFFLINE_FAILURE_THRESHOLD) {
            ProvisioningTrace::communication('device probe trace: transient failure retained online state', $this->deviceProvisioningContext($device, [
                'trace' => 'device probe',
                'layer' => 'error_handling',
                'protocol' => strtoupper($probeMethod === 'icmp' ? 'ICMP' : 'TCP'),
                'state' => 'warning',
                'device_ip' => $ip,
                'reason' => 'Device missed a probe but has not crossed the offline retry threshold yet.',
                'retry' => [
                    'attempt' => $failures,
                    'max' => self::OFFLINE_FAILURE_THRESHOLD,
                ],
                'response' => [
                    'status' => 'retrying',
                    'summary' => 'Keeping device online until the retry threshold is reached.',
                ],
            ]));
            return true;
        }

        ProvisioningTrace::communication('device probe trace: offline threshold reached', $this->deviceProvisioningContext($device, [
            'trace' => 'device probe',
            'layer' => 'error_handling',
            'protocol' => strtoupper($probeMethod === 'icmp' ? 'ICMP' : 'TCP'),
            'state' => 'failure',
            'device_ip' => $ip,
            'reason' => 'Probe retry threshold reached; device is now considered offline.',
            'retry' => [
                'attempt' => $failures,
                'max' => self::OFFLINE_FAILURE_THRESHOLD,
            ],
            'response' => [
                'status' => 'offline',
                'summary' => 'No successful probe response before the offline threshold elapsed.',
            ],
        ]));

        return false;
    }

    private function resolveDeviceIp(Device $device, array $meta): ?string
    {
        $ip = $device->ip_address ?: data_get($meta, 'cisco.ip_address');
        if (!$ip) {
            $ip = data_get($meta, 'mimosa.ip');
        }
        if (!$ip) {
            $ip = data_get($meta, 'server.ip_address');
        }
        if (!$ip) {
            $ip = data_get($meta, 'mikrotik.ip_address');
        }
        $ip = is_string($ip) ? trim($ip) : $ip;
        return $ip !== '' ? $ip : null;
    }

    private function resolveProbePorts(array $meta): array
    {
        $snmpPort = data_get($meta, 'snmp_port');
        if (!is_numeric($snmpPort)) {
            $snmpPort = data_get($meta, 'server.snmp_port');
        }
        if (!is_numeric($snmpPort)) {
            $snmpPort = data_get($meta, 'mikrotik.snmp_port');
        }
        $snmpPort = is_numeric($snmpPort) ? (int) $snmpPort : null;

        $sshPort = data_get($meta, 'server.ssh_port');
        $sshPort = is_numeric($sshPort) ? (int) $sshPort : null;
        $mikrotikPorts = [];
        foreach (self::MIKROTIK_PORT_KEYS as $portKey) {
            $portValue = data_get($meta, 'mikrotik.' . $portKey);
            if (is_numeric($portValue)) {
                $mikrotikPorts[] = (int) $portValue;
            }
        }

        $defaults = [22, 23, 161];
        return array_values(array_filter(array_merge([$sshPort, $snmpPort], $mikrotikPorts, $defaults)));
    }

    private function tcpProbe(Device $device, string $ip, array $ports): bool
    {
        $ports = array_values(array_unique(array_filter($ports, static fn ($port) => is_int($port) && $port > 0)));
        $maxAttempts = max(1, count($ports));

        foreach ($ports as $index => $port) {
            $errno = 0;
            $errstr = '';
            $startedAt = microtime(true);
            $socket = @fsockopen($ip, $port, $errno, $errstr, 0.8);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            ProvisioningTrace::communication(
                $socket ? 'device probe trace: tcp probe succeeded' : 'device probe trace: tcp probe failed',
                $this->deviceProvisioningContext($device, [
                    'trace' => 'device probe',
                    'layer' => 'network_discovery',
                    'protocol' => 'TCP',
                    'state' => $socket ? 'success' : 'warning',
                    'device_ip' => $ip,
                    'latency_ms' => $latencyMs,
                    'request' => [
                        'operation' => 'tcp_connect',
                        'target' => $ip . ':' . $port,
                        'summary' => "TCP connect attempt to {$ip}:{$port}",
                    ],
                    'response' => [
                        'status' => $socket ? 'connected' : 'failed',
                        'summary' => $socket
                            ? "TCP connection opened on {$ip}:{$port}"
                            : ($errstr !== '' ? $errstr : "TCP connection failed on {$ip}:{$port}"),
                    ],
                    'reason' => $socket ? null : ($errstr !== '' ? $errstr : ($errno !== 0 ? "TCP error {$errno}" : 'TCP probe failed without an OS error message.')),
                    'retry' => [
                        'attempt' => $index + 1,
                        'max' => $maxAttempts,
                    ],
                ])
            );

            if ($socket) {
                fclose($socket);
                return true;
            }
        }
        return false;
    }

    private function pingHost(Device $device, string $ip): bool
    {
        $timeoutMs = 1000;
        if (PHP_OS_FAMILY === 'Windows') {
            $command = ['ping', '-n', '1', '-w', (string) $timeoutMs, $ip];
        } else {
            $timeoutSec = max(1, (int) ceil($timeoutMs / 1000));
            $command = ['ping', '-c', '1', '-W', (string) $timeoutSec, $ip];
        }

        $process = new \Symfony\Component\Process\Process($command);
        $process->setTimeout(5);
        $result = ProvisioningTrace::runProcess($process, [
            'label' => 'device probe trace',
            'line_prefix' => 'device probe trace',
            'log_output' => false,
            'context' => [
                'trace' => 'device probe',
                'probe_step' => 'ping',
                'device_ip' => $ip,
                'command' => $command,
            ],
        ]);

        ProvisioningTrace::communication(
            $result['ok'] ? 'device probe trace: icmp probe succeeded' : 'device probe trace: icmp probe failed',
            $this->deviceProvisioningContext($device, [
                'trace' => 'device probe',
                'layer' => 'network_discovery',
                'protocol' => 'ICMP',
                'state' => $result['ok'] ? 'success' : 'failure',
                'device_ip' => $ip,
                'latency_ms' => $result['duration_ms'] ?? null,
                'request' => [
                    'operation' => 'ping',
                    'target' => $ip,
                    'summary' => 'ICMP echo request',
                ],
                'response' => [
                    'status' => $result['ok'] ? 'reachable' : 'unreachable',
                    'summary' => ProvisioningTrace::summarizeText($result['output'] ?? '') ?? ($result['ok'] ? 'Ping completed successfully.' : 'Ping failed.'),
                ],
                'reason' => $result['ok'] ? null : (ProvisioningTrace::summarizeText($result['output'] ?? '') ?? 'Ping failed or timed out.'),
            ])
        );

        return $result['ok'];
    }

    private function normalizeMetadata($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private function isMonitoringDisabled(array $meta): bool
    {
        return !empty($meta['monitoring_disabled'])
            || !empty(data_get($meta, 'cisco.monitoring_disabled'))
            || !empty(data_get($meta, 'server.monitoring_disabled'))
            || !empty(data_get($meta, 'mikrotik.monitoring_disabled'));
    }

    private function writeTelemetryLog(Device $device, string $level, string $message, array $payload = []): void
    {
        try {
            TelemetryLog::create([
                'device_id' => $device->id,
                'level' => strtolower(trim($level)) !== '' ? strtolower(trim($level)) : 'info',
                'message' => $message,
                'payload' => $payload,
                'recorded_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('device telemetry log write failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function deviceProvisioningContext(Device $device, array $context = []): array
    {
        return $context + [
            'device_id' => $device->id,
            'device_name' => $device->name,
            'device_type' => $device->type,
        ];
    }

    private function resolveSubmittedDeviceName(
        string $type,
        array $data,
        ?string $model = null,
        ?string $ipAddress = null,
        ?Device $existing = null
    ): ?string {
        $normalizedType = strtoupper(trim($type));
        $name = $this->normalizeOptionalString($data['name'] ?? null)
            ?? $this->normalizeOptionalString($existing?->name ?? null);

        if ($normalizedType === 'CISCO') {
            $name = $this->normalizeOptionalString($data['cisco_name'] ?? null) ?? $name;
        } elseif ($normalizedType === 'SERVER') {
            $name = $this->normalizeOptionalString($data['server_name'] ?? null) ?? $name;
        } elseif ($normalizedType === 'MIMOSA') {
            $name = $this->firstNonEmptyString([
                $data['mimosa_c5c_name'] ?? null,
                $data['mimosa_c5x_name'] ?? null,
                $data['mimosa_b11_name'] ?? null,
            ]) ?? $name;

            if ($name === null) {
                $mimosaIp = $this->firstNonEmptyString([
                    $data['mimosa_c5c_ip'] ?? null,
                    $data['mimosa_c5x_ip'] ?? null,
                    $data['mimosa_b11_ip'] ?? null,
                ]);
                if ($mimosaIp !== null) {
                    $slug = trim((string) preg_replace('/[^a-z0-9]+/i', '-', strtolower($mimosaIp)), '-');
                    $name = $slug !== '' ? 'mimosa-' . $slug : null;
                }
            }
        } elseif ($normalizedType === 'OLT') {
            if ($name === null && $ipAddress !== null) {
                $name = 'OLT ' . $ipAddress;
            }
            if ($name === null && $model !== null) {
                $name = 'OLT ' . $model;
            }
        } elseif ($normalizedType === 'MIKROTIK') {
            if ($name === null && $ipAddress !== null) {
                $name = 'MikroTik ' . $ipAddress;
            }
        }

        return $name !== null ? substr($name, 0, 255) : null;
    }

    private function firstNonEmptyString(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $value = $this->normalizeOptionalString($candidate);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function validateCreateRequiredFields(string $type, array $data): array
    {
        return match (strtoupper(trim($type))) {
            'CISCO' => $this->buildMissingFieldErrors(array_filter([
                'cisco_name' => ['label' => 'Device name', 'value' => $data['cisco_name'] ?? null],
                'ip_address' => ['label' => 'IP address', 'value' => $data['ip_address'] ?? null],
                'cisco_username' => ['label' => 'Username', 'value' => $data['cisco_username'] ?? null],
                'cisco_password' => ['label' => 'Password', 'value' => $data['cisco_password'] ?? null],
            ], fn (mixed $field, string $key) => $key !== 'cisco_username'
                || $this->ciscoModelUsesUsername($data['switch_model'] ?? null), ARRAY_FILTER_USE_BOTH)),
            'MIMOSA' => $this->buildMissingFieldErrors([
                'mimosa_' . $this->resolveMimosaFormKey($data['mimosa_model'] ?? null) . '_name' => [
                    'label' => 'Device name',
                    'value' => $data['mimosa_' . $this->resolveMimosaFormKey($data['mimosa_model'] ?? null) . '_name'] ?? null,
                ],
                'mimosa_' . $this->resolveMimosaFormKey($data['mimosa_model'] ?? null) . '_ip' => [
                    'label' => 'IP address',
                    'value' => $data['mimosa_' . $this->resolveMimosaFormKey($data['mimosa_model'] ?? null) . '_ip'] ?? null,
                ],
                'mimosa_' . $this->resolveMimosaFormKey($data['mimosa_model'] ?? null) . '_username' => [
                    'label' => 'Username',
                    'value' => $data['mimosa_' . $this->resolveMimosaFormKey($data['mimosa_model'] ?? null) . '_username'] ?? null,
                ],
                'mimosa_' . $this->resolveMimosaFormKey($data['mimosa_model'] ?? null) . '_password' => [
                    'label' => 'Password',
                    'value' => $data['mimosa_' . $this->resolveMimosaFormKey($data['mimosa_model'] ?? null) . '_password'] ?? null,
                ],
            ]),
            'SERVER' => $this->buildMissingFieldErrors([
                'server_name' => ['label' => 'Device name', 'value' => $data['server_name'] ?? null],
                'ip_address' => ['label' => 'IP address', 'value' => $data['ip_address'] ?? null],
                'server_username' => ['label' => 'Username', 'value' => $data['server_username'] ?? null],
                'server_password' => ['label' => 'Password', 'value' => $data['server_password'] ?? null],
            ]),
            'OLT' => $this->buildMissingFieldErrors([
                'name' => ['label' => 'Device name', 'value' => $data['name'] ?? null],
                'olt_device_type' => ['label' => 'OLT type', 'value' => $data['olt_device_type'] ?? null],
                'olt_ip_address' => ['label' => 'IP address', 'value' => $data['olt_ip_address'] ?? null],
                'olt_username' => ['label' => 'Username', 'value' => $data['olt_username'] ?? null],
                'olt_password' => ['label' => 'Password', 'value' => $data['olt_password'] ?? null],
            ]),
            'MIKROTIK' => $this->buildMissingFieldErrors([
                'name' => ['label' => 'Device name', 'value' => $data['name'] ?? null],
                'mikrotik_ip_address' => ['label' => 'IP address', 'value' => $data['mikrotik_ip_address'] ?? null],
                'mikrotik_username' => ['label' => 'Username', 'value' => $data['mikrotik_username'] ?? null],
                'mikrotik_password' => ['label' => 'Password', 'value' => $data['mikrotik_password'] ?? null],
            ]),
            default => [],
        };
    }

    private function buildMissingFieldErrors(array $fields): array
    {
        $errors = [];
        foreach ($fields as $key => $field) {
            $label = $field['label'] ?? 'This field';
            $value = $field['value'] ?? null;
            if ($this->normalizeOptionalString($value) === null) {
                $errors[$key] = $label . ' is required.';
            }
        }

        return $errors;
    }

    private function resolveMimosaFormKey(mixed $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        return match ($normalized) {
            'C5X' => 'c5x',
            'B11' => 'b11',
            default => 'c5c',
        };
    }

    private function normalizeServerServices(mixed $value): array
    {
        if (is_string($value) || is_numeric($value)) {
            $value = [$value];
        }
        if (!is_array($value)) {
            return [];
        }

        $services = [];
        foreach ($value as $service) {
            if (!is_scalar($service)) {
                continue;
            }
            $normalized = strtolower(trim((string) $service));
            if ($normalized === 'netplat') {
                $normalized = 'netplay';
            }
            if ($normalized === '') {
                continue;
            }
            if (!in_array($normalized, self::SERVER_SERVICE_OPTIONS, true)) {
                continue;
            }
            $services[] = $normalized;
        }

        return array_values(array_unique($services));
    }

    private function ciscoModelUsesUsername(mixed $value): bool
    {
        $normalized = strtoupper(trim((string) $value));

        return !in_array($normalized, self::CISCO_MODELS_WITHOUT_USERNAME, true);
    }

    private function serverServiceNeedsWebAddress(string $service): bool
    {
        return in_array($service, self::SERVER_WEB_ADDRESS_SERVICE_OPTIONS, true);
    }

    private function serverServiceNeedsWebCredentials(string $service): bool
    {
        return in_array($service, self::SERVER_WEB_AUTH_SERVICE_OPTIONS, true);
    }

    private function validateServerServiceAccess(array $data): array
    {
        $services = $this->normalizeServerServices($data['server_service'] ?? []);
        $access = $data['server_service_access'] ?? [];
        if (!is_array($access)) {
            $access = [];
        }

        $errors = [];
        foreach ($services as $service) {
            $serviceAccess = $access[$service] ?? [];
            if (!is_array($serviceAccess)) {
                $serviceAccess = [];
            }

            if ($this->serverServiceNeedsWebAddress($service)
                && $this->normalizeOptionalString($serviceAccess['address_port'] ?? null) === null) {
                $errors["server_service_access.$service.address_port"] = sprintf(
                    'Server Web Address and Port (%s) is required.',
                    $this->serverServiceLabel($service)
                );
            }

            if ($service === 'vnc'
                && $this->normalizeOptionalString($serviceAccess['vnc_ip'] ?? null) === null) {
                $errors["server_service_access.$service.vnc_ip"] = 'VNC IP (VNC) is required.';
            }
        }

        return $errors;
    }

    private function buildServerServiceAccess(array $services, mixed $value, array $existingServer): array
    {
        $input = is_array($value) ? $value : [];
        $existingAccess = data_get($existingServer, 'service_access', []);
        if (!is_array($existingAccess)) {
            $existingAccess = [];
        }

        $legacyWebPassword = data_get($existingServer, 'web_password');
        $legacyVncPassword = data_get($existingServer, 'vnc_password');
        $normalized = [];

        foreach ($services as $service) {
            $serviceInput = $input[$service] ?? [];
            if (!is_array($serviceInput)) {
                $serviceInput = [];
            }
            $existingServiceAccess = $existingAccess[$service] ?? [];
            if (!is_array($existingServiceAccess)) {
                $existingServiceAccess = [];
            }

            $serviceMeta = [];
            if ($this->serverServiceNeedsWebAddress($service)) {
                $addressPort = $this->normalizeOptionalString($serviceInput['address_port'] ?? null);
                if ($addressPort !== null) {
                    $serviceMeta['address_port'] = $addressPort;
                }
            }

            if ($this->serverServiceNeedsWebCredentials($service)) {
                $username = $this->normalizeOptionalString($serviceInput['username'] ?? null);
                if ($username !== null) {
                    $serviceMeta['username'] = $username;
                }

                $password = $this->normalizeOptionalString($serviceInput['password'] ?? null);
                if ($password !== null) {
                    $serviceMeta['password'] = encrypt($password);
                } elseif (!empty($existingServiceAccess['password'])) {
                    $serviceMeta['password'] = $existingServiceAccess['password'];
                } elseif (!empty($legacyWebPassword)) {
                    $serviceMeta['password'] = $legacyWebPassword;
                }
            }

            if ($service === 'vnc') {
                $vncIp = $this->normalizeOptionalString($serviceInput['vnc_ip'] ?? null);
                if ($vncIp !== null) {
                    $serviceMeta['vnc_ip'] = $vncIp;
                }

                $vncPassword = $this->normalizeOptionalString($serviceInput['vnc_password'] ?? null);
                if ($vncPassword !== null) {
                    $serviceMeta['vnc_password'] = encrypt($vncPassword);
                } elseif (!empty($existingServiceAccess['vnc_password'])) {
                    $serviceMeta['vnc_password'] = $existingServiceAccess['vnc_password'];
                } elseif (!empty($legacyVncPassword)) {
                    $serviceMeta['vnc_password'] = $legacyVncPassword;
                }
            }

            if ($serviceMeta !== []) {
                $normalized[$service] = $serviceMeta;
            }
        }

        return $normalized;
    }

    private function serverServiceLabel(string $service): string
    {
        return self::SERVER_SERVICE_LABELS[$service]
            ?? Str::title(str_replace('_', ' ', $service));
    }

    private function typeUsesSerialNumber(string $type): bool
    {
        return !in_array(strtoupper(trim($type)), ['CISCO', 'MIMOSA', 'SERVER', 'OLT', 'MIKROTIK'], true);
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeServerType(mixed $value): string
    {
        if (!is_scalar($value)) {
            return 'virtual_server';
        }
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, self::SERVER_TYPE_OPTIONS, true)
            ? $normalized
            : 'virtual_server';
    }

    private function normalizeOltDeviceType(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = strtoupper(trim((string) $value));
        return in_array($normalized, self::OLT_DEVICE_TYPE_OPTIONS, true)
            ? $normalized
            : null;
    }

    private function resolveSignalAndBattery(array $meta, string $deviceType = ''): array
    {
        $signalCandidates = [
            data_get($meta, 'signal'),
            data_get($meta, 'rssi'),
            data_get($meta, 'radio.signal'),
            data_get($meta, 'radio.rssi'),
            data_get($meta, 'wireless.signal'),
            data_get($meta, 'wireless.rssi'),
            data_get($meta, 'mimosa.signal'),
            data_get($meta, 'mimosa.rssi'),
            data_get($meta, 'cisco.signal'),
            data_get($meta, 'payload.signal'),
            data_get($meta, 'payload.rssi'),
        ];

        $batteryCandidates = [
            data_get($meta, 'battery'),
            data_get($meta, 'battery_level'),
            data_get($meta, 'battery.percent'),
            data_get($meta, 'power.battery'),
            data_get($meta, 'mimosa.battery'),
            data_get($meta, 'mimosa.battery_level'),
            data_get($meta, 'cisco.battery'),
            data_get($meta, 'payload.battery'),
            data_get($meta, 'payload.battery_level'),
        ];

        $mimosa = data_get($meta, 'mimosa', []);
        if (is_array($mimosa)) {
            foreach ($mimosa as $key => $value) {
                $normalizedKey = strtolower((string) $key);
                if (str_contains($normalizedKey, 'signal') || str_contains($normalizedKey, 'rssi')) {
                    $signalCandidates[] = $value;
                }
                if (str_contains($normalizedKey, 'battery')) {
                    $batteryCandidates[] = $value;
                }
            }
        }

        $signalRaw = $this->firstMetricValue($signalCandidates);
        $batteryRaw = $this->firstMetricValue($batteryCandidates);

        $signal = $this->formatSignalValue($signalRaw);
        $battery = $this->formatBatteryValue($batteryRaw);

        if (($signal === '-' || $signal === '') && strtoupper(trim($deviceType)) === 'CISCO') {
            $signal = 'N/A';
        }
        if (($battery === '-' || $battery === '') && strtoupper(trim($deviceType)) === 'CISCO') {
            $battery = 'N/A';
        }

        return [
            'signal' => $signal !== '' ? $signal : '-',
            'battery' => $battery !== '' ? $battery : '-',
        ];
    }

    private function resolveRackHeight(array $meta): int
    {
        foreach ([
            data_get($meta, 'rack.height_u'),
            data_get($meta, 'rack_height_u'),
            data_get($meta, 'server.height_u'),
            data_get($meta, 'cisco.height_u'),
            data_get($meta, 'mikrotik.height_u'),
        ] as $value) {
            if (is_numeric($value) && (int) $value >= 1 && (int) $value <= 8) {
                return (int) $value;
            }
        }

        return 1;
    }

    private function firstMetricValue(array $candidates): mixed
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }

            if (is_string($candidate)) {
                $trimmed = trim($candidate);
                if ($trimmed === '' || $trimmed === '-') {
                    continue;
                }
                return $trimmed;
            }

            if (is_numeric($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function formatSignalValue(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        if (is_numeric($value)) {
            $num = (float) $value;
            if ($num < 0 && $num >= -200) {
                return rtrim(rtrim((string) $num, '0'), '.') . ' dBm';
            }
            if ($num >= 0 && $num <= 100) {
                return rtrim(rtrim((string) $num, '0'), '.') . '%';
            }
            return rtrim(rtrim((string) $num, '0'), '.');
        }

        return trim((string) $value);
    }

    private function formatBatteryValue(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        if (is_numeric($value)) {
            $num = (float) $value;
            if ($num >= 0 && $num <= 100) {
                return rtrim(rtrim((string) $num, '0'), '.') . '%';
            }
            return rtrim(rtrim((string) $num, '0'), '.');
        }

        return trim((string) $value);
    }
}

