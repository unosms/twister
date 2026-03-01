<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<meta name="app-base" content="{{ url('/') }}"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Debugging &amp; Diagnostics | Device Control Manager</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
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
        body {
            font-family: 'Inter', sans-serif;
        }
</style>
@include('partials.admin_sidebar_styles')
<script src="{{ asset('js/actions.js') . '?v=' . filemtime(public_path('js/actions.js')) }}" defer></script>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 h-screen overflow-hidden">
@php
$deviceNavActive = request()->routeIs('devices.*');
$deviceControlActive = request()->routeIs('devices.index');
$deviceDetailsActive = request()->routeIs('devices.details');
$supportActive = request()->routeIs('support.index');
$settingsActive = request()->routeIs('settings.*');
$profileName = $authUser->name ?? 'Admin';
$profileRole = ($authUser->role ?? 'admin') === 'admin' ? 'Super Admin' : 'User';
$healthPercent = $totalDevices > 0 ? (int) round(($onlineDevices / $totalDevices) * 100) : 0;
$supportResult = is_array($supportResult ?? null) ? $supportResult : null;
@endphp

<div class="flex h-screen overflow-hidden">
@include('partials.admin_sidebar', ['sidebarAuthUser' => $authUser ?? null])

<main class="flex-1 min-w-0 flex flex-col overflow-y-auto">
<header class="min-h-16 flex-shrink-0 border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-background-dark/80 backdrop-blur-md px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-between gap-3 sticky top-0 z-10">
<div class="flex items-center gap-3 min-w-0 flex-1">
<button class="h-10 w-10 flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/40 hover:bg-slate-100 dark:hover:bg-slate-800" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
<span class="material-symbols-outlined text-slate-600 dark:text-slate-300">menu</span>
</button>
<div class="relative hidden sm:flex items-center flex-1 max-w-xl">
<span class="material-symbols-outlined absolute left-3 text-slate-400 text-[20px]">search</span>
<input class="w-full bg-slate-100 dark:bg-slate-800 border-none rounded-lg py-2 pl-10 pr-4 text-sm focus:ring-2 focus:ring-primary/50 placeholder:text-slate-500" placeholder="Search diagnostics or log lines..." type="text" data-live-search data-live-search-target="[data-log-line],[data-diagnostic-row]"/>
</div>
</div>
<div class="flex items-center gap-2 sm:gap-4">
<a class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800" href="{{ route('support.index') }}">
<span class="material-symbols-outlined text-[18px]">refresh</span>
Refresh
</a>
<div class="relative">
<button class="relative h-10 w-10 flex items-center justify-center rounded-full bg-primary/10 text-primary" type="button">
<span class="material-symbols-outlined">help</span>
</button>
</div>
<div class="hidden sm:block h-8 w-[1px] bg-slate-200 dark:border-slate-800 mx-1"></div>
<div class="flex items-center gap-3">
<div class="text-right hidden md:block">
<p class="text-xs font-bold leading-none">{{ $profileName }}</p>
<p class="text-[10px] text-slate-500 mt-1">{{ $profileRole }}</p>
</div>
<div class="h-10 w-10 rounded-full bg-center bg-cover border-2 border-primary/20" data-alt="Profile picture of an administrator" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAvfO67O67l_DBdWQHQprq2tGQS4_-3LZi3TgZWvSmOQ0ddG3G5_sPWZAvpVHb9buoAdrf5Ei98s_38HwjyFQAVmYM0UNTjO6Lj6Sq3liR0Qudxl-LZC7Z9jKpCHRxLwhAO59LpHL--q61-gW6nqn-38R3yzXckfDDk9VayClvL1Wnip0ee1yD3Ll1LperuPspjHuo3CnTu-M5j_Q9pHYY6UxFMB8q9yOaZi9HUaMEFuaI1koEmvp2a0vL5wG-nz2-fllDBMspbvQ')"></div>
</div>
</div>
</header>

