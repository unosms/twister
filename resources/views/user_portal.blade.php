<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<meta name="app-base" content="{{ url('/') }}"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>User Portal | Twister Device Control</title>
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
                        "display": ["Inter", "sans-serif"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1rem", "3xl": "1.5rem", "full": "9999px"},
                },
            },
        }
    </script>
<style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .status-pulse {
            position: relative;
            display: inline-flex;
            height: 0.625rem;
            width: 0.625rem;
            border-radius: 9999px;
        }
        .status-pulse::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            animation: portal-pulse 2s infinite;
            opacity: 0.5;
        }
        .status-pulse-online {
            background: #10b981;
        }
        .status-pulse-online::after {
            background: #10b981;
        }
        .status-pulse-warning {
            background: #f59e0b;
        }
        .status-pulse-warning::after {
            background: #f59e0b;
        }
        .status-pulse-offline {
            background: #94a3b8;
        }
        .status-pulse-offline::after {
            background: #94a3b8;
        }
        .portal-surface {
            animation: portal-rise 0.35s ease-out;
        }
        @keyframes portal-pulse {
            0% {
                transform: scale(1);
                opacity: 0.5;
            }
            100% {
                transform: scale(2.8);
                opacity: 0;
            }
        }
        @keyframes portal-rise {
            0% {
                transform: translateY(10px);
                opacity: 0;
            }
            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
<script src="{{ asset('js/actions.js') }}" defer></script>
</head>
<body class="min-h-screen bg-background-light text-slate-900 dark:bg-background-dark dark:text-slate-100">
@php
$profileName = $authUser->name ?? 'Operator';
$profileRole = ($authUser->role ?? 'user') === 'admin' ? 'Administrator' : 'User';
$visibleDevicesOnPage = $devices->count();
$deviceTypeBreakdown = collect($deviceTypeBreakdown ?? []);
$attentionDevices = collect($attentionDevices ?? []);
$recentDevices = collect($recentDevices ?? []);
$notificationItems = collect([
    ['label' => 'Offline devices', 'count' => $offlineDevices ?? 0, 'tone' => 'rose'],
    ['label' => 'Warning devices', 'count' => $warningDevices ?? 0, 'tone' => 'amber'],
    ['label' => 'Stale devices', 'count' => $staleDevices ?? 0, 'tone' => 'slate'],
])->filter(static fn ($item) => ($item['count'] ?? 0) > 0)->values();
$commandTemplates = $commandTemplates ?? collect();
@endphp

<div class="relative min-h-screen overflow-hidden">
<div class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-[radial-gradient(circle_at_top_left,_rgba(19,91,236,0.18),_transparent_42%),radial-gradient(circle_at_top_right,_rgba(16,185,129,0.14),_transparent_30%)]"></div>

<header class="sticky top-0 z-50 border-b border-slate-200/70 bg-white/90 backdrop-blur-xl dark:border-slate-800 dark:bg-background-dark/85">
<div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
<div class="flex min-w-0 items-center gap-4">
<div class="flex items-center gap-3">
<div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary text-white shadow-lg shadow-primary/20">
<span class="material-symbols-outlined">settings_remote</span>
</div>
<div class="min-w-0">
<p class="text-sm font-black tracking-tight">Twister Device Control</p>
<p class="text-xs text-slate-500">User operations portal</p>
</div>
</div>
<div class="relative hidden lg:block w-[23rem]">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
<input class="h-11 w-full rounded-2xl border-none bg-slate-100 pl-10 pr-4 text-sm text-slate-700 focus:ring-2 focus:ring-primary/20 dark:bg-slate-800 dark:text-slate-100" placeholder="Search devices, firmware, IP or location..." type="text" data-live-search data-live-search-target="[data-portal-device-card],[data-portal-secondary-row]" data-sidebar-tip="Search visible devices by name, IP, model, location, firmware, or port tags."/>
</div>
</div>

<div class="flex items-center gap-3">
<div class="hidden md:flex items-center gap-2 rounded-full bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
<span class="material-symbols-outlined text-[16px] text-primary">shield</span>
{{ $accessibleScopeLabel ?? 'Device access' }}
</div>

<div class="relative">
<button class="relative flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800" type="button" data-portal-notifications data-sidebar-tip="Open portal notifications for offline, warning, and stale device counts.">
<span class="material-symbols-outlined">notifications</span>
@if (($portalNotificationCount ?? 0) > 0)
<span class="absolute right-1.5 top-1.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">{{ $portalNotificationCount }}</span>
@endif
</button>
<div id="portal-notifications" class="hidden absolute right-0 mt-3 w-80 rounded-3xl border border-slate-200 bg-white p-4 shadow-2xl shadow-slate-200/60 dark:border-slate-700 dark:bg-slate-900 dark:shadow-black/30">
<div class="flex items-start justify-between gap-3">
<div>
<p class="text-xs font-bold uppercase tracking-[0.28em] text-slate-400">Attention</p>
<h3 class="mt-2 text-base font-black text-slate-900 dark:text-white">Portal notifications</h3>
</div>
<span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $portalNotificationCount ?? 0 }}</span>
</div>
<div class="mt-4 space-y-2">
@forelse ($notificationItems as $item)
<div class="flex items-center justify-between rounded-2xl border border-slate-200 px-3 py-3 dark:border-slate-700">
<div class="flex items-center gap-2">
<span class="h-2.5 w-2.5 rounded-full {{ $item['tone'] === 'rose' ? 'bg-rose-500' : ($item['tone'] === 'amber' ? 'bg-amber-500' : 'bg-slate-400') }}"></span>
<span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $item['label'] }}</span>
</div>
<span class="text-sm font-black text-slate-900 dark:text-white">{{ $item['count'] }}</span>
</div>
@empty
<div class="rounded-2xl bg-emerald-50 px-4 py-4 text-sm text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300">
All visible devices are currently healthy.
</div>
@endforelse
</div>
<p class="mt-4 text-xs text-slate-400">Last telemetry refresh: {{ $lastUpdatedAt?->diffForHumans() ?? 'No recent updates' }}</p>
</div>
</div>

