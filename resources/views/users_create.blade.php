<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<meta name="app-base" content="{{ url('/') }}"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Create User - Device Control Manager</title>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script>
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                primary: "#135bec",
                "background-light": "#f6f6f8",
                "background-dark": "#101622",
            },
            fontFamily: {
                display: ["Inter", "sans-serif"],
            },
        },
    },
};
</script>
<style>
body { font-family: 'Inter', sans-serif; }
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
</style>
@include('partials.admin_sidebar_styles')
<script src="{{ asset('js/actions.js') . '?v=' . filemtime(public_path('js/actions.js')) }}" defer></script>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 h-screen overflow-hidden">
@php
$assignedDeviceIds = old('device_ids', []);
if (!is_array($assignedDeviceIds)) { $assignedDeviceIds = []; }
$assignedDeviceIds = array_values(array_unique(array_map('intval', array_filter($assignedDeviceIds, static fn ($id): bool => is_numeric($id)))));
$selectedPermissionDeviceIds = old('device_permission_ids', []);
if (!is_array($selectedPermissionDeviceIds)) { $selectedPermissionDeviceIds = []; }
$selectedPermissionDeviceIds = array_values(array_unique(array_map('intval', array_filter($selectedPermissionDeviceIds, static fn ($id): bool => is_numeric($id)))));
$permissionPortMap = [];
$oldPermissionPortMap = old('device_permission_ports');
if (is_array($oldPermissionPortMap)) {
    foreach ($oldPermissionPortMap as $deviceId => $value) {
        if (is_numeric($deviceId)) { $permissionPortMap[(int) $deviceId] = trim((string) $value); }
    }
}
$permissionCommandTemplateMap = [];
$oldPermissionCommandTemplateMap = old('device_permission_command_template_ids');
if (is_array($oldPermissionCommandTemplateMap)) {
    foreach ($oldPermissionCommandTemplateMap as $deviceId => $templateIds) {
        if (!is_numeric($deviceId) || !is_array($templateIds)) { continue; }
        $permissionCommandTemplateMap[(int) $deviceId] = array_values(array_unique(array_map('intval', array_filter($templateIds, static fn ($id): bool => is_numeric($id)))));
    }
}
$selectedTelegramDevices = old('telegram_devices', $assignedDeviceIds);
if (!is_array($selectedTelegramDevices) || empty($selectedTelegramDevices)) { $selectedTelegramDevices = $assignedDeviceIds; }
$selectedTelegramDevices = array_values(array_unique(array_map('intval', array_filter($selectedTelegramDevices, static fn ($id): bool => is_numeric($id)))));
$severityOptions = $telegramSeverityOptions ?? ['low', 'medium', 'high', 'critical'];
$selectedTelegramSeverities = old('telegram_severities', ['high', 'critical']);
if (!is_array($selectedTelegramSeverities) || empty($selectedTelegramSeverities)) { $selectedTelegramSeverities = ['high', 'critical']; }
$selectedTelegramSeverities = array_values(array_unique(array_map(static fn ($value): string => strtolower(trim((string) $value)), $selectedTelegramSeverities)));
$eventTypeOptions = $telegramEventTypeOptions ?? ['device.offline', 'port.down'];
$selectedTelegramEventTypes = old('telegram_event_types', ['device.offline', 'port.down']);
if (!is_array($selectedTelegramEventTypes) || empty($selectedTelegramEventTypes)) { $selectedTelegramEventTypes = ['device.offline', 'port.down']; }
$selectedTelegramEventTypes = array_values(array_unique(array_map(static fn ($value): string => strtolower(trim((string) $value)), $selectedTelegramEventTypes)));
$customTelegramEventTypes = old('telegram_event_types_custom', implode(',', array_values(array_diff($selectedTelegramEventTypes, $eventTypeOptions))));
$selectedCommandTemplateIds = old('command_template_ids', []);
if (!is_array($selectedCommandTemplateIds)) { $selectedCommandTemplateIds = []; }
$selectedCommandTemplateIds = array_values(array_unique(array_map('intval', array_filter($selectedCommandTemplateIds, static fn ($id): bool => is_numeric($id)))));
$selectedCommandTemplateLookup = array_fill_keys($selectedCommandTemplateIds, true);
$customCommandType = old('custom_command_type', '');
$customCommandScriptName = old('custom_command_script_name', '');
$customCommandScriptCode = old('custom_command_script_code', '');
$showCustomCommandFields = $customCommandType === 'custom';
$telegramEnabled = (bool) old('telegram_enabled', false);
$canViewAssignedDeviceGraphs = (bool) old('can_view_assigned_device_graphs', false);
$assignedDeviceGraphAccessReady = (bool) ($assignedDeviceGraphAccessReady ?? false);
$deviceGraphScopeReady = (bool) ($deviceGraphScopeReady ?? false);
$selectedGraphDeviceIds = old('graph_device_ids', []);
if (!is_array($selectedGraphDeviceIds)) { $selectedGraphDeviceIds = []; }
$selectedGraphDeviceIds = array_values(array_unique(array_map('intval', array_filter($selectedGraphDeviceIds, static fn ($id): bool => is_numeric($id)))));
$graphInterfaceMap = [];
$oldGraphInterfaceMap = old('graph_device_interfaces');
if (is_array($oldGraphInterfaceMap)) {
    foreach ($oldGraphInterfaceMap as $deviceId => $value) {
        if (is_numeric($deviceId)) { $graphInterfaceMap[(int) $deviceId] = trim((string) $value); }
    }
}
@endphp

