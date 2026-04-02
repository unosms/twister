<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<meta name="app-base" content="{{ url('/') }}"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Device Assignment Wizard</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
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
        body.sidebar-collapsed [data-wizard-footer] {
            left: 4.5rem !important;
        }
</style>
@include('partials.admin_sidebar_styles')
<script src="{{ asset('js/actions.js') . '?v=' . filemtime(public_path('js/actions.js')) }}" defer></script>
</head>
<body class="bg-background-light dark:bg-background-dark text-[#0d121b] dark:text-gray-100 h-screen overflow-hidden">
@php
$deviceNavActive = request()->routeIs('devices.*');
$deviceControlActive = request()->routeIs('devices.index');
$deviceDetailsActive = request()->routeIs('devices.details');
$assignmentsActive = request()->routeIs('devices.wizard');
$supportActive = request()->routeIs('support.index');
$settingsActive = request()->routeIs('settings.*');
$profileName = $authUser->name ?? 'Admin';
$profileRole = ($authUser->role ?? 'admin') === 'admin' ? 'Super Admin' : 'User';
$selectedUserId = $selectedUser?->id;
@endphp

<div class="flex h-screen overflow-hidden">
@include('partials.admin_sidebar', ['sidebarAuthUser' => $authUser ?? null])

<main class="flex-1 flex flex-col min-h-0 overflow-y-auto">
<header class="h-16 border-b border-[#e7ebf3] dark:border-gray-800 bg-white dark:bg-background-dark flex items-center justify-between px-8 shrink-0">
<div class="flex items-center gap-3 flex-1">
<button class="h-10 w-10 flex items-center justify-center rounded-lg border border-[#e7ebf3] dark:border-gray-800 bg-white dark:bg-background-dark hover:bg-gray-50 dark:hover:bg-gray-800" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
<span class="material-symbols-outlined text-gray-500">menu</span>
</button>
<div class="relative w-full max-w-md">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xl">search</span>
<input class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-lg pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-primary/20" placeholder="Search users or devices..." type="text" data-live-search data-live-search-target="[data-user-row],[data-device-card]"/>
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
<div class="hidden md:flex items-center gap-3">
<div class="text-right">
<p class="text-xs font-bold leading-none">{{ $profileName }}</p>
<p class="text-[10px] text-slate-500 mt-1">{{ $profileRole }}</p>
</div>
@include('partials.user_avatar', ['user' => $authUser ?? null, 'name' => $profileName, 'sizeClass' => 'h-10 w-10'])
</div>
</div>
</header>