<div class="flex items-center gap-3 rounded-full border border-slate-200 bg-white px-2 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
<div class="hidden text-right sm:block">
<p class="text-xs font-bold leading-none">{{ $profileName }}</p>
<p class="mt-1 text-[11px] text-slate-500">{{ $profileRole }}</p>
</div>
<div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-sm font-black text-primary">
{{ strtoupper(substr($profileName, 0, 1)) }}
</div>
</div>
</div>
</div>
</header>

<main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
@if (session('status'))
<div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-300">
{{ session('status') }}
</div>
@endif
@if ($errors->any())
<div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-300">
<p class="font-semibold">Could not update Telegram settings.</p>
<ul class="mt-2 list-disc pl-5 space-y-1">
@foreach ($errors->all() as $error)
<li>{{ $error }}</li>
@endforeach
</ul>
</div>
@endif
<section class="portal-surface relative overflow-hidden rounded-[2rem] border border-slate-200/70 bg-white/90 p-6 shadow-xl shadow-slate-200/40 dark:border-slate-800 dark:bg-slate-900/60 dark:shadow-black/20 lg:p-8">
<div class="pointer-events-none absolute inset-y-0 right-0 w-1/2 bg-[radial-gradient(circle_at_center,_rgba(19,91,236,0.16),_transparent_60%)]"></div>
<div class="relative grid gap-8 xl:grid-cols-[minmax(0,1.15fr)_22rem] xl:items-start">
<div>
<div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.28em] text-slate-600 dark:bg-slate-800 dark:text-slate-300">
<span class="material-symbols-outlined text-[16px] text-primary">monitor_heart</span>
Personal Command Center
</div>
<h1 class="mt-5 text-4xl font-black tracking-tight text-slate-950 dark:text-white sm:text-5xl">My Devices</h1>
<p class="mt-4 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300">
Track device health, watch for stale endpoints, and launch approved commands from one richer dashboard.
</p>

