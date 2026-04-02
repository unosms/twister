<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/><meta name="csrf-token" content="{{ csrf_token() }}"/><meta name="app-base" content="{{ url('/') }}"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Notifications &amp; Real-time Alerts | Device Control Manager</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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
        .filled-icon {
            font-variation-settings: 'FILL' 1;
        }
    </style>
    @include('partials.admin_sidebar_styles')
    <script src="{{ asset('js/actions.js') }}" defer></script></head>
<body class="bg-background-light dark:bg-background-dark font-display text-[#0d121b] dark:text-white h-screen overflow-hidden">
<!-- Top Navigation Bar -->
<header class="flex items-center justify-between whitespace-nowrap border-b border-solid border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-[#1a2130] px-6 py-3 sticky top-0 z-50">
<div class="flex items-center gap-3">
<button class="h-10 w-10 flex items-center justify-center rounded-lg border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-[#1a2130] hover:bg-[#f0f2f7] dark:hover:bg-[#2a3447]" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
<span class="material-symbols-outlined text-[#4c669a]">menu</span>
</button>
<div class="flex items-center gap-4 text-primary">
<div class="size-8 bg-primary/10 rounded-lg flex items-center justify-center">
<span class="material-symbols-outlined text-primary">developer_board</span>
</div>
<h2 class="text-[#0d121b] dark:text-white text-lg font-bold leading-tight tracking-[-0.015em]">Device Control Manager</h2>
</div>
<label class="flex flex-col min-w-40 !h-10 max-w-64">
<div class="flex w-full flex-1 items-stretch rounded-lg h-full">
<div class="text-[#4c669a] flex border-none bg-[#f0f2f7] dark:bg-[#2a3447] items-center justify-center pl-4 rounded-l-lg" data-icon="MagnifyingGlass">
<span class="material-symbols-outlined text-xl">search</span>
</div>
<input class="form-input flex w-full min-w-0 flex-1 border-none bg-[#f0f2f7] dark:bg-[#2a3447] text-[#0d121b] dark:text-white focus:ring-0 h-full placeholder:text-[#4c669a] px-4 rounded-r-lg pl-2 text-base font-normal" placeholder="Search notifications..." value=""/>
</div>
</label>
</div>
<div class="flex flex-1 justify-end gap-4 items-center">
<div class="flex gap-2">
<a class="flex items-center justify-center rounded-lg h-10 w-10 bg-[#f0f2f7] dark:bg-[#2a3447] text-[#0d121b] dark:text-white" href="{{ route('support.index') }}">
<span class="material-symbols-outlined">help</span>
</a>
<div class="relative">
<button class="relative flex items-center justify-center rounded-lg h-10 w-10 bg-primary/10 text-primary" type="button" data-no-dispatch="true" data-notifications-menu-button data-notifications-endpoint="{{ route('notifications.menu') }}">
<span class="material-symbols-outlined filled-icon">notifications</span>
<span class="absolute top-2 right-2 flex h-2 w-2 rounded-full bg-red-500 hidden" data-notifications-indicator></span>
</button>
@include('partials.notifications_menu')
</div>
</div>
<div class="bg-center bg-no-repeat aspect-square bg-cover rounded-full size-10 border-2 border-primary/20" data-alt="User profile avatar" style='background-image: url("https://lh3.googleusercontent.com/aida-public/AB6AXuD9Jv_cQEgI2oWMXX9Ig71pILSVx0g7dSG4ayHCAUP_q6wX1FWt3PGaZkqqOWYpF8e0CyiGKtBEITdRG26pIhHXuK7yMfJmPgPDEeEDBdQBOvkLKEIFn2h93TNri67Zn3j4woYqrN-mG30WUzwxq9U5wk7BIrttwajTfX6qDwrp8nCelGno4Qx7DZ4mP14ptyT3N0YEg5T1sUzV4nmAMJDViqTiuo-3Rz5WADOFXh8wdFZrwPZdbdlhB-3bZGIpHnfG60TddYDxYA");'></div>
</div>
</header>
<div class="flex h-[calc(100vh-64px)] overflow-hidden">
<!-- Sidebar Navigation -->
@include('partials.admin_sidebar', ['extraContentView' => 'partials.notifications_sidebar_filters'])
<!-- Main Content Area -->
<main class="flex-1 overflow-y-auto">
<div class="max-w-[1200px] mx-auto p-6 md:p-8 space-y-6">
<section class="bg-white dark:bg-[#1a2130] border border-[#e7ebf3] dark:border-[#2a3447] rounded-xl p-4 md:p-5">
<nav class="flex items-center gap-2 mb-4">
<a class="text-[#4c669a] text-sm font-medium hover:text-primary" href="{{ route('dashboard') }}">Portal</a>
<span class="material-symbols-outlined text-[#4c669a] text-sm">chevron_right</span>
<span class="text-[#0d121b] dark:text-white text-sm font-semibold">Notifications</span>
</nav>
<details class="group mb-4 rounded-lg border border-[#cfd7e7] bg-[#f8faff] dark:border-[#2a3447] dark:bg-[#151c2a]">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
<span class="inline-flex items-center gap-2 text-sm font-semibold text-[#0d121b] dark:text-white">
<span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-[#4c669a] text-[11px] font-bold leading-none text-[#4c669a] dark:border-slate-400 dark:text-slate-200">i</span>
Notifications Help
</span>
<span class="material-symbols-outlined text-[18px] text-[#4c669a] transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-[#cfd7e7] px-3 pb-3 pt-2 text-xs text-[#4c669a] dark:border-[#2a3447] dark:text-slate-300">
<ol class="list-decimal space-y-1 pl-5">
<li>Use severity and status filters together to narrow active issues quickly.</li>
<li><span class="font-semibold">Investigate</span> keeps the alert for tracking, while <span class="font-semibold">Dismiss</span> closes it from the active queue.</li>
<li><span class="font-semibold">Mark all as read</span> updates read state only and does not delete alert records.</li>
<li>Use tab shortcuts (<span class="font-semibold">All</span>, <span class="font-semibold">Archived</span>, <span class="font-semibold">System Updates</span>) for faster triage.</li>
</ol>
</div>
</details>
<div class="flex flex-wrap items-center gap-3">
<form method="POST" action="{{ route('notifications.markAllRead') }}">
@csrf
<button class="inline-flex items-center gap-2 bg-[#f0f2f7] dark:bg-[#2a3447] text-[#0d121b] dark:text-white px-4 py-2 rounded-lg font-bold text-sm hover:brightness-95 transition-all" type="submit">
<span class="material-symbols-outlined text-lg">check_circle</span>
Mark all as read
</button>
</form>
<button class="inline-flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg font-bold text-sm hover:bg-primary/90 transition-all shadow-lg shadow-primary/20" type="submit" form="notification-filters">
<span class="material-symbols-outlined text-lg">filter_list</span>
Filter Settings
</button>
<form id="notification-filters" class="flex flex-wrap items-center gap-3 md:ml-auto" method="GET" action="{{ route('notifications.index') }}">
@foreach (($selectedDeviceIds ?? []) as $selectedDeviceId)
<input type="hidden" name="device_ids[]" value="{{ $selectedDeviceId }}"/>
@endforeach
<div class="relative">
<select class="pl-3 pr-8 py-2 bg-[#f0f2f7] dark:bg-[#2a3447] text-sm rounded-lg border border-transparent focus:border-primary/40 focus:ring-primary/30 appearance-none" name="severity">
<option value="all">Severity: All</option>
<option value="critical" @selected(($filters['severity'] ?? '') === 'critical')>Severity: Critical</option>
<option value="warning" @selected(($filters['severity'] ?? '') === 'warning')>Severity: Warning</option>
<option value="info" @selected(($filters['severity'] ?? '') === 'info')>Severity: Info</option>
</select>
<span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 text-base">expand_more</span>
</div>
<div class="relative">
<select class="pl-3 pr-8 py-2 bg-[#f0f2f7] dark:bg-[#2a3447] text-sm rounded-lg border border-transparent focus:border-primary/40 focus:ring-primary/30 appearance-none" name="status">
<option value="all">Status: All</option>
<option value="open" @selected(($filters['status'] ?? '') === 'open')>Status: Open</option>
<option value="closed" @selected(($filters['status'] ?? '') === 'closed')>Status: Closed</option>
</select>
<span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 text-base">expand_more</span>
</div>
<button class="px-4 py-2 rounded-lg bg-white dark:bg-[#232d40] border border-[#cfd7e7] dark:border-[#2f3b52] text-sm font-semibold hover:bg-[#f8faff] dark:hover:bg-[#2a3447]" type="submit">Apply Filters</button>
<a class="px-3 py-2 text-sm font-semibold text-[#4c669a] hover:text-primary" href="{{ route('notifications.index') }}">Clear</a>
</form>
</div>
</section>
@if (session('status'))
<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
{{ session('status') }}
</div>
@endif
@php
$statusFilter = $filters['status'] ?? 'all';
$severityFilter = $filters['severity'] ?? 'all';
@endphp
<section class="bg-white dark:bg-[#1a2130] border border-[#e7ebf3] dark:border-[#2a3447] rounded-xl overflow-hidden">
<div class="px-4 md:px-5 pt-4 md:pt-5">
<div class="flex items-center gap-6 border-b border-[#cfd7e7] dark:border-[#2a3447] overflow-x-auto">
<a class="flex items-center gap-2 border-b-[3px] {{ $statusFilter === 'all' ? 'border-primary text-[#0d121b] dark:text-white' : 'border-transparent text-[#4c669a]' }} pb-3 whitespace-nowrap" href="{{ route('notifications.index', array_merge(request()->except('page'), ['status' => 'all'])) }}">
<span class="text-sm font-bold tracking-[0.015em]">All Notifications</span>
<span class="text-[11px] font-bold px-2 py-0.5 rounded bg-[#f0f2f7] dark:bg-[#2a3447]">{{ $severityCounts['all'] ?? 0 }}</span>
</a>
<a class="flex items-center gap-2 border-b-[3px] {{ $statusFilter === 'closed' ? 'border-primary text-[#0d121b] dark:text-white' : 'border-transparent text-[#4c669a]' }} pb-3 whitespace-nowrap" href="{{ route('notifications.index', array_merge(request()->except('page'), ['status' => 'closed'])) }}">
<span class="text-sm font-bold tracking-[0.015em]">Archived</span>
<span class="text-[11px] font-bold px-2 py-0.5 rounded bg-[#f0f2f7] dark:bg-[#2a3447]">{{ $statusCounts['closed'] ?? 0 }}</span>
</a>
<a class="flex items-center gap-2 border-b-[3px] {{ $severityFilter === 'info' ? 'border-primary text-[#0d121b] dark:text-white' : 'border-transparent text-[#4c669a]' }} pb-3 whitespace-nowrap" href="{{ route('notifications.index', array_merge(request()->except('page'), ['severity' => 'info'])) }}">
<span class="text-sm font-bold tracking-[0.015em]">System Updates</span>
<span class="text-[11px] font-bold px-2 py-0.5 rounded bg-[#f0f2f7] dark:bg-[#2a3447]">{{ $severityCounts['info'] ?? 0 }}</span>
</a>
</div>
</div>
<div class="p-4 md:p-5 space-y-4">
@forelse ($alerts as $alert)
@php
$severity = strtolower($alert->severity ?? 'info');
$borderClass = match ($severity) {
    'critical', 'error' => 'border-red-500',
    'warning' => 'border-amber-500',
    default => 'border-primary',
};
$badgeClass = match ($severity) {
    'critical', 'error' => 'bg-red-100 text-red-600',
    'warning' => 'bg-amber-100 text-amber-700',
    default => 'bg-blue-100 text-primary',
};
$icon = match ($severity) {
    'critical', 'error' => 'error',
    'warning' => 'warning',
    default => 'info',
};
@endphp
<div class="group flex flex-col md:flex-row md:items-center gap-4 bg-[#f9fbff] dark:bg-[#121a28] p-4 md:p-5 rounded-xl border-l-4 {{ $borderClass }} hover:shadow-md transition-all">
<div class="flex items-start gap-4 flex-1 min-w-0">
<div class="flex-shrink-0 size-11 rounded-full {{ $badgeClass }} flex items-center justify-center">
<span class="material-symbols-outlined filled-icon">{{ $icon }}</span>
</div>
<div class="min-w-0">
<div class="flex flex-wrap items-center gap-2">
<h4 class="font-bold text-[#0d121b] dark:text-white">{{ $alert->title }}</h4>
<span class="px-2 py-0.5 rounded {{ $badgeClass }} text-[10px] font-bold uppercase tracking-wider">{{ strtoupper($severity) }}</span>
</div>
<p class="text-sm text-[#4c669a] mt-1 break-words">{{ $alert->message ?? 'No details available.' }}</p>
@if ($alert->device)
<p class="text-xs text-slate-500 mt-1">Device: {{ $alert->device->name }}</p>
@endif
</div>
</div>
<div class="md:text-right flex-shrink-0 flex flex-col md:items-end gap-2">
<span class="text-xs font-medium text-[#4c669a]">{{ $alert->created_at?->diffForHumans() ?? 'Just now' }}</span>
<div class="flex flex-wrap gap-2">
<form method="POST" action="{{ route('notifications.investigate') }}">
@csrf
<input type="hidden" name="alert_id" value="{{ $alert->id }}"/>
<button class="text-xs font-bold bg-red-500 text-white px-3 py-1.5 rounded" type="submit">Investigate</button>
</form>
<form method="POST" action="{{ route('notifications.dismiss') }}">
@csrf
<input type="hidden" name="alert_id" value="{{ $alert->id }}"/>
<button class="text-xs font-bold bg-white/10 text-slate-700 dark:text-white px-3 py-1.5 rounded border border-slate-200 dark:border-white/10" type="submit">Dismiss</button>
</form>
</div>
</div>
</div>
@empty
<div class="rounded-xl border border-dashed border-[#cfd7e7] dark:border-[#2a3447] bg-[#f8faff] dark:bg-[#121a28] p-8 text-center">
<div class="mx-auto mb-3 size-10 rounded-full bg-primary/10 text-primary flex items-center justify-center">
<span class="material-symbols-outlined">notifications_off</span>
</div>
<p class="text-sm font-semibold text-[#0d121b] dark:text-white">No alerts found</p>
<p class="text-sm text-[#4c669a] mt-1">Try changing severity or status filters.</p>
</div>
@endforelse
</div>
</section>
@if ($alerts instanceof \Illuminate\Pagination\LengthAwarePaginator)
<div class="flex justify-end gap-2">
<a class="px-3 py-1 border border-[#cfd7e7] dark:border-[#2a3447] rounded text-sm {{ $alerts->previousPageUrl() ? '' : 'opacity-50 pointer-events-none' }}" href="{{ $alerts->previousPageUrl() ?? '#' }}">Previous</a>
<span class="px-3 py-1 bg-primary text-white rounded text-sm">{{ $alerts->currentPage() }}</span>
<a class="px-3 py-1 border border-[#cfd7e7] dark:border-[#2a3447] rounded text-sm {{ $alerts->nextPageUrl() ? '' : 'opacity-50 pointer-events-none' }}" href="{{ $alerts->nextPageUrl() ?? '#' }}">Next</a>
</div>
@endif
</div>
<!-- Toast Notification (Real-time event) -->
@if ($alerts->first())
@php
$toastAlert = $alerts->first();
@endphp
<div class="fixed bottom-8 right-8 z-[100] w-96 animate-in slide-in-from-right duration-500">
<div class="bg-[#1a2130] dark:bg-[#0d121b] border-l-4 border-red-500 text-white p-4 rounded-lg shadow-2xl flex gap-4 ring-1 ring-white/10">
<div class="flex-shrink-0 mt-1">
<span class="material-symbols-outlined text-red-500 filled-icon animate-pulse">error</span>
</div>
<div class="flex-grow">
<div class="flex justify-between items-start">
<h5 class="text-sm font-bold uppercase tracking-wider text-red-500">Live Alert</h5>
<button class="text-white/40 hover:text-white" type="button" data-toast-close>
<span class="material-symbols-outlined text-lg">close</span>
</button>
</div>
<p class="text-sm font-semibold mt-1">{{ $toastAlert->title }}</p>
<p class="text-xs text-white/60 mt-0.5">{{ $toastAlert->message }}</p>
<div class="mt-3 flex gap-2">
<form method="POST" action="{{ route('notifications.investigate') }}">
@csrf
<input type="hidden" name="alert_id" value="{{ $toastAlert->id }}"/>
<button class="text-xs font-bold bg-red-500 text-white px-3 py-1.5 rounded" type="submit">Investigate</button>
</form>
<form method="POST" action="{{ route('notifications.dismiss') }}">
@csrf
<input type="hidden" name="alert_id" value="{{ $toastAlert->id }}"/>
<button class="text-xs font-bold bg-white/10 text-white px-3 py-1.5 rounded" type="submit">Dismiss</button>
</form>
</div>
</div>
</div>
</div>
@endif
</main>
</div>
<!-- Overlay Background (subtle gradient) -->
<div class="fixed inset-0 pointer-events-none bg-gradient-to-tr from-primary/5 via-transparent to-transparent -z-10"></div>
</body></html>