<div class="relative p-4 sm:p-6 lg:p-8 space-y-6 lg:space-y-8">
<div class="pointer-events-none absolute inset-x-4 top-4 h-24 rounded-3xl bg-gradient-to-r from-primary/20 via-sky-200/40 to-emerald-200/40 blur-3xl"></div>

@if ($supportStatus)
<section class="relative rounded-2xl border border-emerald-200 bg-emerald-50/90 px-5 py-4 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-950/30">
<div class="flex items-start gap-3">
<span class="material-symbols-outlined text-emerald-600">task_alt</span>
<div>
<p class="text-sm font-bold text-emerald-800 dark:text-emerald-300">Support action complete</p>
<p class="mt-1 text-sm text-emerald-700 dark:text-emerald-200">{{ $supportStatus }}</p>
</div>
</div>
</section>
@endif

<section class="relative overflow-hidden rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white/95 dark:bg-slate-900/40 shadow-sm">
<div class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-sky-100/60 dark:bg-sky-900/20 blur-2xl"></div>
<div class="relative px-5 py-6 sm:px-8 sm:py-8">
<div class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(360px,0.9fr)] xl:items-start">
<div class="space-y-5">
<div>
<p class="text-[11px] font-bold uppercase tracking-[0.28em] text-primary">Support Console</p>
<h2 class="mt-3 text-3xl font-black tracking-tight text-slate-900 dark:text-white">Debugging &amp; Diagnostics</h2>
<p class="mt-3 max-w-2xl text-sm text-slate-500 dark:text-slate-400">Provisioning capture, fleet health, alerts, telemetry history, and audit traces in one workspace.</p>
</div>
<div class="grid gap-3 sm:grid-cols-3">
<div class="rounded-2xl border border-slate-200/80 bg-white/70 px-4 py-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
<p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Provisioning</p>
<p class="mt-3 text-lg font-black {{ $provisioningLogEnabled ? 'text-emerald-600' : 'text-slate-500' }}">{{ $provisioningLogEnabled ? 'Capture Enabled' : 'Capture Disabled' }}</p>
<p class="mt-2 text-xs text-slate-500">{{ $provisioningLogExists ? 'Live log file detected.' : 'Waiting for provisioning output.' }}</p>
</div>
<div class="rounded-2xl border border-slate-200/80 bg-white/70 px-4 py-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
<p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Priority Queue</p>
<p class="mt-3 text-lg font-black text-slate-900 dark:text-white">{{ $openAlertCount }}</p>
<p class="mt-2 text-xs text-slate-500">Open alerts feeding the auto-debug target list.</p>
</div>
<div class="rounded-2xl border border-slate-200/80 bg-white/70 px-4 py-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
<p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Fleet Health</p>
<p class="mt-3 text-lg font-black text-slate-900 dark:text-white">{{ $healthPercent }}%</p>
<p class="mt-2 text-xs text-slate-500">{{ $onlineDevices }} online / {{ $offlineDevices }} offline</p>
</div>
</div>
</div>
<div class="rounded-2xl border border-slate-200/80 bg-white/85 p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950/50">
<div>
<p class="text-[11px] font-bold uppercase tracking-[0.28em] text-slate-500">Actions</p>
<h3 class="mt-2 text-xl font-black text-slate-900 dark:text-white">Run support workflows</h3>
<p class="mt-2 text-sm text-slate-500">Probe devices directly from this page and keep the result summary visible after the run.</p>
</div>
<div class="mt-5 grid gap-3 sm:grid-cols-2">
<form action="{{ route('support.auto-debug') }}" method="POST">
@csrf
<button class="flex h-full w-full flex-col items-start rounded-2xl bg-primary px-4 py-4 text-left text-white shadow-lg shadow-primary/20 transition hover:bg-primary/90" type="submit">
<span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white/15">
<span class="material-symbols-outlined">auto_fix_high</span>
</span>
<span class="mt-4 text-base font-bold">Auto Debug</span>
<span class="mt-1 text-sm text-white/80">Enable capture and probe the devices most likely to need attention.</span>
</button>
</form>
<form action="{{ route('support.run-diagnostic') }}" method="POST">
@csrf
<button class="flex h-full w-full flex-col items-start rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-left text-slate-900 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800" type="submit">
<span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900/5 dark:bg-white/10">
<span class="material-symbols-outlined">health_and_safety</span>
</span>
<span class="mt-4 text-base font-bold">Run Diagnostic</span>
<span class="mt-1 text-sm text-slate-500 dark:text-slate-400">Refresh status across the fleet and return a clean summary of device health.</span>
</button>
</form>
<a class="flex flex-col items-start rounded-2xl border border-slate-200 px-4 py-4 text-left transition hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800" href="{{ route('telemetry.index') }}">
<span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">
<span class="material-symbols-outlined">monitoring</span>
</span>
<span class="mt-4 text-base font-bold text-slate-900 dark:text-white">Telemetry Logs</span>
<span class="mt-1 text-sm text-slate-500 dark:text-slate-400">Open the full telemetry stream in its dedicated page.</span>
</a>
<a class="flex flex-col items-start rounded-2xl border border-slate-200 px-4 py-4 text-left transition hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800" href="{{ route('notifications.index') }}">
<span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
<span class="material-symbols-outlined">notifications</span>
</span>
<span class="mt-4 text-base font-bold text-slate-900 dark:text-white">Notifications</span>
<span class="mt-1 text-sm text-slate-500 dark:text-slate-400">Review the alerts that are feeding the support queue.</span>
</a>
</div>
<div class="mt-3">
<a class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-primary dark:text-slate-400" href="{{ route('debug.provisioning-log.view') }}" target="_blank" rel="noreferrer">
<span class="material-symbols-outlined text-[18px]">data_object</span>
Open raw provisioning log
</a>
</div>
</div>
</div>
</div>
</section>