<div class="mt-6 flex flex-wrap gap-3 text-xs font-semibold">
<span class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-2 text-primary">
<span class="material-symbols-outlined text-[16px]">hub</span>
{{ $totalDevices ?? 0 }} total devices
</span>
<span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-2 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300">
<span class="material-symbols-outlined text-[16px]">check_circle</span>
{{ $activeDevices ?? 0 }} active now
</span>
<span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-2 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
<span class="material-symbols-outlined text-[16px]">bolt</span>
{{ $commandTemplateCount ?? 0 }} approved commands
</span>
</div>

<div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
<div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-950/40">
<p class="text-[11px] font-bold uppercase tracking-[0.24em] text-slate-400">Fleet Health</p>
<p class="mt-3 text-3xl font-black text-slate-950 dark:text-white">{{ $healthPercent ?? 0 }}%</p>
<p class="mt-2 text-sm text-slate-500">Healthy devices available to you right now.</p>
</div>
<div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-950/40">
<p class="text-[11px] font-bold uppercase tracking-[0.24em] text-slate-400">Recently Seen</p>
<p class="mt-3 text-3xl font-black text-slate-950 dark:text-white">{{ $recentlySeenDevices ?? 0 }}</p>
<p class="mt-2 text-sm text-slate-500">Reported in during the last 15 minutes.</p>
</div>
<div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-950/40">
<p class="text-[11px] font-bold uppercase tracking-[0.24em] text-slate-400">Needs Attention</p>
<p class="mt-3 text-3xl font-black text-amber-600">{{ ($warningDevices ?? 0) + ($offlineDevices ?? 0) }}</p>
<p class="mt-2 text-sm text-slate-500">Warning or offline devices in your scope.</p>
</div>
<div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-950/40">
<p class="text-[11px] font-bold uppercase tracking-[0.24em] text-slate-400">Stale Devices</p>
<p class="mt-3 text-3xl font-black text-slate-950 dark:text-white">{{ $staleDevices ?? 0 }}</p>
<p class="mt-2 text-sm text-slate-500">Not updated for more than one day.</p>
</div>
</div>
</div>

<aside class="rounded-[1.75rem] border border-slate-200 bg-slate-50/90 p-5 dark:border-slate-800 dark:bg-slate-950/50">
<p class="text-[11px] font-bold uppercase tracking-[0.28em] text-slate-400">Access Profile</p>
<h2 class="mt-2 text-xl font-black text-slate-950 dark:text-white">{{ $profileRole }}</h2>
<p class="mt-2 text-sm text-slate-500">{{ $accessibleScopeLabel ?? 'Device access' }}</p>

<div class="mt-5 rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-900">
<div class="flex items-center justify-between text-xs font-bold uppercase tracking-[0.22em] text-slate-400">
<span>Availability</span>
<span>{{ $healthPercent ?? 0 }}%</span>
</div>
<div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
<div class="h-full rounded-full bg-gradient-to-r from-primary via-sky-500 to-emerald-400" style="width: {{ $healthPercent ?? 0 }}%"></div>
</div>
<p class="mt-3 text-sm text-slate-500">Last device update {{ $lastUpdatedAt?->diffForHumans() ?? 'not available yet' }}</p>
</div>

