@php
$dashboardActive = request()->routeIs('dashboard');
$usersActive = request()->routeIs('users.*');
$deviceNavActive = request()->routeIs('devices.*');
$deviceControlActive = request()->routeIs('devices.index');
$deviceDetailsActive = request()->routeIs('devices.details');
$assignmentsActive = request()->routeIs('devices.wizard');
$settingsActive = request()->routeIs('settings.*');
@endphp

<nav class="rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900/85">
<div class="flex flex-wrap items-center gap-2">
<a class="inline-flex min-h-[48px] items-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold transition-colors {{ $dashboardActive ? 'bg-primary text-white shadow-sm shadow-primary/20' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}" href="{{ route('dashboard') }}">
<span class="material-symbols-outlined text-[18px]">dashboard</span>
Dashboard
</a>
<a class="inline-flex min-h-[48px] items-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold transition-colors {{ $usersActive ? 'bg-primary text-white shadow-sm shadow-primary/20' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}" href="{{ route('users.index') }}">
<span class="material-symbols-outlined text-[18px]">group</span>
Users
</a>
<div class="flex min-h-[48px] flex-1 flex-wrap items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700">
<div class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold {{ $deviceNavActive ? 'bg-primary/10 text-primary' : 'text-slate-600 dark:text-slate-300' }}">
<span class="material-symbols-outlined text-[18px]">devices</span>
Devices
</div>
<div class="hidden h-6 w-px bg-slate-200 dark:bg-slate-700 sm:block"></div>
<div class="flex flex-wrap items-center gap-2">
<a class="rounded-lg px-3 py-2 text-xs font-semibold transition-colors {{ $deviceControlActive ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('devices.index') }}">Device Management</a>
<a class="rounded-lg px-3 py-2 text-xs font-semibold transition-colors {{ $deviceDetailsActive ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('devices.details') }}">Devices List</a>
</div>
</div>
<a class="inline-flex min-h-[48px] items-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold transition-colors {{ $assignmentsActive ? 'bg-primary text-white shadow-sm shadow-primary/20' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}" href="{{ route('devices.wizard') }}">
<span class="material-symbols-outlined text-[18px]">assignment</span>
Assignments
</a>
<a class="inline-flex min-h-[48px] items-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold transition-colors {{ $settingsActive ? 'bg-primary text-white shadow-sm shadow-primary/20' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}" href="{{ route('settings.index') }}">
<span class="material-symbols-outlined text-[18px]">tune</span>
Settings
</a>
</div>
</nav>