@if ($supportResult)
<section id="provisioning-debug" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
<div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
<div>
<p class="text-[11px] font-bold uppercase tracking-[0.28em] text-slate-500">Last Run</p>
<h3 class="mt-2 text-xl font-black text-slate-900 dark:text-white">{{ $supportResult['mode'] ?? 'Support Run' }}</h3>
<p class="mt-2 text-sm text-slate-500">Scope: {{ $supportResult['scope'] ?? 'N/A' }}. Completed {{ $supportResult['ran_at'] ?? now()->toDateTimeString() }}.</p>
</div>
<div class="grid gap-3 sm:grid-cols-4">
<div class="rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-800/80">
<p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Targets</p>
<p class="mt-2 text-xl font-black text-slate-900 dark:text-white">{{ $supportResult['target_count'] ?? 0 }}</p>
</div>
<div class="rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-800/80">
<p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Probed</p>
<p class="mt-2 text-xl font-black text-slate-900 dark:text-white">{{ $supportResult['probed_count'] ?? 0 }}</p>
</div>
<div class="rounded-xl bg-emerald-50 px-4 py-3 dark:bg-emerald-950/30">
<p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Online</p>
<p class="mt-2 text-xl font-black text-emerald-700 dark:text-emerald-300">{{ $supportResult['online_count'] ?? 0 }}</p>
</div>
<div class="rounded-xl bg-rose-50 px-4 py-3 dark:bg-rose-950/30">
<p class="text-[11px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-300">Offline</p>
<p class="mt-2 text-xl font-black text-rose-700 dark:text-rose-300">{{ $supportResult['offline_count'] ?? 0 }}</p>
</div>
</div>
</div>
@if (($supportResult['warning_count'] ?? 0) > 0 || ($supportResult['error_count'] ?? 0) > 0)
<div class="mt-4 flex flex-wrap gap-3">
<span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Warnings: {{ $supportResult['warning_count'] ?? 0 }}</span>
<span class="inline-flex rounded-full bg-rose-100 px-3 py-1 text-xs font-bold text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">Errors: {{ $supportResult['error_count'] ?? 0 }}</span>
</div>
@endif
</section>
@endif