<div class="flex h-screen overflow-hidden">
@include('partials.admin_sidebar')
<main class="flex-1 flex flex-col overflow-y-auto">
<header class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-slate-200 bg-white/90 px-4 py-3 backdrop-blur dark:border-slate-800 dark:bg-background-dark/80 sm:px-6 lg:px-8">
<div class="flex min-w-0 items-center gap-3">
<button class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-300 dark:hover:bg-slate-800" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
<span class="material-symbols-outlined">menu</span>
</button>
<div class="min-w-0">
<p class="text-xs font-bold uppercase tracking-[0.2em] text-primary">Users</p>
<h1 class="truncate text-xl font-black tracking-tight sm:text-2xl">Create New User</h1>
<p class="mt-1 text-sm text-slate-500">Use the same identity, access, command, and Telegram controls available in the edit flow.</p>
</div>
</div>
<a class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-slate-50 dark:bg-slate-900 dark:border-slate-700 dark:hover:bg-slate-800" href="{{ route('users.index') }}">
<span class="material-symbols-outlined text-[18px]">arrow_back</span>
Back to Users
</a>
</header>

<div class="mx-auto w-full max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">

@if ($errors->any())
<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
<p class="font-semibold">Could not create user.</p>
<ul class="mt-2 list-disc pl-5 space-y-1">
@foreach ($errors->all() as $error)
<li>{{ $error }}</li>
@endforeach
</ul>
</div>
@endif