<div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
<div class="flex items-center justify-between">
<p class="text-sm font-bold text-slate-900 dark:text-white">Top device types</p>
<span class="text-xs text-slate-400">{{ $deviceTypeBreakdown->count() }} tracked</span>
</div>
<div class="mt-4 space-y-3">
@forelse ($deviceTypeBreakdown as $entry)
@php
$share = ($totalDevices ?? 0) > 0 ? (int) round(($entry['count'] / $totalDevices) * 100) : 0;
@endphp
<div>
<div class="flex items-center justify-between text-sm">
<span class="font-semibold text-slate-700 dark:text-slate-200">{{ $entry['type'] }}</span>
<span class="text-slate-400">{{ $entry['count'] }}</span>
</div>
<div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
<div class="h-full rounded-full bg-slate-900 dark:bg-slate-200" style="width: {{ $share }}%"></div>
</div>
</div>
@empty
<p class="text-sm text-slate-500">No device type distribution available yet.</p>
@endforelse
</div>
</div>

<div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
<div class="flex items-center justify-between gap-3">
<p class="text-sm font-bold text-slate-900 dark:text-white">Telegram Settings</p>
<span class="text-xs text-slate-400">User portal</span>
</div>
<details class="group mt-3 rounded-xl border border-slate-200 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-800/40">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
<span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
<span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-400 text-[11px] font-bold leading-none text-slate-600 dark:border-slate-500 dark:text-slate-200">i</span>
Telegram Setup Help
</span>
<span class="material-symbols-outlined text-[18px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-slate-200 px-3 pb-3 pt-2 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
<ol class="list-decimal space-y-1 pl-5">
<li>Create your bot in Telegram with <code>@BotFather</code> using <code>/newbot</code>.</li>
<li>Copy the returned token and send one message to your bot or target group.</li>
<li>Open <code>https://api.telegram.org/bot&lt;YOUR_BOT_TOKEN&gt;/getUpdates</code> and copy <code>chat.id</code>.</li>
<li>Private chats use positive IDs; groups/channels are usually negative (<code>-100...</code>).</li>
</ol>
</div>
</details>
<form class="mt-4 space-y-3" method="POST" action="{{ route('portal.telegram-settings.update') }}">
@csrf
<div class="space-y-1">
<label class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400" for="portal_telegram_chat_id">Telegram Chat ID</label>
<input
id="portal_telegram_chat_id"
class="h-11 w-full rounded-xl border-slate-200 bg-white text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white"
name="telegram_chat_id"
type="text"
value="{{ old('telegram_chat_id', $authUser->telegram_chat_id ?? '') }}"
placeholder="123456789 or -1001234567890"
/>
</div>
<div class="space-y-1">
<label class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400" for="portal_telegram_bot_token">Telegram Bot Token (optional)</label>
<div class="relative">
<input
id="portal_telegram_bot_token"
class="h-11 w-full rounded-xl border-slate-200 bg-white pr-11 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white"
name="telegram_bot_token"
type="password"
value="{{ old('telegram_bot_token', $authUser->telegram_bot_token ?? '') }}"
placeholder="123456:ABC..."
/>
<button class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-slate-600 dark:hover:text-slate-200" type="button" data-toggle="password" data-target="#portal_telegram_bot_token" data-no-dispatch="true" aria-label="Toggle Telegram bot token visibility" aria-pressed="false">
<span class="material-symbols-outlined text-[20px]">visibility_off</span>
</button>
</div>
</div>
<p class="text-xs text-slate-500">Leave token blank to use the global TELEGRAM_BOT_TOKEN.</p>
<button class="inline-flex h-10 items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-white transition hover:bg-primary/90" type="submit" data-sidebar-tip="Save your personal Telegram chat ID and optional bot token for portal alerts.">
Save Telegram Settings
</button>
</form>
</div>
</aside>
</div>
</section>