<section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/40" data-diagnostic-row>
<p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Fleet Health</p>
<p class="mt-4 text-2xl font-black text-slate-900 dark:text-white">{{ $healthPercent }}%</p>
<p class="mt-2 text-sm text-slate-500">{{ $onlineDevices }} online / {{ $offlineDevices }} offline</p>
</div>
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/40" data-diagnostic-row>
<p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Telemetry Events</p>
<p class="mt-4 text-2xl font-black text-slate-900 dark:text-white">{{ $telemetryEventCount }}</p>
<p class="mt-2 text-sm text-slate-500">Last 24h. Latest event: {{ $latestTelemetryAt?->diffForHumans() ?? 'none' }}</p>
</div>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
<div class="flex flex-col gap-2 border-b border-slate-200 pb-4 dark:border-slate-800 sm:flex-row sm:items-end sm:justify-between">
<div>
<p class="text-[11px] font-bold uppercase tracking-[0.28em] text-slate-500">Provisioning Debug</p>
<h3 class="mt-2 text-xl font-black text-slate-900 dark:text-white">Provisioning log workspace</h3>
<p class="mt-2 text-sm text-slate-500">Keep provisioning capture controls and the live log feed grouped in one dedicated section.</p>
</div>
<a class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-primary dark:text-slate-400" href="{{ route('debug.provisioning-log.view') }}" target="_blank" rel="noreferrer">
<span class="material-symbols-outlined text-[18px]">data_object</span>
Open raw log
</a>
</div>

<div class="mt-5 grid gap-5 xl:grid-cols-[320px_minmax(0,1fr)]">
<div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-5 dark:border-emerald-900/40 dark:bg-emerald-950/20">
<div class="flex items-start justify-between gap-3">
<div>
<p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Capture Status</p>
<p class="mt-3 text-2xl font-black {{ $provisioningLogEnabled ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-600 dark:text-slate-300' }}" data-provisioning-status>{{ $provisioningLogEnabled ? 'Provisioning Log: ON' : 'Provisioning Log: OFF' }}</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300" data-provisioning-summary>{{ $provisioningLogExists ? 'Log file detected and ready to tail.' : 'No provisioning output has been written yet.' }}</p>
</div>
<span class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $provisioningLogExists ? 'bg-white text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}" data-provisioning-file-status>{{ $provisioningLogExists ? 'File Ready' : 'No File Yet' }}</span>
</div>
<p class="mt-4 rounded-xl bg-white/80 px-3 py-2 font-mono text-[11px] text-slate-600 dark:bg-slate-900/50 dark:text-slate-300" data-provisioning-path>{{ $provisioningLogPath }}</p>
<button class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-white px-4 py-3 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-900/50 dark:bg-slate-900/40 dark:text-emerald-300 dark:hover:bg-emerald-950/20" type="button" data-provisioning-toggle data-provisioning-endpoint="{{ route('debug.provisioning-log') }}" data-provisioning-enabled="{{ $provisioningLogEnabled ? '1' : '0' }}">
<span class="material-symbols-outlined text-[18px]">bug_report</span>
<span data-provisioning-label>{{ $provisioningLogEnabled ? 'Disable Capture' : 'Enable Capture' }}</span>
</button>
</div>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950/40">
<div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
<div>
<h4 class="text-lg font-bold text-slate-900 dark:text-white">Provisioning Log Tail</h4>
<p class="mt-1 text-xs text-slate-500">Latest provisioning output captured from the debug logger.</p>
</div>
<span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300" data-provisioning-tail-badge>Live tail</span>
</div>
<div
class="max-h-[420px] overflow-y-auto bg-slate-950 px-5 py-4 font-mono text-[12px] leading-6 text-emerald-300"
data-provisioning-tail
data-provisioning-log-endpoint="{{ route('debug.provisioning-log.view') }}"
data-provisioning-log-empty="No provisioning log lines available yet."
>
@forelse ($provisioningLogLines as $line)
<div data-log-line>{{ $line }}</div>
@empty
<div class="text-slate-400">No provisioning log lines available yet.</div>
@endforelse
</div>
<div class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400" data-provisioning-tail-meta>
Last updated {{ now()->format('H:i:s') }}
</div>
</div>
</div>
</section>
</div>
</main>
</div>
</body>
</html>
