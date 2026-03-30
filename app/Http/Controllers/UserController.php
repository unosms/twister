<?php

namespace App\Http\Controllers;

use App\Models\CommandTemplate;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\DeviceEventPermission;
use App\Models\DeviceGraphPermission;
use App\Models\DevicePermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const TELEGRAM_SEVERITIES = ['low', 'medium', 'high', 'critical'];
    private const TELEGRAM_TEMPLATE_INPUTS = [
        'low' => 'telegram_template_low',
        'medium' => 'telegram_template_medium',
        'high' => 'telegram_template_high',
        'critical' => 'telegram_template_critical',
    ];

    private const TELEGRAM_EVENT_TYPES = [
        'device.online',
        'device.offline',
        'device.status_changed',
        'port.up',
        'port.down',
        'port.speed_changed',
        'port.status_changed',
        'snmp.timeout',
        'snmp.auth_fail',
        'snmp.response_slow',
        'ping.high_latency',
        'auth.admin_login',
        'system.error',
    ];
    private const EXEC_COMMAND_TEMPLATES = [
        [
            'name' => 'Restart Interface',
            'action_key' => 'restartint',
            'description' => 'Restart interface (exec cmd).',
        ],
        [
            'name' => 'Show Spanning-Tree ($interface)',
            'action_key' => 'shspantree',
            'description' => 'Show spanning-tree for interface (exec cmd).',
        ],
        [
            'name' => 'Show MAC Address ($interface)',
            'action_key' => 'showmac',
            'description' => 'Show MAC address for interface (exec cmd).',
        ],
        [
            'name' => 'Show Log | Include ($interface)',
            'action_key' => 'showlog',
            'description' => 'Show log filtered by interface (exec cmd).',
        ],
        [
            'name' => 'Showintstatus',
            'action_key' => 'showintstatus',
            'description' => 'Show interface status (exec cmd).',
        ],
        [
            'name' => 'Show Interface Transceiver Detail ($interface)',
            'action_key' => 'shtransceiver',
            'description' => 'Show optical transceiver detail (exec cmd).',
        ],
        [
            'name' => 'Rename Interface ($interface)',
            'action_key' => 'renameint',
            'description' => 'Rename interface (exec cmd).',
        ],
    ];

    public function create(Request $request)
    {
        if (!$this->currentUserIsSuperAdmin($request)) {
            return redirect()
                ->route('users.index')
                ->withErrors(['user_create' => 'Only super admin can create users.']);
        }

        return view('users_create', array_merge(
            $this->buildUserFormViewData(),
            [
                'authUser' => $this->resolveCurrentUser($request),
                'canManageUserIdentity' => true,
            ]
        ));
    }

    public function index(Request $request)
    {
        $filters = $this->normalizeUserFilters($request);
        $editUserId = max(0, (int) $request->query('edit_user', 0));

        $userQuery = $this->buildFilteredUserQuery($filters)
            ->withCount('devices');

        $this->applySuperAdminFirstOrdering($userQuery);
        $userQuery->orderByRaw('LOWER(COALESCE(name, \'\')) ASC');

        $users = $userQuery->paginate(10)->withQueryString();
        $selectedEditUser = null;
        if ($editUserId > 0) {
            $selectedEditUser = $users->getCollection()->first(
                static fn (User $candidate): bool => (int) $candidate->id === $editUserId
            );
        }

        if ($selectedEditUser instanceof User) {
            $selectedEditUser->load(['commandTemplates', 'permittedDevices']);
        } else {
            $editUserId = 0;
        }

        $formViewData = $editUserId > 0
            ? $this->buildUserFormViewData()
            : $this->emptyUserFormViewData();

        return view('user_management_tables_drawer', array_merge([
            'users' => $users,
            'filters' => $filters,
            'editUserId' => $editUserId,
            'authUser' => $this->resolveCurrentUser($request),
            'canManageUserIdentity' => $this->currentUserIsSuperAdmin($request),
        ], $formViewData));
    }

    public function store(Request $request)
    {
        if (!$this->currentUserIsSuperAdmin($request)) {
            return redirect()
                ->route('users.index')
                ->withErrors(['user_create' => 'Only super admin can create users.']);
        }

        $data = $this->validateUserPayload($request, null, true);
        $submittedTelegramChatId = $this->normalizeTelegramCredentialValue($data['telegram_chat_id'] ?? null);
        $submittedTelegramBotToken = $this->normalizeTelegramCredentialValue($data['telegram_bot_token'] ?? null);

        $user = DB::transaction(function () use ($request, $data) {
            $user = new User();
            $this->persistUser($request, $user, $data);

            return $user;
        });

        $statusMessage = "User {$user->name} created.";
        $telegramStatus = $this->attemptTelegramSetupConfirmation(
            $user,
            null,
            null,
            $submittedTelegramChatId,
            $submittedTelegramBotToken
        );
        if ($telegramStatus !== null) {
            $statusMessage .= ' ' . $telegramStatus;
        }

        return redirect()
            ->route('users.index')
            ->with('status', $statusMessage);
    }

    public function export(Request $request)
    {
        $filters = $this->normalizeUserFilters($request);
        $filename = 'users_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $columns = ['id', 'name', 'email', 'role', 'status', 'created_at'];

        $callback = function () use ($columns, $filters) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            $this->buildFilteredUserQuery($filters)
                ->orderBy('id')
                ->chunk(500, function ($users) use ($handle, $columns) {
                foreach ($users as $user) {
                    $row = [];
                    foreach ($columns as $column) {
                        $row[] = $user->{$column};
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
            'action' => 'users.filter',
            'filters' => $request->all(),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->isSuperAdmin()) {
            return back()->withErrors([
                'user_delete' => 'Super admin accounts cannot be deleted.',
            ]);
        }

        $currentUserId = $this->resolveCurrentUserId($request);
        if ($currentUserId !== null && $currentUserId === (int) $user->id) {
            return back()->withErrors([
                'user_delete' => 'You cannot delete your own account.',
            ]);
        }

        Device::where('assigned_user_id', $user->id)->update(['assigned_user_id' => null]);
        DeviceAssignment::where('user_id', $user->id)
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);
        $user->commandTemplates()->detach();
        $user->permittedDevices()->detach();

        $userName = $user->name;
        $this->deleteAvatarFile($user->avatar_path);
        $user->delete();

        return back()->with('status', "User {$userName} deleted.");
    }

    public function updateStatus(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $user = User::findOrFail($data['user_id']);

        if ($user->isSuperAdmin()) {
            return back()->withErrors([
                'user_status' => 'Super admin accounts cannot be deactivated.',
            ]);
        }

        if (($user->role ?? 'user') === 'admin') {
            return back()->withErrors([
                'user_status' => 'Admin accounts cannot be deactivated.',
            ]);
        }

        $user->update([
            'status' => $data['status'],
        ]);

        if ($data['status'] === 'inactive' && (int) $request->session()->get('auth.user_id') === (int) $user->id) {
            $request->session()->forget(['auth.logged_in', 'auth.pending', 'auth.user_id', 'auth.verified_at', 'auth.role']);
            return redirect()->route('auth.login')->withErrors(['username' => 'Your account is inactive. Please contact an administrator.']);
        }

        return back()->with('status', "User {$user->name} status updated.");
    }

    public function update(Request $request, User $user)
    {
        if ($user->isSuperAdmin() && !$this->currentUserCanEditProtectedSuperAdmin($request, $user)) {
            return back()->withErrors([
                'user_update' => 'Super admin accounts are locked and cannot be modified.',
            ]);
        }

        $canManageIdentity = $this->currentUserIsSuperAdmin($request);
        if (!$canManageIdentity && $this->hasProtectedIdentityChanges($request, $user)) {
            return back()->withErrors([
                'user_update' => 'Only super admin can change username, role, or password.',
            ]);
        }

        $data = $this->validateUserPayload($request, $user);
        if (!$canManageIdentity) {
            $data['username'] = $user->name;
            $data['role'] = $user->role ?? 'user';
            $data['password'] = null;
        }

        $previousTelegramChatId = $this->normalizeTelegramCredentialValue($user->telegram_chat_id ?? null);
        $previousTelegramBotToken = $this->normalizeTelegramCredentialValue($user->telegram_bot_token ?? null);
        $submittedTelegramChatId = $this->normalizeTelegramCredentialValue($data['telegram_chat_id'] ?? null);
        $submittedTelegramBotToken = $this->normalizeTelegramCredentialValue($data['telegram_bot_token'] ?? null);

        DB::transaction(function () use ($request, $user, $data) {
            $this->persistUser($request, $user, $data);
        });

        $statusMessage = "User {$user->name} updated.";
        $telegramStatus = $this->attemptTelegramSetupConfirmation(
            $user,
            $previousTelegramChatId,
            $previousTelegramBotToken,
            $submittedTelegramChatId,
            $submittedTelegramBotToken
        );
        if ($telegramStatus !== null) {
            $statusMessage .= ' ' . $telegramStatus;
        }

        return back()->with('status', $statusMessage);
    }

    private function buildUserFormViewData(): array
    {
        $devices = Device::orderBy('name')->get();
        $this->ensureExecCommandTemplates();
        $commandTemplates = CommandTemplate::where('active', true)->orderBy('name')->get();
        $graphInterfaceOptionsByDevice = $this->buildGraphInterfaceOptionsByDevice($devices);

        return [
            'devices' => $devices,
            'commandTemplates' => $commandTemplates,
            'graphInterfaceOptionsByDevice' => $graphInterfaceOptionsByDevice,
            'avatarStorageReady' => User::supportsAvatarStorage(),
            'assignedDeviceGraphAccessReady' => User::supportsAssignedDeviceGraphAccess(),
            'assignedDeviceEventAccessReady' => User::supportsAssignedDeviceEventAccess(),
            'passwordRevealStorageReady' => User::supportsPasswordRevealStorage(),
            'deviceGraphScopeReady' => DeviceGraphPermission::supportsScopedAccess(),
            'deviceEventScopeReady' => DeviceEventPermission::supportsScopedAccess(),
            'deviceEventInterfaceScopeReady' => DeviceEventPermission::supportsInterfaceScope(),
            'telegramDeviceInterfaceScopeReady' => User::supportsTelegramDeviceInterfaceScope(),
            'deviceCommandRestrictionsReady' => DevicePermission::supportsAllowedCommandTemplateIds(),
            'telegramSeverityOptions' => self::TELEGRAM_SEVERITIES,
            'telegramEventTypeOptions' => self::TELEGRAM_EVENT_TYPES,
        ];
    }

    private function emptyUserFormViewData(): array
    {
        return [
            'devices' => collect(),
            'commandTemplates' => collect(),
            'graphInterfaceOptionsByDevice' => [],
            'avatarStorageReady' => User::supportsAvatarStorage(),
            'assignedDeviceGraphAccessReady' => User::supportsAssignedDeviceGraphAccess(),
            'assignedDeviceEventAccessReady' => User::supportsAssignedDeviceEventAccess(),
            'passwordRevealStorageReady' => User::supportsPasswordRevealStorage(),
            'deviceGraphScopeReady' => DeviceGraphPermission::supportsScopedAccess(),
            'deviceEventScopeReady' => DeviceEventPermission::supportsScopedAccess(),
            'deviceEventInterfaceScopeReady' => DeviceEventPermission::supportsInterfaceScope(),
            'telegramDeviceInterfaceScopeReady' => User::supportsTelegramDeviceInterfaceScope(),
            'deviceCommandRestrictionsReady' => DevicePermission::supportsAllowedCommandTemplateIds(),
            'telegramSeverityOptions' => self::TELEGRAM_SEVERITIES,
            'telegramEventTypeOptions' => self::TELEGRAM_EVENT_TYPES,
        ];
    }

    private function applySuperAdminFirstOrdering(Builder $query): void
    {
        $identifiers = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => Str::lower(trim((string) $value)),
            config('admin.super_admin_identifiers', [])
        ), static fn (string $value): bool => $value !== '')));

        if ($identifiers === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($identifiers), '?'));
        $bindings = array_merge($identifiers, $identifiers);
        $query->orderByRaw(
            "CASE WHEN role = 'admin' AND (LOWER(COALESCE(name, '')) IN ({$placeholders}) OR LOWER(COALESCE(email, '')) IN ({$placeholders})) THEN 0 ELSE 1 END ASC",
            $bindings
        );
    }

    private function buildGraphInterfaceOptionsByDevice(\Illuminate\Support\Collection $devices): array
    {
        $deviceIds = $devices->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if (empty($deviceIds)) {
            return [];
        }

        $schema = DB::getSchemaBuilder();
        if (!$schema->hasTable('interfaces')) {
            return [];
        }

        $rows = DB::table('interfaces')
            ->whereIn('device_id', $deviceIds)
            ->orderBy('device_id')
            ->orderBy('ifIndex')
            ->get(['device_id', 'ifIndex', 'ifName', 'ifDescr', 'ifAlias']);

        $optionsByDevice = [];
        $seenByDevice = [];

        foreach ($rows as $row) {
            $deviceId = (int) ($row->device_id ?? 0);
            if ($deviceId <= 0) {
                continue;
            }

            $value = $this->resolveGraphInterfaceValue($row);
            if ($value === '') {
                continue;
            }

            $valueKey = strtolower($value);
            if (isset($seenByDevice[$deviceId][$valueKey])) {
                continue;
            }
            $seenByDevice[$deviceId][$valueKey] = true;

            $optionsByDevice[$deviceId][] = [
                'value' => $value,
                'label' => $this->formatGraphInterfaceLabel($row, $value),
            ];
        }

        return $optionsByDevice;
    }

    private function resolveGraphInterfaceValue(object $row): string
    {
        $ifName = trim((string) ($row->ifName ?? ''));
        if ($ifName !== '') {
            return $ifName;
        }

        $ifDescr = trim((string) ($row->ifDescr ?? ''));
        if ($ifDescr !== '') {
            return $ifDescr;
        }

        $ifIndex = (int) ($row->ifIndex ?? 0);
        return $ifIndex > 0 ? (string) $ifIndex : '';
    }

    private function formatGraphInterfaceLabel(object $row, string $fallbackValue): string
    {
        $ifIndex = (int) ($row->ifIndex ?? 0);
        $ifName = trim((string) ($row->ifName ?? ''));
        $ifAlias = trim((string) ($row->ifAlias ?? ''));
        if ($ifAlias === '') {
            $ifAlias = trim((string) ($row->ifDescr ?? ''));
        }

        $namePart = $ifName !== '' ? $ifName : $fallbackValue;
        $label = $ifIndex > 0 ? ('[#' . $ifIndex . '] ' . $namePart) : $namePart;

        if ($ifAlias !== '' && strcasecmp($ifAlias, $namePart) !== 0) {
            $label .= ' - ' . $ifAlias;
        }

        return $label;
    }

    private function resolveCurrentUserId(Request $request): ?int
    {
        $requestUserId = $request->user()?->id;
        if (is_numeric($requestUserId)) {
            return (int) $requestUserId;
        }

        $sessionUserId = $request->session()->get('auth.user_id');
        if (is_numeric($sessionUserId)) {
            return (int) $sessionUserId;
        }

        return null;
    }

    private function resolveCurrentUser(Request $request): ?User
    {
        $currentUserId = $this->resolveCurrentUserId($request);
        if ($currentUserId === null) {
            return null;
        }

        return User::find($currentUserId);
    }

    private function currentUserIsSuperAdmin(Request $request): bool
    {
        return $this->resolveCurrentUser($request)?->isSuperAdmin() ?? false;
    }

    private function currentUserCanEditProtectedSuperAdmin(Request $request, User $user): bool
    {
        $currentUser = $this->resolveCurrentUser($request);

        return $currentUser instanceof User
            && $currentUser->isSuperAdmin()
            && (int) $currentUser->id === (int) $user->id;
    }

    private function hasProtectedIdentityChanges(Request $request, User $user): bool
    {
        $submittedUsername = trim((string) $request->input('username', $user->name));
        $submittedRole = trim((string) $request->input('role', $user->role ?? 'user'));
        $submittedPassword = trim((string) $request->input('password', ''));

        return $submittedUsername !== (string) $user->name
            || $submittedRole !== (string) ($user->role ?? 'user')
            || $submittedPassword !== '';
    }

    private function normalizeUserFilters(Request $request): array
    {
        $search = trim((string) $request->query('search', $request->input('search', '')));
        $role = strtolower(trim((string) $request->query('role', $request->input('role', 'all'))));
        $status = strtolower(trim((string) $request->query('status', $request->input('status', 'all'))));

        if (!in_array($role, ['all', 'admin', 'user'], true)) {
            $role = 'all';
        }

        if (!in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'all';
        }

        return [
            'search' => $search,
            'role' => $role,
            'status' => $status,
        ];
    }

    private function buildFilteredUserQuery(array $filters): Builder
    {
        $query = User::query();

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $role = (string) ($filters['role'] ?? 'all');
        if ($role !== 'all') {
            $query->where('role', $role);
        }

        $status = (string) ($filters['status'] ?? 'all');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return $query;
    }

    private function validateUserPayload(Request $request, ?User $user = null, bool $requirePassword = false): array
    {
        $usernameRules = [
            'required',
            'string',
            'max:255',
        ];

        if ($user instanceof User) {
            $usernameRules[] = Rule::unique('users', 'name')->ignore($user->id);
            $usernameRules[] = Rule::unique('users', 'email')->ignore($user->id);
        } else {
            $usernameRules[] = Rule::unique('users', 'name');
            $usernameRules[] = Rule::unique('users', 'email');
        }

        $passwordRules = ['string', 'min:6'];
        array_unshift($passwordRules, $requirePassword ? 'required' : 'nullable');

        $selectedPermissionDeviceLookup = $this->normalizeDeviceIdLookup($request->input('device_permission_ids', []));
        $selectedGraphDeviceLookup = $this->normalizeDeviceIdLookup($request->input('graph_device_ids', []));
        $selectedEventDeviceLookup = $this->normalizeDeviceIdLookup($request->input('event_device_ids', []));
        $selectedTelegramDeviceLookup = $this->normalizeDeviceIdLookup($request->input('telegram_devices', []));

        return $request->validate([
            'username' => $usernameRules,
            'role' => ['required', 'string', Rule::in(['admin', 'user'])],
            'password' => $passwordRules,
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],

            'device_ids' => ['nullable', 'array'],
            'device_ids.*' => ['integer', 'exists:devices,id'],
            'device_permission_ids' => ['nullable', 'array'],
            'device_permission_ids.*' => ['integer', 'exists:devices,id'],
            'device_permission_ports' => ['nullable', 'array'],
            'device_permission_ports.*' => [
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail) use ($selectedPermissionDeviceLookup): void {
                    if (!$this->attributeTargetsSelectedDevice($attribute, 'device_permission_ports', $selectedPermissionDeviceLookup)) {
                        return;
                    }

                    $error = $this->validateInterfaceExpression(is_string($value) ? $value : '');
                    if ($error !== null) {
                        $fail($error);
                    }
                },
            ],
            'device_permission_command_template_ids' => ['nullable', 'array'],
            'device_permission_command_template_ids.*' => ['nullable', 'array'],
            'device_permission_command_template_ids.*.*' => ['nullable', 'integer', 'exists:command_templates,id'],

            'command_template_ids' => ['nullable', 'array'],
            'command_template_ids.*' => ['integer', 'exists:command_templates,id'],
            'custom_command_type' => ['nullable', 'string'],
            'custom_command_script_name' => ['nullable', 'string', 'max:255', 'required_if:custom_command_type,custom'],
            'custom_command_script_code' => ['nullable', 'string', 'max:65535', 'required_if:custom_command_type,custom'],

            'can_view_assigned_device_graphs' => ['nullable', 'boolean'],
            'can_view_assigned_device_events' => ['nullable', 'boolean'],
            'graph_device_ids' => ['nullable', 'array'],
            'graph_device_ids.*' => ['integer', 'exists:devices,id'],
            'event_device_ids' => ['nullable', 'array'],
            'event_device_ids.*' => ['integer', 'exists:devices,id'],
            'event_device_interfaces' => ['nullable', 'array'],
            'event_device_interfaces.*' => [
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail) use ($selectedEventDeviceLookup): void {
                    if (!$this->attributeTargetsSelectedDevice($attribute, 'event_device_interfaces', $selectedEventDeviceLookup)) {
                        return;
                    }

                    $error = $this->validateInterfaceExpression(is_string($value) ? $value : '');
                    if ($error !== null) {
                        $fail($error);
                    }
                },
            ],
            'graph_device_interfaces' => ['nullable', 'array'],
            'graph_device_interfaces.*' => [
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail) use ($selectedGraphDeviceLookup): void {
                    if (!$this->attributeTargetsSelectedDevice($attribute, 'graph_device_interfaces', $selectedGraphDeviceLookup)) {
                        return;
                    }

                    $error = $this->validateInterfaceExpression(is_string($value) ? $value : '');
                    if ($error !== null) {
                        $fail($error);
                    }
                },
            ],
            'telegram_enabled' => ['nullable', 'boolean'],
            'telegram_chat_id' => ['nullable', 'string', 'max:500'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_devices' => ['nullable', 'array'],
            'telegram_devices.*' => ['integer', 'exists:devices,id'],
            'telegram_device_interfaces' => ['nullable', 'array'],
            'telegram_device_interfaces.*' => ['nullable', 'array'],
            'telegram_device_interfaces.*.*' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($selectedTelegramDeviceLookup): void {
                    if (!$this->attributeTargetsSelectedDevice($attribute, 'telegram_device_interfaces', $selectedTelegramDeviceLookup)) {
                        return;
                    }

                    $error = $this->validateInterfaceExpression(is_string($value) ? $value : '');
                    if ($error !== null) {
                        $fail($error);
                    }
                },
            ],
            'telegram_severities' => ['nullable', 'array'],
            'telegram_severities.*' => ['string', Rule::in(self::TELEGRAM_SEVERITIES)],
            'telegram_event_types' => ['nullable', 'array'],
            'telegram_event_types.*' => ['string', 'max:64'],
            'telegram_event_types_custom' => ['nullable', 'string', 'max:500'],
            'telegram_template' => ['nullable', 'string', 'max:4000'],
            'telegram_template_low' => ['nullable', 'string', 'max:4000'],
            'telegram_template_medium' => ['nullable', 'string', 'max:4000'],
            'telegram_template_high' => ['nullable', 'string', 'max:4000'],
            'telegram_template_critical' => ['nullable', 'string', 'max:4000'],
        ]);
    }

    private function normalizeDeviceIdLookup(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $lookup = [];
        foreach ($values as $value) {
            if (!is_numeric($value)) {
                continue;
            }

            $deviceId = (int) $value;
            if ($deviceId > 0) {
                $lookup[$deviceId] = true;
            }
        }

        return $lookup;
    }

    private function attributeTargetsSelectedDevice(string $attribute, string $prefix, array $selectedDeviceLookup): bool
    {
        $pattern = '/^' . preg_quote($prefix, '/') . '\.(\d+)(?:\..+)?$/';
        if (!preg_match($pattern, $attribute, $matches)) {
            return true;
        }

        $deviceId = (int) ($matches[1] ?? 0);
        if ($deviceId <= 0) {
            return false;
        }

        return isset($selectedDeviceLookup[$deviceId]);
    }

    private function persistUser(Request $request, User $user, array $data): void
    {
        $isNew = !$user->exists;

        $deviceIds = $data['device_ids'] ?? [];
        $deviceIds = array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            array_filter($deviceIds, static fn ($id): bool => is_numeric($id) && (int) $id > 0)
        )));

        $telegramEnabled = $request->boolean('telegram_enabled');
        $canViewAssignedDeviceGraphs = $request->boolean('can_view_assigned_device_graphs');
        $canViewAssignedDeviceEvents = $request->boolean('can_view_assigned_device_events');
        $assignedDeviceGraphAccessReady = User::supportsAssignedDeviceGraphAccess();
        $assignedDeviceEventAccessReady = User::supportsAssignedDeviceEventAccess();
        $telegramChatId = trim((string) ($data['telegram_chat_id'] ?? ''));
        $telegramChatId = $telegramChatId !== '' ? $telegramChatId : null;
        $telegramBotToken = trim((string) ($data['telegram_bot_token'] ?? ''));
        $telegramBotToken = $telegramBotToken !== '' ? $telegramBotToken : null;

        $telegramSeverities = array_values(array_unique(array_map(
            static fn (string $value): string => strtolower(trim($value)),
            $data['telegram_severities'] ?? []
        )));
        if (empty($telegramSeverities)) {
            $telegramSeverities = ['high', 'critical'];
        }

        $telegramEventTypes = $this->normalizeTelegramEventTypes(
            $data['telegram_event_types'] ?? [],
            $data['telegram_event_types_custom'] ?? ''
        );
        if (empty($telegramEventTypes)) {
            $telegramEventTypes = ['device.offline', 'port.down'];
        }

        $templateConfig = $this->decodeTelegramTemplateConfig((string) ($user->telegram_template ?? ''));
        $telegramTemplateDefault = trim((string) ($data['telegram_template'] ?? $templateConfig['default']));
        $telegramSeverityTemplates = [];
        foreach (self::TELEGRAM_TEMPLATE_INPUTS as $severity => $fieldName) {
            $value = trim((string) ($data[$fieldName] ?? ($templateConfig['severity_templates'][$severity] ?? '')));
            if ($value !== '') {
                $telegramSeverityTemplates[$severity] = $value;
            }
        }
        $telegramTemplate = $this->encodeTelegramTemplateConfig($telegramTemplateDefault, $telegramSeverityTemplates);
        $avatarStorageReady = User::supportsAvatarStorage();
        $avatarPath = $avatarStorageReady ? ($user->avatar_path ?? null) : null;

        if ($avatarStorageReady) {
            if ($request->hasFile('avatar')) {
                $avatarPath = $this->storeAvatarFile($request->file('avatar'), $avatarPath);
            } elseif ($request->boolean('remove_avatar')) {
                $this->deleteAvatarFile($avatarPath);
                $avatarPath = null;
            }
        }

        $updates = [
            'name' => $data['username'],
            'email' => $data['username'],
            'role' => $data['role'],
            'telegram_enabled' => $telegramEnabled,
            'telegram_chat_id' => $telegramChatId,
            'telegram_bot_token' => $telegramBotToken,
            'telegram_severities' => $telegramSeverities,
            'telegram_event_types' => $telegramEventTypes,
            'telegram_template' => $telegramTemplate,
        ];

        if ($assignedDeviceGraphAccessReady) {
            $updates['can_view_assigned_device_graphs'] = $canViewAssignedDeviceGraphs;
        }
        if ($assignedDeviceEventAccessReady) {
            $updates['can_view_assigned_device_events'] = $canViewAssignedDeviceEvents;
        }

        if ($avatarStorageReady) {
            $updates['avatar_path'] = $avatarPath;
        }

        if ($isNew) {
            $updates['status'] = 'active';
        }

        $passwordValue = trim((string) ($data['password'] ?? ''));
        if ($passwordValue !== '') {
            $updates['password'] = Hash::make($passwordValue);
            if (User::supportsPasswordRevealStorage()) {
                $updates['password_reveal'] = $passwordValue;
            }
        }

        $user->fill($updates);
        $user->save();

        $telegramDevices = $data['telegram_devices'] ?? null;
        if (!is_array($telegramDevices)) {
            $telegramDevices = $this->defaultTelegramDeviceIds($user, $deviceIds);
        }
        $telegramDevices = array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            array_filter($telegramDevices, static fn ($id): bool => is_numeric($id) && (int) $id > 0)
        )));

        $telegramDeviceInterfaces = [];
        if (User::supportsTelegramDeviceInterfaceScope()) {
            $telegramDeviceInterfacesRaw = $data['telegram_device_interfaces'] ?? [];
            if (!is_array($telegramDeviceInterfacesRaw)) {
                $telegramDeviceInterfacesRaw = [];
            }

            foreach ($telegramDeviceInterfacesRaw as $deviceIdRaw => $interfacesRaw) {
                if (!is_numeric($deviceIdRaw)) {
                    continue;
                }

                $deviceId = (int) $deviceIdRaw;
                if (!in_array($deviceId, $telegramDevices, true) || !is_array($interfacesRaw)) {
                    continue;
                }

                $interfaces = array_values(array_unique(array_map(
                    static fn ($value): string => trim((string) $value),
                    array_filter($interfacesRaw, static fn ($value): bool => trim((string) $value) !== '')
                )));

                if (!empty($interfaces)) {
                    $telegramDeviceInterfaces[(string) $deviceId] = $interfaces;
                }
            }
        }

        $telegramUpdates = [
            'telegram_devices' => $telegramDevices,
        ];
        if (User::supportsTelegramDeviceInterfaceScope()) {
            $telegramUpdates['telegram_device_interfaces'] = !empty($telegramDeviceInterfaces)
                ? $telegramDeviceInterfaces
                : null;
        }

        $user->update($telegramUpdates);

        $assignedBy = $request->session()->get('auth.user_id');
        $currentlyAssigned = Device::where('assigned_user_id', $user->id)
            ->pluck('id')
            ->all();

        $toUnassign = array_diff($currentlyAssigned, $deviceIds);
        if (!empty($toUnassign)) {
            Device::whereIn('id', $toUnassign)->update(['assigned_user_id' => null]);
            DeviceAssignment::whereIn('device_id', $toUnassign)
                ->where('user_id', $user->id)
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now()]);
        }

        if (!empty($deviceIds)) {
            DeviceAssignment::whereIn('device_id', $deviceIds)
                ->whereNull('unassigned_at')
                ->where('user_id', '!=', $user->id)
                ->update(['unassigned_at' => now()]);
            Device::whereIn('id', $deviceIds)->update(['assigned_user_id' => $user->id]);

            $existingAssignments = DeviceAssignment::whereIn('device_id', $deviceIds)
                ->where('user_id', $user->id)
                ->whereNull('unassigned_at')
                ->pluck('device_id')
                ->all();

            $missingAssignments = array_diff($deviceIds, $existingAssignments);
            foreach ($missingAssignments as $deviceId) {
                DeviceAssignment::create([
                    'device_id' => $deviceId,
                    'user_id' => $user->id,
                    'assigned_by' => $assignedBy,
                    'assigned_at' => now(),
                ]);
            }
        }

        $commandIds = $data['command_template_ids'] ?? [];
        $customCommandType = strtolower(trim((string) ($data['custom_command_type'] ?? '')));
        if ($customCommandType === 'custom') {
            $scriptName = trim((string) ($data['custom_command_script_name'] ?? ''));
            $scriptCode = trim((string) ($data['custom_command_script_code'] ?? ''));

            $baseActionKey = 'custom_command_' . Str::slug($scriptName, '_');
            if ($baseActionKey === 'custom_command_') {
                $baseActionKey .= now()->format('YmdHis');
            }

            $creatorId = (int) $request->session()->get('auth.user_id', 0);
            $template = CommandTemplate::updateOrCreate(
                ['action_key' => $baseActionKey],
                [
                    'name' => 'Custom Command: ' . $scriptName,
                    'description' => 'Custom script command.',
                    'ui_type' => 'button',
                    'script_name' => $scriptName,
                    'script_code' => $scriptCode,
                    'active' => true,
                    'created_by' => $creatorId > 0 ? $creatorId : null,
                ]
            );

            $commandIds[] = (int) $template->id;
        }

        $commandIds = array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            array_filter($commandIds, static fn ($id): bool => is_numeric($id) && (int) $id > 0)
        )));
        $user->commandTemplates()->sync($commandIds);

        $permissionIds = $data['device_permission_ids'] ?? [];
        $permissionIds = array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            array_filter($permissionIds, static fn ($id): bool => is_numeric($id) && (int) $id > 0)
        )));

        $permissionPortsRaw = $data['device_permission_ports'] ?? [];
        if (!is_array($permissionPortsRaw)) {
            $permissionPortsRaw = [];
        }

        $permissionPorts = [];
        foreach ($permissionPortsRaw as $deviceIdRaw => $expressionRaw) {
            if (!is_numeric($deviceIdRaw)) {
                continue;
            }
            $deviceId = (int) $deviceIdRaw;
            if (!in_array($deviceId, $permissionIds, true)) {
                continue;
            }
            $permissionPorts[$deviceId] = $this->normalizeInterfaceExpression(is_string($expressionRaw) ? $expressionRaw : null);
        }

        $permissionCommandTemplateIdsRaw = $data['device_permission_command_template_ids'] ?? [];
        if (!is_array($permissionCommandTemplateIdsRaw)) {
            $permissionCommandTemplateIdsRaw = [];
        }

        $permissionCommandTemplateIds = [];
        foreach ($permissionCommandTemplateIdsRaw as $deviceIdRaw => $templateIdsRaw) {
            if (!is_numeric($deviceIdRaw)) {
                continue;
            }

            $deviceId = (int) $deviceIdRaw;
            if (!in_array($deviceId, $permissionIds, true)) {
                continue;
            }

            $templateIds = is_array($templateIdsRaw) ? $templateIdsRaw : [];
            $permissionCommandTemplateIds[$deviceId] = array_values(array_unique(array_map(
                static fn ($id): int => (int) $id,
                array_filter($templateIds, static fn ($id): bool => is_numeric($id) && (int) $id > 0)
            )));
        }

        $existingPermissionMetaQuery = DB::table('device_permissions')
            ->where('user_id', $user->id)
            ->whereIn('device_id', $permissionIds);

        $existingPermissionColumns = ['device_id', 'granted_by', 'granted_at'];
        $supportsDeviceCommandRestrictions = DevicePermission::supportsAllowedCommandTemplateIds();
        if ($supportsDeviceCommandRestrictions) {
            $existingPermissionColumns[] = 'allowed_command_template_ids';
        }

        $existingPermissionMeta = $existingPermissionMetaQuery
            ->get($existingPermissionColumns)
            ->keyBy(static fn ($row): int => (int) $row->device_id);

        $permissionPayload = [];
        foreach ($permissionIds as $deviceId) {
            $existing = $existingPermissionMeta->get($deviceId);
            $permissionPayload[$deviceId] = [
                'granted_by' => $existing?->granted_by ?? $assignedBy,
                'granted_at' => $existing?->granted_at ?? now(),
                'allowed_ports' => $permissionPorts[$deviceId] ?? null,
            ];

            if ($supportsDeviceCommandRestrictions) {
                $selectedTemplateIds = $permissionCommandTemplateIds[$deviceId] ?? null;
                $permissionPayload[$deviceId]['allowed_command_template_ids'] = is_array($selectedTemplateIds)
                    ? DevicePermission::encodeAllowedCommandTemplateIds($selectedTemplateIds)
                    : ($existing?->allowed_command_template_ids ?? null);
            }
        }

        if (!empty($permissionPayload)) {
            $user->permittedDevices()->sync($permissionPayload);
        } else {
            $user->permittedDevices()->detach();
        }

        if (DeviceGraphPermission::supportsScopedAccess()) {
            $graphDeviceIds = $data['graph_device_ids'] ?? [];
            $graphDeviceIds = array_values(array_unique(array_map(
                static fn ($id): int => (int) $id,
                array_filter($graphDeviceIds, static fn ($id): bool => is_numeric($id) && (int) $id > 0)
            )));

            $graphInterfaceRaw = $data['graph_device_interfaces'] ?? [];
            if (!is_array($graphInterfaceRaw)) {
                $graphInterfaceRaw = [];
            }

            $graphInterfaceMap = [];
            foreach ($graphInterfaceRaw as $deviceIdRaw => $expressionRaw) {
                if (!is_numeric($deviceIdRaw)) {
                    continue;
                }

                $deviceId = (int) $deviceIdRaw;
                if (!in_array($deviceId, $graphDeviceIds, true)) {
                    continue;
                }

                $graphInterfaceMap[$deviceId] = $this->normalizeInterfaceExpression(
                    is_string($expressionRaw) ? $expressionRaw : null
                );
            }

            $graphPayload = [];
            if (!empty($graphDeviceIds)) {
                $existingGraphMeta = DB::table('device_graph_permissions')
                    ->where('user_id', $user->id)
                    ->whereIn('device_id', $graphDeviceIds)
                    ->get(['device_id', 'granted_by', 'granted_at'])
                    ->keyBy(static fn ($row): int => (int) $row->device_id);

                foreach ($graphDeviceIds as $deviceId) {
                    $existing = $existingGraphMeta->get($deviceId);
                    $graphPayload[$deviceId] = [
                        'granted_by' => $existing?->granted_by ?? $assignedBy,
                        'granted_at' => $existing?->granted_at ?? now(),
                        'allowed_interfaces' => $graphInterfaceMap[$deviceId] ?? null,
                    ];
                }
            }

            if (!empty($graphPayload)) {
                $user->graphScopedDevices()->sync($graphPayload);
            } else {
                $user->graphScopedDevices()->detach();
            }
        }

        if (DeviceEventPermission::supportsScopedAccess()) {
            $supportsEventInterfaceScope = DeviceEventPermission::supportsInterfaceScope();
            $eventDeviceIds = $data['event_device_ids'] ?? [];
            $eventDeviceIds = array_values(array_unique(array_map(
                static fn ($id): int => (int) $id,
                array_filter($eventDeviceIds, static fn ($id): bool => is_numeric($id) && (int) $id > 0)
            )));

            $eventInterfaceMap = [];
            if ($supportsEventInterfaceScope) {
                $eventInterfaceRaw = $data['event_device_interfaces'] ?? [];
                if (!is_array($eventInterfaceRaw)) {
                    $eventInterfaceRaw = [];
                }
                foreach ($eventInterfaceRaw as $deviceIdRaw => $expressionRaw) {
                    if (!is_numeric($deviceIdRaw)) {
                        continue;
                    }

                    $deviceId = (int) $deviceIdRaw;
                    if (!in_array($deviceId, $eventDeviceIds, true)) {
                        continue;
                    }

                    $eventInterfaceMap[$deviceId] = $this->normalizeInterfaceExpression(
                        is_string($expressionRaw) ? $expressionRaw : null
                    );
                }
            }

            $eventPayload = [];
            if (!empty($eventDeviceIds)) {
                $existingEventColumns = ['device_id', 'granted_by', 'granted_at'];
                if ($supportsEventInterfaceScope) {
                    $existingEventColumns[] = 'allowed_interfaces';
                }
                $existingEventMeta = DB::table('device_event_permissions')
                    ->where('user_id', $user->id)
                    ->whereIn('device_id', $eventDeviceIds)
                    ->get($existingEventColumns)
                    ->keyBy(static fn ($row): int => (int) $row->device_id);

                foreach ($eventDeviceIds as $deviceId) {
                    $existing = $existingEventMeta->get($deviceId);
                    $eventPayload[$deviceId] = [
                        'granted_by' => $existing?->granted_by ?? $assignedBy,
                        'granted_at' => $existing?->granted_at ?? now(),
                    ];
                    if ($supportsEventInterfaceScope) {
                        $eventPayload[$deviceId]['allowed_interfaces'] = $eventInterfaceMap[$deviceId] ?? $existing?->allowed_interfaces ?? null;
                    }
                }
            }

            if (!empty($eventPayload)) {
                $user->eventScopedDevices()->sync($eventPayload);
            } else {
                $user->eventScopedDevices()->detach();
            }
        }

        if (($user->role ?? 'user') === 'admin') {
            $this->grantAdminFullAccess($user, (int) $request->session()->get('auth.user_id', 0));
        }
    }

    public function telegramTest(Request $request, User $user)
    {
        $targetUser = clone $user;
        $targetUser->telegram_enabled = $request->boolean('telegram_enabled', (bool) ($user->telegram_enabled ?? false));

        $chatId = trim((string) $request->input('telegram_chat_id', $user->telegram_chat_id));
        $targetUser->telegram_chat_id = $chatId !== '' ? $chatId : null;

        $botToken = trim((string) $request->input('telegram_bot_token', $user->telegram_bot_token));
        $targetUser->telegram_bot_token = $botToken !== '' ? $botToken : null;

        $templateConfig = $this->decodeTelegramTemplateConfig((string) ($user->telegram_template ?? ''));
        $templateDefault = trim((string) $request->input('telegram_template', $templateConfig['default']));
        $severityTemplates = [];
        foreach (self::TELEGRAM_TEMPLATE_INPUTS as $severity => $fieldName) {
            $value = trim((string) $request->input($fieldName, $templateConfig['severity_templates'][$severity] ?? ''));
            if ($value !== '') {
                $severityTemplates[$severity] = $value;
            }
        }
        $targetUser->telegram_template = $this->encodeTelegramTemplateConfig($templateDefault, $severityTemplates);

        $notifier = $this->resolveTelegramNotifier();
        if ($notifier === null) {
            return back()->with('status', 'Telegram notifier service is unavailable on this server.');
        }

        $sent = $notifier->sendTestMessage($targetUser);

        if (!$sent) {
            $detail = '';
            if (method_exists($notifier, 'getLastError')) {
                $lastError = trim((string) ($notifier->getLastError() ?? ''));
                if ($lastError !== '') {
                    $detail = " Reason: {$lastError}";
                }
            }

            if ($detail === '') {
                $detail = ' Check chat ID, enabled toggle, and bot token.';
            }

            return back()->with('status', "Could not send Telegram test for {$user->name}.{$detail}");
        }

        return back()->with('status', "Telegram test sent to {$user->name}.");
    }
    private function resolveTelegramNotifier(): ?object
    {
        $serviceClass = 'App\\Services\\TelegramEventNotifier';
        if (!class_exists($serviceClass)) {
            return null;
        }

        try {
            $service = app($serviceClass);
            if (is_object($service) && method_exists($service, 'sendTestMessage')) {
                return $service;
            }
        } catch (\Throwable $e) {
            // ignore and fall back to null
        }

        return null;
    }
    private function defaultTelegramDeviceIds(User $user, array $deviceIds): array
    {
        if (!empty($deviceIds)) {
            return array_values(array_unique(array_map('intval', $deviceIds)));
        }

        return Device::where('assigned_user_id', $user->id)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    private function validateInterfaceExpression(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $tokens = preg_split('/\s*,\s*/', $value) ?: [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            if (preg_match('/[\x00-\x1F\x7F]/u', $token)) {
                return 'Interface access values must be comma-separated entries (for example Gi1/0/1,Gi1/0/2 or Gi1/0/*).';
            }
        }

        return null;
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
            return 'Telegram settings saved, but test message could not be sent (service unavailable).';
        }

        $targetUser = clone $user;
        $targetUser->telegram_enabled = true;
        $targetUser->telegram_chat_id = $currentChatId;
        $targetUser->telegram_bot_token = $currentBotToken;

        $message = "Telegram settings were added successfully for {$user->name}.";
        $sent = (bool) $notifier->sendDirectMessage($targetUser, $message, [
            'scope' => 'users.save.telegram_confirmation',
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
        if ($currentChatId === null) {
            return false;
        }

        return $previousChatId !== $currentChatId || $previousBotToken !== $currentBotToken;
    }

    private function normalizeTelegramCredentialValue(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeInterfaceExpression(?string $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $tokens = preg_split('/\s*,\s*/', $value) ?: [];
        $items = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }
            $items[] = $token;
        }

        $items = array_values(array_unique($items, SORT_STRING));
        return empty($items) ? null : implode(',', $items);
    }

    private function normalizeTelegramEventTypes(array $base, string $custom): array
    {
        $items = [];

        foreach ($base as $type) {
            $normalized = strtolower(trim((string) $type));
            if ($normalized !== '') {
                $items[] = $normalized;
            }
        }

        $customTokens = preg_split('/\s*,\s*/', trim($custom)) ?: [];
        foreach ($customTokens as $token) {
            $normalized = strtolower(trim($token));
            if ($normalized !== '') {
                $items[] = $normalized;
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @return array{default:string,severity_templates:array<string,string>}
     */
    private function decodeTelegramTemplateConfig(string $stored): array
    {
        $stored = trim($stored);
        if ($stored === '') {
            return [
                'default' => '',
                'severity_templates' => [],
            ];
        }

        $decoded = json_decode($stored, true);
        if (!is_array($decoded)) {
            return [
                'default' => $stored,
                'severity_templates' => [],
            ];
        }

        $default = trim((string) ($decoded['default'] ?? ''));
        $rawSeverityTemplates = $decoded['severity_templates'] ?? ($decoded['templates_by_severity'] ?? []);
        $severityTemplates = [];
        if (is_array($rawSeverityTemplates)) {
            foreach (self::TELEGRAM_TEMPLATE_INPUTS as $severity => $fieldName) {
                $value = trim((string) ($rawSeverityTemplates[$severity] ?? ''));
                if ($value !== '') {
                    $severityTemplates[$severity] = $value;
                }
            }
        }

        return [
            'default' => $default,
            'severity_templates' => $severityTemplates,
        ];
    }

    /**
     * @param array<string,string> $severityTemplates
     */
    private function encodeTelegramTemplateConfig(string $defaultTemplate, array $severityTemplates): ?string
    {
        $defaultTemplate = trim($defaultTemplate);

        $normalizedSeverityTemplates = [];
        foreach (self::TELEGRAM_TEMPLATE_INPUTS as $severity => $fieldName) {
            $value = trim((string) ($severityTemplates[$severity] ?? ''));
            if ($value !== '') {
                $normalizedSeverityTemplates[$severity] = $value;
            }
        }

        if ($defaultTemplate === '' && empty($normalizedSeverityTemplates)) {
            return null;
        }

        if (empty($normalizedSeverityTemplates)) {
            return $defaultTemplate !== '' ? $defaultTemplate : null;
        }

        return json_encode([
            'default' => $defaultTemplate,
            'severity_templates' => $normalizedSeverityTemplates,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ($defaultTemplate !== '' ? $defaultTemplate : null);
    }
    private function ensureExecCommandTemplates(): void
    {
        foreach (self::EXEC_COMMAND_TEMPLATES as $template) {
            CommandTemplate::updateOrCreate(
                ['action_key' => $template['action_key']],
                [
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'ui_type' => 'button',
                    'active' => true,
                    'created_by' => null,
                ]
            );
        }

    }

    private function storeAvatarFile(UploadedFile $file, ?string $currentPath = null): string
    {
        $directory = public_path('uploads/avatars');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $this->deleteAvatarFile($currentPath);

        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg'));
        $filename = (string) Str::uuid() . '.' . $extension;
        $file->move($directory, $filename);

        return 'uploads/avatars/' . $filename;
    }

    private function deleteAvatarFile(?string $path): void
    {
        $normalized = ltrim(str_replace('\\', '/', (string) $path), '/');
        if ($normalized === '' || !Str::startsWith($normalized, 'uploads/avatars/')) {
            return;
        }

        $fullPath = public_path($normalized);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function grantAdminFullAccess(User $user, int $grantedByUserId = 0): void
    {
        if (($user->role ?? 'user') !== 'admin') {
            return;
        }

        $activeCommandIds = CommandTemplate::where('active', true)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $user->commandTemplates()->sync($activeCommandIds);

        $allDeviceIds = Device::query()
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if (empty($allDeviceIds)) {
            $user->permittedDevices()->detach();
            return;
        }

        $existingPermissionColumns = ['device_id', 'granted_by', 'granted_at'];
        if (DevicePermission::supportsAllowedCommandTemplateIds()) {
            $existingPermissionColumns[] = 'allowed_command_template_ids';
        }

        $existingPermissionMeta = DB::table('device_permissions')
            ->where('user_id', $user->id)
            ->whereIn('device_id', $allDeviceIds)
            ->get($existingPermissionColumns)
            ->keyBy(static fn ($row): int => (int) $row->device_id);

        $grantedBy = $grantedByUserId > 0 ? $grantedByUserId : null;
        $payload = [];
        foreach ($allDeviceIds as $deviceId) {
            $existing = $existingPermissionMeta->get($deviceId);
            $payload[$deviceId] = [
                'granted_by' => $existing?->granted_by ?? $grantedBy,
                'granted_at' => $existing?->granted_at ?? now(),
                'allowed_ports' => null,
            ];

            if (DevicePermission::supportsAllowedCommandTemplateIds()) {
                $payload[$deviceId]['allowed_command_template_ids'] = null;
            }
        }

        $user->permittedDevices()->sync($payload);
    }
}