<section class="mt-8 grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
<div class="portal-surface rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
<div class="flex items-center justify-between gap-3">
<div>
<p class="text-[11px] font-bold uppercase tracking-[0.28em] text-slate-400">Attention Queue</p>
<h2 class="mt-2 text-xl font-black text-slate-950 dark:text-white">Devices needing review</h2>
</div>
<span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-700 dark:bg-amber-950/30 dark:text-amber-300">{{ $attentionDevices->count() }}</span>
</div>
<div class="mt-5 space-y-3">
@forelse ($attentionDevices as $device)
@php
$status = strtolower((string) ($device->status ?? 'offline'));
$tone = $status === 'warning'
    ? 'border-amber-200 bg-amber-50 dark:border-amber-900/40 dark:bg-amber-950/20'
    : ($status === 'error'
        ? 'border-rose-200 bg-rose-50 dark:border-rose-900/40 dark:bg-rose-950/20'
        : 'border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950/40');
@endphp
<div class="flex items-center justify-between gap-4 rounded-2xl border px-4 py-4 {{ $tone }}" data-portal-secondary-row data-live-search-suggest-text="{{ $device->name }}" data-live-search-text="{{ $device->name }} {{ $device->serial_number }} {{ $device->type }} {{ $device->location }} {{ $device->ip_address }}">
<div class="min-w-0">
<p class="text-sm font-bold text-slate-900 dark:text-white">{{ $device->name }}</p>
<p class="mt-1 text-xs text-slate-500">{{ strtoupper((string) ($device->type ?? 'Device')) }} | {{ $device->location ?: 'No location set' }}</p>
</div>
<div class="text-right">
<p class="text-sm font-bold capitalize {{ $status === 'warning' ? 'text-amber-700 dark:text-amber-300' : ($status === 'error' ? 'text-rose-700 dark:text-rose-300' : 'text-slate-600 dark:text-slate-300') }}">{{ $status }}</p>
<p class="mt-1 text-xs text-slate-400">{{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Never seen' }}</p>
</div>
</div>
@empty
<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-5 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-300">
Everything in your scope is currently stable.
</div>
@endforelse
</div>
</div>

<div class="portal-surface rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
<div class="flex items-center justify-between gap-3">
<div>
<p class="text-[11px] font-bold uppercase tracking-[0.28em] text-slate-400">Recent Activity</p>
<h2 class="mt-2 text-xl font-black text-slate-950 dark:text-white">Recently active devices</h2>
</div>
<span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">{{ $recentDevices->count() }}</span>
</div>
<div class="mt-5 space-y-3">
@forelse ($recentDevices as $device)
<div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950/40" data-portal-secondary-row data-live-search-suggest-text="{{ $device->name }}" data-live-search-text="{{ $device->name }} {{ $device->serial_number }} {{ $device->type }} {{ $device->location }} {{ $device->ip_address }}">
<div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
<span class="material-symbols-outlined text-[20px]">router</span>
</div>
<div class="min-w-0 flex-1">
<p class="text-sm font-bold text-slate-900 dark:text-white">{{ $device->name }}</p>
<p class="mt-1 text-xs text-slate-500">{{ $device->ip_address ?: 'No IP recorded' }} | {{ $device->firmware_version ?: 'Firmware unknown' }}</p>
</div>
<div class="text-right">
<p class="text-sm font-bold text-slate-900 dark:text-white">{{ ucfirst((string) ($device->status ?? 'unknown')) }}</p>
<p class="mt-1 text-xs text-slate-400">{{ $device->last_seen_at?->diffForHumans() ?? 'No data' }}</p>
</div>
</div>
@empty
<div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-950/40">
No recent activity has been recorded yet.
</div>
@endforelse
</div>
</div>
</section>

<section class="portal-surface mt-8 rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/60 lg:p-6">
<div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
<div>
<p class="text-[11px] font-bold uppercase tracking-[0.28em] text-slate-400">Inventory</p>
<h2 class="mt-2 text-2xl font-black text-slate-950 dark:text-white">Command-ready device catalog</h2>
<p class="mt-2 text-sm text-slate-500">Filter by status, search across key metadata, and launch only the commands assigned to your account.</p>
</div>
<div class="flex flex-wrap items-center gap-3 text-sm">
<span class="rounded-full bg-slate-100 px-3 py-2 font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Showing <span data-portal-visible-count>{{ $visibleDevicesOnPage }}</span> devices on this page</span>
<span class="rounded-full bg-slate-100 px-3 py-2 font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Last update {{ $lastUpdatedAt?->diffForHumans() ?? 'unknown' }}</span>
</div>
</div>

<div class="mt-6 flex gap-2 overflow-x-auto pb-2 no-scrollbar">
<button class="flex h-11 shrink-0 items-center justify-center gap-2 rounded-full bg-primary px-5 text-sm font-semibold text-white transition" type="button" data-device-filter="all" data-sidebar-tip="Show all devices available in your current page and scope.">
All Devices
<span class="rounded-full bg-white/15 px-2 py-0.5 text-xs">{{ $totalDevices ?? 0 }}</span>
</button>
<button class="flex h-11 shrink-0 items-center justify-center gap-2 rounded-full border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:border-primary dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200" type="button" data-device-filter="online" data-sidebar-tip="Filter to devices that are currently online.">
Online
<span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300">{{ $activeDevices ?? 0 }}</span>
</button>
<button class="flex h-11 shrink-0 items-center justify-center gap-2 rounded-full border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:border-primary dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200" type="button" data-device-filter="warning" data-sidebar-tip="Filter to devices with warning status and possible issues.">
Warning
<span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700 dark:bg-amber-950/30 dark:text-amber-300">{{ $warningDevices ?? 0 }}</span>
</button>
<button class="flex h-11 shrink-0 items-center justify-center gap-2 rounded-full border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:border-primary dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200" type="button" data-device-filter="offline" data-sidebar-tip="Filter to devices that are currently offline.">
Offline
<span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ $offlineDevices ?? 0 }}</span>
</button>
</div>

