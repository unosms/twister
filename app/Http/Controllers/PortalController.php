<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\CommandTemplate;
use App\Models\DevicePermission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalController extends Controller
{
    public function index()
    {
        $userId = request()->session()->get('auth.user_id');
        $role = request()->session()->get('auth.role');
        $authUser = $userId ? User::find($userId) : null;
        $devices = collect();
        $allDevices = collect();
        $commandTemplates = collect();
        $commandTemplatesByDevice = [];
        if ($role === 'admin') {
            $allDevices = Device::orderByDesc('created_at')->get();
            $devices = Device::orderByDesc('created_at')->paginate(12)->withQueryString();
            $commandTemplates = CommandTemplate::where('active', true)
                ->orderBy('name')
                ->get();
        } elseif ($userId) {
            $assignmentDeviceIds = DeviceAssignment::where('user_id', $userId)
                ->whereNull('unassigned_at')
                ->pluck('device_id')
                ->all();

            $permissionColumns = ['device_id'];
            if (DevicePermission::supportsAllowedCommandTemplateIds()) {
                $permissionColumns[] = 'allowed_command_template_ids';
            }

            $permissionRows = DB::table('device_permissions')
                ->where('user_id', $userId)
                ->get($permissionColumns);

            $permissionDeviceIds = $permissionRows
                ->pluck('device_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $deviceQuery = Device::query()
                ->where('assigned_user_id', $userId);

            if (!empty($assignmentDeviceIds)) {
                $deviceQuery->orWhereIn('id', $assignmentDeviceIds);
            }

            if (!empty($permissionDeviceIds)) {
                $deviceQuery->orWhereIn('id', $permissionDeviceIds);
            }

            $allDevices = $deviceQuery
                ->orderByDesc('created_at')
                ->get();

            $devices = $deviceQuery->paginate(12)->withQueryString();

            $user = User::with('commandTemplates')->find($userId);
            if ($user) {
                $commandTemplates = $user->commandTemplates
                    ->where('active', true)
                    ->values();
            }

            $globalCommandTemplatesById = $commandTemplates->keyBy(static fn ($template) => (int) $template->id);
            $restrictedCommandMap = $permissionRows->mapWithKeys(static function ($row) {
                return [(int) $row->device_id => DevicePermission::decodeAllowedCommandTemplateIds($row->allowed_command_template_ids ?? null)];
            });

            foreach ($allDevices as $device) {
                $deviceId = (int) $device->id;
                $restrictedIds = $restrictedCommandMap->get($deviceId, []);

                if (empty($restrictedIds)) {
                    $commandTemplatesByDevice[$deviceId] = $commandTemplates->values();
                    continue;
                }

                $commandTemplatesByDevice[$deviceId] = collect($restrictedIds)
                    ->map(static fn ($id) => $globalCommandTemplatesById->get((int) $id))
                    ->filter()
                    ->values();
            }
        }

        $totalDevices = $allDevices?->count() ?? 0;
        $activeDevices = ($allDevices ?? collect())->filter(static function ($device) {
            return strtolower($device->status ?? 'offline') === 'online';
        })->count();
        $warningDevices = ($allDevices ?? collect())->filter(static function ($device) {
            return strtolower($device->status ?? 'offline') === 'warning';
        })->count();
        $offlineDevices = ($allDevices ?? collect())->filter(static function ($device) {
            $status = strtolower($device->status ?? 'offline');
            return !in_array($status, ['online', 'warning'], true);
        })->count();

        $recentThreshold = now()->copy()->subMinutes(15);
        $staleThreshold = now()->copy()->subDay();

        $recentlySeenDevices = ($allDevices ?? collect())->filter(static function ($device) use ($recentThreshold) {
            $lastSeen = $device->last_seen_at;
            return $lastSeen && $lastSeen->greaterThanOrEqualTo($recentThreshold);
        })->count();

        $staleDevices = ($allDevices ?? collect())->filter(static function ($device) use ($staleThreshold) {
            $lastSeen = $device->last_seen_at;
            return !$lastSeen || $lastSeen->lessThan($staleThreshold);
        })->count();

        $lastUpdatedAt = ($allDevices ?? collect())
            ->filter(static fn ($device) => (bool) $device->last_seen_at)
            ->sortByDesc(static fn ($device) => $device->last_seen_at)
            ->first()?->last_seen_at;

        $portalNotificationCount = $warningDevices + $offlineDevices;
        $commandTemplateCount = $commandTemplates?->count() ?? 0;
        $healthPercent = $totalDevices > 0 ? (int) round(($activeDevices / $totalDevices) * 100) : 0;
        $accessibleScopeLabel = $role === 'admin'
            ? 'Administrative fleet visibility'
            : 'Assigned and permitted device access';

        $deviceTypeBreakdown = ($allDevices ?? collect())
            ->groupBy(static function ($device) {
                $type = strtoupper(trim((string) ($device->type ?? '')));

                return $type !== '' ? $type : 'UNKNOWN';
            })
            ->map(static fn ($group, $type) => [
                'type' => $type,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(4);

        $attentionDevices = ($allDevices ?? collect())
            ->filter(static function ($device) {
                return in_array(strtolower((string) ($device->status ?? 'offline')), ['warning', 'offline', 'error'], true);
            })
            ->sortBy([
                static function ($device) {
                    return match (strtolower((string) ($device->status ?? 'offline'))) {
                        'error' => 0,
                        'warning' => 1,
                        default => 2,
                    };
                },
                static fn ($device) => $device->last_seen_at?->timestamp ?? 0,
            ])
            ->values()
            ->take(5);

        $recentDevices = ($allDevices ?? collect())
            ->filter(static fn ($device) => (bool) $device->last_seen_at)
            ->sortByDesc(static fn ($device) => $device->last_seen_at?->timestamp ?? 0)
            ->values()
            ->take(5);

        return view('user_portal', [
            'authUser' => $authUser,
            'devices' => $devices,
            'totalDevices' => $totalDevices,
            'activeDevices' => $activeDevices,
            'warningDevices' => $warningDevices,
            'offlineDevices' => $offlineDevices,
            'recentlySeenDevices' => $recentlySeenDevices,
            'staleDevices' => $staleDevices,
            'lastUpdatedAt' => $lastUpdatedAt,
            'portalNotificationCount' => $portalNotificationCount,
            'commandTemplateCount' => $commandTemplateCount,
            'commandTemplates' => $commandTemplates,
            'commandTemplatesByDevice' => $commandTemplatesByDevice,
            'healthPercent' => $healthPercent,
            'accessibleScopeLabel' => $accessibleScopeLabel,
            'deviceTypeBreakdown' => $deviceTypeBreakdown,
            'attentionDevices' => $attentionDevices,
            'recentDevices' => $recentDevices,
        ]);
    }
}
