<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<meta name="app-base" content="{{ url('/') }}"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>System Settings | Device Control Manager</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
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
$assignmentsActive = request()->routeIs('devices.wizard');
$supportActive = request()->routeIs('support.index');
$settingsActive = request()->routeIs('settings.*');
$profileName = session('auth.role') === 'admin' ? 'Admin' : 'User';
$localNow = now()->copy()->timezone($currentTimezone);
$selectedTimezone = old('timezone', $currentTimezone);
$backupRootPrimary = $backupRootOptions[0] ?? '/srv/tftp';
$selectedBackupDeviceId = old('device_id', $selectedBackupDeviceId ?? '');
$selectedCleanupEventDeviceId = old('event_device_id', '');
$cleanupAutoSettings = is_array($cleanupAutoSettings ?? null) ? $cleanupAutoSettings : [];
$cleanupLogsAuto = is_array($cleanupAutoSettings['logs'] ?? null) ? $cleanupAutoSettings['logs'] : ['enabled' => false, 'frequency' => 'weekly'];
$cleanupNotificationsAuto = is_array($cleanupAutoSettings['notifications'] ?? null) ? $cleanupAutoSettings['notifications'] : ['enabled' => false, 'frequency' => 'weekly'];
$cleanupEventsAuto = is_array($cleanupAutoSettings['events'] ?? null) ? $cleanupAutoSettings['events'] : ['enabled' => false, 'frequency' => 'weekly'];
$cleanupFrequencyOptions = [
    'weekly' => 'Every 1 week',
    'monthly' => 'Every month',
    'yearly' => 'Every year',
];
$cleanupRangeStartValue = old('range_start', '');
$cleanupRangeEndValue = old('range_end', '');
$telegramEventTypesCustomValue = old('telegram_event_types_custom', $telegramEventTypesCustom ?? '');
$telegramTemplateDefaultValue = old('telegram_template_default', $telegramTemplateDefault ?? '');
$telegramTemplateLowValue = old('telegram_template_low', $telegramTemplateLow ?? '');
$telegramTemplateMediumValue = old('telegram_template_medium', $telegramTemplateMedium ?? '');
$telegramTemplateHighValue = old('telegram_template_high', $telegramTemplateHigh ?? '');
$telegramTemplateCriticalValue = old('telegram_template_critical', $telegramTemplateCritical ?? '');
@endphp

<div class="flex h-screen overflow-hidden">
@include('partials.admin_sidebar')

<main class="flex-1 min-w-0 flex flex-col overflow-y-auto scroll-smooth">
<header class="min-h-16 flex-shrink-0 border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-background-dark/80 backdrop-blur-md px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-between gap-3 sticky top-0 z-10">
<div class="flex items-center gap-3 min-w-0 flex-1">
<button class="h-10 w-10 flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/40 hover:bg-slate-100 dark:hover:bg-slate-800" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
<span class="material-symbols-outlined text-slate-600 dark:text-slate-300">menu</span>
</button>
</div>
<div class="flex items-center gap-4">
<a class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800" href="{{ route('settings.index') }}">
<span class="material-symbols-outlined text-[18px]">refresh</span>
Refresh
</a>
</div>
</header>

<div class="relative p-4 sm:p-6 lg:p-8 space-y-6 lg:space-y-8">
<div class="pointer-events-none absolute inset-x-4 top-4 h-24 rounded-3xl bg-gradient-to-r from-primary/20 via-sky-200/40 to-emerald-200/40 blur-3xl"></div>

@if (session('status'))
<section class="relative rounded-2xl border border-emerald-200 bg-emerald-50/90 px-5 py-4 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-950/30">
<p class="text-sm font-bold text-emerald-800 dark:text-emerald-300">Settings updated</p>
<p class="mt-1 text-sm text-emerald-700 dark:text-emerald-200">{{ session('status') }}</p>
</section>
@endif

@if ($errors->any())
<section class="relative rounded-2xl border border-red-200 bg-red-50/90 px-5 py-4 shadow-sm dark:border-red-900/60 dark:bg-red-950/30">
<p class="text-sm font-bold text-red-800 dark:text-red-300">Could not update settings</p>
<ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700 dark:text-red-200">
@foreach ($errors->all() as $error)
<li>{{ $error }}</li>
@endforeach
</ul>
</section>
@endif

<section class="relative overflow-hidden rounded-3xl border border-slate-200/80 bg-white/95 px-6 py-6 shadow-xl shadow-slate-200/60 dark:border-slate-800 dark:bg-slate-900/85 dark:shadow-black/20 sm:px-8">
<div class="absolute -right-16 -top-16 h-40 w-40 rounded-full bg-primary/10 blur-3xl"></div>
<div class="relative flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
<div class="max-w-3xl">
<p class="text-xs font-bold uppercase tracking-[0.24em] text-primary">System Settings</p>
<h1 class="mt-3 text-3xl font-black tracking-tight text-slate-900 dark:text-white">Operations and Configuration</h1>
<p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
This page is organized around practical system tasks: change the timezone, manage Telegram templates, run or clear backups, reset runtime data, and export or import the full configuration.
</p>
<div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/40">
<p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Navigation</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Use the quick section navigator below to jump directly to each settings area.</p>
</div>
</div>
<div class="grid gap-3 sm:grid-cols-2 xl:w-[400px]">
<div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-900/70">
<p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Current Zone</p>
<p class="mt-3 text-sm font-bold text-slate-900 dark:text-white">{{ $currentTimezone }}</p>
</div>
<div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-900/70">
<p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Local Preview</p>
<p class="mt-3 text-sm font-bold text-slate-900 dark:text-white" id="local-preview-clock" data-timezone="{{ $currentTimezone }}">{{ $localNow->format('D, d M Y H:i:s') }}</p>
</div>
<div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-900/70">
<p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Managed Devices</p>
<p class="mt-3 text-2xl font-black text-slate-900 dark:text-white">{{ $maintenanceStats['device_count'] }}</p>
</div>
<div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-900/70">
<p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Primary Backup Root</p>
<p class="mt-3 text-sm font-bold text-slate-900 dark:text-white break-all">{{ $backupRootPrimary }}</p>
</div>
</div>
</div>
</section>

