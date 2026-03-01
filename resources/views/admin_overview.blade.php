<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<meta name="app-base" content="{{ url('/') }}"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Device Control Manager - Admin Dashboard</title>
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
    $profileName = $authUser->name ?? 'Admin';
    $profileRole = ($authUser->role ?? 'admin') === 'admin' ? 'Super Admin' : 'User';
@endphp
<div class="flex h-full">
@include('partials.admin_sidebar', ['sidebarAuthUser' => $authUser ?? null])

<main class="flex-1 min-w-0 flex flex-col overflow-y-auto">
<header class="min-h-16 flex-shrink-0 border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-background-dark/80 backdrop-blur-md px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-between gap-3 sticky top-0 z-10">
<div class="flex items-center gap-3 min-w-0 flex-1">
<button class="h-10 w-10 flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/40 hover:bg-slate-100 dark:hover:bg-slate-800" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
<span class="material-symbols-outlined text-slate-600 dark:text-slate-300">menu</span>
</button>
<div class="relative hidden sm:flex items-center flex-1 max-w-xl">
<span class="material-symbols-outlined absolute left-3 text-slate-400 text-[20px]">search</span>
<input class="w-full bg-slate-100 dark:bg-slate-800 border-none rounded-lg py-2 pl-10 pr-4 text-sm focus:ring-2 focus:ring-primary/50 placeholder:text-slate-500" placeholder="Search devices, users or logs..." type="text" data-live-search data-live-search-target="[data-dashboard-device-row]"/>
</div>
</div>
<div class="flex items-center gap-2 sm:gap-4">
<div class="relative">
<button class="relative h-10 w-10 flex items-center justify-center rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" type="button" data-no-dispatch="true" data-notifications-menu-button data-notifications-endpoint="{{ route('notifications.menu') }}">
<span class="material-symbols-outlined text-slate-600 dark:text-slate-400">notifications</span>
<span class="absolute top-2 right-2 h-2 w-2 bg-red-500 border-2 border-white dark:border-background-dark rounded-full hidden" data-notifications-indicator></span>
</button>
@include('partials.notifications_menu')
</div>
<div class="hidden sm:block h-8 w-[1px] bg-slate-200 dark:border-slate-800 mx-1"></div>
<div class="flex items-center gap-3">
<div class="text-right hidden md:block">
<p class="text-xs font-bold leading-none">{{ $profileName }}</p>
<p class="text-[10px] text-slate-500 mt-1">{{ $profileRole }}</p>
</div>
@include('partials.user_avatar', ['user' => $authUser ?? null, 'name' => $profileName, 'sizeClass' => 'h-10 w-10'])
</div>
</div>
</header>

<div class="relative p-4 sm:p-6 lg:p-8 space-y-6 lg:space-y-8">
@php
$totalUsers = $totalUsers ?? 0;
$totalDevices = $totalDevices ?? 0;
$onlineDevices = $onlineDevices ?? 0;
$activeAlerts = $activeAlerts ?? 0;
$offlineDevices = max(0, $totalDevices - $onlineDevices);
$uptimePercent = $totalDevices > 0 ? (int) round(($onlineDevices / $totalDevices) * 100) : 0;
$healthTone = match (true) {
    $uptimePercent >= 90 => 'text-emerald-700 bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-300',
    $uptimePercent >= 70 => 'text-amber-700 bg-amber-100 dark:bg-amber-900/30 dark:text-amber-300',
    default => 'text-rose-700 bg-rose-100 dark:bg-rose-900/30 dark:text-rose-300',
};
@endphp

<div class="pointer-events-none absolute inset-x-4 top-4 h-24 rounded-3xl bg-gradient-to-r from-primary/20 via-sky-200/40 to-emerald-200/40 blur-3xl"></div>

@if (session('status'))
<div class="relative rounded-2xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-700 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-300">
{{ session('status') }}
</div>
@endif