@if ($devices->count() > 0)
<div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
@foreach ($devices as $device)
@php
$status = strtolower((string) ($device->status ?? 'offline'));
$statusLabel = match ($status) {
    'online' => 'Running',
    'warning' => 'Warning',
    'error' => 'Error',
    default => 'Offline',
};
$statusClasses = match ($status) {
    'online' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300',
    'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/30 dark:text-amber-300',
    'error' => 'bg-rose-100 text-rose-700 dark:bg-rose-950/30 dark:text-rose-300',
    default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
};
$pulseClass = match ($status) {
    'online' => 'status-pulse-online',
    'warning' => 'status-pulse-warning',
    default => 'status-pulse-offline',
};
$lastSeenText = $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'No recent check-in';
$assignedPortsExpression = trim((string) ($deviceAllowedPortsByDevice[(int) $device->id] ?? ''));
$assignedPortsTokens = $assignedPortsExpression !== ''
    ? array_values(array_filter(array_map(static fn ($token) => trim((string) $token), preg_split('/\s*,\s*/', $assignedPortsExpression) ?: []), static fn ($token) => $token !== ''))
    : [];
@endphp
<article class="group overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl dark:border-slate-800 dark:bg-slate-950/40" data-device-status="{{ $status }}" data-portal-device-card data-live-search-suggest-text="{{ $device->name }}" data-live-search-text="{{ $device->name }} {{ $device->serial_number }} {{ $device->type }} {{ $device->location }} {{ $device->ip_address }} {{ $device->firmware_version }} {{ $assignedPortsExpression }}">
<div class="relative border-b border-slate-100 bg-[radial-gradient(circle_at_top_left,_rgba(19,91,236,0.16),_transparent_55%),linear-gradient(135deg,_#f8fafc,_#eef4ff)] px-5 py-5 dark:border-slate-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(19,91,236,0.18),_transparent_45%),linear-gradient(135deg,_#111827,_#0f172a)]">
<div class="flex items-start justify-between gap-4">
<div class="min-w-0">
<p class="text-[11px] font-bold uppercase tracking-[0.24em] text-slate-400">ID {{ $device->serial_number ?? $device->id }}</p>
<h3 class="mt-2 truncate text-xl font-black text-slate-950 transition group-hover:text-primary dark:text-white">{{ $device->name }}</h3>
<p class="mt-2 text-sm text-slate-500">{{ strtoupper((string) ($device->type ?? 'Device')) }}{{ $device->model ? ' | ' . $device->model : '' }}</p>
</div>
<span class="rounded-full px-3 py-1 text-xs font-bold {{ $statusClasses }}">{{ $statusLabel }}</span>
</div>

