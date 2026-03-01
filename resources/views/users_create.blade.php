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
<div class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-950/40">
<div class="flex flex-col gap-4 md:flex-row md:items-center">
@include('partials.user_avatar', ['user' => null, 'name' => old('username', 'New User'), 'sizeClass' => 'h-16 w-16', 'textClass' => 'text-lg'])
<div class="min-w-0 flex-1 flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="avatar">Profile Picture</label>
<input id="avatar" class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-primary file:px-4 file:py-2 file:font-semibold file:text-white hover:file:bg-primary/90 dark:text-slate-300" name="avatar" type="file" accept="image/png,image/jpeg,image/webp,image/gif"/>
<p class="text-xs text-slate-400">Optional. Upload a PNG, JPG, WEBP, or GIF up to 2 MB.</p>
</div>
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
<div class="flex flex-col gap-2" data-multi-select>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Assigned Devices</label>
<span class="text-[11px] font-semibold text-slate-500"><span data-selected-count>0</span> selected</span>
</div>
<select class="h-32 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:border-primary focus:ring-primary" multiple name="device_ids[]" data-multi-select-input>
@foreach ($devices as $device)
<option value="{{ $device->id }}" @selected(in_array((int) $device->id, $assignedDeviceIds, true))>{{ $device->name }}@if ($device->serial_number) ({{ $device->serial_number }})@endif</option>
@endforeach
</select>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-select-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-select-action="none">Clear</button>
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-select-action="invert">Invert</button>
</div>
</div>

<div class="flex flex-col gap-2" data-multi-select>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Command Device Access</label>
<span class="text-[11px] font-semibold text-slate-500"><span data-selected-count>0</span> selected</span>
</div>
<select class="h-32 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:border-primary focus:ring-primary" multiple name="device_permission_ids[]" data-device-permission-select data-multi-select-input>
@foreach ($devices as $device)
<option value="{{ $device->id }}" @selected(in_array((int) $device->id, $selectedPermissionDeviceIds, true))>{{ $device->name }}@if ($device->serial_number) ({{ $device->serial_number }})@endif</option>
@endforeach
</select>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-select-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-select-action="none">Clear</button>
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-select-action="invert">Invert</button>
</div>
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
<div class="flex flex-col gap-2" data-multi-select>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Telegram Devices</label>
<span class="text-[11px] font-semibold text-slate-500"><span data-selected-count>0</span> selected</span>
</div>
<select class="h-32 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:border-primary focus:ring-primary" multiple name="telegram_devices[]" data-multi-select-input>
@foreach ($devices as $device)
<option value="{{ $device->id }}" @selected(in_array((int) $device->id, $selectedTelegramDevices, true))>{{ $device->name }}@if ($device->serial_number) ({{ $device->serial_number }})@endif</option>
@endforeach
</select>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-select-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-select-action="none">Clear</button>
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
<div class="flex flex-col gap-2" data-multi-select>
<div class="flex items-center justify-between gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Telegram Event Types</label>
<span class="text-[11px] font-semibold text-slate-500"><span data-selected-count>0</span> selected</span>
</div>
<select class="h-32 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:border-primary focus:ring-primary" multiple name="telegram_event_types[]" data-multi-select-input>
@foreach ($eventTypeOptions as $eventTypeOption)
<option value="{{ $eventTypeOption }}" @selected(in_array(strtolower((string) $eventTypeOption), $selectedTelegramEventTypes, true))>{{ $eventTypeOption }}</option>
@endforeach
</select>
<div class="flex flex-wrap gap-2">
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-select-action="all">Select all</button>
<button class="px-2.5 py-1 text-xs font-semibold border border-slate-300 rounded-lg hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800" type="button" data-no-dispatch="true" data-select-action="none">Clear</button>
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
</body>
</html>