<section class="relative overflow-hidden rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white/95 dark:bg-slate-900/40 shadow-sm">
<div class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-sky-100/60 dark:bg-sky-900/20 blur-2xl"></div>
<div class="absolute -left-16 -bottom-20 h-56 w-56 rounded-full bg-emerald-100/60 dark:bg-emerald-900/20 blur-2xl"></div>
<div class="relative p-5 sm:p-6 lg:p-7 flex flex-col lg:flex-row lg:items-end justify-between gap-5">
<div class="space-y-4">
<span class="inline-flex items-center gap-2 rounded-full bg-slate-100 dark:bg-slate-800 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">
<span class="material-symbols-outlined text-[16px] text-primary">monitor_heart</span>
Network Operations
</span>
<div>
<h2 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white">Dashboard Overview</h2>
<p class="text-slate-600 dark:text-slate-300 text-sm mt-1 max-w-2xl">Real-time status of your device ecosystem, with quick access to assignment and incident actions.</p>
</div>
<div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
<span class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 {{ $healthTone }}">
<span class="material-symbols-outlined text-[15px]">monitoring</span>
{{ $uptimePercent }}% uptime
</span>
<span class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
<span class="material-symbols-outlined text-[15px]">portable_wifi_off</span>
{{ $offlineDevices }} offline
</span>
<span class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">
<span class="material-symbols-outlined text-[15px]">notification_important</span>
{{ $activeAlerts }} active alerts
</span>
</div>
</div>
<div class="w-full lg:w-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-2">
@if (($authUser?->isSuperAdmin() ?? false))
<a class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 transition-colors" href="{{ route('users.create') }}">
<span class="material-symbols-outlined text-[18px]">person_add</span>
Add User
</a>
@else
<span class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-bold text-slate-400 dark:text-slate-500 cursor-not-allowed">
<span class="material-symbols-outlined text-[18px]">person_add</span>
Add User
</span>
@endif
<a class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-primary rounded-lg text-sm font-bold text-white shadow-lg shadow-primary/20 hover:bg-primary/90 transition-all" href="{{ route('devices.create') }}">
<span class="material-symbols-outlined text-[18px]">add</span>
Add Device
</a>
</div>
</div>
</section>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5">
<div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 px-5 py-4 shadow-sm">
<div class="flex items-start justify-between">
<div>
<p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Users</p>
<p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ $totalUsers }}</p>
<p class="mt-1 text-xs text-slate-500">Portal users with access</p>
</div>
<span class="h-9 w-9 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-600 flex items-center justify-center">
<span class="material-symbols-outlined text-[20px]">group</span>
</span>
</div>
</div>
<div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 px-5 py-4 shadow-sm">
<div class="flex items-start justify-between">
<div>
<p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Devices</p>
<p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ $totalDevices }}</p>
<p class="mt-1 text-xs text-slate-500">Onboarded infrastructure</p>
</div>
<span class="h-9 w-9 rounded-xl bg-sky-50 dark:bg-sky-900/20 text-sky-600 flex items-center justify-center">
<span class="material-symbols-outlined text-[20px]">devices</span>
</span>
</div>
</div>
<div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 px-5 py-4 shadow-sm">
<div class="flex items-start justify-between">
<div>
<p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Online Now</p>
<p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ $onlineDevices }}</p>
<p class="mt-1 text-xs text-slate-500">Healthy endpoints responding</p>
</div>
<span class="h-9 w-9 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 flex items-center justify-center">
<span class="material-symbols-outlined text-[20px]">lan</span>
</span>
</div>
</div>
<div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 px-5 py-4 shadow-sm">
<div class="flex items-start justify-between">
<div>
<p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Active Alerts</p>
<p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ $activeAlerts }}</p>
<p class="mt-1 text-xs text-slate-500">Requires follow-up</p>
</div>
<span class="h-9 w-9 rounded-xl bg-rose-50 dark:bg-rose-900/20 text-rose-600 flex items-center justify-center">
<span class="material-symbols-outlined text-[20px]">warning</span>
</span>
</div>
</div>
</div>