<div class="mt-5 flex items-center justify-between text-sm">
<div class="flex items-center gap-2 font-semibold text-slate-700 dark:text-slate-200">
<span class="status-pulse {{ $pulseClass }}"></span>
{{ ucfirst($status) }}
</div>
<span class="text-xs text-slate-500">{{ $lastSeenText }}</span>
</div>
</div>

<div class="p-5">
<div class="grid grid-cols-2 gap-3 text-sm">
<div class="rounded-2xl bg-slate-50 px-3 py-3 dark:bg-slate-900">
<p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">IP Address</p>
<p class="mt-2 font-semibold text-slate-800 dark:text-slate-100">{{ $device->ip_address ?: 'Not set' }}</p>
</div>
<div class="rounded-2xl bg-slate-50 px-3 py-3 dark:bg-slate-900">
<p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Location</p>
<p class="mt-2 font-semibold text-slate-800 dark:text-slate-100">{{ $device->location ?: 'Unassigned' }}</p>
</div>
<div class="rounded-2xl bg-slate-50 px-3 py-3 dark:bg-slate-900">
<p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Firmware</p>
<p class="mt-2 font-semibold text-slate-800 dark:text-slate-100">{{ $device->firmware_version ?: 'Unknown' }}</p>
</div>
<div class="rounded-2xl bg-slate-50 px-3 py-3 dark:bg-slate-900">
<p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Command Access</p>
@php
    $deviceCommandTemplates = collect($commandTemplatesByDevice[$device->id] ?? $commandTemplates ?? collect())->values();
@endphp
<p class="mt-2 font-semibold text-slate-800 dark:text-slate-100">{{ $deviceCommandTemplates->count() }} available</p>
</div>
<div class="col-span-2 rounded-2xl bg-slate-50 px-3 py-3 dark:bg-slate-900">
<div class="flex items-start justify-between gap-3">
<p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Assigned Ports</p>
<span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300">
{{ count($assignedPortsTokens) > 0 ? count($assignedPortsTokens) . ' scoped' : 'All ports' }}
</span>
</div>
@if (count($assignedPortsTokens) > 0)
<div class="mt-2 flex flex-wrap gap-1.5">
@foreach (array_slice($assignedPortsTokens, 0, 6) as $token)
<span class="inline-flex items-center rounded-full bg-primary/10 px-2 py-1 text-[11px] font-semibold text-primary">{{ $token }}</span>
@endforeach
@if (count($assignedPortsTokens) > 6)
<span class="inline-flex items-center rounded-full bg-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200">+{{ count($assignedPortsTokens) - 6 }} more</span>
@endif
</div>
@else
<p class="mt-2 text-sm font-semibold text-emerald-700 dark:text-emerald-300">No per-port restriction.</p>
@endif
</div>
</div>

<div class="mt-5">
<label class="text-[11px] font-bold uppercase tracking-[0.24em] text-slate-400">Launch Approved Command</label>
<select class="mt-2 h-11 w-full rounded-2xl border-slate-200 bg-white text-sm font-medium text-slate-800 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-white" data-command-select data-device-id="{{ $device->id }}" data-port-scoped="{{ ($devicePortScopedLookup[(int) $device->id] ?? false) ? '1' : '0' }}" data-sidebar-tip="Choose an approved command for this device and its allowed scope.">
@if ($deviceCommandTemplates->isNotEmpty())
<option value="" disabled selected>Select a command</option>
@foreach ($deviceCommandTemplates as $template)
<option value="{{ $template->action_key }}">{{ $template->name }}</option>
@endforeach
@else
<option value="" disabled selected>No commands approved for this device</option>
@endif
</select>
</div>

