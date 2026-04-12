<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/><meta name="csrf-token" content="{{ csrf_token() }}"/><meta name="app-base" content="{{ url('/') }}"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Logs | Device Control Manager</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&amp;display=swap" rel="stylesheet"/>
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
            font-family: 'Material Symbols Outlined';
            font-weight: normal;
            font-style: normal;
            font-size: 24px;
            line-height: 1;
            letter-spacing: normal;
            text-transform: none;
            display: inline-block;
            white-space: nowrap;
            word-wrap: normal;
            direction: ltr;
            -webkit-font-feature-settings: 'liga';
            -webkit-font-smoothing: antialiased;
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
    @include('partials.admin_sidebar_styles')
    <script src="{{ asset('js/actions.js') . '?v=' . filemtime(public_path('js/actions.js')) }}" defer></script></head>
<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 min-h-screen">
@php
$selectedDeviceId = $selectedDevice?->id;
$refreshUrl = $selectedDeviceId ? route('telemetry.index', ['device' => $selectedDeviceId]) : route('telemetry.index');
@endphp
<div class="flex h-screen overflow-hidden">
<!-- Sidebar Navigation -->
@include('partials.admin_sidebar')
<!-- Main Content Area -->
<main class="flex-1 flex flex-col overflow-y-auto">
<header class="h-16 flex-shrink-0 border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-background-dark/80 backdrop-blur-md px-8 flex items-center justify-between sticky top-0 z-10">
<div class="flex items-center gap-3">
<button class="h-10 w-10 flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/40 hover:bg-slate-100 dark:hover:bg-slate-800" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
<span class="material-symbols-outlined text-slate-600 dark:text-slate-300">menu</span>
</button>
<h2 class="text-lg font-bold">Logs</h2>
</div>
<div class="flex items-center gap-3">
<label class="hidden md:flex items-center gap-2 px-3 h-10 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
<span class="material-symbols-outlined text-slate-400 text-[20px]">search</span>
<input class="bg-transparent border-none focus:ring-0 text-sm w-56 placeholder:text-slate-400" placeholder="Search logs..." type="text" data-live-search data-live-search-target="[data-telemetry-row],[data-audit-log-item]"/>
</label>
<a class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-semibold hover:bg-slate-50" href="{{ $refreshUrl }}">Refresh</a>
</div>
</header>
<div class="p-8 space-y-8">
@if ($selectedDevice)
<div class="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800 px-6 py-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
<div>
<p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Device Telemetry</p>
<h3 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $selectedDevice->name }}</h3>
<p class="mt-1 text-sm text-slate-500">UUID: {{ $selectedDevice->uuid }}</p>
</div>
<div class="flex items-center gap-3">
<span class="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">{{ ucfirst($selectedDevice->status ?? 'unknown') }}</span>
<a class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-50" href="{{ route('devices.index', ['device' => $selectedDevice->id]) }}">Back to Device</a>
</div>
</div>
@endif
@if (!$selectedDevice)
<div class="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
<div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
<h3 class="font-bold text-slate-900 dark:text-white">Audit Activity</h3>
</div>
<div class="divide-y divide-slate-100 dark:divide-slate-800">
@forelse ($auditLogs as $log)
@php
$actorName = $log->actor?->name ?? 'System';
$action = $log->action ?? 'action';
$meta = is_array($log->metadata ?? null) ? $log->metadata : [];
$subjectLabel = $meta['name'] ?? $meta['label'] ?? null;
if (!$subjectLabel && $log->subject_type) {
    $subjectLabel = class_basename($log->subject_type) . ($log->subject_id ? " #{$log->subject_id}" : '');
}
@endphp
<div class="p-4 flex items-start gap-4" data-audit-log-item>
<div class="h-10 w-10 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500">
<span class="material-symbols-outlined text-[20px]">history</span>
</div>
<div class="flex-1 min-w-0">
<p class="text-sm text-slate-900 dark:text-slate-200">
<span class="font-bold">{{ $actorName }}</span> {{ $action }}
@if ($subjectLabel)
<span class="font-bold">{{ $subjectLabel }}</span>
@endif
.</p>
<p class="text-xs text-slate-500 mt-1">{{ $log->occurred_at?->diffForHumans() ?? 'Just now' }}</p>
</div>
</div>
@empty
<div class="p-8 text-center">
<p class="text-xs text-slate-400">No audit activity yet.</p>
</div>
@endforelse
</div>
</div>
@endif
<div class="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
<div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
<h3 class="font-bold text-slate-900 dark:text-white">{{ $selectedDevice ? 'Device Telemetry Logs' : 'Telemetry Logs' }}</h3>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50 dark:bg-slate-800/70 border-b border-slate-200 dark:border-slate-800">
<th class="px-6 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Device</th>
<th class="px-6 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Level</th>
<th class="px-6 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Message</th>
<th class="px-6 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Recorded</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100 dark:divide-slate-800">
@forelse ($telemetryLogs as $log)
@php
$deviceName = $log->device?->name ?? ($log->device_id ? "Device #{$log->device_id}" : 'Unknown');
$level = strtoupper($log->level ?? 'INFO');
$badgeClass = match (strtolower($log->level ?? 'info')) {
    'error', 'critical' => 'bg-red-100 text-red-700',
    'warning' => 'bg-amber-100 text-amber-700',
    'debug' => 'bg-slate-100 text-slate-600',
    default => 'bg-green-100 text-green-700',
};
@endphp
<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors" data-telemetry-row data-live-search-suggest-text="{{ $deviceName }}" data-live-search-text="{{ trim((string) ($deviceName . ' ' . $level . ' ' . ($log->message ?? ''))) }}">
<td class="px-6 py-4 text-sm text-slate-900 dark:text-white">{{ $deviceName }}</td>
<td class="px-6 py-4">
<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $badgeClass }}">{{ $level }}</span>
</td>
<td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $log->message ?? '-' }}</td>
<td class="px-6 py-4 text-sm text-slate-500">{{ $log->recorded_at?->diffForHumans() ?? '-' }}</td>
</tr>
@empty
<tr>
<td class="px-6 py-6 text-sm text-slate-500" colspan="4">{{ $selectedDevice ? 'No telemetry logs found for this device.' : 'No telemetry logs found.' }}</td>
</tr>
@endforelse
</tbody>
</table>
</div>
</div>
</div>
</main>
</div>
</body></html>












