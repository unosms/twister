<?php

namespace App\Http\Controllers;

use App\Models\Cabinet;
use App\Models\CabinetPlacement;
use App\Models\Device;
use App\Models\Room;
use App\Models\TelemetryLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CabinetRoomController extends Controller
{
    private const ALLOWED_FACES = ['front', 'back'];

    private ?bool $interfacesTableAvailable = null;

    private array $rackInterfaceCache = [];

    public function index(Request $request)
    {
        return view('device_cabinet_room', [
            'authUser' => User::find($request->session()->get('auth.user_id')),
            'initialRooms' => $this->roomsPayload(),
            'initialRoomId' => (int) $request->query('room', 0),
            'initialCabinetId' => (int) $request->query('cabinet', 0),
        ]);
    }

    public function rooms()
    {
        return $this->noStoreJson([
            'rooms' => $this->roomsPayload(),
        ]);
    }

    public function storeRoom(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $room = Room::create($data);

        Log::debug('cabinet room action: room created', [
            'room_id' => $room->id,
            'name' => $room->name,
            'location' => $room->location,
        ]);

        return response()->json([
            'status' => 'ok',
            'room' => $this->roomPayload($room->fresh('cabinets')),
        ], 201);
    }

    public function cabinets(Room $room)
    {
        return $this->noStoreJson([
            'room' => $this->roomPayload($room->load('cabinets')),
            'cabinets' => $room->cabinets()->withCount('placements')->orderBy('name')->get()->map(
                fn (Cabinet $cabinet): array => $this->cabinetPayload($cabinet)
            )->values(),
        ]);
    }

    public function storeCabinet(Request $request, Room $room)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size_u' => ['required', 'integer', 'min:1', 'max:60'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
        ]);

        $cabinet = $room->cabinets()->create($data);

        Log::debug('cabinet room action: cabinet created', [
            'room_id' => $room->id,
            'cabinet_id' => $cabinet->id,
            'name' => $cabinet->name,
            'size_u' => $cabinet->size_u,
        ]);

        return response()->json([
            'status' => 'ok',
            'cabinet' => $this->cabinetPayload($cabinet->fresh('placements')),
        ], 201);
    }

    public function placements(Cabinet $cabinet)
    {
        $cabinet->load(['room', 'placements.device']);

        return $this->noStoreJson([
            'cabinet' => $this->cabinetPayload($cabinet),
            'placements' => $cabinet->placements
                ->sortBy(fn (CabinetPlacement $placement): string => $placement->face . ':' . str_pad((string) $placement->start_u, 4, '0', STR_PAD_LEFT))
                ->values()
                ->map(fn (CabinetPlacement $placement): array => $this->placementPayload($placement))
                ->all(),
        ]);
    }

    public function storePlacement(Request $request, Cabinet $cabinet)
    {
        $data = $request->validate([
            'device_id' => ['required', 'integer', 'exists:devices,id'],
            'start_u' => ['required', 'integer', 'min:1'],
            'height_u' => ['required', 'integer', 'min:1'],
            'face' => ['required', 'string', 'in:front,back'],
        ]);

        $device = Device::findOrFail((int) $data['device_id']);
        $startU = (int) $data['start_u'];
        $heightU = (int) $data['height_u'];
        $face = $this->normalizeFace($data['face']);

        $this->assertPlacementFitsCabinet($cabinet, $startU, $heightU);
        $this->assertDeviceAvailable($device);
        $this->assertNoOverlap($cabinet, $face, $startU, $heightU);

        $placement = CabinetPlacement::create([
            'cabinet_id' => $cabinet->id,
            'device_id' => $device->id,
            'start_u' => $startU,
            'height_u' => $heightU,
            'face' => $face,
        ]);

        Log::debug('cabinet room action: placement created', [
            'placement_id' => $placement->id,
            'cabinet_id' => $cabinet->id,
            'device_id' => $device->id,
            'start_u' => $startU,
            'height_u' => $heightU,
            'face' => $face,
        ]);

        return response()->json([
            'status' => 'ok',
            'placement' => $this->placementPayload($placement->load('device', 'cabinet.room')),
        ], 201);
    }

    public function updatePlacement(Request $request, CabinetPlacement $placement)
    {
        $data = $request->validate([
            'cabinet_id' => ['nullable', 'integer', 'exists:cabinets,id'],
            'start_u' => ['nullable', 'integer', 'min:1'],
            'height_u' => ['nullable', 'integer', 'min:1'],
            'face' => ['nullable', 'string', 'in:front,back'],
        ]);

        $targetCabinet = isset($data['cabinet_id'])
            ? Cabinet::findOrFail((int) $data['cabinet_id'])
            : $placement->cabinet;
        $startU = isset($data['start_u']) ? (int) $data['start_u'] : (int) $placement->start_u;
        $heightU = isset($data['height_u']) ? (int) $data['height_u'] : (int) $placement->height_u;
        $face = isset($data['face']) ? $this->normalizeFace($data['face']) : $this->normalizeFace($placement->face);

        $this->assertPlacementFitsCabinet($targetCabinet, $startU, $heightU);
        $this->assertNoOverlap($targetCabinet, $face, $startU, $heightU, $placement->id);

        $placement->update([
            'cabinet_id' => $targetCabinet->id,
            'start_u' => $startU,
            'height_u' => $heightU,
            'face' => $face,
        ]);

        Log::debug('cabinet room action: placement updated', [
            'placement_id' => $placement->id,
            'cabinet_id' => $targetCabinet->id,
            'device_id' => $placement->device_id,
            'start_u' => $startU,
            'height_u' => $heightU,
            'face' => $face,
        ]);

        return $this->noStoreJson([
            'status' => 'ok',
            'placement' => $this->placementPayload($placement->fresh('device', 'cabinet.room')),
        ]);
    }

    public function destroyPlacement(CabinetPlacement $placement)
    {
        Log::debug('cabinet room action: placement deleted', [
            'placement_id' => $placement->id,
            'cabinet_id' => $placement->cabinet_id,
            'device_id' => $placement->device_id,
        ]);

        $placement->delete();

        return $this->noStoreJson([
            'status' => 'ok',
        ]);
    }

    public function deviceDetails(Device $device)
    {
        Log::debug('cabinet room action: device details fetched', [
            'device_id' => $device->id,
            'status' => $device->status,
        ]);

        return $this->noStoreJson([
            'device' => $this->deviceDetailPayload($device->load('cabinetPlacement.cabinet.room')),
        ]);
    }

    public function streamDeviceDetails(Device $device): StreamedResponse
    {
        Log::debug('cabinet room action: device detail stream opened', [
            'device_id' => $device->id,
        ]);

        return response()->stream(function () use ($device): void {
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');

            echo ": cabinet-room-stream\n\n";
            @ob_flush();
            flush();

            for ($tick = 0; $tick < 18; $tick++) {
                if (connection_aborted()) {
                    break;
                }

                $snapshot = $this->deviceDetailPayload($device->fresh()->load('cabinetPlacement.cabinet.room'));

                echo "event: device\n";
                echo 'data: ' . json_encode(['device' => $snapshot], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";

                @ob_flush();
                flush();

                sleep(10);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function roomsPayload(): array
    {
        return Room::with(['cabinets' => fn ($query) => $query->withCount('placements')->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room): array => $this->roomPayload($room))
            ->all();
    }

    private function roomPayload(Room $room): array
    {
        $cabinets = $room->relationLoaded('cabinets')
            ? $room->cabinets
            : $room->cabinets()->withCount('placements')->orderBy('name')->get();

        return [
            'id' => $room->id,
            'name' => $room->name,
            'location' => $room->location,
            'notes' => $room->notes,
            'cabinet_count' => $cabinets->count(),
            'cabinets' => $cabinets->map(fn (Cabinet $cabinet): array => $this->cabinetPayload($cabinet))->all(),
        ];
    }

    private function cabinetPayload(Cabinet $cabinet): array
    {
        return [
            'id' => $cabinet->id,
            'room_id' => $cabinet->room_id,
            'name' => $cabinet->name,
            'size_u' => (int) $cabinet->size_u,
            'manufacturer' => $cabinet->manufacturer,
            'model' => $cabinet->model,
            'placements_count' => $cabinet->placements_count ?? ($cabinet->relationLoaded('placements') ? $cabinet->placements->count() : 0),
            'created_at' => $cabinet->created_at?->toIso8601String(),
        ];
    }

    private function placementPayload(CabinetPlacement $placement): array
    {
        $device = $placement->relationLoaded('device') ? $placement->device : $placement->device()->first();
        $cabinet = $placement->relationLoaded('cabinet') ? $placement->cabinet : $placement->cabinet()->first();
        $meta = $this->normalizeMetadata($device?->metadata);

        return [
            'id' => $placement->id,
            'cabinet_id' => $placement->cabinet_id,
            'device_id' => $placement->device_id,
            'start_u' => (int) $placement->start_u,
            'height_u' => (int) $placement->height_u,
            'end_u' => (int) $placement->start_u + (int) $placement->height_u - 1,
            'face' => $placement->face,
            'device' => $device ? [
                'id' => $device->id,
                'name' => $device->name,
                'model' => $device->model,
                'type' => $device->type,
                'status' => $device->status,
                'ip_address' => $device->ip_address ?: data_get($meta, 'cisco.ip_address'),
                'serial_number' => $device->serial_number,
                'default_height_u' => $this->defaultDeviceHeight($device),
                'rack_interfaces' => $this->rackInterfacesPayload($device),
            ] : null,
            'cabinet' => $cabinet ? [
                'id' => $cabinet->id,
                'room_id' => $cabinet->room_id,
                'size_u' => (int) $cabinet->size_u,
                'name' => $cabinet->name,
            ] : null,
            'updated_at' => $placement->updated_at?->toIso8601String(),
        ];
    }

    private function deviceDetailPayload(Device $device): array
    {
        $meta = $this->normalizeMetadata($device->metadata);
        $telemetry = TelemetryLog::query()
            ->where('device_id', $device->id)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();
        $telemetryPayload = is_array($telemetry?->payload) ? $telemetry->payload : [];

        return [
            'id' => $device->id,
            'name' => $device->name,
            'ip_address' => $device->ip_address ?: $this->firstNonEmpty(
                data_get($meta, 'cisco.ip_address'),
                data_get($meta, 'server.ip_address'),
                data_get($meta, 'mikrotik.ip_address'),
                data_get($meta, 'olt.ip_address'),
                data_get($meta, 'mimosa.ip')
            ),
            'vendor' => $this->firstNonEmpty(
                data_get($meta, 'vendor'),
                data_get($meta, 'manufacturer'),
                data_get($meta, 'cisco.vendor'),
                data_get($meta, 'server.vendor'),
                $device->type
            ),
            'model' => $device->model,
            'serial_number' => $device->serial_number,
            'location' => $device->location,
            'status' => $device->status ?? 'offline',
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'last_seen_formatted' => $device->last_seen_at?->format('Y-m-d H:i:s'),
            'last_seen_human' => $device->last_seen_at?->diffForHumans(),
            'placement' => $device->cabinetPlacement ? [
                'id' => $device->cabinetPlacement->id,
                'cabinet_id' => $device->cabinetPlacement->cabinet_id,
                'cabinet_name' => $device->cabinetPlacement->cabinet?->name,
                'room_name' => $device->cabinetPlacement->cabinet?->room?->name,
                'start_u' => (int) $device->cabinetPlacement->start_u,
                'height_u' => (int) $device->cabinetPlacement->height_u,
                'face' => $device->cabinetPlacement->face,
            ] : null,
            'metrics' => [
                'cpu' => $this->metricValue($meta, $telemetryPayload, ['cpu', 'cpu_percent', 'usage.cpu', 'metrics.cpu']),
                'memory' => $this->metricValue($meta, $telemetryPayload, ['memory', 'memory_percent', 'usage.memory', 'metrics.memory']),
                'disk' => $this->metricValue($meta, $telemetryPayload, ['disk', 'disk_percent', 'usage.disk', 'metrics.disk']),
                'ping_latency' => $this->metricValue($meta, $telemetryPayload, ['latency_ms', 'ping_latency', 'metrics.latency_ms']),
                'uptime' => $this->metricValue($meta, $telemetryPayload, ['uptime', 'metrics.uptime']),
                'temperature' => $this->metricValue($meta, $telemetryPayload, ['temperature', 'metrics.temperature']),
            ],
            'rack_interfaces' => $this->rackInterfacesPayload($device),
            'metadata' => $meta,
        ];
    }

    private function rackInterfacesPayload(?Device $device): array
    {
        if (!$device) {
            return [];
        }

        if (array_key_exists($device->id, $this->rackInterfaceCache)) {
            return $this->rackInterfaceCache[$device->id];
        }

        if (!$this->interfacesTableExists()) {
            return $this->rackInterfaceCache[$device->id] = [];
        }

        $ports = DB::table('interfaces')
            ->where('device_id', $device->id)
            ->get(['ifName', 'ifDescr', 'ifAlias', 'speed_bps', 'is_up', 'last_seen_at'])
            ->map(function (object $row): ?array {
                $name = trim((string) ($row->ifName ?? ''));
                if (!$this->isRackRenderableInterface($name)) {
                    return null;
                }

                $tone = $this->rackInterfaceTone($row->is_up ?? null);

                return [
                    'name' => $name,
                    'short_name' => $this->rackInterfaceShortName($name),
                    'family' => $this->rackInterfaceFamily($name),
                    'status' => $this->rackInterfaceStatusLabel($tone),
                    'status_tone' => $tone,
                    'is_up' => $row->is_up === null ? null : (bool) $row->is_up,
                    'description' => $this->emptyToNull($row->ifDescr ?? null),
                    'alias' => $this->emptyToNull($row->ifAlias ?? null),
                    'speed_bps' => is_numeric($row->speed_bps ?? null) ? (int) $row->speed_bps : null,
                    'sort_key' => $this->rackInterfaceSortKey($name),
                ];
            })
            ->filter()
            ->sortBy('sort_key')
            ->values()
            ->map(function (array $port): array {
                unset($port['sort_key']);

                return $port;
            })
            ->all();

        return $this->rackInterfaceCache[$device->id] = $ports;
    }

    private function interfacesTableExists(): bool
    {
        if ($this->interfacesTableAvailable !== null) {
            return $this->interfacesTableAvailable;
        }

        return $this->interfacesTableAvailable = Schema::hasTable('interfaces');
    }

    private function isRackRenderableInterface(string $name): bool
    {
        return (bool) preg_match('/^(?:fa|fastethernet|gi|gigabitethernet|te|tengigabitethernet|tw|twentyfivegige|fo|fortygigabitethernet|hu|hundredgige|eth|ethernet)\b/i', $name);
    }

    private function rackInterfaceFamily(string $name): string
    {
        $normalized = strtolower(trim($name));

        if (preg_match('/^(?:te|tengigabitethernet|tw|twentyfivegige|fo|fortygigabitethernet|hu|hundredgige)\b/', $normalized)) {
            return 'uplink';
        }

        return 'access';
    }

    private function rackInterfaceShortName(string $name): string
    {
        if (preg_match('/(\d+)(?!.*\d)/', $name, $matches)) {
            return $matches[1];
        }

        return $name;
    }

    private function rackInterfaceStatusLabel(string $tone): string
    {
        return match ($tone) {
            'online' => 'Online',
            'offline' => 'Offline',
            default => 'Unknown',
        };
    }

    private function rackInterfaceTone(mixed $isUp): string
    {
        if ($isUp === null) {
            return 'unknown';
        }

        return (bool) $isUp ? 'online' : 'offline';
    }

    private function rackInterfaceSortKey(string $name): string
    {
        preg_match_all('/\d+/', $name, $matches);
        $numbers = array_map(
            static fn (string $value): string => str_pad($value, 5, '0', STR_PAD_LEFT),
            $matches[0] ?? []
        );

        return strtolower(preg_replace('/\d+/', '', $name) ?: $name) . ':' . implode(':', $numbers);
    }

    private function emptyToNull(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    private function metricValue(array $meta, array $telemetryPayload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($telemetryPayload, $key);
            if ($this->hasMetricValue($value)) {
                return $this->stringifyMetric($value);
            }
        }

        foreach ($keys as $key) {
            $value = data_get($meta, $key);
            if ($this->hasMetricValue($value)) {
                return $this->stringifyMetric($value);
            }
        }

        return null;
    }

    private function hasMetricValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '' && trim($value) !== '-';
        }

        return is_numeric($value) || is_bool($value);
    }

    private function stringifyMetric(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return trim((string) $value);
    }

    private function defaultDeviceHeight(Device $device): int
    {
        $meta = $this->normalizeMetadata($device->metadata);

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

    private function normalizeFace(string $face): string
    {
        $face = strtolower(trim($face));

        return in_array($face, self::ALLOWED_FACES, true) ? $face : 'front';
    }

    private function assertPlacementFitsCabinet(Cabinet $cabinet, int $startU, int $heightU): void
    {
        if ($startU < 1 || $startU > (int) $cabinet->size_u) {
            abort(response()->json([
                'message' => "start_u must be within 1..{$cabinet->size_u}.",
            ], 422));
        }

        if ($heightU < 1) {
            abort(response()->json([
                'message' => 'height_u must be at least 1.',
            ], 422));
        }

        if (($startU + $heightU - 1) > (int) $cabinet->size_u) {
            abort(response()->json([
                'message' => 'The requested placement extends beyond the cabinet height.',
            ], 422));
        }
    }

    private function assertDeviceAvailable(Device $device, ?int $ignorePlacementId = null): void
    {
        $existing = CabinetPlacement::query()
            ->where('device_id', $device->id)
            ->when($ignorePlacementId !== null, fn ($query) => $query->where('id', '!=', $ignorePlacementId))
            ->exists();

        if ($existing) {
            abort(response()->json([
                'message' => 'This device is already placed in another cabinet.',
            ], 422));
        }
    }

    private function assertNoOverlap(Cabinet $cabinet, string $face, int $startU, int $heightU, ?int $ignorePlacementId = null): void
    {
        $endU = $startU + $heightU - 1;

        $overlapExists = CabinetPlacement::query()
            ->where('cabinet_id', $cabinet->id)
            ->where('face', $face)
            ->when($ignorePlacementId !== null, fn ($query) => $query->where('id', '!=', $ignorePlacementId))
            ->whereRaw('start_u <= ?', [$endU])
            ->whereRaw('(start_u + height_u - 1) >= ?', [$startU])
            ->exists();

        if ($overlapExists) {
            abort(response()->json([
                'message' => 'The selected U range overlaps an existing device on this cabinet face.',
            ], 422));
        }
    }

    private function firstNonEmpty(...$values): ?string
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $string = trim((string) $value);
            if ($string !== '') {
                return $string;
            }
        }

        return null;
    }

    private function normalizeMetadata(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function noStoreJson(array $payload)
    {
        return response()->json($payload)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