<form class="space-y-6" method="POST" action="{{ route('users.store') }}" enctype="multipart/form-data">
@csrf
<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:bg-slate-900 dark:border-slate-800">
<div class="mb-4">
<p class="text-xs font-bold uppercase tracking-wider text-slate-500">1) Account</p>
<p class="mt-1 text-xs text-slate-400">Identity, role, and first-login password.</p>
</div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
<div class="md:col-span-2 flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="username">Username</label>
<input id="username" class="h-11 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:border-primary focus:ring-primary" name="username" type="text" value="{{ old('username') }}" required autofocus/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="role">Role</label>
<select id="role" class="h-11 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:border-primary focus:ring-primary" name="role">
<option value="admin" @selected(old('role') === 'admin')>Admin</option>
<option value="user" @selected(old('role', 'user') !== 'admin')>User</option>
</select>
</div>
<div class="md:col-span-3 flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="password">Password</label>
<input id="password" class="h-11 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:border-primary focus:ring-primary" name="password" type="password" placeholder="Minimum 6 characters" required/>
<p class="text-xs text-slate-400">The account is created as active. Use the users page if you need to deactivate it later.</p>
</div>
<div class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-950/40" data-avatar-upload>
<div class="flex flex-col gap-4 md:flex-row md:items-center">
@if ($avatarStorageReady ?? false)
<label class="group relative cursor-pointer self-start" for="avatar" title="Upload profile picture">
<span class="block" data-avatar-preview data-preview-class="h-16 w-16 rounded-full border border-slate-200 object-cover shadow-sm dark:border-slate-700">
@include('partials.user_avatar', ['user' => null, 'name' => old('username', 'New User'), 'sizeClass' => 'h-16 w-16', 'textClass' => 'text-lg', 'class' => 'shadow-sm'])
</span>
<span class="absolute -bottom-1 -right-1 inline-flex h-7 w-7 items-center justify-center rounded-full bg-primary text-white shadow-sm transition group-hover:bg-primary/90">
<span class="material-symbols-outlined text-[16px]">edit</span>
</span>
</label>
<div class="min-w-0 flex-1 flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="avatar">Profile Picture</label>
<div class="flex flex-wrap items-center gap-3">
<label class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary/90" for="avatar">
<span class="material-symbols-outlined text-[18px]">upload</span>
Upload Picture
</label>
<span class="text-xs text-slate-500">PNG, JPG, WEBP, or GIF up to 2 MB.</span>
</div>
<input id="avatar" class="sr-only" name="avatar" type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-avatar-input/>
<p class="text-xs text-slate-400">Click the picture or the button to choose a profile image.</p>
<p class="text-xs font-medium text-slate-500" data-avatar-file-name data-default-text="No file selected yet.">No file selected yet.</p>
</div>
@else
@include('partials.user_avatar', ['user' => null, 'name' => old('username', 'New User'), 'sizeClass' => 'h-16 w-16', 'textClass' => 'text-lg', 'class' => 'shadow-sm'])
<div class="min-w-0 flex-1 flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Profile Picture</label>
<p class="text-sm text-amber-700 dark:text-amber-300">Profile uploads are disabled on this server until the latest users migration is applied.</p>
<p class="text-xs text-slate-400">Run <code>php artisan migrate --force</code> to add the required <code>avatar_path</code> column.</p>
</div>
@endif
</div>
</div>
</div>
</div>

<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:bg-slate-900 dark:border-slate-800">
<div class="mb-4">
<p class="text-xs font-bold uppercase tracking-wider text-slate-500">2) Device Scope</p>
<p class="mt-1 text-xs text-slate-400">Assign owned devices first, then grant command-only device access.</p>
</div>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
<div class="flex flex-col gap-2" data-checkbox-group>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Assigned Devices</label>
<span class="text-[11px] font-semibold text-slate-500"><span data-checkbox-count>0</span> selected</span>
</div>
<div class="max-h-44 overflow-y-auto rounded-lg border border-slate-300 bg-white p-3 dark:border-slate-700 dark:bg-slate-800 space-y-2">
@foreach ($devices as $device)
<label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
<input class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" name="device_ids[]" value="{{ $device->id }}" data-checkbox-item @checked(in_array((int) $device->id, $assignedDeviceIds, true))/>
<span>{{ $device->name }}@if ($device->serial_number) ({{ $device->serial_number }})@endif</span>
</label>
@endforeach
</div>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-checkbox-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-checkbox-action="none">Clear</button>
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-checkbox-action="invert">Invert</button>
</div>
</div>