<section class="bg-white dark:bg-slate-900/40 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
<div class="px-4 sm:px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
<div>
<h3 class="font-bold text-slate-900 dark:text-white">Recent Devices</h3>
<p class="text-xs text-slate-500 mt-0.5">Latest {{ $devices->count() }} devices and their current status.</p>
</div>
<div class="flex items-center gap-3">
<span class="hidden md:inline text-[11px] text-slate-400">Horizontal scroll enabled on smaller screens.</span>
<a class="text-primary text-xs font-bold hover:underline" href="{{ route('devices.index') }}">View All</a>
</div>
</div>
<div class="overflow-x-auto">
<table class="min-w-[760px] w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50 dark:bg-slate-800/70 border-b border-slate-200 dark:border-slate-800">
<th class="px-6 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Device</th>
<th class="px-6 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Type</th>
<th class="px-6 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Status</th>
<th class="px-6 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Assigned</th>
<th class="px-6 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Last Seen</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100 dark:divide-slate-800">
@forelse ($devices as $device)
@php
$status = strtolower($device->status ?? 'offline');
$statusClass = match ($status) {
    'online' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    'error' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    default => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
};
@endphp
<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors" data-dashboard-device-row>
<td class="px-6 py-4">
<div class="flex items-center gap-3">
<div class="h-9 w-9 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500">
<span class="material-symbols-outlined text-[18px]">router</span>
</div>
<div>
<p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $device->name }}</p>
<p class="text-xs text-slate-500">{{ $device->serial_number ?: 'N/A' }}</p>
</div>
</div>
</td>
<td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $device->type ?: 'N/A' }}</td>
<td class="px-6 py-4">
<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $statusClass }}">{{ ucfirst($status) }}</span>
</td>
<td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $device->assignedUser?->name ?? 'Unassigned' }}</td>
<td class="px-6 py-4 text-sm text-slate-500">{{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'N/A' }}</td>
</tr>
@empty
<tr>
<td class="px-6 py-6 text-sm text-slate-500" colspan="5">No devices found.</td>
</tr>
@endforelse
</tbody>
</table>
</div>
</section>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 sm:gap-6">
<section class="xl:col-span-2 bg-white dark:bg-slate-900/40 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
<div class="px-4 sm:px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-slate-500 text-[18px]">history</span>
<h3 class="font-bold text-slate-900 dark:text-white">Recent Activity</h3>
</div>
@if (!$showAllActivity)
<a class="text-primary text-xs font-bold hover:underline" href="{{ route('dashboard', ['activity' => 'all']) }}">View All</a>
@else
<a class="text-primary text-xs font-bold hover:underline" href="{{ route('dashboard') }}">Show Less</a>
@endif
</div>
<div class="divide-y divide-slate-100 dark:divide-slate-800">
@forelse ($recentActivity as $activity)
@php
$actorName = $activity->actor?->name ?? 'System';
$actionText = $activity->action ?? 'performed an action';
$meta = is_array($activity->metadata ?? null) ? $activity->metadata : [];
$subjectLabel = $meta['name'] ?? $meta['label'] ?? $meta['device'] ?? $meta['user'] ?? null;
if (!$subjectLabel && $activity->subject_type) {
    $subjectLabel = class_basename($activity->subject_type) . ($activity->subject_id ? " #{$activity->subject_id}" : '');
}
$actionLower = strtolower($actionText);
$icon = 'history';
$iconBg = 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400';
if (str_contains($actionLower, 'assign')) {
    $icon = 'assignment_turned_in';
} elseif (str_contains($actionLower, 'create') || str_contains($actionLower, 'add')) {
    $icon = 'add_circle';
    $iconBg = 'bg-blue-50 dark:bg-blue-900/20 text-blue-600';
} elseif (str_contains($actionLower, 'update') || str_contains($actionLower, 'edit')) {
    $icon = 'edit';
    $iconBg = 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600';
} elseif (str_contains($actionLower, 'command') || str_contains($actionLower, 'execute')) {
    $icon = 'terminal';
    $iconBg = 'bg-orange-50 dark:bg-orange-900/20 text-orange-600';
}
@endphp
<div class="p-4 sm:p-5 flex items-start gap-4 hover:bg-slate-50 dark:hover:bg-slate-800/80 transition-colors">
<div class="h-10 w-10 rounded-full {{ $iconBg }} flex items-center justify-center flex-shrink-0">
<span class="material-symbols-outlined text-[20px]">{{ $icon }}</span>
</div>
<div class="flex-1 min-w-0">
<p class="text-sm text-slate-900 dark:text-slate-200">
<span class="font-bold">{{ $actorName }}</span> {{ $actionText }}
@if ($subjectLabel)
<span class="font-bold">{{ $subjectLabel }}</span>
@endif
.</p>
<p class="text-xs text-slate-500 mt-1">{{ $activity->occurred_at?->diffForHumans() ?? 'Just now' }}</p>
</div>
</div>
@empty
<div class="p-8 text-center">
<p class="text-xs text-slate-400">No recent activity yet.</p>
</div>
@endforelse
</div>
</section>

<section class="bg-white dark:bg-slate-900/40 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm flex flex-col">
<div class="px-4 sm:px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-rose-50/60 dark:bg-rose-950/10">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-rose-500 text-[20px]">error</span>
<h3 class="font-bold text-slate-900 dark:text-white">Critical Alerts</h3>
</div>
<span class="px-2 py-0.5 rounded text-[10px] font-black bg-rose-500 text-white uppercase tracking-tighter">{{ $activeAlerts }} Active</span>
</div>
<div class="flex-1 overflow-y-auto">
<div class="divide-y divide-slate-100 dark:divide-slate-800">
@forelse ($alerts as $alert)
@php
$severity = strtolower($alert->severity ?? 'info');
$badgeClass = match ($severity) {
    'critical', 'error' => 'bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400',
    'warning' => 'bg-orange-100 dark:bg-orange-900/40 text-orange-600 dark:text-orange-400',
    default => 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
};
@endphp
<div class="p-4 sm:p-5">
<div class="flex justify-between items-start gap-3">
<div>
<p class="text-sm font-bold text-slate-900 dark:text-white">{{ $alert->device?->name ?? $alert->title }}</p>
<p class="text-xs text-slate-500 mt-0.5">{{ $alert->message ?? $alert->title }}</p>
</div>
<span class="px-2 py-1 rounded {{ $badgeClass }} text-[10px] font-bold">{{ strtoupper($severity) }}</span>
</div>
</div>
@empty
<div class="p-8 text-center border-t border-slate-100 dark:border-slate-800">
<p class="text-xs text-slate-400">No active alerts.</p>
</div>
@endforelse
</div>
</div>
</section>
</div>
</div>
</main>
</div>
</body>
</html>