<div class="flex-grow flex flex-col max-w-[1400px] mx-auto w-full px-4 sm:px-10 py-6 pb-32">
<div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-6">
<div class="flex flex-col gap-2">
<h1 class="text-[#0d121b] dark:text-white text-4xl font-black leading-tight tracking-[-0.033em]">Assign Devices Wizard</h1>
<p class="text-[#4c669a] text-base font-normal">Step 1: Link hardware assets to your organization's personnel.</p>
<details class="group mt-2 rounded-lg border border-[#cfd7e7] bg-white/80 p-0 dark:border-gray-700 dark:bg-gray-800/50">
<summary class="list-none flex cursor-pointer items-center justify-between gap-3 px-3 py-2">
<span class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
<span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-gray-400 text-[11px] font-bold leading-none text-gray-600 dark:border-gray-500 dark:text-gray-200">i</span>
Assignment Wizard Help
</span>
<span class="material-symbols-outlined text-[18px] text-gray-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="border-t border-[#cfd7e7] px-3 pb-3 pt-2 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300">
<ol class="list-decimal space-y-1 pl-5">
<li>Select a user first, then choose available devices in the right panel.</li>
<li>Devices locked to another user must be unassigned first from device/user management before reassigning here.</li>
<li>Use filters and search to narrow large inventories by type, ID, or serial.</li>
</ol>
</div>
</details>
</div>
<div class="flex items-center gap-x-4 bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
<div class="flex items-center gap-3">
<div class="flex items-center justify-center size-8 rounded-full bg-primary text-white">
<span class="material-symbols-outlined text-lg">person_add</span>
</div>
<p class="text-primary text-sm font-bold">Selection</p>
</div>
<div class="w-12 h-[2px] bg-gray-200 dark:bg-gray-700"></div>
<div class="flex items-center gap-3 opacity-50">
<div class="flex items-center justify-center size-8 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
<span class="material-symbols-outlined text-lg">verified</span>
</div>
<p class="text-sm font-medium">Review &amp; Confirm</p>
</div>
</div>
</div>

<div class="flex flex-1 flex-col xl:flex-row gap-6 min-h-[600px]">
<div class="xl:w-1/3 flex flex-col bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden shadow-sm min-h-[320px]">
<div class="p-4 border-b border-gray-100 dark:border-gray-800">
<h3 class="text-lg font-bold mb-3">1. Select User</h3>
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xl">search</span>
<input class="w-full pl-10 pr-4 py-2 bg-background-light dark:bg-gray-800 border-none rounded-lg focus:ring-2 focus:ring-primary/50 text-sm" placeholder="Search users..." type="text" data-user-search/>
</div>
</div>
<div class="flex-grow overflow-y-auto p-2 space-y-1">
@forelse ($users as $user)
@php
$isSelected = $selectedUser && $selectedUser->id === $user->id;
$initial = strtoupper(substr($user->name ?? 'U', 0, 1));
@endphp
<a class="flex items-center gap-3 p-3 rounded-lg border {{ $isSelected ? 'bg-primary/10 border-primary/20' : 'border-transparent hover:bg-gray-50 dark:hover:bg-gray-800' }} transition-colors" href="{{ route('devices.wizard', ['user_id' => $user->id]) }}" data-user-row data-user-name="{{ $user->name }}" data-user-email="{{ $user->email }}">
<div class="size-10 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold">
{{ $initial }}
</div>
<div class="flex-grow">
<p class="text-sm font-bold {{ $isSelected ? 'text-primary' : '' }}">{{ $user->name }}</p>
<p class="text-xs text-[#4c669a]">{{ $user->email }}</p>
</div>
@if ($isSelected)
<span class="material-symbols-outlined text-primary">check_circle</span>
@else
<span class="text-xs text-slate-400 font-semibold">{{ $user->devices_count ?? 0 }} devices</span>
@endif
</a>
@empty
<div class="p-4 text-sm text-slate-500">No users found.</div>
@endforelse
</div>
</div>

<div class="xl:w-2/3 flex flex-col bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden shadow-sm min-h-[420px]">
<div class="p-4 border-b border-gray-100 dark:border-gray-800 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 bg-white dark:bg-gray-900 sticky top-0 z-10">
<div>
<h3 class="text-lg font-bold">2. Select Devices</h3>
<p class="text-xs text-[#4c669a]">
@if ($selectedUser)
Choose hardware to assign to {{ $selectedUser->name }}.
@else
Select a user to assign devices.
@endif
</p>
</div>
<div class="flex gap-2 flex-wrap">
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xl">filter_list</span>
<select class="pl-10 pr-8 py-2 bg-background-light dark:bg-gray-800 border-none rounded-lg text-sm appearance-none focus:ring-2 focus:ring-primary/50" data-device-filter>
<option value="all">All Devices</option>
<option value="CISCO">CISCO</option>
<option value="MIMOSA">MIMOSA</option>
<option value="OLT">OLT</option>
</select>
</div>
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xl">search</span>
<input class="pl-10 pr-4 py-2 bg-background-light dark:bg-gray-800 border-none rounded-lg focus:ring-2 focus:ring-primary/50 text-sm w-48" placeholder="ID or Serial..." type="text" data-device-search/>
</div>
</div>
</div>
<div class="p-6 grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-4 overflow-y-auto flex-grow">
@forelse ($devices as $device)
@php
$assignedUserId = $assignmentMap[$device->id] ?? $device->assigned_user_id;
$assignedUser = $assignedUserId ? ($userLookup[$assignedUserId] ?? null) : null;
$isAssignedToSelected = $selectedUserId && $assignedUserId === $selectedUserId;
$isAssignedToOther = $assignedUserId && (!$selectedUserId || $assignedUserId !== $selectedUserId);
$cardClass = $isAssignedToSelected
    ? 'border-2 border-primary rounded-xl p-4 bg-primary/5 transition-all'
    : 'relative group border border-gray-200 dark:border-gray-700 rounded-xl p-4 hover:border-primary/50 hover:bg-gray-50 dark:hover:bg-gray-800 transition-all';
$icon = match (strtoupper($device->type ?? '')) {
    'CISCO' => 'router',
    'MIMOSA' => 'cell_tower',
    'OLT' => 'hub',
    default => 'devices',
};
$availabilityLabel = $assignedUser ? 'Assigned' : 'Available';
$availabilityColor = $assignedUser ? 'text-red-600' : 'text-green-600';
$availabilityDot = $assignedUser ? 'bg-red-500' : 'bg-green-500';
@endphp
<div class="{{ $cardClass }} {{ $isAssignedToOther ? 'opacity-80' : '' }}" data-device-card data-device-type="{{ $device->type }}" data-device-name="{{ $device->name }}" data-device-serial="{{ $device->serial_number }}">
<div class="absolute top-3 right-3">
@if ($isAssignedToSelected)
<div class="size-6 bg-primary rounded flex items-center justify-center text-white">
<span class="material-symbols-outlined text-sm">check</span>
</div>
@elseif ($isAssignedToOther)
<span class="material-symbols-outlined text-gray-400 text-lg">lock</span>
@else
<div class="size-6 border-2 border-gray-300 dark:border-gray-600 rounded"></div>
@endif
</div>
<span class="material-symbols-outlined {{ $isAssignedToSelected ? 'text-primary' : 'text-gray-400 group-hover:text-primary' }} text-3xl mb-2">{{ $icon }}</span>
<p class="text-sm font-bold mb-1">{{ $device->name }}</p>
<p class="text-[11px] font-mono text-gray-500 uppercase">SN: {{ $device->serial_number ?? '-' }}</p>
@if ($assignedUser)
<p class="text-[11px] text-slate-500 mt-1">Assigned to {{ $assignedUser->name }}</p>
@endif
<div class="mt-4 flex items-center gap-2">
<span class="size-2 rounded-full {{ $availabilityDot }}"></span>
<span class="text-[10px] font-bold uppercase {{ $availabilityColor }}">{{ $availabilityLabel }}</span>
</div>
@if ($selectedUser && !$isAssignedToSelected)
<form class="mt-4" method="POST" action="{{ route('devices.assign') }}">
@csrf
<input type="hidden" name="device_id" value="{{ $device->id }}"/>
<input type="hidden" name="user_id" value="{{ $selectedUser->id }}"/>
<button class="w-full px-3 py-2 text-xs font-bold rounded-lg {{ $isAssignedToOther ? 'bg-amber-100 text-amber-700 hover:bg-amber-200' : 'bg-primary text-white hover:bg-primary/90' }}" type="submit">
{{ $isAssignedToOther ? 'Reassign to ' . $selectedUser->name : 'Assign to ' . $selectedUser->name }}
</button>
</form>
@elseif ($isAssignedToSelected)
<div class="mt-4 text-xs font-bold text-primary">Assigned to this user</div>
@endif
</div>
@empty
<div class="col-span-full text-sm text-slate-500">No devices found.</div>
@endforelse
</div>
</div>
</div>
</div>
</main>
</div>

<div id="wizard-review" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50" data-modal>
<div class="bg-white dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-lg p-6">
<div class="flex items-center justify-between mb-4">
<h3 class="text-lg font-bold">Review &amp; Confirm</h3>
<button class="text-gray-400 hover:text-gray-600" type="button" data-modal-close="wizard-review">
<span class="material-symbols-outlined">close</span>
</button>
</div>
<p class="text-sm text-gray-500 mb-4">
@if ($selectedUser)
Review assigned devices for <span class="font-semibold text-gray-800 dark:text-white">{{ $selectedUser->name }}</span>.
@else
No user selected.
@endif
</p>
<div class="space-y-2 max-h-64 overflow-y-auto">
@forelse ($assignedDevices as $device)
<div class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2">
<div>
<p class="text-sm font-semibold">{{ $device->name }}</p>
<p class="text-[11px] text-gray-400">SN: {{ $device->serial_number ?? '-' }}</p>
</div>
<span class="text-xs font-semibold text-primary">{{ $device->type ?? 'Device' }}</span>
</div>
@empty
<p class="text-sm text-gray-400">No devices assigned yet.</p>
@endforelse
</div>
<div class="flex justify-end gap-3 mt-6">
<button class="px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50" type="button" data-modal-close="wizard-review">Close</button>
<a class="px-5 py-2 text-sm font-semibold text-white bg-primary rounded-lg hover:bg-primary/90" href="{{ route('devices.index') }}">Done</a>
</div>
</div>
</div>

<footer data-wizard-footer class="fixed bottom-0 left-64 right-0 bg-white dark:bg-background-dark border-t border-gray-200 dark:border-gray-800 px-6 lg:px-10 py-5 z-40 shadow-2xl">
<div class="max-w-[1400px] mx-auto flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
<div class="flex items-center gap-8">
<div class="flex flex-col">
<span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Active Selection</span>
<div class="flex items-center gap-4 mt-1">
<div class="flex items-center gap-2">
<span class="text-sm font-bold text-[#0d121b] dark:text-white">{{ $selectedUser ? 1 : 0 }}</span>
<span class="text-sm text-[#4c669a]">User</span>
</div>
<div class="w-[1px] h-4 bg-gray-200 dark:bg-gray-700"></div>
<div class="flex items-center gap-2">
<span class="text-sm font-bold text-primary">{{ $selectedUserDeviceCount ?? 0 }}</span>
<span class="text-sm text-[#4c669a]">Devices Assigned</span>
</div>
</div>
</div>
<div class="hidden lg:flex items-center gap-2 bg-gray-50 dark:bg-gray-800/50 p-2 rounded-lg border border-gray-100 dark:border-gray-700">
<div class="flex -space-x-2">
<div class="size-6 rounded-full bg-primary/20 flex items-center justify-center border border-white dark:border-gray-900">
<span class="material-symbols-outlined text-[12px] text-primary">laptop</span>
</div>
<div class="size-6 rounded-full bg-primary/20 flex items-center justify-center border border-white dark:border-gray-900">
<span class="material-symbols-outlined text-[12px] text-primary">tablet</span>
</div>
<div class="size-6 rounded-full bg-primary/20 flex items-center justify-center border border-white dark:border-gray-900">
<span class="material-symbols-outlined text-[12px] text-primary">keyboard</span>
</div>
</div>
<span class="text-xs text-[#4c669a] font-medium pr-2">Asset queue ready</span>
</div>
</div>
<div class="flex items-center gap-4">
<a class="px-6 py-2.5 rounded-lg text-sm font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" href="{{ route('devices.index') }}">Cancel</a>
<button class="bg-primary hover:bg-primary/90 text-white px-10 py-2.5 rounded-lg text-sm font-bold flex items-center gap-2 shadow-lg shadow-primary/25 transition-all" type="button" data-modal-open="wizard-review">Next: Review &amp; Confirm<span class="material-symbols-outlined text-lg">arrow_forward</span></button>
</div>
</div>
</footer>
</body>
</html>