<section class="sticky top-20 z-[6] rounded-2xl border border-slate-200 bg-white/95 px-4 py-4 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/90 sm:px-5">
<div class="flex flex-col gap-1">
<p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Quick Navigation</p>
<p class="text-sm text-slate-600 dark:text-slate-300">Jump to the settings area you want to update.</p>
</div>
<div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-8">
<a class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-200 dark:hover:border-primary dark:hover:text-primary" href="#timezone-settings">Time Zone</a>
<a class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-200 dark:hover:border-primary dark:hover:text-primary" href="#telegram-templates">Telegram Templates</a>
<a class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-200 dark:hover:border-primary dark:hover:text-primary" href="#backup-operations">Backup Operations</a>
<a class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-200 dark:hover:border-primary dark:hover:text-primary" href="#backup-folder-permissions">Backup Permissions</a>
<a class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-200 dark:hover:border-primary dark:hover:text-primary" href="#backup-scripts">Backup Scripts</a>
<a class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-200 dark:hover:border-primary dark:hover:text-primary" href="#database-backup">Database Backup</a>
<a class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-200 dark:hover:border-primary dark:hover:text-primary" href="#system-cleanup">Cleanup</a>
<a class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-200 dark:hover:border-primary dark:hover:text-primary" href="#config-backup">Config Backup</a>
</div>
</section>

