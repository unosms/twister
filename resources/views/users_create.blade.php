<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<meta name="app-base" content="{{ url('/') }}"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Create User - Twister Device Control</title>
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
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 h-screen overflow-hidden" data-user-form-checkbox-filters="1">
@php
$assignedDeviceIds = old('device_ids', []);
if (!is_array($assignedDeviceIds)) { $assignedDeviceIds = []; }
$assignedDeviceIds = array_values(array_unique(array_map('intval', array_filter($assignedDeviceIds, static fn ($id): bool => is_numeric($id)))));
$selectedPermissionDeviceIds = old('device_permission_ids', []);
if (!is_array($selectedPermissionDeviceIds)) { $selectedPermissionDeviceIds = []; }
$selectedPermissionDeviceIds = array_values(array_unique(array_map('intval', array_filter($selectedPermissionDeviceIds, static fn ($id): bool => is_numeric($id)))));
$graphInterfaceOptionsByDevice = is_array($graphInterfaceOptionsByDevice ?? null) ? $graphInterfaceOptionsByDevice : [];
$permissionPortMap = [];
$oldPermissionPortMap = old('device_permission_ports');
if (is_array($oldPermissionPortMap)) {
    foreach ($oldPermissionPortMap as $deviceId => $value) {
        if (is_numeric($deviceId)) { $permissionPortMap[(int) $deviceId] = trim((string) $value); }
    }
}
$permissionPortSelectedLookupMap = [];
foreach ($permissionPortMap as $deviceId => $expression) {
    $tokens = preg_split('/\s*,\s*/', trim((string) $expression)) ?: [];
    $lookup = [];
    foreach ($tokens as $token) {
        $token = strtolower(trim((string) $token));
        if ($token !== '') {
            $lookup[$token] = true;
        }
    }
    $permissionPortSelectedLookupMap[(int) $deviceId] = $lookup;
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
$telegramDeviceInterfaceScopeReady = (bool) ($telegramDeviceInterfaceScopeReady ?? false);
$telegramDeviceInterfacesMap = old('telegram_device_interfaces', []);
if (!is_array($telegramDeviceInterfacesMap)) { $telegramDeviceInterfacesMap = []; }
$severityOptions = $telegramSeverityOptions ?? ['info', 'average', 'high', 'disaster'];
$severityOptions = array_values(array_unique(array_map(static fn ($value): string => strtolower(trim((string) $value)), (array) $severityOptions)));
$selectedTelegramSeverities = old('telegram_severities', ['high', 'disaster']);
if (!is_array($selectedTelegramSeverities) || empty($selectedTelegramSeverities)) { $selectedTelegramSeverities = ['high', 'disaster']; }
$selectedTelegramSeverities = array_values(array_unique(array_map(static fn ($value): string => strtolower(trim((string) $value)), $selectedTelegramSeverities)));
$severityOptionLookup = array_fill_keys($severityOptions, true);
$selectedTelegramSeverities = array_values(array_filter(
    $selectedTelegramSeverities,
    static fn ($value): bool => isset($severityOptionLookup[$value])
));
$eventTypeOptions = $telegramEventTypeOptions ?? ['device_down', 'link_down', 'link_up', 'speed_changed', 'device_up'];
$eventTypeOptions = array_values(array_unique(array_map(static fn ($value): string => strtolower(trim((string) $value)), (array) $eventTypeOptions)));
$selectedTelegramEventTypes = old('telegram_event_types', ['device_down', 'link_down']);
if (!is_array($selectedTelegramEventTypes) || empty($selectedTelegramEventTypes)) { $selectedTelegramEventTypes = ['device_down', 'link_down']; }
$selectedTelegramEventTypes = array_values(array_unique(array_map(static fn ($value): string => strtolower(trim((string) $value)), $selectedTelegramEventTypes)));
$eventTypeOptionLookup = array_fill_keys($eventTypeOptions, true);
$selectedTelegramEventTypes = array_values(array_filter(
    $selectedTelegramEventTypes,
    static fn ($value): bool => isset($eventTypeOptionLookup[$value])
));
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
$canViewAssignedDeviceEvents = (bool) old('can_view_assigned_device_events', false);
$assignedDeviceGraphAccessReady = (bool) ($assignedDeviceGraphAccessReady ?? false);
$assignedDeviceEventAccessReady = (bool) ($assignedDeviceEventAccessReady ?? false);
$deviceGraphScopeReady = (bool) ($deviceGraphScopeReady ?? false);
$deviceEventScopeReady = (bool) ($deviceEventScopeReady ?? false);
$deviceEventInterfaceScopeReady = (bool) ($deviceEventInterfaceScopeReady ?? false);
$selectedGraphDeviceIds = old('graph_device_ids', []);
if (!is_array($selectedGraphDeviceIds)) { $selectedGraphDeviceIds = []; }
$selectedGraphDeviceIds = array_values(array_unique(array_map('intval', array_filter($selectedGraphDeviceIds, static fn ($id): bool => is_numeric($id)))));
$selectedEventDeviceIds = old('event_device_ids', []);
if (!is_array($selectedEventDeviceIds)) { $selectedEventDeviceIds = []; }
$selectedEventDeviceIds = array_values(array_unique(array_map('intval', array_filter($selectedEventDeviceIds, static fn ($id): bool => is_numeric($id)))));
$eventInterfaceMap = [];
$oldEventInterfaceMap = old('event_device_interfaces');
if (is_array($oldEventInterfaceMap)) {
    foreach ($oldEventInterfaceMap as $deviceId => $value) {
        if (is_numeric($deviceId)) { $eventInterfaceMap[(int) $deviceId] = trim((string) $value); }
    }
}
$eventInterfaceSelectedLookupMap = [];
foreach ($eventInterfaceMap as $deviceId => $expression) {
    $tokens = preg_split('/\s*,\s*/', trim((string) $expression)) ?: [];
    $lookup = [];
    foreach ($tokens as $token) {
        $token = strtolower(trim((string) $token));
        if ($token !== '') {
            $lookup[$token] = true;
        }
    }
    $eventInterfaceSelectedLookupMap[(int) $deviceId] = $lookup;
}
$graphInterfaceMap = [];
$oldGraphInterfaceMap = old('graph_device_interfaces');
if (is_array($oldGraphInterfaceMap)) {
    foreach ($oldGraphInterfaceMap as $deviceId => $value) {
        if (is_numeric($deviceId)) { $graphInterfaceMap[(int) $deviceId] = trim((string) $value); }
    }
}
$graphInterfaceSelectedLookupMap = [];
foreach ($graphInterfaceMap as $deviceId => $expression) {
    $tokens = preg_split('/\s*,\s*/', trim((string) $expression)) ?: [];
    $lookup = [];
    foreach ($tokens as $token) {
        $token = strtolower(trim((string) $token));
        if ($token !== '') {
            $lookup[$token] = true;
        }
    }
    $graphInterfaceSelectedLookupMap[(int) $deviceId] = $lookup;
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
<details class="group mb-4 rounded-lg border border-slate-200 bg-slate-50/80 p-0 dark:border-slate-700 dark:bg-slate-800/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
<span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-400 text-[11px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
Account Section Help
</span>
<span class="material-symbols-outlined text-[18px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-3 pb-3 pt-2 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
<ol class="list-decimal space-y-1 pl-5">
<li><span class="font-semibold">Username</span> is the login identifier and should be unique.</li>
<li><span class="font-semibold">Role</span>: <code>admin</code> has full access, <code>user</code> follows assigned scopes and permissions below.</li>
<li><span class="font-semibold">Password</span> is required on create; in edit mode, leaving new password empty keeps the current one.</li>
</ol>
</div>
</details>
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
</div>
</div>

<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:bg-slate-900 dark:border-slate-800">
<div class="mb-4">
<p class="text-xs font-bold uppercase tracking-wider text-slate-500">2) Device Scope</p>
<p class="mt-1 text-xs text-slate-400">Assign owned devices first, then grant command-only device access.</p>
</div>
<details class="group mb-4 rounded-lg border border-slate-200 bg-slate-50/80 p-0 dark:border-slate-700 dark:bg-slate-800/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
<span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-400 text-[11px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
Device Scope Help
</span>
<span class="material-symbols-outlined text-[18px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-3 pb-3 pt-2 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
<ol class="list-decimal space-y-1 pl-5">
<li><span class="font-semibold">Assigned Devices</span> are the user-owned devices shown in their main access scope.</li>
<li><span class="font-semibold">Command Device Access</span> grants command execution on selected devices even if not assigned.</li>
<li>Event and graph controls can be enabled separately, with optional per-device and per-interface scope.</li>
<li>When event or graph device lists are empty, access falls back to assigned/permitted devices (based on enabled toggles).</li>
</ol>
</div>
</details>
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

<div class="xl:col-span-2 order-12 flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Device Event Access</label>
@if ($assignedDeviceEventAccessReady)
<label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-200">
<input class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" name="can_view_assigned_device_events" value="1" @checked($canViewAssignedDeviceEvents) />
<span>Enable event visibility for this user account.</span>
</label>
<p class="text-xs text-slate-400">Admins always have event access; this toggle applies to user accounts.</p>
@if ($deviceEventScopeReady)
<div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
<div class="flex flex-col gap-2" data-checkbox-group>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Event Devices</label>
<span class="text-[11px] font-semibold text-slate-500"><span data-checkbox-count>0</span> selected</span>
</div>
<div class="max-h-44 overflow-y-auto rounded-lg border border-slate-300 bg-white p-3 dark:border-slate-700 dark:bg-slate-800 space-y-2">
@foreach ($devices as $device)
<label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
<input class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" name="event_device_ids[]" value="{{ $device->id }}" data-checkbox-item data-event-device-checkbox @checked(in_array((int) $device->id, $selectedEventDeviceIds, true))/>
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
@if ($deviceEventInterfaceScopeReady)
<div class="flex flex-col gap-2" data-device-event-interface-permissions>
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Event Interfaces Per Device (optional)</label>
<div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3 space-y-3 bg-slate-50/70 dark:bg-slate-800/40">
@foreach ($devices as $device)
@php
$deviceId = (int) $device->id;
$eventAllowedInterfaces = trim((string) ($eventInterfaceMap[$deviceId] ?? ''));
$hasEventDevice = in_array($deviceId, $selectedEventDeviceIds, true);
$interfaceOptions = $graphInterfaceOptionsByDevice[$deviceId] ?? [];
$selectedEventInterfaceLookup = $eventInterfaceSelectedLookupMap[$deviceId] ?? [];
@endphp
<div class="{{ $hasEventDevice ? '' : 'hidden' }} rounded-lg border border-slate-200 dark:border-slate-700 p-3 bg-white dark:bg-slate-900" data-device-event-interface-item data-device-id="{{ $deviceId }}">
<div class="text-xs font-semibold text-slate-500 mb-2">{{ $device->name }}@if ($device->serial_number) ({{ $device->serial_number }})@endif</div>
<input type="hidden" name="event_device_interfaces[{{ $deviceId }}]" value="{{ $eventAllowedInterfaces }}" data-event-interface-hidden/>
@if (!empty($interfaceOptions))
<div class="space-y-2">
<div class="flex flex-wrap items-center justify-between gap-2">
<span class="text-[11px] font-semibold text-slate-500"><span data-event-interface-count>0</span> selected</span>
<div class="flex flex-wrap gap-2">
<button class="px-2 py-1 text-[11px] font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-event-interface-action="all">Select all</button>
<button class="px-2 py-1 text-[11px] font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-event-interface-action="none">Clear</button>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-40 overflow-y-auto rounded-lg border border-slate-200 p-2 dark:border-slate-700">
@foreach ($interfaceOptions as $option)
@php
$optionValue = trim((string) ($option['value'] ?? ''));
$optionLabel = trim((string) ($option['label'] ?? $optionValue));
$isChecked = $optionValue !== '' && isset($selectedEventInterfaceLookup[strtolower($optionValue)]);
@endphp
@if ($optionValue !== '')
<label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200">
<input class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" value="{{ $optionValue }}" data-event-interface-option @checked($isChecked)/>
<span>{{ $optionLabel }}</span>
</label>
@endif
@endforeach
</div>
</div>
@else
<p class="text-xs text-slate-400">No discovered interfaces yet for this device.</p>
@if ($eventAllowedInterfaces !== '')
<p class="text-xs text-slate-500">Current saved scope: <code>{{ $eventAllowedInterfaces }}</code></p>
@endif
@endif
</div>
@endforeach
<p class="text-xs text-slate-400 {{ !empty($selectedEventDeviceIds) ? 'hidden' : '' }}" data-device-event-interface-empty>Select one or more event devices to set interface scope.</p>
</div>
<p class="text-xs text-slate-400">Leave blank to allow all interfaces on that device.</p>
</div>
@else
<div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
Run <code>php artisan migrate --force</code> to enable per-device event interface scope controls.
</div>
@endif
</div>
<p class="text-xs text-slate-400">If no event devices are selected, enabled users can view events for all assigned/permitted devices.</p>
@else
<div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
Run <code>php artisan migrate --force</code> to enable per-device event scope controls.
</div>
@endif
@else
<div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
Run <code>php artisan migrate --force</code> to enable user event access toggles.
</div>
@endif
</div>

<div class="xl:col-span-2 order-last flex flex-col gap-2">
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
$interfaceOptions = $graphInterfaceOptionsByDevice[$deviceId] ?? [];
$selectedGraphInterfaceLookup = $graphInterfaceSelectedLookupMap[$deviceId] ?? [];
@endphp
<div class="{{ $hasGraphDevice ? '' : 'hidden' }} rounded-lg border border-slate-200 dark:border-slate-700 p-3 bg-white dark:bg-slate-900" data-device-graph-interface-item data-device-id="{{ $deviceId }}">
<div class="text-xs font-semibold text-slate-500 mb-2">{{ $device->name }}@if ($device->serial_number) ({{ $device->serial_number }})@endif</div>
<input type="hidden" name="graph_device_interfaces[{{ $deviceId }}]" value="{{ $graphAllowedInterfaces }}" data-graph-interface-hidden/>
@if (!empty($interfaceOptions))
<div class="space-y-2">
<div class="flex flex-wrap items-center justify-between gap-2">
<span class="text-[11px] font-semibold text-slate-500"><span data-graph-interface-count>0</span> selected</span>
<div class="flex flex-wrap gap-2">
<button class="px-2 py-1 text-[11px] font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-graph-interface-action="all">Select all</button>
<button class="px-2 py-1 text-[11px] font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-graph-interface-action="none">Clear</button>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-40 overflow-y-auto rounded-lg border border-slate-200 p-2 dark:border-slate-700">
@foreach ($interfaceOptions as $option)
@php
$optionValue = trim((string) ($option['value'] ?? ''));
$optionLabel = trim((string) ($option['label'] ?? $optionValue));
$isChecked = $optionValue !== '' && isset($selectedGraphInterfaceLookup[strtolower($optionValue)]);
@endphp
@if ($optionValue !== '')
<label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200">
<input class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" value="{{ $optionValue }}" data-graph-interface-option @checked($isChecked)/>
<span>{{ $optionLabel }}</span>
</label>
@endif
@endforeach
</div>
</div>
@else
<p class="text-xs text-slate-400">No discovered interfaces yet for this device.</p>
@if ($graphAllowedInterfaces !== '')
<p class="text-xs text-slate-500">Current saved scope: <code>{{ $graphAllowedInterfaces }}</code></p>
@endif
@endif
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

<div class="xl:col-span-2 order-11 flex flex-col gap-2" data-device-port-permissions>
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Port Access Per Device (optional)</label>
<div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3 space-y-3 bg-slate-50/70 dark:bg-slate-800/40">
@foreach ($devices as $device)
@php
$deviceId = (int) $device->id;
$deviceAllowedPorts = trim((string) ($permissionPortMap[$deviceId] ?? ''));
$hasDevicePermission = in_array($deviceId, $selectedPermissionDeviceIds, true);
$interfaceOptions = $graphInterfaceOptionsByDevice[$deviceId] ?? [];
$selectedPortLookup = $permissionPortSelectedLookupMap[$deviceId] ?? [];
@endphp
<div class="{{ $hasDevicePermission ? '' : 'hidden' }} rounded-lg border border-slate-200 dark:border-slate-700 p-3 bg-white dark:bg-slate-900" data-device-port-item data-device-id="{{ $deviceId }}">
<div class="text-xs font-semibold text-slate-500 mb-2">{{ $device->name }}@if ($device->serial_number) ({{ $device->serial_number }})@endif</div>
<input type="hidden" name="device_permission_ports[{{ $deviceId }}]" value="{{ $deviceAllowedPorts }}" data-device-port-hidden/>
@if (!empty($interfaceOptions))
<div class="space-y-2">
<div class="flex flex-wrap items-center justify-between gap-2">
<span class="text-[11px] font-semibold text-slate-500"><span data-device-port-count>0</span> selected</span>
<div class="flex flex-wrap gap-2">
<button class="px-2 py-1 text-[11px] font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-device-port-action="all">Select all</button>
<button class="px-2 py-1 text-[11px] font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-device-port-action="none">Clear</button>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-40 overflow-y-auto rounded-lg border border-slate-200 p-2 dark:border-slate-700">
@foreach ($interfaceOptions as $option)
@php
$optionValue = trim((string) ($option['value'] ?? ''));
$optionLabel = trim((string) ($option['label'] ?? $optionValue));
$isChecked = $optionValue !== '' && isset($selectedPortLookup[strtolower($optionValue)]);
@endphp
@if ($optionValue !== '')
<label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200">
<input class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" value="{{ $optionValue }}" data-device-port-option @checked($isChecked)/>
<span>{{ $optionLabel }}</span>
</label>
@endif
@endforeach
</div>
</div>
@else
<p class="text-xs text-slate-400">No discovered interfaces yet for this device.</p>
@if ($deviceAllowedPorts !== '')
<p class="text-xs text-slate-500">Current saved scope: <code>{{ $deviceAllowedPorts }}</code></p>
@endif
@endif
</div>
@endforeach
<p class="text-xs text-slate-400 {{ !empty($selectedPermissionDeviceIds) ? 'hidden' : '' }}" data-device-port-empty>Select one or more command devices to set port-level access.</p>
</div>
<p class="text-xs text-slate-400">Leave clear to allow all ports on that device.</p>
</div>
<div class="xl:col-span-2 order-9 rounded-lg border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-800/40">
<p class="text-xs font-bold uppercase tracking-wider text-slate-500">Command Access Scope</p>
<p class="mt-1 text-xs text-slate-400">These controls refine access for devices selected in Command Device Access.</p>
</div>
<div class="xl:col-span-2 order-10 flex flex-col gap-2" data-device-command-permissions>
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
<details class="group mb-4 rounded-lg border border-slate-200 bg-slate-50/80 p-0 dark:border-slate-700 dark:bg-slate-800/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
<span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-400 text-[11px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
Command Permissions Help
</span>
<span class="material-symbols-outlined text-[18px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-3 pb-3 pt-2 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
<ol class="list-decimal space-y-1 pl-5">
<li>Select only the command templates this user is allowed to run.</li>
<li>Use <span class="font-semibold">Select all</span> and <span class="font-semibold">Clear</span> for quick bulk changes.</li>
<li>Choose <code>Custom Command</code> to create a new permission from script name and script code.</li>
<li>Per-device command scope (in Device Scope section) can further narrow this global command list.</li>
</ol>
</div>
</details>
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
<details class="group rounded-lg border border-slate-200 bg-slate-50/80 p-0 dark:border-slate-700 dark:bg-slate-800/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
<span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-400 text-[11px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
Telegram Setup Help
</span>
<span class="material-symbols-outlined text-[18px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-3 pb-3 pt-2 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
<p class="font-semibold text-slate-700 dark:text-slate-200">How to get your Telegram bot token and chat ID</p>
<ol class="mt-2 list-decimal space-y-1 pl-5">
<li>Open Telegram and start a chat with <code>@BotFather</code>.</li>
<li>Send <code>/newbot</code>, complete bot name and username, then copy the token BotFather returns.</li>
<li>Open your bot chat and send any message (for groups/channels, add the bot and send a message there).</li>
<li>Open <code>https://api.telegram.org/bot&lt;YOUR_BOT_TOKEN&gt;/getUpdates</code>.</li>
<li>Find <code>chat.id</code> in the response: private chats use positive IDs, groups/channels use negative IDs (usually <code>-100...</code>).</li>
</ol>
<p class="mt-2">Paste <span class="font-semibold">chat ID</span> below. You can leave bot token empty to use the global <code>TELEGRAM_BOT_TOKEN</code>.</p>
</div>
</details>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<input type="hidden" name="telegram_severities_present" value="1" />
<input type="hidden" name="telegram_event_types_present" value="1" />
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Telegram Chat ID</label>
<input class="h-11 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:border-primary focus:ring-primary" name="telegram_chat_id" type="text" value="{{ old('telegram_chat_id') }}" placeholder="123456789 or -1001234567890"/>
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
@if ($telegramDeviceInterfaceScopeReady)
<div class="md:col-span-2 flex flex-col gap-2" data-telegram-device-interface-permissions>
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Telegram Interfaces Per Device</label>
<div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3 space-y-3 bg-slate-50/70 dark:bg-slate-800/40">
@foreach ($devices as $device)
@php
$deviceId = (int) $device->id;
$hasTelegramDevice = in_array($deviceId, $selectedTelegramDevices, true);
$telegramInterfaceOptions = $graphInterfaceOptionsByDevice[$deviceId] ?? [];
$telegramInterfaceValuesRaw = $telegramDeviceInterfacesMap[$deviceId] ?? null;
$explicitTelegramInterfaceScope = false;
$selectedTelegramInterfaceLookup = [];
$selectedTelegramInterfaceValues = [];
$telegramInterfaceValueTokens = [];
if (is_array($telegramInterfaceValuesRaw)) {
    $telegramInterfaceValueTokens = $telegramInterfaceValuesRaw;
} elseif (is_string($telegramInterfaceValuesRaw)) {
    $telegramInterfaceValueTokens = preg_split('/\s*,\s*/', $telegramInterfaceValuesRaw) ?: [];
}
foreach ($telegramInterfaceValueTokens as $interfaceValueRaw) {
    $interfaceValue = trim((string) $interfaceValueRaw);
    $interfaceKey = strtolower($interfaceValue);
    if ($interfaceKey === '') { continue; }
    if (!isset($selectedTelegramInterfaceLookup[$interfaceKey])) {
        $selectedTelegramInterfaceLookup[$interfaceKey] = true;
        $selectedTelegramInterfaceValues[] = $interfaceValue;
    }
}
 $explicitTelegramInterfaceScope = !empty($selectedTelegramInterfaceLookup);
if (!$explicitTelegramInterfaceScope && $hasTelegramDevice) {
    foreach ($telegramInterfaceOptions as $telegramInterfaceOption) {
        $interfaceValue = trim((string) ($telegramInterfaceOption['value'] ?? ''));
        $interfaceKey = strtolower($interfaceValue);
        if ($interfaceKey !== '' && !isset($selectedTelegramInterfaceLookup[$interfaceKey])) {
            $selectedTelegramInterfaceLookup[$interfaceKey] = true;
            $selectedTelegramInterfaceValues[] = $interfaceValue;
        }
    }
}
 $telegramInterfaceHiddenValue = implode(',', $selectedTelegramInterfaceValues);
@endphp
<div class="{{ $hasTelegramDevice ? '' : 'hidden' }} rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" data-telegram-device-interface-item data-device-id="{{ $deviceId }}" data-default-select-all="{{ $explicitTelegramInterfaceScope ? '0' : '1' }}">
<details class="group" data-telegram-device-interface-panel>
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
<div class="min-w-0">
<p class="truncate text-xs font-semibold text-slate-500">{{ $device->name }}@if ($device->serial_number) ({{ $device->serial_number }})@endif</p>
@if (!empty($telegramInterfaceOptions))
<p class="mt-1 text-[11px] font-semibold text-slate-500"><span data-telegram-device-interface-count>0</span> selected</p>
@else
<p class="mt-1 text-[11px] text-slate-400">No discovered interfaces yet for this device.</p>
@endif
</div>
<span class="material-symbols-outlined text-[18px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
@if (!empty($telegramInterfaceOptions))
<div class="space-y-2 border-t border-slate-200 px-3 pb-3 pt-2 dark:border-slate-700">
<input type="hidden" name="telegram_device_interfaces[{{ $deviceId }}]" value="{{ $telegramInterfaceHiddenValue }}" data-telegram-device-interface-hidden />
<div class="flex flex-wrap items-center justify-end gap-2">
<button class="px-2 py-1 text-[11px] font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-telegram-device-interface-action="all">Select all</button>
<button class="px-2 py-1 text-[11px] font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-telegram-device-interface-action="none">Clear</button>
</div>
<div class="grid grid-cols-1 gap-2 max-h-40 overflow-y-auto rounded-lg border border-slate-200 p-2 dark:border-slate-700 md:grid-cols-2">
@foreach ($telegramInterfaceOptions as $telegramInterfaceOption)
@php
$telegramInterfaceValue = trim((string) ($telegramInterfaceOption['value'] ?? ''));
$telegramInterfaceLabel = trim((string) ($telegramInterfaceOption['label'] ?? $telegramInterfaceValue));
$telegramInterfaceChecked = $telegramInterfaceValue !== '' && isset($selectedTelegramInterfaceLookup[strtolower($telegramInterfaceValue)]);
@endphp
@if ($telegramInterfaceValue !== '')
<label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200">
<input class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" value="{{ $telegramInterfaceValue }}" data-telegram-device-interface-option @checked($telegramInterfaceChecked)/>
<span>{{ $telegramInterfaceLabel }}</span>
</label>
@endif
@endforeach
</div>
</div>
@endif
</details>
</div>
@endforeach
<p class="text-xs text-slate-400 {{ !empty($selectedTelegramDevices) ? 'hidden' : '' }}" data-telegram-device-interface-empty>Select one or more Telegram devices to scope interfaces.</p>
</div>
<p class="text-xs text-slate-400">When a Telegram device is selected, all discovered interfaces start selected by default.</p>
</div>
@else
<div class="md:col-span-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
Run <code>php artisan migrate --force</code> to enable Telegram per-device interface scope controls.
</div>
@endif
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
<p class="text-xs text-slate-400">Global custom tags and message templates are managed in Settings.</p>
</div>
</div>
</div>

<div class="mt-4 flex flex-wrap items-center justify-end gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:bg-slate-900 dark:border-slate-800">
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
</body>
</html>