<div class="flex flex-col gap-2" data-checkbox-group>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Command Device Access</label>
<span class="text-[11px] font-semibold text-slate-500"><span data-checkbox-count>0</span> selected</span>
</div>
<div class="max-h-44 overflow-y-auto rounded-lg border border-slate-300 bg-white p-3 dark:border-slate-700 dark:bg-slate-800 space-y-2">
@foreach ($devices as $device)
<label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
<input class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" name="device_permission_ids[]" value="{{ $device->id }}" data-checkbox-item data-device-permission-checkbox @checked(in_array((int) $device->id, $selectedPermissionDeviceIds, true))/>
<span>{{ $device->name }}@if ($device->serial_number) ({{ $device->serial_number }})@endif</span>
</label>
@endforeach
</div>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-checkbox-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-checkbox-action="none">Clear</button>
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-checkbox-action="invert">Invert</button>
</div>
</div>

<div class="xl:col-span-2 flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Device Graph Access</label>
@if ($assignedDeviceGraphAccessReady)
<label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-200">
<input class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" name="can_view_assigned_device_graphs" value="1" @checked($canViewAssignedDeviceGraphs) />
<span>Enable graph visibility for this user account.</span>
</label>
<p class="text-xs text-slate-400">Admins always have graph access; this toggle applies to user accounts.</p>
@if ($deviceGraphScopeReady)
<div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
<div class="flex flex-col gap-2" data-checkbox-group>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Graph Devices</label>
<span class="text-[11px] font-semibold text-slate-500"><span data-checkbox-count>0</span> selected</span>
</div>
<div class="max-h-44 overflow-y-auto rounded-lg border border-slate-300 bg-white p-3 dark:border-slate-700 dark:bg-slate-800 space-y-2">
@foreach ($devices as $device)
<label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
<input class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" name="graph_device_ids[]" value="{{ $device->id }}" data-checkbox-item data-graph-device-checkbox @checked(in_array((int) $device->id, $selectedGraphDeviceIds, true))/>
<span>{{ $device->name }}@if ($device->serial_number) ({{ $device->serial_number }})@endif</span>
</label>
@endforeach
</div>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-checkbox-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-checkbox-action="none">Clear</button>
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-checkbox-action="invert">Invert</button>
</div>
</div>
<div class="flex flex-col gap-2" data-device-graph-interface-permissions>
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Graph Interfaces Per Device (optional)</label>
<div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3 space-y-3 bg-slate-50/70 dark:bg-slate-800/40">
@foreach ($devices as $device)
@php
$deviceId = (int) $device->id;
$graphAllowedInterfaces = trim((string) ($graphInterfaceMap[$deviceId] ?? ''));
$hasGraphDevice = in_array($deviceId, $selectedGraphDeviceIds, true);
@endphp
<div class="{{ $hasGraphDevice ? '' : 'hidden' }} rounded-lg border border-slate-200 dark:border-slate-700 p-3 bg-white dark:bg-slate-900" data-device-graph-interface-item data-device-id="{{ $deviceId }}">
<div class="text-xs font-semibold text-slate-500 mb-2">{{ $device->name }}@if ($device->serial_number) ({{ $device->serial_number }})@endif</div>
<input class="h-10 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:border-primary focus:ring-primary" type="text" name="graph_device_interfaces[{{ $deviceId }}]" value="{{ $graphAllowedInterfaces }}" placeholder="Gi1/0/1,Gi1/0/2,Gi1/0/* or *"/>
</div>
@endforeach
<p class="text-xs text-slate-400 {{ !empty($selectedGraphDeviceIds) ? 'hidden' : '' }}" data-device-graph-interface-empty>Select one or more graph devices to set interface scope.</p>
</div>
<p class="text-xs text-slate-400">Leave blank to allow all interfaces on that device.</p>
</div>
</div>
<p class="text-xs text-slate-400">If no graph devices are selected, enabled users can view graphs for all assigned/permitted devices.</p>
@else
<div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
Run <code>php artisan migrate --force</code> to enable per-device and per-interface graph scope controls.
</div>
@endif
@else
<div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
Run <code>php artisan migrate --force</code> to enable user graph access toggles.
</div>
@endif
</div>

