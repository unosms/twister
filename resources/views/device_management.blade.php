<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/><meta name="csrf-token" content="{{ csrf_token() }}"/><meta name="app-base" content="{{ url('/') }}"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Device Management &amp; Telemetry</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#135bec",
                        "background-light": "#f6f6f8",
                        "background-dark": "#101622",
                    },
                    fontFamily: {
                        "display": ["Inter"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        body { font-family: 'Inter', sans-serif; }
        details > summary { list-style: none; }
        details > summary::-webkit-details-marker { display: none; }
    </style>
    @include('partials.admin_sidebar_styles')
    <script src="{{ asset('js/actions.js') . '?v=' . filemtime(public_path('js/actions.js')) }}" defer></script></head>
<body class="bg-background-light dark:bg-background-dark text-[#0d121b] dark:text-gray-100 h-screen overflow-hidden">
<div class="flex h-screen overflow-hidden">
<!-- Side Navigation -->
@include('partials.admin_sidebar')
<!-- Main Content Area -->
<main class="flex-1 flex flex-col min-h-0 overflow-y-auto">
<!-- Top Navbar -->
<header class="h-16 border-b border-[#e7ebf3] dark:border-gray-800 bg-white dark:bg-background-dark flex items-center justify-between px-8 shrink-0">
<div class="flex items-center gap-3 flex-1">
<button class="h-10 w-10 flex items-center justify-center rounded-lg border border-[#e7ebf3] dark:border-gray-800 bg-white dark:bg-background-dark hover:bg-gray-50 dark:hover:bg-gray-800" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
<span class="material-symbols-outlined text-gray-500">menu</span>
</button>
<div class="relative w-full max-w-md">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xl">search</span>
<input class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-lg pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-primary/20" placeholder="Search devices by name, ID or location..." type="text" data-live-search data-live-search-target="[data-device-table-row]"/>
</div>
</div>
<div class="flex items-center gap-4">
<div class="relative">
<button class="relative p-2 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg" type="button" data-no-dispatch="true" data-notifications-menu-button data-notifications-endpoint="{{ route('notifications.menu') }}">
<span class="material-symbols-outlined">notifications</span>
<span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-red-500 hidden" data-notifications-indicator></span>
</button>
@include('partials.notifications_menu')
</div>
<a class="p-2 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg" href="{{ route('support.index') }}">
<span class="material-symbols-outlined">help_outline</span>
</a>
</div>
</header>
<div class="flex-1 flex" data-device-layout="split">
<!-- Table Section -->
<section class="flex-1 flex flex-col p-8">
<!-- Page Heading -->
<div class="flex flex-wrap justify-between items-end gap-3 mb-8">
<div class="flex flex-col gap-1">
<h2 class="text-3xl font-bold tracking-tight">Devices</h2>
<p class="text-gray-500 text-sm">
Manage and monitor {{ $totalDevices ?? 0 }} total devices
(<span class="text-green-600 font-medium">{{ $activeDevices ?? 0 }} active</span>)
</p>
</div>
<div class="flex gap-3">
<form method="POST" action="{{ route('devices.export') }}">
@csrf
<button class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-[#cfd7e7] dark:border-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50" type="submit">
<span class="material-symbols-outlined text-xl">file_download</span>
Export
</button>
</form>
<a class="flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold shadow-sm hover:bg-primary/90" href="{{ route('devices.create') }}">
<span class="material-symbols-outlined text-xl">add</span>
                                Add Device
                            </a>
</div>
</div>
<!-- Filters -->
<form class="flex gap-3 mb-6 flex-wrap items-center" method="GET" action="{{ route('devices.index') }}">
<div class="relative">
<select class="flex items-center gap-2 px-3 py-1.5 bg-white dark:bg-gray-800 border border-[#cfd7e7] dark:border-gray-700 rounded-lg text-sm font-medium appearance-none pr-8" name="status" onchange="this.form.submit()">
<option value="all" @selected(($filters['status'] ?? 'all') === 'all')>Status: All</option>
<option value="online" @selected(($filters['status'] ?? '') === 'online')>Status: Online</option>
<option value="warning" @selected(($filters['status'] ?? '') === 'warning')>Status: Warning</option>
<option value="error" @selected(($filters['status'] ?? '') === 'error')>Status: Error</option>
<option value="offline" @selected(($filters['status'] ?? '') === 'offline')>Status: Offline</option>
</select>
<span class="material-symbols-outlined text-lg absolute right-2 top-1/2 -translate-y-1/2 text-gray-400">expand_more</span>
</div>
<div class="relative">
<select class="flex items-center gap-2 px-3 py-1.5 bg-white dark:bg-gray-800 border border-[#cfd7e7] dark:border-gray-700 rounded-lg text-sm font-medium appearance-none pr-8" name="type" onchange="this.form.submit()">
<option value="all" @selected(($filters['type'] ?? 'all') === 'all')>Type: All</option>
<option value="CISCO" @selected(($filters['type'] ?? '') === 'CISCO')>Type: CISCO</option>
<option value="MIMOSA" @selected(($filters['type'] ?? '') === 'MIMOSA')>Type: MIMOSA</option>
<option value="OLT" @selected(($filters['type'] ?? '') === 'OLT')>Type: OLT</option>
<option value="SERVER" @selected(($filters['type'] ?? '') === 'SERVER')>Type: SERVER</option>
<option value="MIKROTIK" @selected(($filters['type'] ?? '') === 'MIKROTIK')>Type: MIKROTIK</option>
</select>
<span class="material-symbols-outlined text-lg absolute right-2 top-1/2 -translate-y-1/2 text-gray-400">expand_more</span>
</div>
<div class="relative">
<select class="flex items-center gap-2 px-3 py-1.5 bg-white dark:bg-gray-800 border border-[#cfd7e7] dark:border-gray-700 rounded-lg text-sm font-medium appearance-none pr-8" name="firmware" onchange="this.form.submit()">
<option value="all" @selected(($filters['firmware'] ?? 'all') === 'all')>Firmware: All</option>
@foreach (($firmwareOptions ?? []) as $firmwareOption)
<option value="{{ $firmwareOption }}" @selected(($filters['firmware'] ?? '') === $firmwareOption)>Firmware {{ $firmwareOption }}</option>
@endforeach
</select>
<span class="material-symbols-outlined text-lg absolute right-2 top-1/2 -translate-y-1/2 text-gray-400">expand_more</span>
</div>
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">search</span>
<input class="pl-10 pr-4 py-2 bg-white dark:bg-gray-800 border border-[#cfd7e7] dark:border-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-primary/50" name="search" placeholder="Search devices" type="text" value="{{ $filters['search'] ?? '' }}"/>
</div>
<button class="text-sm font-semibold text-primary" type="submit">Apply filters</button>
<a class="text-sm font-semibold text-slate-500 hover:text-primary" href="{{ route('devices.index') }}">Clear all filters</a>
</form>
@if (session('status'))
<div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
{{ session('status') }}
</div>
@endif
@if ($errors->has('backup_permissions'))
<div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
<p class="font-semibold mb-1">Backup permission action completed with warnings:</p>
<p>{{ $errors->first('backup_permissions') }}</p>
</div>
@endif
@php
$deviceErrorMessages = collect($errors->getMessages())
    ->except('backup_permissions')
    ->flatten();
@endphp
@if ($deviceErrorMessages->isNotEmpty())
<div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
<p class="font-semibold mb-1">Could not save device. Please fix the following:</p>
<ul class="list-disc pl-5 space-y-0.5">
@foreach ($deviceErrorMessages as $error)
<li>{{ $error }}</li>
@endforeach
</ul>
</div>
@endif
@php
$serverServiceOptions = [
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
$serverServiceLabels = [
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
$serverWebCredentialServices = ['web', 'log', 'middleware', 'radius', 'vertiofiber', 'netplay', 'hls_restream', 'xtream', 'voip', 'stock_management', 'crm', 'vmware'];
$serverWebAddressServices = array_values(array_unique(array_merge($serverWebCredentialServices, ['speedtest'])));
$serverServiceFieldOptions = array_values(array_filter(
    $serverServiceOptions,
    static fn ($service) => in_array($service, array_merge($serverWebAddressServices, ['vnc']), true)
));
@endphp
@if (false)
@php
$serverServiceOptions = [
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
$oldServerServices = old('server_service', []);
if (!is_array($oldServerServices)) {
    $oldServerServices = [$oldServerServices];
}
$oldServerServices = array_values(array_filter(array_map(
    static fn ($service) => is_scalar($service)
        ? str_replace('netplat', 'netplay', strtolower(trim((string) $service)))
        : null,
    $oldServerServices
), static fn ($service) => $service !== null && $service !== ''));
@endphp
<!-- Add Device Form -->
<details id="add-device-form" class="bg-white dark:bg-gray-900 border border-[#cfd7e7] dark:border-gray-800 rounded-xl p-6 shadow-sm mb-6 group">
<summary class="flex items-center justify-between cursor-pointer select-none">
<span class="text-lg font-bold">Add Device</span>
<span class="material-symbols-outlined text-xl transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="pt-4">
<form class="space-y-6" method="POST" action="{{ route('devices.store') }}">
@csrf
<div class="grid grid-cols-1 gap-4">
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Device Type</label>
<select class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="type" required data-device-type>
<option value="CISCO">CISCO</option>
<option value="MIMOSA">MIMOSA</option>
<option value="OLT">OLT</option>
<option value="SERVER">SERVER</option>
<option value="MIKROTIK">MIKROTIK</option>
</select>
</div>
</div>
<div class="border-t border-[#e7ebf3] dark:border-gray-800 pt-6 hidden" data-cisco-fields>
<div class="flex flex-col gap-1">
<div class="flex flex-col gap-1">
<h4 class="text-base font-bold">Register Cisco</h4>
<p class="text-xs text-gray-500">Fill switch identity, connectivity, and optional automation settings.</p>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
<div class="flex flex-col gap-2 order-last">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Port</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_port" placeholder="161" type="number" min="1" max="65535" disabled/>
</div>
<div class="flex flex-col gap-2 order-last">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Version</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_version" type="text" value="2c"/>
</div>
<div class="flex flex-col gap-2 order-last">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Community</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_community" placeholder="e.g., public" type="text"/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">CISCO Name *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="cisco_name" placeholder="e.g. core-switch-01" type="text" data-cisco-name data-cisco-required/>
<p class="text-[11px] text-gray-400">Used to auto-fill Backup/Exec URLs and Location.</p>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Switch Model *</label>
<select class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="switch_model" data-cisco-required>
<option value="">--Select Model--</option>
<option value="Nexus">Nexus</option>
<option value="4948">4948</option>
<option value="3560">3560</option>
<option value="Other">Other</option>
</select>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">IP Address *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="ip_address" placeholder="e.g., 192.168.1.10" type="text" data-cisco-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Cisco Username</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="cisco_username" placeholder="e.g., admin" type="text"/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Password *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="cisco_password" placeholder="********" type="password" data-cisco-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Temp Poll Minutes (default 1)</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="temp_poll_minutes" type="number" min="1" max="1440" value="1"/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Enable Password</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="enable_password" placeholder="********" type="password"/>
</div>
<div class="flex flex-col gap-2 md:col-span-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Folder Location</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="folder_location" placeholder="uno/<switch_name>" type="text" data-cisco-folder/>
<p class="text-[11px] text-gray-400">Default: uno/&lt;switch_name&gt;</p>
</div>
</div>
</div>
<div class="border-t border-[#e7ebf3] dark:border-gray-800 pt-6 hidden" data-mimosa-fields>
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div class="flex flex-col gap-1">
      <h4 class="text-base font-bold">Register Mimosa</h4>
      <p class="text-xs text-gray-500">Select a model, then fill only the model-specific fields below.</p>
    </div>
    <div class="flex items-center gap-2">
      <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Model</label>
      <select class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-10 text-sm" name="mimosa_model" data-mimosa-model>
        <option value="C5C">C5C</option>
        <option value="C5X">C5X</option>
        <option value="B11">B11</option>
      </select>
    </div>
  </div>
  <div class="mt-5 space-y-6">
    <div class="hidden" data-mimosa-form="C5C">
      <h5 class="text-lg font-bold text-primary underline">Admin Panel - Add C5C Device</h5>
      <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Add C5C Device (C5C)</p>
      <div class="mt-4 grid grid-cols-1 gap-4">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5C Name:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_name" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5C IP:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_ip" placeholder="e.g., 192.168.1.10" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5C Password:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_password" type="password"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5C MAC Address:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_mac_address" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5C URL:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_url" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5C Station:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_station" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5C Switch ID:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_switch_id" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5C Switch Port:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_switch_port" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5C VLAN:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_vlan" type="text"/>
        </div>
      </div>
    </div>
    <div class="hidden" data-mimosa-form="C5X">
      <h5 class="text-lg font-bold text-primary underline">Admin Panel - Add C5X Device</h5>
      <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Add C5X Device (C5X)</p>
      <div class="mt-4 grid grid-cols-1 gap-4">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5X Name:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_name" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5X IP:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_ip" placeholder="e.g., 192.168.1.10" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5X Password:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_password" type="password"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5X MAC Address:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_mac_address" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5X URL:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_url" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5X Station:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_station" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5X Switch ID:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_switch_id" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5X Switch Port:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_switch_port" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5X VLAN:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_vlan" type="text"/>
        </div>
      </div>
    </div>
    <div class="hidden" data-mimosa-form="B11">
      <h5 class="text-lg font-bold text-primary underline">Admin Panel - Add B11 Device</h5>
      <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Add B11 Device (B11)</p>
      <div class="mt-4 grid grid-cols-1 gap-4">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">B11 Name:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_name" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">B11 IP:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_ip" placeholder="e.g., 192.168.1.10" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">B11 Password:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_password" type="password"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">B11 MAC Address:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_mac_address" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">B11 URL:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_url" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">B11 Station:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_station" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">B11 Switch ID:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_switch_id" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">B11 Switch Port:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_switch_port" type="text"/>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">B11 VLAN:</label>
          <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_vlan" type="text"/>
        </div>
      </div>
    </div>
</div>
  <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="flex flex-col gap-2 md:col-span-2">
      <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Port</label>
      <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_port" placeholder="161" type="number" min="1" max="65535" disabled/>
    </div>
    <div class="flex flex-col gap-2 md:col-span-2">
      <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Community</label>
      <input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_community" placeholder="e.g., public" type="text" disabled/>
    </div>
  </div>
</div>
<div class="border-t border-[#e7ebf3] dark:border-gray-800 pt-6 hidden" data-server-fields>
<div class="flex flex-col gap-1">
<h4 class="text-base font-bold">Register Server</h4>
<p class="text-xs text-gray-500">Start with server role and specs, then fill access and network details.</p>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
<div class="flex flex-col gap-2 order-last">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Port *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_port" type="number" min="1" max="65535" placeholder="161" data-server-required disabled/>
</div>
<div class="flex flex-col gap-2 order-last">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Community *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_community" placeholder="e.g., public" type="text" data-server-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Server Type *</label>
<select class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_type" data-server-type data-server-required disabled>
<option value="virtual_server">Virtual Server</option>
<option value="stand_alone_server">Stand Alone Server</option>
</select>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Hardware Specs *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_hardware_specs" placeholder="e.g., 8 vCPU, 32GB RAM, 500GB NVMe" type="text" data-server-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Server Name *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_name" placeholder="e.g., app-node-01" type="text" data-server-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Services *</label>
<div class="rounded-lg border border-[#cfd7e7] dark:border-gray-700 bg-white dark:bg-gray-800 p-3 max-h-44 overflow-y-auto space-y-2" data-server-service>
@foreach ($serverServiceOptions as $serviceOption)
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
<input class="rounded border-[#cfd7e7] text-primary focus:ring-primary" type="checkbox" name="server_service[]" value="{{ $serviceOption }}" data-server-service-option @checked(in_array($serviceOption, $oldServerServices, true)) disabled/>
<span>{{ $serviceOption === 'vnc' ? 'VNC' : ucwords(str_replace('_', ' ', $serviceOption)) }}</span>
</label>
@endforeach
</div>
<p class="text-[11px] text-gray-400">Select one or more services.</p>
</div>
<div class="flex flex-col gap-2 hidden" data-server-vnc-field>
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">VNC Address and Port *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_vnc_address_port" placeholder="e.g., 192.168.1.20:5900" type="text" data-server-vnc-required disabled/>
</div>
<div class="flex flex-col gap-2 hidden" data-server-vnc-field>
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">VNC Password *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_vnc_password" placeholder="********" type="password" data-server-vnc-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Server Web Address and Port *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_web_address_port" placeholder="e.g., https://example.com:8080" type="text" data-server-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Server Web Username</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_web_username" placeholder="e.g., admin" type="text" disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Server Web Password</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_web_password" placeholder="********" type="password" disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Server SSH Port *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_ssh_port" type="number" min="1" max="65535" placeholder="22" data-server-required disabled/>
</div>
<div class="flex flex-col gap-2 hidden" data-server-standalone-field>
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Cabinet ID *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_cabinet_id" type="text" placeholder="e.g., CAB-01" data-server-standalone-required disabled/>
</div>
<div class="flex flex-col gap-2 hidden" data-server-standalone-field>
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Rack UID *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_rack_uid" type="text" placeholder="e.g., RACK-U12" data-server-standalone-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">IP Address *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="ip_address" placeholder="e.g., 192.168.1.10" type="text" data-server-required disabled/>
</div>
</div>
</div>
<div class="border-t border-[#e7ebf3] dark:border-gray-800 pt-6 hidden" data-olt-fields>
<div class="flex flex-col gap-1">
<h4 class="text-base font-bold">Register OLT</h4>
<p class="text-xs text-gray-500">Add OLT identity, capacity, and access details.</p>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Device Name *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="name" placeholder="e.g. OLT-01" type="text" data-olt-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">OLT Type *</label>
<select class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_device_type" data-olt-required disabled>
<option value="HUAWEI" selected>Huawei</option>
<option value="VSOL">VSOL</option>
<option value="HIOSO">Hioso</option>
</select>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Model *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_model" placeholder="e.g., MA5800-X17" type="text" data-olt-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Number of Ports *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_number_of_ports" type="number" min="1" max="4096" placeholder="e.g., 16" data-olt-required disabled/>
</div>
<div class="flex flex-col gap-2 order-last">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Port</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_port" placeholder="161" type="number" min="1" max="65535" disabled/>
</div>
<div class="flex flex-col gap-2 order-last">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Community *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_snmp_community" placeholder="e.g., public" type="text" data-olt-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">IP *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_ip_address" placeholder="e.g., 192.168.1.20" type="text" data-olt-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Username *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_username" placeholder="e.g., admin" type="text" data-olt-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Password *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_password" placeholder="********" type="password" data-olt-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Web Address *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_web_address" placeholder="e.g., http://192.168.1.20" type="text" data-olt-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Folder Location</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_folder_location" placeholder="e.g., uno/OLT-01" type="text" disabled/>
</div>
</div>
</div>
<div class="border-t border-[#e7ebf3] dark:border-gray-800 pt-6 hidden" data-mikrotik-fields>
<div class="flex flex-col gap-1">
<h4 class="text-base font-bold">Register MikroTik</h4>
<p class="text-xs text-gray-500">Add MikroTik connectivity, access, and rack details.</p>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Device Name *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="name" placeholder="e.g. mikrotik-01" type="text" data-mikrotik-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">IP *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_ip_address" placeholder="e.g., 192.168.88.1" type="text" data-mikrotik-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Username *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_username" placeholder="e.g., admin" type="text" data-mikrotik-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Password *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_password" placeholder="********" type="password" data-mikrotik-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Location *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_location" placeholder="e.g., POP-A / Floor 2" type="text" data-mikrotik-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Cabinet ID *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_cabinet_id" placeholder="e.g., CAB-03" type="text" data-mikrotik-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Rack UID *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_rack_uid" placeholder="e.g., U18" type="text" data-mikrotik-required disabled/>
</div>
<div class="flex flex-col gap-2 order-last">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Community *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_snmp_community" placeholder="e.g., public" type="text" data-mikrotik-required disabled/>
</div>
<div class="flex flex-col gap-2 order-last">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Port *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_snmp_port" type="number" min="1" max="65535" placeholder="161" data-mikrotik-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Winbox Port *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_winbox_port" type="number" min="1" max="65535" placeholder="8291" data-mikrotik-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SSH Port *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_ssh_port" type="number" min="1" max="65535" placeholder="22" data-mikrotik-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Telnet Port *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_telnet_port" type="number" min="1" max="65535" placeholder="23" data-mikrotik-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">API Port *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_api_port" type="number" min="1" max="65535" placeholder="8728" data-mikrotik-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">API SSL Port *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_api_ssl_port" type="number" min="1" max="65535" placeholder="8729" data-mikrotik-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">FTP Port *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_ftp_port" type="number" min="1" max="65535" placeholder="21" data-mikrotik-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">HTTP Port *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_http_port" type="number" min="1" max="65535" placeholder="80" data-mikrotik-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Web Address *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_web_address" placeholder="e.g., http://router.local:80" type="text" data-mikrotik-required disabled/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Winbox Address *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_winbox_address" placeholder="e.g., 192.168.88.1:8291" type="text" data-mikrotik-required disabled/>
</div>
</div>
</div>
<div class="flex justify-end">
<button class="px-6 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary/90" type="submit">Create Device</button>
</div>
</form>
</div>
</details>
@endif
<!-- Table -->
<div class="bg-white dark:bg-gray-900 border border-[#cfd7e7] dark:border-gray-800 rounded-xl overflow-hidden shadow-sm">
<table class="w-full text-left border-collapse">
<thead class="bg-gray-50 dark:bg-gray-800/50 border-b border-[#cfd7e7] dark:border-gray-800">
<tr>
<th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Device Name</th>
<th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">ID</th>
<th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Type</th>
<th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
<th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Assigned To</th>
<th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Last Seen</th>
<th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-[#cfd7e7] dark:divide-gray-800">
@forelse ($devices as $device)
@php
$meta = $device->metadata ?? [];
$monitoringDisabled = (bool) data_get($meta, 'monitoring_disabled', false);
$status = strtolower($device->status ?? 'offline');
$statusClass = match ($status) {
    'online' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    'error' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
    default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400',
};
$isSelected = isset($selectedDevice) && $selectedDevice && $selectedDevice->id === $device->id;
@endphp
<tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 cursor-pointer {{ $isSelected ? 'bg-primary/5' : '' }}" data-device-link="{{ route('devices.index', ['device' => $device->id]) }}" data-device-row data-device-id="{{ $device->id }}" data-device-table-row tabindex="0" aria-label="View {{ $device->name }} details">
<td class="px-6 py-4">
<div class="flex items-center gap-3">
<span class="material-symbols-outlined {{ $status === 'online' ? 'text-primary' : 'text-gray-400' }}">hub</span>
<a class="font-semibold text-sm hover:text-primary" href="{{ route('devices.index', ['device' => $device->id]) }}">{{ $device->name }}</a>
</div>
</td>
<td class="px-6 py-4 font-mono text-xs text-gray-500">{{ $device->id }}</td>
<td class="px-6 py-4 text-sm">{{ $device->type ?? 'N/A' }}</td>
<td class="px-6 py-4">
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}" data-status-badge data-status-base="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" data-status-online="bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400" data-status-warning="bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400" data-status-error="bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400" data-status-offline="bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400">
<span data-status-text>{{ ucfirst($status) }}</span>
</span>
</td>
<td class="px-6 py-4">
@if ($device->assignedUser)
<div class="flex items-center gap-2">
<div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-[10px] font-bold text-blue-700">
{{ strtoupper(substr($device->assignedUser->name, 0, 2)) }}
</div>
<span class="text-sm">{{ $device->assignedUser->name }}</span>
</div>
@else
<span class="text-sm text-gray-500">Unassigned</span>
@endif
</td>
<td class="px-6 py-4 text-sm text-gray-500">
<span data-last-seen>{{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : '-' }}</span>
</td>
<td class="px-6 py-4 text-right">
<div class="flex items-center justify-end flex-wrap gap-2">
<a class="px-2 py-1 text-xs font-semibold text-slate-600 border border-slate-200 rounded hover:bg-slate-50" href="{{ route('devices.index', ['device' => $device->id]) }}">View</a>
<button class="px-2 py-1 text-xs font-semibold text-amber-700 border border-amber-200 rounded hover:bg-amber-50" type="button" data-no-dispatch="true" data-device-edit="{{ $device->id }}">Edit</button>
<form class="flex items-center gap-2" method="POST" action="{{ route('devices.assign') }}">
@csrf
<input type="hidden" name="device_id" value="{{ $device->id }}"/>
<select class="text-xs rounded border-gray-200 px-2 py-1" name="user_id">
@foreach ($users as $user)
<option value="{{ $user->id }}" @selected($device->assigned_user_id === $user->id)>
{{ $user->name }}
</option>
@endforeach
</select>
<button class="px-2 py-1 text-xs font-semibold text-primary border border-primary/30 rounded hover:bg-primary/5" type="submit">Assign</button>
</form>
@if ($monitoringDisabled)
<form method="POST" action="{{ route('devices.activate', ['device' => $device->id]) }}">
@csrf
<button class="px-2 py-1 text-xs font-semibold text-green-700 border border-green-200 rounded hover:bg-green-50" type="submit">Activate</button>
</form>
@else
<form method="POST" action="{{ route('devices.deactivate', ['device' => $device->id]) }}">
@csrf
<button class="px-2 py-1 text-xs font-semibold text-gray-600 border border-gray-200 rounded hover:bg-gray-50" type="submit">Deactivate</button>
</form>
@endif
<form method="POST" action="{{ route('devices.refresh', ['device' => $device->id]) }}">
@csrf
<button class="px-2 py-1 text-xs font-semibold text-gray-600 border border-gray-200 rounded hover:bg-gray-50" type="submit">Refresh</button>
</form>
<form method="POST" action="{{ route('devices.delete', ['device' => $device->id]) }}">
@csrf
<button class="px-2 py-1 text-xs font-semibold text-red-600 border border-red-200 rounded hover:bg-red-50" type="submit">Delete</button>
</form>
</div>
</td>
</tr>
<tr class="hidden bg-gray-50/60 dark:bg-gray-900/40" data-device-edit-row="{{ $device->id }}">
<td class="px-6 py-4" colspan="7">
<form class="space-y-4" method="POST" action="{{ route('devices.update', ['device' => $device->id]) }}" data-device-edit-form>
@csrf
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div class="flex flex-col gap-2" data-device-edit-common-name-field>
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Device Name</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="name" type="text" value="{{ $device->name }}" data-device-edit-common-name-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Device Type</label>
<select class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="type" required data-device-edit-type>
<option value="CISCO" @selected(($device->type ?? '') === 'CISCO')>CISCO</option>
<option value="MIMOSA" @selected(($device->type ?? '') === 'MIMOSA')>MIMOSA</option>
<option value="OLT" @selected(($device->type ?? '') === 'OLT')>OLT</option>
<option value="SERVER" @selected(($device->type ?? '') === 'SERVER')>SERVER</option>
<option value="MIKROTIK" @selected(($device->type ?? '') === 'MIKROTIK')>MIKROTIK</option>
</select>
</div>
<div class="flex flex-col gap-2" data-device-edit-generic-ip-field>
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">IP Address</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="ip_address" type="text" value="{{ $device->ip_address }}"/>
</div>
<div class="flex flex-col gap-2 order-last" data-device-edit-snmp-community-field>
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Community</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_community" placeholder="e.g., public" type="text" value="{{ data_get($device->metadata, 'snmp_community') ?? data_get($device->metadata, 'server.snmp_community') ?? data_get($device->metadata, 'cisco.snmp_community') ?? data_get($device->metadata, 'mikrotik.snmp_community') ?? '' }}"/>
</div>
<div class="flex flex-col gap-2 hidden order-last" data-device-edit-snmp-port-field>
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Port</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_port" type="number" min="1" max="65535" value="{{ data_get($device->metadata, 'snmp_port') ?? data_get($device->metadata, 'server.snmp_port') ?? data_get($device->metadata, 'mikrotik.snmp_port') ?? '' }}"/>
</div>
<div class="contents" data-device-edit-cisco-fields data-device-edit-cisco-model="{{ data_get($device->metadata, 'cisco.switch_model') ?? $device->model ?? '' }}">
<div class="flex flex-col gap-2" data-device-edit-cisco-username-field>
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Cisco Username</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="cisco_username" type="text" value="{{ data_get($device->metadata, 'cisco.username') ?? '' }}"/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Cisco Password</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="cisco_password" placeholder="Leave blank to keep" type="password"/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Enable Password</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="enable_password" placeholder="Leave blank to keep" type="password"/>
</div>
</div>
@php
$serverType = data_get($device->metadata, 'server.server_type');
if (!in_array($serverType, ['virtual_server', 'stand_alone_server'], true)) {
    $serverType = (data_get($device->metadata, 'server.cabinet_id') || data_get($device->metadata, 'server.rack_uid'))
        ? 'stand_alone_server'
        : 'virtual_server';
}
$selectedServerServices = data_get($device->metadata, 'server.services');
if (!is_array($selectedServerServices)) {
    $legacyService = data_get($device->metadata, 'server.service');
    $selectedServerServices = $legacyService ? [$legacyService] : [];
}
$selectedServerServices = array_values(array_filter(array_map(
    static fn ($service) => is_scalar($service)
        ? str_replace('netplat', 'netplay', strtolower(trim((string) $service)))
        : null,
    $selectedServerServices
), static fn ($service) => $service !== null && $service !== ''));
$selectedServerServiceAccess = data_get($device->metadata, 'server.service_access');
if (!is_array($selectedServerServiceAccess)) {
    $selectedServerServiceAccess = [];
}
$legacyServerWebAddress = data_get($device->metadata, 'server.web_address_port');
$legacyServerWebUsername = data_get($device->metadata, 'server.web_username');
$legacyServerVncIp = data_get($device->metadata, 'server.vnc_address_port');
@endphp
<div class="md:col-span-2 hidden" data-device-edit-server-fields>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 rounded-lg border border-[#dce3f2] dark:border-gray-700 p-4 bg-white/70 dark:bg-gray-900/30">
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Server Type</label>
<select class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_type" data-device-edit-server-type data-device-edit-server-required>
<option value="virtual_server" @selected($serverType === 'virtual_server')>Virtual Server</option>
<option value="stand_alone_server" @selected($serverType === 'stand_alone_server')>Stand Alone Server</option>
</select>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Hardware Specs</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_hardware_specs" type="text" value="{{ data_get($device->metadata, 'server.hardware_specs') ?? '' }}" data-device-edit-server-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Server Name</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_name" type="text" value="{{ data_get($device->metadata, 'server.server_name') ?? $device->name }}" data-device-edit-server-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Services</label>
<div class="rounded-lg border border-[#cfd7e7] dark:border-gray-700 bg-white dark:bg-gray-800 p-3 max-h-44 overflow-y-auto space-y-2" data-device-edit-server-service>
@foreach ($serverServiceOptions as $serviceOption)
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
<input class="rounded border-[#cfd7e7] text-primary focus:ring-primary" type="checkbox" name="server_service[]" value="{{ $serviceOption }}" data-device-edit-server-service-option @checked(in_array($serviceOption, $selectedServerServices, true))/>
<span>{{ $serviceOption === 'vnc' ? 'VNC' : ucwords(str_replace('_', ' ', $serviceOption)) }}</span>
</label>
@endforeach
</div>
<p class="text-[11px] text-gray-400">Select one or more services.</p>
</div>
@foreach ($serverServiceFieldOptions as $serviceOption)
@php
    $serviceLabel = $serverServiceLabels[$serviceOption] ?? ucwords(str_replace('_', ' ', $serviceOption));
    $needsWebAddress = in_array($serviceOption, $serverWebAddressServices, true);
    $needsWebCredentials = in_array($serviceOption, $serverWebCredentialServices, true);
    $isVncService = $serviceOption === 'vnc';
    $serviceAddressValue = data_get($selectedServerServiceAccess, $serviceOption . '.address_port');
    if ($serviceAddressValue === null && $needsWebAddress) {
        $serviceAddressValue = $legacyServerWebAddress;
    }
    $serviceUsernameValue = data_get($selectedServerServiceAccess, $serviceOption . '.username');
    if ($serviceUsernameValue === null && $needsWebCredentials) {
        $serviceUsernameValue = $legacyServerWebUsername;
    }
    $serviceVncIpValue = data_get($selectedServerServiceAccess, $serviceOption . '.vnc_ip');
    if ($serviceVncIpValue === null && $isVncService) {
        $serviceVncIpValue = $legacyServerVncIp;
    }
@endphp
<div class="md:col-span-2 hidden rounded-lg border border-[#dce3f2] dark:border-gray-700 bg-white/70 dark:bg-gray-900/30 p-4" data-device-edit-server-service-fields="{{ $serviceOption }}">
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div class="md:col-span-2">
<p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">{{ $serviceLabel }}</p>
</div>
@if ($needsWebAddress)
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Server Web Address and Port ({{ $serviceLabel }})</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_service_access[{{ $serviceOption }}][address_port]" type="text" value="{{ $serviceAddressValue ?? '' }}" data-device-edit-server-service-address-required/>
</div>
@endif
@if ($needsWebCredentials)
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Server Web Username ({{ $serviceLabel }})</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_service_access[{{ $serviceOption }}][username]" type="text" value="{{ $serviceUsernameValue ?? '' }}"/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Server Web Password ({{ $serviceLabel }})</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_service_access[{{ $serviceOption }}][password]" type="password" placeholder="Leave blank to keep"/>
</div>
@endif
@if ($isVncService)
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">VNC IP ({{ $serviceLabel }})</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_service_access[{{ $serviceOption }}][vnc_ip]" type="text" value="{{ $serviceVncIpValue ?? '' }}" data-device-edit-server-service-vnc-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">VNC Password ({{ $serviceLabel }})</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_service_access[{{ $serviceOption }}][vnc_password]" type="password" placeholder="Leave blank to keep"/>
</div>
@endif
</div>
</div>
@endforeach
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Server SSH Port</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_ssh_port" type="number" min="1" max="65535" value="{{ data_get($device->metadata, 'server.ssh_port') ?? '' }}" data-device-edit-server-required/>
</div>
<div class="flex flex-col gap-2 hidden" data-device-edit-server-standalone-field>
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Cabinet ID</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_cabinet_id" type="text" value="{{ data_get($device->metadata, 'server.cabinet_id') ?? '' }}" data-device-edit-server-standalone-required/>
</div>
<div class="flex flex-col gap-2 hidden" data-device-edit-server-standalone-field>
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Rack UID</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_rack_uid" type="text" value="{{ data_get($device->metadata, 'server.rack_uid') ?? '' }}" data-device-edit-server-standalone-required/>
</div>
</div>
</div>
<div class="md:col-span-2 hidden" data-device-edit-olt-fields>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 rounded-lg border border-[#dce3f2] dark:border-gray-700 p-4 bg-white/70 dark:bg-gray-900/30">
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">OLT Type</label>
<select class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_device_type" data-device-edit-olt-required>
@php($oltDeviceType = strtoupper((string) data_get($device->metadata, 'olt.device_type', '')))
<option value="HUAWEI" @selected($oltDeviceType === 'HUAWEI' || $oltDeviceType === '')>Huawei</option>
<option value="VSOL" @selected($oltDeviceType === 'VSOL')>VSOL</option>
<option value="HIOSO" @selected($oltDeviceType === 'HIOSO')>Hioso</option>
</select>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Model</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_model" type="text" value="{{ data_get($device->metadata, 'olt.model') ?? $device->model ?? '' }}" data-device-edit-olt-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Number of Ports</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_number_of_ports" type="number" min="1" max="4096" value="{{ data_get($device->metadata, 'olt.number_of_ports') ?? (is_numeric($device->serial_number ?? null) ? (int) $device->serial_number : '') }}" data-device-edit-olt-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">IP</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_ip_address" type="text" value="{{ data_get($device->metadata, 'olt.ip_address') ?? $device->ip_address ?? '' }}" data-device-edit-olt-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Username</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_username" type="text" value="{{ data_get($device->metadata, 'olt.username') ?? '' }}" data-device-edit-olt-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Password</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_password" type="password" placeholder="Leave blank to keep"/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Web Address</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_web_address" type="text" value="{{ data_get($device->metadata, 'olt.web_address') ?? '' }}" data-device-edit-olt-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Folder Location</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_folder_location" type="text" value="{{ data_get($device->metadata, 'olt.folder_location') ?? '' }}"/>
</div>
<div class="flex flex-col gap-2 order-last">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Port</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_port" type="number" min="1" max="65535" value="{{ data_get($device->metadata, 'snmp_port') ?? data_get($device->metadata, 'olt.snmp_port') ?? '' }}"/>
</div>
<div class="flex flex-col gap-2 order-last">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Community</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_snmp_community" type="text" value="{{ data_get($device->metadata, 'olt.snmp_community') ?? data_get($device->metadata, 'snmp_community') ?? '' }}" data-device-edit-olt-required/>
</div>
</div>
</div>
<div class="md:col-span-2 hidden" data-device-edit-mikrotik-fields>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 rounded-lg border border-[#dce3f2] dark:border-gray-700 p-4 bg-white/70 dark:bg-gray-900/30">
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">IP</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_ip_address" type="text" value="{{ data_get($device->metadata, 'mikrotik.ip_address') ?? $device->ip_address ?? '' }}" data-device-edit-mikrotik-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Username</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_username" type="text" value="{{ data_get($device->metadata, 'mikrotik.username') ?? '' }}" data-device-edit-mikrotik-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Password</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_password" type="password" placeholder="Leave blank to keep"/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Location</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_location" type="text" value="{{ data_get($device->metadata, 'mikrotik.location') ?? $device->location ?? '' }}" data-device-edit-mikrotik-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Cabinet ID</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_cabinet_id" type="text" value="{{ data_get($device->metadata, 'mikrotik.cabinet_id') ?? '' }}" data-device-edit-mikrotik-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Rack UID</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_rack_uid" type="text" value="{{ data_get($device->metadata, 'mikrotik.rack_uid') ?? '' }}" data-device-edit-mikrotik-required/>
</div>
<div class="flex flex-col gap-2 order-last">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Community</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_snmp_community" type="text" value="{{ data_get($device->metadata, 'mikrotik.snmp_community') ?? data_get($device->metadata, 'snmp_community') ?? '' }}" data-device-edit-mikrotik-required/>
</div>
<div class="flex flex-col gap-2 order-last">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Port</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_snmp_port" type="number" min="1" max="65535" value="{{ data_get($device->metadata, 'mikrotik.snmp_port') ?? data_get($device->metadata, 'snmp_port') ?? '' }}" data-device-edit-mikrotik-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Winbox Port</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_winbox_port" type="number" min="1" max="65535" value="{{ data_get($device->metadata, 'mikrotik.winbox_port') ?? '' }}" data-device-edit-mikrotik-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SSH Port</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_ssh_port" type="number" min="1" max="65535" value="{{ data_get($device->metadata, 'mikrotik.ssh_port') ?? '' }}" data-device-edit-mikrotik-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Telnet Port</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_telnet_port" type="number" min="1" max="65535" value="{{ data_get($device->metadata, 'mikrotik.telnet_port') ?? '' }}" data-device-edit-mikrotik-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">API Port</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_api_port" type="number" min="1" max="65535" value="{{ data_get($device->metadata, 'mikrotik.api_port') ?? '' }}" data-device-edit-mikrotik-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">API SSL Port</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_api_ssl_port" type="number" min="1" max="65535" value="{{ data_get($device->metadata, 'mikrotik.api_ssl_port') ?? '' }}" data-device-edit-mikrotik-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">FTP Port</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_ftp_port" type="number" min="1" max="65535" value="{{ data_get($device->metadata, 'mikrotik.ftp_port') ?? '' }}" data-device-edit-mikrotik-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">HTTP Port</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_http_port" type="number" min="1" max="65535" value="{{ data_get($device->metadata, 'mikrotik.http_port') ?? '' }}" data-device-edit-mikrotik-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Web Address</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_web_address" type="text" value="{{ data_get($device->metadata, 'mikrotik.web_address') ?? '' }}" data-device-edit-mikrotik-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Winbox Address</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_winbox_address" type="text" value="{{ data_get($device->metadata, 'mikrotik.winbox_address') ?? '' }}" data-device-edit-mikrotik-required/>
</div>
</div>
</div>
</div>
<div class="flex justify-end gap-3">
<button class="px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50" type="button" data-no-dispatch="true" data-device-edit-close="{{ $device->id }}">Close</button>
<button class="px-4 py-2 text-sm font-semibold text-white bg-primary rounded-lg hover:bg-primary/90" type="submit">Save Changes</button>
</div>
</form>
</td>
</tr>
@empty
<tr>
<td class="px-6 py-6 text-sm text-gray-500" colspan="7">No devices found.</td>
</tr>
@endforelse
</tbody>
</table>
<div class="px-6 py-4 border-t border-[#cfd7e7] dark:border-gray-800 flex justify-between items-center bg-gray-50/50 dark:bg-gray-800/30">
<span class="text-xs text-gray-500 font-medium tracking-tight">
Showing {{ $devices->firstItem() ?? 0 }}-{{ $devices->lastItem() ?? 0 }} of {{ $devices->total() ?? 0 }} results
</span>
<div class="flex gap-2">
<a class="px-3 py-1 bg-white dark:bg-gray-800 border border-[#cfd7e7] dark:border-gray-700 rounded text-xs font-semibold {{ $devices->previousPageUrl() ? '' : 'opacity-50 pointer-events-none' }}" href="{{ $devices->previousPageUrl() ?? '#' }}">Previous</a>
<span class="px-3 py-1 bg-primary text-white rounded text-xs font-semibold">{{ $devices->currentPage() }}</span>
<a class="px-3 py-1 bg-white dark:bg-gray-800 border border-[#cfd7e7] dark:border-gray-700 rounded text-xs font-semibold {{ $devices->nextPageUrl() ? '' : 'opacity-50 pointer-events-none' }}" href="{{ $devices->nextPageUrl() ?? '#' }}">Next</a>
</div>
</div>
</div>

</section>
<!-- Detail View Drawer (Always Visible for this view) -->
<aside class="w-[420px] bg-white dark:bg-background-dark border-l border-[#e7ebf3] dark:border-gray-800 flex flex-col shrink-0 overflow-y-auto min-h-0" data-device-drawer data-device-row data-device-id="{{ $selectedDevice?->id }}">
<div class="p-6 border-b border-[#e7ebf3] dark:border-gray-800">
<div class="flex justify-between items-start mb-4">
<div>
<h3 class="text-xl font-bold">{{ $selectedDevice?->name ?? 'No device selected' }}</h3>
<p class="text-xs font-mono text-gray-400">UUID: {{ $selectedDevice?->uuid ?? '-' }}</p>
</div>
<div class="flex gap-2">
<button class="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-gray-600 dark:text-gray-300" type="button" data-no-dispatch="true" data-device-edit="{{ $selectedDevice?->id }}">
<span class="material-symbols-outlined text-xl">settings</span>
</button>
@if ($selectedDevice)
<form method="POST" action="{{ route('devices.refresh', ['device' => $selectedDevice->id]) }}">
@csrf
<button class="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-gray-600 dark:text-gray-300" type="submit">
<span class="material-symbols-outlined text-xl">refresh</span>
</button>
</form>
@else
<button class="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-gray-300" type="button" disabled>
<span class="material-symbols-outlined text-xl">refresh</span>
</button>
@endif
</div>
</div>
<div class="grid grid-cols-2 gap-4">
<div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
<p class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Signal</p>
<p class="text-lg font-bold text-primary" data-signal-value>{{ $selectedSignal ?? '-' }}</p>
</div>
<div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
<p class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Battery</p>
<p class="text-lg font-bold" data-battery-value>{{ $selectedBattery ?? '-' }}</p>
</div>
<div class="grid grid-cols-2 gap-4 mt-4">
<div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
<p class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Type</p>
<p class="text-sm font-semibold">{{ $selectedDevice?->type ?? '-' }}</p>
</div>
<div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
<p class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Status</p>
<p class="text-sm font-semibold" data-status-text>{{ ucfirst($selectedDevice?->status ?? '-') }}</p>
</div>
<div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
<p class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Assigned</p>
<p class="text-sm font-semibold">{{ $selectedDevice?->assignedUser?->name ?? 'Unassigned' }}</p>
</div>
<div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
<p class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Last Seen</p>
<p class="text-sm font-semibold" data-last-seen>{{ $selectedDevice?->last_seen_at?->diffForHumans() ?? '-' }}</p>
</div>
</div>
</div>
</div>
<div class="p-6 flex-1">
<div class="rounded-2xl border border-[#e7ebf3] dark:border-gray-800 bg-gray-50 dark:bg-gray-900 p-5">
<h4 class="text-sm font-bold uppercase tracking-wider text-gray-500">Telemetry Logs</h4>
<p class="mt-3 text-sm text-gray-600 dark:text-gray-300">Telemetry history has moved to its own page instead of showing inside this drawer.</p>
<a class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary/90 transition-colors" href="{{ $selectedDevice ? route('telemetry.index', ['device' => $selectedDevice->id]) : route('telemetry.index') }}">
Open Telemetry Logs
</a>
</div>
@verbatim<!--
<h4 class="text-sm font-bold uppercase tracking-wider text-gray-500 mb-4">Telemetry Logs</h4>
<div class="space-y-4">
@forelse ($telemetryLogs as $log)
@php
$level = strtolower($log->level ?? 'info');
$dotClass = match ($level) {
    'error' => 'bg-red-500',
    'warning' => 'bg-yellow-500',
    'success' => 'bg-green-500',
    default => 'bg-blue-500',
};
@endphp
<div class="flex gap-3">
<div class="w-2 h-2 rounded-full {{ $dotClass }} mt-1.5 shrink-0"></div>
<div>
<p class="text-sm font-medium">{{ $log->message }}</p>
<p class="text-xs text-gray-400">{{ is_array($log->payload) ? json_encode($log->payload) : ($log->payload ?? '') }}</p>
<p class="text-[10px] text-gray-500 mt-1">{{ $log->recorded_at ? $log->recorded_at->diffForHumans() : '�' }}</p>
</div>
</div>
@empty
<p class="text-xs text-gray-400">No telemetry logs yet.</p>
@endforelse
</div>
<a class="w-full mt-6 py-2 text-sm font-semibold text-primary bg-primary/5 rounded-lg hover:bg-primary/10 transition-colors text-center" href="{{ route('telemetry.index') }}">
                            View Full History
                        </a>
-->@endverbatim
</div>
</aside>
</div>
</main>
</div>
</body></html>




