@if ((($canViewAssignedDeviceGraphs ?? false) && ($graphAccessibleDeviceLookup[(int) $device->id] ?? false))
    || (($canViewAssignedDeviceEvents ?? false) && ($eventAccessibleDeviceLookup[(int) $device->id] ?? false)))
<div class="mt-4 flex flex-wrap gap-2">
@if (($canViewAssignedDeviceGraphs ?? false) && ($graphAccessibleDeviceLookup[(int) $device->id] ?? false))
<a class="inline-flex items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-xs font-bold text-blue-700 transition hover:bg-blue-100 dark:border-blue-900/50 dark:bg-blue-950/20 dark:text-blue-200 dark:hover:bg-blue-900/30" href="{{ route('portal.devices.graphs', ['id' => $device->id]) }}" data-sidebar-tip="Open interactive traffic charts for this device.">
<span class="material-symbols-outlined text-[16px]">monitoring</span>
View Device Graphs
</a>
@endif
@if (($canViewAssignedDeviceEvents ?? false) && ($eventAccessibleDeviceLookup[(int) $device->id] ?? false))
<a class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-bold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-900/50 dark:bg-emerald-950/20 dark:text-emerald-200 dark:hover:bg-emerald-900/30" href="{{ route('portal.devices.events', ['device' => $device->id]) }}" data-sidebar-tip="Open the filtered event timeline for this device.">
<span class="material-symbols-outlined text-[16px]">event</span>
View Device Events
</a>
@endif
</div>
@endif
</div>
</article>
@endforeach
</div>

<div data-portal-empty class="mt-6 hidden rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-400">
No devices match the current search and status filters on this page.
</div>
@else
<div class="mt-6 rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900/40">
<div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
<span class="material-symbols-outlined">devices</span>
</div>
<h3 class="mt-4 text-lg font-black text-slate-900 dark:text-white">No devices assigned yet</h3>
<p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Once devices are assigned or permitted to this account, they will appear here with command access.</p>
</div>
@endif
</section>

<div class="mt-8 flex justify-center">
@if ($devices->nextPageUrl())
<a class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-6 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" href="{{ $devices->nextPageUrl() }}" data-sidebar-tip="Load the next page of devices in your assigned scope.">
<span>Load More Devices</span>
<span class="material-symbols-outlined text-[18px]">arrow_forward</span>
</a>
@else
<button class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-6 py-3 text-sm font-bold text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-500" disabled>
<span>All visible devices loaded</span>
</button>
@endif
</div>
</main>

<footer class="border-t border-slate-200 bg-white/85 px-4 py-6 dark:border-slate-800 dark:bg-background-dark/85 sm:px-6 lg:px-8">
<div class="mx-auto flex max-w-7xl flex-col gap-4 md:flex-row md:items-center md:justify-between">
<div>
<p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Twister Device Control</p>
<p class="mt-1 text-xs text-slate-500">Operational visibility for assigned devices and approved command access.</p>
</div>
<div class="flex flex-wrap items-center gap-4 text-sm text-slate-500">
<span>{{ $totalDevices ?? 0 }} visible devices</span>
<span>{{ $commandTemplateCount ?? 0 }} command templates</span>
<a class="inline-flex items-center gap-2 rounded-full px-3 py-2 font-semibold text-red-500 transition hover:bg-red-50 dark:hover:bg-red-950/20" href="{{ route('auth.logout') }}" data-sidebar-tip="Sign out from the user portal.">
<span class="material-symbols-outlined text-[18px]">logout</span>
Logout
</a>
</div>
</div>
</footer>
</div>
</body>
</html>