<div class="xl:col-span-2 flex flex-col gap-2" data-device-port-permissions>
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Port Access Per Device (optional)</label>
<div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3 space-y-3 bg-slate-50/70 dark:bg-slate-800/40">
@foreach ($devices as $device)
@php
$deviceId = (int) $device->id;
$deviceAllowedPorts = trim((string) ($permissionPortMap[$deviceId] ?? ''));
$hasDevicePermission = in_array($deviceId, $selectedPermissionDeviceIds, true);
@endphp
<div class="{{ $hasDevicePermission ? '' : 'hidden' }} rounded-lg border border-slate-200 dark:border-slate-700 p-3 bg-white dark:bg-slate-900" data-device-port-item data-device-id="{{ $deviceId }}">
<div class="text-xs font-semibold text-slate-500 mb-2">{{ $device->name }}@if ($device->serial_number) ({{ $device->serial_number }})@endif</div>
<input class="h-10 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:border-primary focus:ring-primary" type="text" name="device_permission_ports[{{ $deviceId }}]" value="{{ $deviceAllowedPorts }}" placeholder="Gi1/0/1,Gi1/0/2,Gi1/0/* or *"/>
</div>
@endforeach
<p class="text-xs text-slate-400 {{ !empty($selectedPermissionDeviceIds) ? 'hidden' : '' }}" data-device-port-empty>Select one or more command devices to set port-level access.</p>
</div>
<p class="text-xs text-slate-400">Leave blank to allow all ports on that device.</p>
</div>
<div class="xl:col-span-2 flex flex-col gap-2" data-device-command-permissions>
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Command Scope Per Device (optional)</label>
@if ($deviceCommandRestrictionsReady ?? false)
<div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3 space-y-3 bg-slate-50/70 dark:bg-slate-800/40">
@foreach ($devices as $device)
@php
$deviceId = (int) $device->id;
$selectedDeviceCommandTemplateIds = $permissionCommandTemplateMap[$deviceId] ?? [];
$hasDevicePermission = in_array($deviceId, $selectedPermissionDeviceIds, true);
@endphp
<div class="{{ $hasDevicePermission ? '' : 'hidden' }} rounded-lg border border-slate-200 dark:border-slate-700 p-3 bg-white dark:bg-slate-900" data-device-command-item data-device-id="{{ $deviceId }}">
<div class="flex items-center justify-between gap-3 mb-2">
<div class="text-xs font-semibold text-slate-500">{{ $device->name }}@if ($device->serial_number) ({{ $device->serial_number }})@endif</div>
<span class="text-[11px] text-slate-400">Leave all unchecked to inherit the global command list.</span>
</div>
@if ($commandTemplates->isNotEmpty())
<input type="hidden" name="device_permission_command_template_ids[{{ $deviceId }}][]" value=""/>
<div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-44 overflow-y-auto">
@foreach ($commandTemplates as $template)
<label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
<input class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" name="device_permission_command_template_ids[{{ $deviceId }}][]" value="{{ $template->id }}" @checked(in_array((int) $template->id, $selectedDeviceCommandTemplateIds, true))/>
<span>{{ $template->name }}</span>
</label>
@endforeach
</div>
@else
<p class="text-xs text-slate-400">Create or enable command permissions below before scoping them per device.</p>
@endif
</div>
@endforeach
<p class="text-xs text-slate-400 {{ !empty($selectedPermissionDeviceIds) ? 'hidden' : '' }}" data-device-command-empty>Select one or more command devices to scope commands on each device.</p>
</div>
<p class="text-xs text-slate-400">When you select commands here, this device uses that list. Leave everything unchecked to inherit the global command list from section 3.</p>
@else
<div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
Run <code>php artisan migrate --force</code> to enable per-device command restrictions on this server.
</div>
@endif
</div>
</div>
</div>

