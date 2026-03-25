<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\CommandTemplate;
use App\Models\DeviceEventPermission;
use App\Models\DeviceGraphPermission;
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
        $canViewAssignedDeviceGraphs = $role === 'admin';
        $canViewAssignedDeviceEvents = $role === 'admin';
        $graphAccessibleDeviceLookup = [];
        $eventAccessibleDeviceLookup = [];
        if ($role !== 'admin') {
            $canViewAssignedDeviceGraphs = User::supportsAssignedDeviceGraphAccess()
                ? (bool) ($authUser?->can_view_assigned_device_graphs ?? false)
                : true;
            $canViewAssignedDeviceEvents = User::supportsAssignedDeviceEventAccess()
                ? (bool) ($authUser?->can_view_assigned_device_events ?? false)
                : true;
        }
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
            $graphAccessibleDeviceLookup = array_fill_keys(
                $allDevices->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
                true
            );
            $eventAccessibleDeviceLookup = array_fill_keys(
                $allDevices->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
                true
            );
        } elseif ($userId) {
            $allActiveCommandTemplates = CommandTemplate::where('active', true)
                ->orderBy('name')
                ->get();

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

            if ($canViewAssignedDeviceGraphs) {
                $graphVisibleDeviceIds = $allDevices->pluck('id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all();

                if (DeviceGraphPermission::supportsScopedAccess()) {
                    $graphScopeDeviceIds = DB::table('device_graph_permissions')
                        ->where('user_id', $userId)
                        ->pluck('device_id')
                        ->map(static fn ($id): int => (int) $id)
                        ->all();

                    if (!empty($graphScopeDeviceIds)) {
                        $graphVisibleDeviceIds = array_values(array_intersect(
                            $graphVisibleDeviceIds,
                            $graphScopeDeviceIds
                        ));
                    }
                }

                $graphAccessibleDeviceLookup = array_fill_keys($graphVisibleDeviceIds, true);
            }

            if ($canViewAssignedDeviceEvents) {
                $eventVisibleDeviceIds = $allDevices->pluck('id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all();

                if (DeviceEventPermission::supportsScopedAccess()) {
                    $eventScopeDeviceIds = DB::table('device_event_permissions')
                        ->where('user_id', $userId)
                        ->pluck('device_id')
                        ->map(static fn ($id): int => (int) $id)
                        ->all();

                    if (!empty($eventScopeDeviceIds)) {
                        $eventVisibleDeviceIds = array_values(array_intersect(
                            $eventVisibleDeviceIds,
                            $eventScopeDeviceIds
                        ));
                    }
                }

                $eventAccessibleDeviceLookup = array_fill_keys($eventVisibleDeviceIds, true);
            }

            $user = User::with('commandTemplates')->find($userId);
            if ($user) {
                $commandTemplates = $user->commandTemplates
                    ->where('active', true)
                    ->values();
            }

            $allActiveCommandTemplatesById = $allActiveCommandTemplates->keyBy(static fn ($template) => (int) $template->id);
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
                    ->map(static fn ($id) => $allActiveCommandTemplatesById->get((int) $id))
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
            'canViewAssignedDeviceGraphs' => $canViewAssignedDeviceGraphs,
            'graphAccessibleDeviceLookup' => $graphAccessibleDeviceLookup,
            'canViewAssignedDeviceEvents' => $canViewAssignedDeviceEvents,
            'eventAccessibleDeviceLookup' => $eventAccessibleDeviceLookup,
            'healthPercent' => $healthPercent,
            'accessibleScopeLabel' => $accessibleScopeLabel,
            'deviceTypeBreakdown' => $deviceTypeBreakdown,
            'attentionDevices' => $attentionDevices,
            'recentDevices' => $recentDevices,
        ]);
    }

    public function updateTelegramSettings(Request $request)
    {
        $userId = (int) $request->session()->get('auth.user_id', 0);
        if ($userId <= 0) {
            return redirect()->route('auth.login');
        }

        $user = User::find($userId);
        if (!$user) {
            $request->session()->forget(['auth.user_id', 'auth.role']);
            return redirect()->route('auth.login');
        }

        $data = $request->validate([
            'telegram_chat_id' => ['nullable', 'string', 'max:500'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
        ]);

        $previousTelegramChatId = $this->normalizeTelegramCredentialValue($user->telegram_chat_id ?? null);
        $previousTelegramBotToken = $this->normalizeTelegramCredentialValue($user->telegram_bot_token ?? null);
        $telegramChatId = trim((string) ($data['telegram_chat_id'] ?? ''));
        $telegramBotToken = trim((string) ($data['telegram_bot_token'] ?? ''));
        $currentTelegramChatId = $telegramChatId !== '' ? $telegramChatId : null;
        $currentTelegramBotToken = $telegramBotToken !== '' ? $telegramBotToken : null;

        $user->fill([
            'telegram_chat_id' => $currentTelegramChatId,
            'telegram_bot_token' => $currentTelegramBotToken,
        ]);
        $user->save();

        $statusMessage = 'Telegram settings updated.';
        $telegramStatus = $this->attemptTelegramSetupConfirmation(
            $user,
            $previousTelegramChatId,
            $previousTelegramBotToken,
            $currentTelegramChatId,
            $currentTelegramBotToken
        );
        if ($telegramStatus !== null) {
            $statusMessage .= ' ' . $telegramStatus;
        }

        return redirect()
            ->route('portal.index')
            ->with('status', $statusMessage);
    }

    private function attemptTelegramSetupConfirmation(
        User $user,
        ?string $previousChatId,
        ?string $previousBotToken,
        ?string $currentChatId,
        ?string $currentBotToken
    ): ?string {
        if (!$this->shouldSendTelegramSetupConfirmation($previousChatId, $previousBotToken, $currentChatId, $currentBotToken)) {
            return null;
        }

        $notifier = $this->resolveTelegramNotifier();
        if ($notifier === null || !method_exists($notifier, 'sendDirectMessage')) {
            return 'Test message could not be sent (service unavailable).';
        }

        $targetUser = clone $user;
        $targetUser->telegram_enabled = true;
        $targetUser->telegram_chat_id = $currentChatId;
        $targetUser->telegram_bot_token = $currentBotToken;

        $sent = (bool) $notifier->sendDirectMessage($targetUser, 'Connection to Telegram bot is successful.', [
            'scope' => 'portal.telegram_settings.save_confirmation',
            'target_user_id' => $user->id,
        ]);

        if ($sent) {
            return 'Telegram test message sent successfully.';
        }

        $detail = '';
        if (method_exists($notifier, 'getLastError')) {
            $lastError = trim((string) ($notifier->getLastError() ?? ''));
            if ($lastError !== '') {
                $detail = " Reason: {$lastError}";
            }
        }

        return "Telegram settings saved, but test message failed.{$detail}";
    }

    private function shouldSendTelegramSetupConfirmation(
        ?string $previousChatId,
        ?string $previousBotToken,
        ?string $currentChatId,
        ?string $currentBotToken
    ): bool {
        return $currentChatId !== null;
    }

    private function normalizeTelegramCredentialValue(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }

    private function resolveTelegramNotifier(): ?object
    {
        $serviceClass = 'App\\Services\\TelegramEventNotifier';
        if (!class_exists($serviceClass)) {
            return null;
        }

        try {
            $service = app($serviceClass);
            if (is_object($service) && method_exists($service, 'sendDirectMessage')) {
                return $service;
            }
        } catch (\Throwable) {
            // ignore and fall back to null
        }

        return null;
    }
}