<div class="space-y-6">
<section class="scroll-mt-28 rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/85 sm:p-8" id="timezone-settings">
<form class="space-y-6" method="POST" action="{{ route('settings.update') }}">
@csrf
<div class="flex flex-col gap-2">
<p class="text-xs font-bold uppercase tracking-[0.24em] text-primary">Time Zone</p>
<h2 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">Global Time Zone</h2>
<p class="text-sm leading-6 text-slate-600 dark:text-slate-300">Choose the timezone used for timestamps, dashboards, human-readable dates, and logs across the admin interface.</p>
</div>
<details class="group rounded-2xl border border-slate-200 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-950/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-4 py-3">
<span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-400 text-[11px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
Time Zone Help
</span>
<span class="material-symbols-outlined text-[18px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-4 pb-4 pt-3 text-xs text-slate-600 dark:border-slate-800 dark:text-slate-300">
<ol class="list-decimal space-y-1 pl-5">
<li>Search by country/city first, then confirm the exact timezone ID in the dropdown.</li>
<li>Saved timezone affects dashboard dates, event timestamps, logs, and relative time labels.</li>
<li>After saving, refresh pages that display time-based data to verify expected offsets.</li>
</ol>
</div>
</details>
<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
<div class="space-y-4">
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="timezone-search">Search Time Zone or Country</label>
<div class="relative mt-2">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">search</span>
<input class="h-11 w-full rounded-2xl border border-slate-300 bg-white pl-10 pr-4 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" id="timezone-search" type="text" placeholder="e.g. Jordan, Amman"/>
</div>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="timezone">Time Zone</label>
<div class="relative mt-2">
<select class="h-11 w-full appearance-none rounded-2xl border border-slate-300 bg-white px-4 pr-11 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" id="timezone" name="timezone" data-selected-timezone="{{ $selectedTimezone }}">
</select>
<span class="material-symbols-outlined pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">expand_more</span>
</div>
</div>
<p class="text-xs text-slate-500" id="timezone-filter-summary">Showing all time zones.</p>
</div>
<div class="space-y-4">
<div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-950/50">
<p class="text-sm font-bold text-slate-900 dark:text-white">How it works</p>
<ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
<li>All admin requests load the saved timezone before controllers and views run.</li>
<li>Formatted dates and relative times use the selected zone automatically.</li>
<li>The saved value is cached and reused on future requests.</li>
</ul>
</div>
<div class="rounded-2xl border {{ $settingsReady ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/50 dark:bg-emerald-950/30' : 'border-amber-200 bg-amber-50 dark:border-amber-900/50 dark:bg-amber-950/30' }} p-5">
<p class="text-sm font-bold {{ $settingsReady ? 'text-emerald-800 dark:text-emerald-200' : 'text-amber-800 dark:text-amber-200' }}">
{{ $settingsReady ? 'Settings storage ready' : 'Migration required' }}
</p>
<p class="mt-2 text-sm {{ $settingsReady ? 'text-emerald-700 dark:text-emerald-100' : 'text-amber-700 dark:text-amber-100' }}">
{{ $settingsReady ? 'This server can save timezone changes immediately.' : 'Run php artisan migrate before saving the timezone on this server.' }}
</p>
</div>
</div>
</div>
<div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-5 dark:border-slate-800">
<a class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" href="{{ route('dashboard') }}">
<span class="material-symbols-outlined text-[18px]">arrow_back</span>
Back
</a>
<button class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white transition hover:bg-primary/90" type="submit">
<span class="material-symbols-outlined text-[18px]">save</span>
Save Time Zone
</button>
</div>
</form>
</section>

<section class="scroll-mt-28 rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/85 sm:p-8" id="telegram-templates">
<div class="flex flex-col gap-2">
<p class="text-xs font-bold uppercase tracking-[0.24em] text-primary">Telegram</p>
<h2 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">Global Event Types and Message Templates</h2>
<p class="text-sm leading-6 text-slate-600 dark:text-slate-300">These values apply system-wide. User forms now only choose devices, severities, and event types from this shared configuration.</p>
</div>
<details class="group mt-6 rounded-2xl border border-slate-200 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-950/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-4 py-3">
<span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-400 text-[11px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
Telegram Template Help
</span>
<span class="material-symbols-outlined text-[18px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-4 pb-4 pt-3 text-xs text-slate-600 dark:border-slate-800 dark:text-slate-300">
<ol class="list-decimal space-y-1 pl-5">
<li>Manage all allowed Telegram event tags in <span class="font-semibold">Custom Event Types</span>.</li>
<li>Use <span class="font-semibold">Default Template</span> as fallback, then override by severity only when needed.</li>
<li>Keep placeholders (like <code>{deviceName}</code>, <code>{eventId}</code>) exactly as shown so runtime substitution works.</li>
<li>After saving, test from user edit with <span class="font-semibold">Save + Send Telegram Test</span> to confirm delivery format.</li>
</ol>
</div>
</details>
<form class="mt-6 space-y-5" method="POST" action="{{ route('settings.telegram-templates.update') }}">
@csrf
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="telegram_event_types_custom">Custom Event Types</label>
<input class="mt-2 h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" id="telegram_event_types_custom" name="telegram_event_types_custom" type="text" value="{{ $telegramEventTypesCustomValue }}" placeholder="device.*, custom.tag"/>
<p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Comma-separated custom tags, supports wildcard like <code>device.*</code>.</p>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="telegram_template_default">Default Message Template (optional)</label>
<textarea class="mt-2 min-h-[110px] w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" id="telegram_template_default" name="telegram_template_default" placeholder="{headline}&#10;Description: {description}&#10;Detected at {detectedAt}&#10;&#10;switch_name: {switchName}&#10;Severity: {severityLabel}&#10;Severity: {severityBadge}&#10;Event ID: {eventId}">{{ $telegramTemplateDefaultValue }}</textarea>
<p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Used when no severity-specific template is set.</p>
</div>
<div class="grid gap-4 md:grid-cols-2">
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="telegram_template_low">Low Severity Template</label>
<textarea class="mt-2 min-h-[96px] w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" id="telegram_template_low" name="telegram_template_low" placeholder="{eventSymbol} {severitySymbol} [{severity}]&#10;{type}&#10;{message}">{{ $telegramTemplateLowValue }}</textarea>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="telegram_template_medium">Medium Severity Template</label>
<textarea class="mt-2 min-h-[96px] w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" id="telegram_template_medium" name="telegram_template_medium" placeholder="{eventSymbol} {severitySymbol} [{severity}]&#10;{type}&#10;{message}">{{ $telegramTemplateMediumValue }}</textarea>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="telegram_template_high">High Severity Template</label>
<textarea class="mt-2 min-h-[96px] w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" id="telegram_template_high" name="telegram_template_high" placeholder="{eventSymbol} {severitySymbol} [{severity}]&#10;{type}&#10;{message}">{{ $telegramTemplateHighValue }}</textarea>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="telegram_template_critical">Critical Severity Template</label>
<textarea class="mt-2 min-h-[96px] w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" id="telegram_template_critical" name="telegram_template_critical" placeholder="{eventSymbol} {severitySymbol} [{severity}]&#10;{type}&#10;{message}">{{ $telegramTemplateCriticalValue }}</textarea>
</div>
</div>
<p class="text-xs text-slate-500 dark:text-slate-400">Available placeholders: {headline}, {description}, {detectedAt}, {switchName}, {severityLabel}, {severityBadge}, {eventId}, {deviceName}, {deviceIp}, {port}, {severity}, {severitySymbol}, {eventSymbol}, {type}, {timestamp}, {message}. Symbols and emojis are supported.</p>
<div class="flex items-center justify-end border-t border-slate-200 pt-5 dark:border-slate-800">
<button class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white transition hover:bg-primary/90" type="submit">
<span class="material-symbols-outlined text-[18px]">save</span>
Save Telegram Templates
</button>
</div>
</form>
</section>

<section class="scroll-mt-28 rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/85 sm:p-8" id="backup-operations">
<div class="flex flex-col gap-2">
<p class="text-xs font-bold uppercase tracking-[0.24em] text-primary">Backups</p>
<h2 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">Backup Operations</h2>
<p class="text-sm leading-6 text-slate-600 dark:text-slate-300">Use one section for fleet backup actions and one-off device cleanup.</p>
</div>
<details class="group mt-6 rounded-2xl border border-slate-200 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-950/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-4 py-3">
<span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-400 text-[11px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
Backup Workflow Help
</span>
<span class="material-symbols-outlined text-[18px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-4 pb-4 pt-3 text-xs text-slate-600 dark:border-slate-800 dark:text-slate-300">
<ol class="list-decimal space-y-1 pl-5">
<li>Set scheduler interval first, then verify TFTP server address and folder permissions.</li>
<li>Use <span class="font-semibold">Run Backup Now</span> for an immediate fleet snapshot without waiting for cron.</li>
<li>Use clear actions carefully: <span class="font-semibold">Clear All</span> affects every device, while <span class="font-semibold">Clear One Device</span> is scoped.</li>
<li>Export scripts/config snapshots before running high-impact cleanup operations.</li>
</ol>
</div>
</details>

<div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
<div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/50">
<p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Backup Folders</p>
<p class="mt-3 text-2xl font-black text-slate-900 dark:text-white">{{ $maintenanceStats['backup_device_count'] }}</p>
</div>
<div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/50">
<p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Scheduler</p>
<p class="mt-3 text-sm font-bold text-slate-900 dark:text-white">{{ $backupScheduleLabel }}</p>
</div>
</div>

<div class="mt-6 rounded-2xl border border-slate-200 p-5 dark:border-slate-800">
<div class="flex flex-col gap-4">
<div>
<p class="text-sm font-bold text-slate-900 dark:text-white">Backup Scheduler</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Choose how often the fleet backup command should run. The cron job still runs every minute; this changes when `devices:run-backups` becomes due.</p>
</div>
<details class="group rounded-xl border border-slate-200 bg-slate-50/70 dark:border-slate-700 dark:bg-slate-900/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
<span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-slate-400 text-[10px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
Scheduler Help
</span>
<span class="material-symbols-outlined text-[16px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-3 pb-3 pt-2 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
Set the desired interval here; cron still runs every minute and executes backup only when interval is due.
</div>
</details>
<form class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-950/40 sm:p-4" method="POST" action="{{ route('settings.backup-schedule.update') }}">
@csrf
<div class="grid gap-3 sm:grid-cols-[minmax(0,220px)_auto] sm:items-center sm:justify-end">
<div class="w-full">
<label class="sr-only" for="backup_schedule_interval_hours">Backup Interval</label>
<select class="h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" id="backup_schedule_interval_hours" name="backup_schedule_interval_hours">
@foreach ($backupScheduleOptions as $hours)
<option value="{{ $hours }}" @selected((int) $backupScheduleIntervalHours === (int) $hours)>{{ $hours === 1 ? 'Every hour' : 'Every ' . $hours . ' hours' }}</option>
@endforeach
</select>
</div>
<button class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:whitespace-nowrap dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" type="submit">
<span class="material-symbols-outlined text-[18px]">schedule</span>
Save Backup Schedule
</button>
</div>
</form>
</div>
</div>

<p class="mt-6 text-xs font-bold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Access Control</p>
<div class="mt-6 rounded-2xl border border-slate-200 p-5 dark:border-slate-800" id="backup-folder-permissions">
<p class="text-sm font-bold text-slate-900 dark:text-white">Backup Folder Permissions</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Manually grant or revoke backup folder permissions, and save the TFTP server address used by backup scripts.</p>
<details class="group mt-3 rounded-xl border border-slate-200 bg-slate-50/70 dark:border-slate-700 dark:bg-slate-900/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
<span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-slate-400 text-[10px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
Permissions Help
</span>
<span class="material-symbols-outlined text-[16px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-3 pb-3 pt-2 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
<ol class="list-decimal space-y-1 pl-5">
<li>Save the TFTP server first so scripts and permission actions target the right host path.</li>
<li>Use grant before scheduled/manual backups if folder writes are failing.</li>
<li>Use revoke after maintenance windows to reduce accidental write risk.</li>
</ol>
</div>
</details>
@if ($errors->has('backup_permissions'))
<div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200">
{{ $errors->first('backup_permissions') }}
</div>
@endif
<div class="mt-5 grid gap-4 md:grid-cols-2">
<form class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/40 md:col-span-2" method="POST" action="{{ route('devices.backup-permissions.update') }}">
@csrf
<input type="hidden" name="operation" value="save_tftp"/>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="settings_backup_tftp_server_address">TFTP Server Address</label>
<input class="mt-2 h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" id="settings_backup_tftp_server_address" name="tftp_server_address" type="text" value="{{ old('tftp_server_address', $backupTftpServerAddress ?? '') }}" placeholder="e.g. 192.168.88.57"/>
<button class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" type="submit">
<span class="material-symbols-outlined text-[18px]">save</span>
Save TFTP Server
</button>
</form>
<form class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/40" method="POST" action="{{ route('devices.backup-permissions.update') }}" onsubmit="return confirm('Grant backup folder permissions now?');">
@csrf
<input type="hidden" name="operation" value="grant"/>
<input type="hidden" name="tftp_server_address" value="{{ old('tftp_server_address', $backupTftpServerAddress ?? '') }}"/>
<p class="text-sm font-bold text-slate-900 dark:text-white">Grant Backup Permissions</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Apply write permissions to backup roots and discovered device folders.</p>
<button class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white transition hover:bg-primary/90" type="submit">
<span class="material-symbols-outlined text-[18px]">lock_open</span>
Grant Backup Permissions
</button>
</form>
<form class="rounded-2xl border border-red-200 bg-red-50/70 p-4 dark:border-red-900/60 dark:bg-red-950/20" method="POST" action="{{ route('devices.backup-permissions.update') }}" onsubmit="return confirm('Revoke backup folder permissions now?');">
@csrf
<input type="hidden" name="operation" value="revoke"/>
<input type="hidden" name="tftp_server_address" value="{{ old('tftp_server_address', $backupTftpServerAddress ?? '') }}"/>
<p class="text-sm font-bold text-slate-900 dark:text-white">Revoke Backup Permissions</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Lock backup directories when manual writes are no longer needed.</p>
<button class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-red-300 bg-red-50 px-5 py-3 text-sm font-bold text-red-700 transition hover:bg-red-100 dark:border-red-900/60 dark:bg-red-950/20 dark:text-red-300 dark:hover:bg-red-950/35" type="submit">
<span class="material-symbols-outlined text-[18px]">lock</span>
Revoke Backup Permissions
</button>
</form>
</div>
<p class="mt-4 text-xs text-slate-500 dark:text-slate-400">Applies to configured backup roots and per-device backup folders.</p>
</div>

<p class="mt-6 text-xs font-bold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Execution</p>
<div class="mt-6 space-y-4">
<div class="rounded-2xl border border-slate-200 p-5 dark:border-slate-800">
<p class="text-sm font-bold text-slate-900 dark:text-white">Fleet Backup Actions</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Run all backups now or clear all existing backup files across every device folder.</p>
<details class="group mt-3 rounded-xl border border-slate-200 bg-slate-50/70 dark:border-slate-700 dark:bg-slate-900/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
<span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-slate-400 text-[10px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
Fleet Actions Help
</span>
<span class="material-symbols-outlined text-[16px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-3 pb-3 pt-2 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
Run backup creates current snapshots for all devices. Clear all removes saved backup files globally but keeps folder structure.
</div>
</details>
<div class="mt-4 grid gap-4 md:grid-cols-2">
<form class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/40" method="POST" action="{{ route('settings.backups.manage') }}" onsubmit="return confirm('Run backups for all devices now?');">
@csrf
<input type="hidden" name="operation" value="run_all"/>
<p class="text-sm font-bold text-slate-900 dark:text-white">Run Backup Now</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Starts the same fleet command used by the scheduler.</p>
<button class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white transition hover:bg-primary/90" type="submit">
<span class="material-symbols-outlined text-[18px]">backup</span>
Backup All Devices Now
</button>
</form>
<form class="rounded-2xl border border-red-200 bg-red-50/70 p-4 dark:border-red-900/60 dark:bg-red-950/20" method="POST" action="{{ route('settings.backups.manage') }}" onsubmit="return confirm('Clear all saved backup files for every device?');">
@csrf
<input type="hidden" name="operation" value="clear_all"/>
<p class="text-sm font-bold text-slate-900 dark:text-white">Clear All Backups</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Deletes saved backup files but keeps the folder structure in place.</p>
<button class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-red-300 bg-red-50 px-5 py-3 text-sm font-bold text-red-700 transition hover:bg-red-100 dark:border-red-900/60 dark:bg-red-950/20 dark:text-red-300 dark:hover:bg-red-950/35" type="submit">
<span class="material-symbols-outlined text-[18px]">delete_sweep</span>
Clear All Backups
</button>
</form>
</div>
</div>

<div class="rounded-2xl border border-slate-200 p-5 dark:border-slate-800">
<p class="text-sm font-bold text-slate-900 dark:text-white">Clear One Device Backup</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Reset one device backup folder without touching the rest of the fleet.</p>
<details class="group mt-3 rounded-xl border border-slate-200 bg-slate-50/70 dark:border-slate-700 dark:bg-slate-900/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
<span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-slate-400 text-[10px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
Single Device Help
</span>
<span class="material-symbols-outlined text-[16px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-3 pb-3 pt-2 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
Use this when only one device backup is corrupt/outdated and you do not want to impact the rest of the fleet.
</div>
</details>
<form class="mt-5 space-y-4" method="POST" action="{{ route('settings.backups.manage') }}" onsubmit="return confirm('Clear backups for the selected device only?');">
@csrf
<input type="hidden" name="operation" value="clear_device"/>
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="settings-backup-device">Device</label>
<select class="mt-2 h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" id="settings-backup-device" name="device_id" required>
<option value="">Select a device</option>
@foreach ($backupDevices as $backupDevice)
<option value="{{ $backupDevice['id'] }}" @selected((string) $selectedBackupDeviceId === (string) $backupDevice['id'])>
{{ $backupDevice['name'] }}{{ $backupDevice['model'] !== '' ? ' | ' . $backupDevice['model'] : '' }}{{ $backupDevice['folder'] ? ' | ' . $backupDevice['folder'] : '' }}
</option>
@endforeach
</select>
</div>
<button class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-5 py-3 text-sm font-bold text-amber-800 transition hover:bg-amber-100 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-300 dark:hover:bg-amber-950/35" type="submit">
<span class="material-symbols-outlined text-[18px]">folder_delete</span>
Clear Selected Device Backups
</button>
</form>
</div>

<p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Script and Database Archives</p>
<div class="rounded-2xl border border-slate-200 p-5 dark:border-slate-800" id="backup-scripts">
<p class="text-sm font-bold text-slate-900 dark:text-white">Backup Scripts</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Export all scripts to a ZIP backup, or import a scripts backup ZIP to restore/update automation scripts.</p>
<details class="group mt-3 rounded-xl border border-slate-200 bg-slate-50/70 dark:border-slate-700 dark:bg-slate-900/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
<span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-slate-400 text-[10px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
Scripts Backup Help
</span>
<span class="material-symbols-outlined text-[16px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-3 pb-3 pt-2 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
<ol class="list-decimal space-y-1 pl-5">
<li>Export scripts before upgrades or manual script edits.</li>
<li>Import overwrites files inside <code>scripts/</code>; use only trusted ZIP snapshots.</li>
<li>Keep one known-good ZIP for rollback after each change cycle.</li>
</ol>
</div>
</details>
@if ($errors->has('scripts_backup'))
<div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200">
{{ $errors->first('scripts_backup') }}
</div>
@endif
@if ($errors->has('scripts_import'))
<div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200">
{{ $errors->first('scripts_import') }}
</div>
@endif
<div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
<div class="rounded-2xl border border-slate-200 p-5 dark:border-slate-800">
<p class="text-sm font-bold text-slate-900 dark:text-white">Export Scripts Backup</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Download all files from the system scripts directory as a single ZIP archive.</p>
<div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/40">
<p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Scripts Source</p>
<p class="mt-2 break-all text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $backupScriptsRoot }}</p>
<p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ number_format($maintenanceStats['backup_script_count'] ?? count($backupScripts)) }} script files detected.</p>
</div>
<a class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white transition hover:bg-primary/90" href="{{ route('settings.scripts.export') }}">
<span class="material-symbols-outlined text-[18px]">download</span>
Export Scripts Backup
</a>
</div>

<div class="rounded-2xl border border-slate-200 p-5 dark:border-slate-800">
<p class="text-sm font-bold text-slate-900 dark:text-white">Import Scripts Backup</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Upload a ZIP exported from this panel to restore or update files in `scripts/`.</p>
<form class="mt-5 space-y-4" method="POST" action="{{ route('settings.scripts.import') }}" enctype="multipart/form-data" onsubmit="return confirm('Importing a scripts backup replaces the existing scripts files. Continue?');">
@csrf
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="scripts_backup_archive">Scripts Backup File</label>
<input class="mt-2 block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:file:bg-slate-800 dark:file:text-slate-200 dark:hover:file:bg-slate-700" id="scripts_backup_archive" name="scripts_backup_archive" type="file" accept=".zip,application/zip" required/>
</div>
<label class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/25 dark:text-amber-200">
<input class="mt-1 rounded border-amber-400 text-primary focus:ring-primary" type="checkbox" name="replace_existing_scripts" value="1" required/>
<span>I understand this will replace existing files inside `scripts/` with files from the uploaded backup ZIP.</span>
</label>
<button class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-5 py-3 text-sm font-bold text-amber-800 transition hover:bg-amber-100 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-300 dark:hover:bg-amber-950/35" type="submit">
<span class="material-symbols-outlined text-[18px]">upload</span>
Import Scripts Backup
</button>
</form>
</div>
</div>
</div>

<div class="rounded-2xl border border-slate-200 p-5 dark:border-slate-800" id="database-backup">
<p class="text-sm font-bold text-slate-900 dark:text-white">Database Backup to FTP</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Create a SQL backup for a standalone or virtual server database, then upload it directly to your FTP server.</p>
<details class="group mt-3 rounded-xl border border-slate-200 bg-slate-50/70 dark:border-slate-700 dark:bg-slate-900/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
<span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-slate-400 text-[10px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
Database Backup Help
</span>
<span class="material-symbols-outlined text-[16px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-3 pb-3 pt-2 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
<ol class="list-decimal space-y-1 pl-5">
<li>Select the correct server type and database name exactly as configured.</li>
<li>Validate FTP IP, username, and password before running to avoid partial backup runs.</li>
<li>Prefer running this during low activity windows for large databases.</li>
</ol>
</div>
</details>
@if ($errors->has('database_backup'))
<div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200">
{{ $errors->first('database_backup') }}
</div>
@endif
<form class="mt-5 space-y-4" method="POST" action="{{ route('settings.database-backup.run') }}" onsubmit="return confirm('Run database backup now and upload it to the FTP server?');">
@csrf
<div class="grid gap-4 md:grid-cols-2">
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="backup_server_type">Server Type</label>
<select class="mt-2 h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" id="backup_server_type" name="backup_server_type" required>
<option value="standalone_server" @selected(old('backup_server_type') === 'standalone_server')>Standalone Server</option>
<option value="virtual_server" @selected(old('backup_server_type') === 'virtual_server')>Virtual Server</option>
</select>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="database_name">Database Name</label>
<input class="mt-2 h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" id="database_name" name="database_name" type="text" value="{{ old('database_name') }}" placeholder="e.g. laravel_db" required>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="ftp_server_ip">FTP Server IP</label>
<input class="mt-2 h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" id="ftp_server_ip" name="ftp_server_ip" type="text" value="{{ old('ftp_server_ip') }}" placeholder="e.g. 192.168.88.57" required>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="ftp_username">FTP Username</label>
<input class="mt-2 h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" id="ftp_username" name="ftp_username" type="text" value="{{ old('ftp_username') }}" placeholder="FTP username" required>
</div>
<div class="md:col-span-2">
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="ftp_password">FTP Password</label>
<input class="mt-2 h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" id="ftp_password" name="ftp_password" type="password" autocomplete="new-password" placeholder="FTP password" required>
</div>
</div>
<button class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white transition hover:bg-primary/90" type="submit">
<span class="material-symbols-outlined text-[18px]">backup</span>
Backup Database to FTP
</button>
</form>
</div>
</div>
</section>

<section class="scroll-mt-28 rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/85 sm:p-8" id="system-cleanup">
<div class="flex flex-col gap-2">
<p class="text-xs font-bold uppercase tracking-[0.24em] text-primary">Cleanup</p>
<h2 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">Logs, Notifications, and Events</h2>
<p class="text-sm leading-6 text-slate-600 dark:text-slate-300">Clear runtime data without affecting saved configuration.</p>
</div>
<details class="group mt-6 rounded-2xl border border-slate-200 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-950/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-4 py-3">
<span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-400 text-[11px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
Cleanup Help
</span>
<span class="material-symbols-outlined text-[18px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-4 pb-4 pt-3 text-xs text-slate-600 dark:border-slate-800 dark:text-slate-300">
<ol class="list-decimal space-y-1 pl-5">
<li><span class="font-semibold">Clear Logs</span>, <span class="font-semibold">Clear Notifications</span>, and <span class="font-semibold">Clear Events</span> now support all-data cleanup and date-range cleanup.</li>
<li>Use the date range tools when you need targeted cleanup without wiping complete history.</li>
<li>Enable auto-cleanup per section with weekly, monthly, or yearly cadence.</li>
<li>Run export backups first if you need a historical record before cleanup.</li>
</ol>
</div>
</details>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
<div class="rounded-2xl border border-slate-200 p-5 dark:border-slate-800">
<div class="flex items-start justify-between gap-4">
<div>
<p class="text-sm font-bold text-slate-900 dark:text-white">Clear Logs</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Run full cleanup, targeted date-range telemetry cleanup, and configure automatic log retention cleanup.</p>
</div>
<div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-400">{{ $maintenanceStats['telemetry_count'] }} telemetry</div>
</div>
<div class="mt-5 space-y-3">
<form method="POST" action="{{ route('settings.logs.clear') }}" onsubmit="return confirm('Clear application and telemetry logs?');">
@csrf
<input type="hidden" name="operation" value="clear_all"/>
<button class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-slate-50 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" type="submit">
<span class="material-symbols-outlined text-[18px]">cleaning_services</span>
Clear Logs (All)
</button>
</form>

<form class="space-y-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700" method="POST" action="{{ route('settings.logs.clear') }}" onsubmit="return confirm('Clear telemetry logs in the selected date range?');">
@csrf
<input type="hidden" name="operation" value="clear_range"/>
<div class="grid gap-3">
<input class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" name="range_start" type="datetime-local" value="{{ $cleanupRangeStartValue }}" required/>
<input class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" name="range_end" type="datetime-local" value="{{ $cleanupRangeEndValue }}" required/>
</div>
<button class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" type="submit">
<span class="material-symbols-outlined text-[18px]">date_range</span>
Clear Logs (Date Range)
</button>
</form>

@php
    $logsAutoEnabled = ($cleanupLogsAuto['enabled'] ?? false) ? '1' : '0';
    $logsAutoFrequency = (string) ($cleanupLogsAuto['frequency'] ?? 'weekly');
@endphp
<form class="space-y-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700" method="POST" action="{{ route('settings.logs.clear') }}">
@csrf
<input type="hidden" name="operation" value="set_auto"/>
<div class="grid gap-3 sm:grid-cols-2">
<select class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" name="auto_cleanup_enabled">
<option value="1" @selected($logsAutoEnabled === '1')>Auto Cleanup: Enabled</option>
<option value="0" @selected($logsAutoEnabled === '0')>Auto Cleanup: Disabled</option>
</select>
<select class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" name="auto_cleanup_frequency">
@foreach ($cleanupFrequencyOptions as $frequencyValue => $frequencyLabel)
<option value="{{ $frequencyValue }}" @selected($logsAutoFrequency === $frequencyValue)>{{ $frequencyLabel }}</option>
@endforeach
</select>
</div>
<button class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary/90" type="submit">
<span class="material-symbols-outlined text-[18px]">autorenew</span>
Save Auto Cleanup
</button>
</form>
</div>
</div>

<div class="rounded-2xl border border-slate-200 p-5 dark:border-slate-800">
<div class="flex items-start justify-between gap-4">
<div>
<p class="text-sm font-bold text-slate-900 dark:text-white">Clear Notifications</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Run full cleanup, targeted date-range cleanup, and configure automatic alert and notification retention cleanup.</p>
</div>
<div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-400">{{ $maintenanceStats['alert_count'] + $maintenanceStats['notification_count'] }} items</div>
</div>
<div class="mt-5 space-y-3">
<form method="POST" action="{{ route('settings.notifications.clear') }}" onsubmit="return confirm('Delete all current alerts and notifications?');">
@csrf
<input type="hidden" name="operation" value="clear_all"/>
<button class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-slate-50 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" type="submit">
<span class="material-symbols-outlined text-[18px]">notifications_off</span>
Clear Notifications (All)
</button>
</form>

<form class="space-y-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700" method="POST" action="{{ route('settings.notifications.clear') }}" onsubmit="return confirm('Delete alerts and notifications in the selected date range?');">
@csrf
<input type="hidden" name="operation" value="clear_range"/>
<div class="grid gap-3">
<input class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" name="range_start" type="datetime-local" value="{{ $cleanupRangeStartValue }}" required/>
<input class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" name="range_end" type="datetime-local" value="{{ $cleanupRangeEndValue }}" required/>
</div>
<button class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" type="submit">
<span class="material-symbols-outlined text-[18px]">date_range</span>
Clear Notifications (Date Range)
</button>
</form>

@php
    $notificationsAutoEnabled = ($cleanupNotificationsAuto['enabled'] ?? false) ? '1' : '0';
    $notificationsAutoFrequency = (string) ($cleanupNotificationsAuto['frequency'] ?? 'weekly');
@endphp
<form class="space-y-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700" method="POST" action="{{ route('settings.notifications.clear') }}">
@csrf
<input type="hidden" name="operation" value="set_auto"/>
<div class="grid gap-3 sm:grid-cols-2">
<select class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" name="auto_cleanup_enabled">
<option value="1" @selected($notificationsAutoEnabled === '1')>Auto Cleanup: Enabled</option>
<option value="0" @selected($notificationsAutoEnabled === '0')>Auto Cleanup: Disabled</option>
</select>
<select class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" name="auto_cleanup_frequency">
@foreach ($cleanupFrequencyOptions as $frequencyValue => $frequencyLabel)
<option value="{{ $frequencyValue }}" @selected($notificationsAutoFrequency === $frequencyValue)>{{ $frequencyLabel }}</option>
@endforeach
</select>
</div>
<button class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary/90" type="submit">
<span class="material-symbols-outlined text-[18px]">autorenew</span>
Save Auto Cleanup
</button>
</form>
</div>
</div>

<div class="rounded-2xl border border-slate-200 p-5 dark:border-slate-800">
<div class="flex items-start justify-between gap-4">
<div>
<p class="text-sm font-bold text-slate-900 dark:text-white">Clear Events</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Delete event history globally or per selected device, with full and date-range cleanup modes plus auto-cleanup options.</p>
</div>
<div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-400">{{ ($maintenanceStats['interface_event_count'] ?? 0) + ($maintenanceStats['device_event_count'] ?? 0) }} rows</div>
</div>

<div class="mt-5 grid gap-3">
<form method="POST" action="{{ route('settings.events.manage') }}" onsubmit="return confirm('Delete all interface and device events for every device?');">
@csrf
<input type="hidden" name="operation" value="clear_all"/>
<button class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-red-300 bg-red-50 px-5 py-3 text-sm font-bold text-red-700 transition hover:bg-red-100 dark:border-red-900/60 dark:bg-red-950/25 dark:text-red-300 dark:hover:bg-red-950/40" type="submit">
<span class="material-symbols-outlined text-[18px]">delete_forever</span>
Clear Events (All Devices)
</button>
</form>

<form class="space-y-3" method="POST" action="{{ route('settings.events.manage') }}" onsubmit="return confirm('Delete all interface and device events for the selected device?');">
@csrf
<input type="hidden" name="operation" value="clear_device"/>
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="settings-events-device">Selected Device</label>
<select class="mt-2 h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" id="settings-events-device" name="event_device_id" required>
<option value="">Choose a device...</option>
@foreach ($backupDevices as $deviceOption)
<option value="{{ $deviceOption['id'] }}" @selected((string) $selectedCleanupEventDeviceId === (string) $deviceOption['id'])>{{ $deviceOption['name'] }}</option>
@endforeach
</select>
</div>
<button class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-slate-50 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" type="submit">
<span class="material-symbols-outlined text-[18px]">delete_sweep</span>
Clear Events (Selected Device)
</button>
</form>

<form class="space-y-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700" method="POST" action="{{ route('settings.events.manage') }}" onsubmit="return confirm('Delete event history in the selected date range for all devices?');">
@csrf
<input type="hidden" name="operation" value="clear_range"/>
<div class="grid gap-3">
<input class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" name="range_start" type="datetime-local" value="{{ $cleanupRangeStartValue }}" required/>
<input class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" name="range_end" type="datetime-local" value="{{ $cleanupRangeEndValue }}" required/>
</div>
<button class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" type="submit">
<span class="material-symbols-outlined text-[18px]">date_range</span>
Clear Events (Date Range)
</button>
</form>

<form class="space-y-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700" method="POST" action="{{ route('settings.events.manage') }}" onsubmit="return confirm('Delete event history in the selected date range for the selected device?');">
@csrf
<input type="hidden" name="operation" value="clear_device_range"/>
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="settings-events-device-range">Selected Device</label>
<select class="mt-2 h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" id="settings-events-device-range" name="event_device_id" required>
<option value="">Choose a device...</option>
@foreach ($backupDevices as $deviceOption)
<option value="{{ $deviceOption['id'] }}" @selected((string) $selectedCleanupEventDeviceId === (string) $deviceOption['id'])>{{ $deviceOption['name'] }}</option>
@endforeach
</select>
</div>
<div class="grid gap-3">
<input class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" name="range_start" type="datetime-local" value="{{ $cleanupRangeStartValue }}" required/>
<input class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" name="range_end" type="datetime-local" value="{{ $cleanupRangeEndValue }}" required/>
</div>
<button class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" type="submit">
<span class="material-symbols-outlined text-[18px]">history_toggle_off</span>
Clear Events (Device + Date Range)
</button>
</form>

@php
    $eventsAutoEnabled = ($cleanupEventsAuto['enabled'] ?? false) ? '1' : '0';
    $eventsAutoFrequency = (string) ($cleanupEventsAuto['frequency'] ?? 'weekly');
@endphp
<form class="space-y-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700" method="POST" action="{{ route('settings.events.manage') }}">
@csrf
<input type="hidden" name="operation" value="set_auto"/>
<div class="grid gap-3 sm:grid-cols-2">
<select class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" name="auto_cleanup_enabled">
<option value="1" @selected($eventsAutoEnabled === '1')>Auto Cleanup: Enabled</option>
<option value="0" @selected($eventsAutoEnabled === '0')>Auto Cleanup: Disabled</option>
</select>
<select class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" name="auto_cleanup_frequency">
@foreach ($cleanupFrequencyOptions as $frequencyValue => $frequencyLabel)
<option value="{{ $frequencyValue }}" @selected($eventsAutoFrequency === $frequencyValue)>{{ $frequencyLabel }}</option>
@endforeach
</select>
</div>
<button class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary/90" type="submit">
<span class="material-symbols-outlined text-[18px]">autorenew</span>
Save Auto Cleanup
</button>
</form>
</div>
</div>
</div>
</section>

<section class="scroll-mt-28 rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/85 sm:p-8" id="config-backup">
<div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
<div class="max-w-3xl">
<p class="text-xs font-bold uppercase tracking-[0.24em] text-primary">System Configuration Backup</p>
<h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900 dark:text-white">Export or Import Full Configuration</h2>
<p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
Export a JSON snapshot of users, devices, assignments, permissions, and system settings. Importing a snapshot replaces the current configuration and signs you out so the restored accounts can sign in cleanly.
</p>
</div>
<div class="flex flex-wrap gap-2">
@foreach ($configBackupTableLabels as $label)
<span class="inline-flex items-center rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300">{{ $label }}</span>
@endforeach
</div>
</div>
<details class="group mt-6 rounded-2xl border border-slate-200 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-950/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-4 py-3">
<span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-400 text-[11px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
Configuration Snapshot Help
</span>
<span class="material-symbols-outlined text-[18px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-4 pb-4 pt-3 text-xs text-slate-600 dark:border-slate-800 dark:text-slate-300">
<ol class="list-decimal space-y-1 pl-5">
<li>Export a fresh snapshot before any major user/device/permission changes.</li>
<li>Import replaces current records and signs out active sessions to reload restored accounts safely.</li>
<li>Use snapshots from trusted environments only; keep one rollback file before every import.</li>
</ol>
</div>
</details>

<div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
<div class="rounded-2xl border border-slate-200 p-5 dark:border-slate-800">
<p class="text-sm font-bold text-slate-900 dark:text-white">Export Snapshot</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Download the current configuration as a portable JSON file for backup, migration, or rollback.</p>
<div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/40">
<p class="text-sm font-semibold text-slate-900 dark:text-white">Included in export</p>
<ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
@foreach ($configBackupTableLabels as $label)
<li>{{ $label }}</li>
@endforeach
</ul>
</div>
<a class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white transition hover:bg-primary/90" href="{{ route('settings.system-config.export') }}">
<span class="material-symbols-outlined text-[18px]">download</span>
Export Configuration Backup
</a>
</div>

<div class="rounded-2xl border border-slate-200 p-5 dark:border-slate-800">
<p class="text-sm font-bold text-slate-900 dark:text-white">Import Snapshot</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Upload a previously exported JSON file to replace the current users, devices, assignments, permissions, and saved settings.</p>
<form class="mt-5 space-y-4" method="POST" action="{{ route('settings.system-config.import') }}" enctype="multipart/form-data" onsubmit="return confirm('Importing a configuration backup replaces the current configuration and signs you out. Continue?');">
@csrf
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-200" for="configuration_backup">Configuration Backup File</label>
<input class="mt-2 block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:file:bg-slate-800 dark:file:text-slate-200 dark:hover:file:bg-slate-700" id="configuration_backup" name="configuration_backup" type="file" accept=".json,application/json" required/>
</div>
<label class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/25 dark:text-amber-200">
<input class="mt-1 rounded border-amber-400 text-primary focus:ring-primary" type="checkbox" name="replace_existing" value="1" required/>
<span>I understand this replaces the current system configuration and will require signing in again after the import completes.</span>
</label>
<button class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-5 py-3 text-sm font-bold text-amber-800 transition hover:bg-amber-100 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-300 dark:hover:bg-amber-950/35" type="submit">
<span class="material-symbols-outlined text-[18px]">upload</span>
Import Configuration Backup
</button>
</form>
</div>
</div>
</section>
</div>
</div>
</main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var localPreviewClock = document.getElementById('local-preview-clock');
    var timezoneSelect = document.getElementById('timezone');
    var timezoneSearch = document.getElementById('timezone-search');
    var summary = document.getElementById('timezone-filter-summary');
    var selectedTimezone = timezoneSelect ? timezoneSelect.dataset.selectedTimezone || '' : '';
    var timezoneEntries = @json($timezoneEntries);

    function formatPreviewTime(date, timezone) {
        var formatter = new Intl.DateTimeFormat('en-GB', {
            weekday: 'short',
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
            timeZone: timezone
        });
        var parts = formatter.formatToParts(date);
        var values = {};

        parts.forEach(function (part) {
            if (part.type !== 'literal') {
                values[part.type] = part.value;
            }
        });

        return values.weekday + ', ' + values.day + ' ' + values.month + ' ' + values.year + ' ' + values.hour + ':' + values.minute + ':' + values.second;
    }

    function updateLocalPreviewClock() {
        if (!localPreviewClock) {
            return;
        }

        var previewTimezone = localPreviewClock.dataset.timezone || 'UTC';
        localPreviewClock.textContent = formatPreviewTime(new Date(), previewTimezone);
    }

    updateLocalPreviewClock();

    if (localPreviewClock) {
        window.setInterval(updateLocalPreviewClock, 1000);
    }

    if (!timezoneSelect || !timezoneSearch) {
        return;
    }

    function normalizeText(value) {
        return (value || '')
            .toLowerCase()
            .replace(/[_/,-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function findBestTimezoneMatch(entries, searchValue) {
        var normalizedSearch = normalizeText(searchValue);

        if (!normalizedSearch || !entries.length) {
            return entries[0] || null;
        }

        var bestEntry = null;
        var bestScore = -1;

        entries.forEach(function (entry) {
            var score = 0;
            var displayLabel = normalizeText(entry.display_label);
            var timezone = normalizeText(entry.timezone);
            var country = normalizeText(entry.country_name);
            var label = normalizeText(entry.label);

            if (displayLabel === normalizedSearch || timezone === normalizedSearch) {
                score = 100;
            } else if (displayLabel.indexOf(normalizedSearch) === 0 || country.indexOf(normalizedSearch) === 0) {
                score = 80;
            } else if (displayLabel.indexOf(normalizedSearch) !== -1 || timezone.indexOf(normalizedSearch) !== -1) {
                score = 60;
            } else if (label.indexOf(normalizedSearch) !== -1 || country.indexOf(normalizedSearch) !== -1) {
                score = 40;
            }

            if (score > bestScore) {
                bestScore = score;
                bestEntry = entry;
            }
        });

        return bestEntry || entries[0] || null;
    }

    function renderTimezoneOptions() {
        var searchValue = (timezoneSearch.value || '').trim().toLowerCase();

        timezoneSelect.innerHTML = '';

        var filtered = timezoneEntries.filter(function (entry) {
            if (searchValue === '') {
                return true;
            }

            var haystack = [
                entry.timezone,
                entry.country_name,
                entry.region,
                entry.label
            ].join(' ').toLowerCase();

            return haystack.indexOf(searchValue) !== -1;
        });

        var bestMatch = searchValue !== '' ? findBestTimezoneMatch(filtered, searchValue) : null;
        if (bestMatch) {
            selectedTimezone = bestMatch.timezone;
        } else if (filtered.length > 0 && !filtered.some(function (entry) {
            return entry.timezone === selectedTimezone;
        })) {
            selectedTimezone = filtered[0].timezone;
        }

        filtered.forEach(function (entry) {
            var option = document.createElement('option');
            option.value = entry.timezone;
            option.textContent = entry.display_label;
            option.selected = entry.timezone === selectedTimezone;
            timezoneSelect.appendChild(option);
        });

        if (timezoneSelect.options.length === 0) {
            var emptyOption = document.createElement('option');
            emptyOption.value = '';
            emptyOption.textContent = 'No time zones match this search.';
            emptyOption.disabled = true;
            emptyOption.selected = true;
            timezoneSelect.appendChild(emptyOption);
        }

        if (timezoneSelect.options.length > 0 && timezoneSelect.options[0].disabled !== true) {
            timezoneSelect.value = selectedTimezone;
            timezoneSelect.selectedIndex = Math.max(timezoneSelect.selectedIndex, 0);
        }

        summary.textContent = 'Showing ' + filtered.length + ' matching time zones.';
    }

    timezoneSelect.addEventListener('change', function () {
        selectedTimezone = timezoneSelect.value;
    });

    timezoneSearch.addEventListener('input', function () {
        renderTimezoneOptions();
    });

    renderTimezoneOptions();
});
</script>
</body>
</html>