<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:bg-slate-900 dark:border-slate-800">
<div class="mb-4">
<p class="text-xs font-bold uppercase tracking-wider text-slate-500">3) Command Permissions</p>
<p class="mt-1 text-xs text-slate-400">Keep only the commands this user should be allowed to execute.</p>
</div>
<div class="space-y-3" data-checkbox-group>
<div class="flex flex-wrap items-center justify-between gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Command Permissions</label>
<div class="flex items-center gap-2">
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-checkbox-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-checkbox-action="none">Clear</button>
</div>
</div>
<div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3 space-y-3 bg-slate-50/70 dark:bg-slate-800/40" data-custom-command-builder>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Add Command Permission</label>
<select class="h-11 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:border-primary focus:ring-primary" name="custom_command_type" data-custom-command-type>
<option value="">Use existing command permissions</option>
<option value="custom" @selected($showCustomCommandFields)>Custom Command</option>
</select>
</div>
<div class="{{ $showCustomCommandFields ? '' : 'hidden' }} grid grid-cols-1 gap-3" data-custom-command-fields>
<input class="h-11 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:border-primary focus:ring-primary" type="text" name="custom_command_script_name" value="{{ $customCommandScriptName }}" placeholder="Script name"/>
<textarea class="min-h-[110px] rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:border-primary focus:ring-primary font-mono text-xs" name="custom_command_script_code" placeholder="#!/bin/bash&#10;echo &quot;custom command&quot;">{{ $customCommandScriptCode }}</textarea>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-2 rounded-lg border border-slate-200 dark:border-slate-700 p-3 max-h-64 overflow-y-auto bg-white dark:bg-slate-800">
@forelse ($commandTemplates as $template)
<label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
<input class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" name="command_template_ids[]" value="{{ $template->id }}" data-checkbox-item @checked(isset($selectedCommandTemplateLookup[(int) $template->id]))/>
<span>{{ $template->name }}</span>
</label>
@empty
<p class="text-xs text-slate-400">No command templates available.</p>
@endforelse
</div>
<p class="text-xs text-slate-400"><span class="font-semibold" data-checkbox-count>0</span> commands selected.</p>
</div>
</div>

<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:bg-slate-900 dark:border-slate-800">
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
<div>
<p class="text-xs font-bold uppercase tracking-wider text-slate-500">4) Telegram Notifications</p>
<p class="mt-1 text-xs text-slate-400">Optional delivery settings for device and port alert events.</p>
</div>
<label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
<input class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" name="telegram_enabled" value="1" @checked($telegramEnabled) />
<span>Enabled</span>
</label>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Telegram Chat ID</label>
<input class="h-11 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:border-primary focus:ring-primary" name="telegram_chat_id" type="text" value="{{ old('telegram_chat_id') }}" placeholder="123456789 or -1001234567890"/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Telegram Ports</label>
<input class="h-11 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:border-primary focus:ring-primary" name="telegram_ports" type="text" value="{{ old('telegram_ports') }}" placeholder="80,443,1000-1010"/>
</div>
<div class="md:col-span-2 flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Telegram Bot Token (optional)</label>
<input class="h-11 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:border-primary focus:ring-primary" name="telegram_bot_token" type="text" value="{{ old('telegram_bot_token') }}" placeholder="123456:ABC..."/>
</div>
<div class="flex flex-col gap-2" data-checkbox-group>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Telegram Devices</label>
<span class="text-[11px] font-semibold text-slate-500"><span data-checkbox-count>0</span> selected</span>
</div>
<div class="max-h-44 overflow-y-auto rounded-lg border border-slate-300 bg-white p-3 dark:border-slate-700 dark:bg-slate-800 space-y-2">
@foreach ($devices as $device)
<label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
<input class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" name="telegram_devices[]" value="{{ $device->id }}" data-checkbox-item @checked(in_array((int) $device->id, $selectedTelegramDevices, true))/>
<span>{{ $device->name }}@if ($device->serial_number) ({{ $device->serial_number }})@endif</span>
</label>
@endforeach
</div>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-checkbox-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-checkbox-action="none">Clear</button>
</div>
</div>
<div class="flex flex-col gap-2" data-checkbox-group>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Telegram Severities</label>
<span class="text-[11px] font-semibold text-slate-500"><span data-checkbox-count>0</span> selected</span>
</div>
<div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3 bg-white dark:bg-slate-800 space-y-2">
@foreach ($severityOptions as $severityOption)
<label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200 mr-4">
<input class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" name="telegram_severities[]" value="{{ $severityOption }}" data-checkbox-item @checked(in_array(strtolower((string) $severityOption), $selectedTelegramSeverities, true))/>
<span class="capitalize">{{ $severityOption }}</span>
</label>
@endforeach
</div>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-checkbox-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-checkbox-action="none">Clear</button>
</div>
</div>
<div class="flex flex-col gap-2" data-checkbox-group>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Telegram Event Types</label>
<span class="text-[11px] font-semibold text-slate-500"><span data-checkbox-count>0</span> selected</span>
</div>
<div class="max-h-44 overflow-y-auto rounded-lg border border-slate-300 bg-white p-3 dark:border-slate-700 dark:bg-slate-800 space-y-2">
@foreach ($eventTypeOptions as $eventTypeOption)
<label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
<input class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" name="telegram_event_types[]" value="{{ $eventTypeOption }}" data-checkbox-item @checked(in_array(strtolower((string) $eventTypeOption), $selectedTelegramEventTypes, true))/>
<span>{{ $eventTypeOption }}</span>
</label>
@endforeach
</div>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-checkbox-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-checkbox-action="none">Clear</button>
</div>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Custom Event Types</label>
<input class="h-11 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:border-primary focus:ring-primary" name="telegram_event_types_custom" type="text" value="{{ $customTelegramEventTypes }}" placeholder="device.*, custom.tag"/>
</div>
<div class="md:col-span-2 flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Custom Message Template (optional)</label>
<textarea class="min-h-[96px] rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:border-primary focus:ring-primary" name="telegram_template" placeholder="[{severity}] {type}&#10;Device: {deviceName} ({deviceIp})&#10;Port: {port}&#10;{message}&#10;Time: {timestamp}">{{ old('telegram_template') }}</textarea>
</div>
</div>
</div>

<div class="sticky bottom-0 z-10 flex flex-wrap items-center justify-end gap-3 rounded-xl border border-slate-200 bg-white/95 px-4 py-3 shadow-sm backdrop-blur dark:bg-slate-900/95 dark:border-slate-800">
<a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-950/20 font-medium transition-colors" href="{{ route('auth.logout') }}">
<span class="material-symbols-outlined text-[20px]">logout</span>
<span class="text-sm">Logout</span>
</a>
<a class="px-4 py-2 text-sm font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800" href="{{ route('users.index') }}">Cancel</a>
<button class="px-4 py-2 text-sm font-semibold text-white bg-primary rounded-lg hover:bg-primary/90" type="submit">Create User</button>
</div>
</form>
</div>
</main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-avatar-upload]').forEach(function (section) {
        var input = section.querySelector('[data-avatar-input]');
        var preview = section.querySelector('[data-avatar-preview]');
        var fileName = section.querySelector('[data-avatar-file-name]');

        if (!input || !preview) {
            return;
        }

        var previewClass = preview.dataset.previewClass || 'h-16 w-16 rounded-full object-cover';
        var defaultText = fileName ? (fileName.dataset.defaultText || fileName.textContent) : '';
        var currentObjectUrl = null;

        input.addEventListener('change', function () {
            var file = input.files && input.files[0] ? input.files[0] : null;

            if (!file) {
                if (fileName) {
                    fileName.textContent = defaultText;
                }
                return;
            }

            if (currentObjectUrl) {
                URL.revokeObjectURL(currentObjectUrl);
            }

            currentObjectUrl = URL.createObjectURL(file);
            preview.innerHTML = '';

            var image = document.createElement('img');
            image.src = currentObjectUrl;
            image.alt = 'Profile picture preview';
            image.className = previewClass;
            preview.appendChild(image);

            if (fileName) {
                fileName.textContent = file.name;
            }
        });
    });
});
</script>
</body>
</html>
