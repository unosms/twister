@php
    $sidebarAuthUser = $sidebarAuthUser ?? $authUser ?? null;

    if (!$sidebarAuthUser && session()->has('auth.user_id')) {
        $sidebarAuthUser = \App\Models\User::find((int) session('auth.user_id'));
    }

    $sidebarRole = strtolower((string) ($sidebarAuthUser->role ?? session('auth.role') ?? 'admin'));
    $dashboardActive = request()->routeIs('dashboard');
    $usersActive = request()->routeIs('users.*');
    $deviceNavActive = request()->routeIs('devices.*') && !request()->routeIs('devices.wizard');
    $deviceControlActive = request()->routeIs('devices.index') || request()->routeIs('devices.create');
    $deviceDetailsActive = request()->routeIs('devices.details')
        || request()->routeIs('devices.backups.*')
        || request()->routeIs('devices.graphs');
    $deviceEventsActive = request()->routeIs('devices.events.*');
    $assignmentsActive = request()->routeIs('devices.wizard');
    $diagnosticsActive = request()->routeIs('support.*')
        || request()->routeIs('telemetry.*')
        || request()->routeIs('debug.*');
    $supportConsoleActive = request()->routeIs('support.*') || request()->routeIs('debug.*');
    $logsActive = request()->routeIs('telemetry.*');
    $settingsActive = request()->routeIs('settings.*');
@endphp

<div class="flex items-center gap-3 px-2" data-sidebar-brand>
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-white">
        <span class="material-symbols-outlined">settings_remote</span>
    </div>
    <div class="flex flex-col" data-sidebar-brand-text>
        <h1 class="text-sm font-bold leading-none">Twister Device Control</h1>
        <p class="mt-1 text-[10px] uppercase tracking-widest text-slate-500">Admin Portal</p>
    </div>
</div>

<nav class="flex flex-col gap-1">
    <a class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition-colors {{ $dashboardActive ? 'bg-primary text-white shadow-sm shadow-primary/20' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('dashboard') }}" data-sidebar-item data-sidebar-tip="System health, activity, and quick status summary.">
        <span class="material-symbols-outlined text-[20px]">dashboard</span>
        <span class="text-sm" data-sidebar-label>Dashboard</span>
    </a>

    <details class="sidebar-collapsible-group flex flex-col gap-1" {{ $deviceNavActive ? 'open' : '' }}>
        <summary class="flex cursor-pointer list-none items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition-colors {{ $deviceNavActive ? 'bg-primary/10 text-primary' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}" data-sidebar-item data-sidebar-tip="Open device tools: management, list, and events.">
            <span class="material-symbols-outlined text-[20px]">devices</span>
            <span class="text-sm" data-sidebar-label>Devices</span>
            <span class="material-symbols-outlined ml-auto text-[18px] sidebar-collapsible-icon">expand_more</span>
        </summary>
        <div class="sidebar-subnav ml-10 flex flex-col gap-1">
            <a class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $deviceControlActive ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-100 hover:text-primary dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('devices.index') }}" data-sidebar-tip="Register and edit devices, monitoring, and credentials.">Device Management</a>
            <a class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $deviceDetailsActive ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-100 hover:text-primary dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('devices.details') }}" data-sidebar-tip="Search and inspect full device inventory.">Devices List</a>
            <a class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $deviceEventsActive ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-100 hover:text-primary dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('devices.events.index') }}" data-sidebar-tip="View timeline of interface and device events.">Events</a>
        </div>
    </details>

    <a class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition-colors {{ $assignmentsActive ? 'bg-primary text-white shadow-sm shadow-primary/20' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('devices.wizard') }}" data-sidebar-item data-sidebar-tip="Assign devices to users and access scopes.">
        <span class="material-symbols-outlined text-[20px]">assignment</span>
        <span class="text-sm" data-sidebar-label>Assignments</span>
    </a>

    <details class="sidebar-collapsible-group flex flex-col gap-1" {{ $diagnosticsActive ? 'open' : '' }}>
        <summary class="flex cursor-pointer list-none items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition-colors {{ $diagnosticsActive ? 'bg-primary/10 text-primary' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}" data-sidebar-item data-sidebar-tip="Troubleshooting tools, support console, and telemetry logs.">
            <span class="material-symbols-outlined text-[20px]">construction</span>
            <span class="text-sm" data-sidebar-label>Diagnostics</span>
            <span class="material-symbols-outlined ml-auto text-[18px] sidebar-collapsible-icon">expand_more</span>
        </summary>
        <div class="sidebar-subnav ml-10 flex flex-col gap-1">
            <a class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $supportConsoleActive ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-100 hover:text-primary dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('support.index') }}" data-sidebar-tip="Run auto-debug and diagnostic workflows.">Support Console</a>
            <a class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $logsActive ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-100 hover:text-primary dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('telemetry.index') }}" data-sidebar-tip="Inspect telemetry and provisioning logs.">Logs</a>
        </div>
    </details>

    <a class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition-colors {{ $usersActive ? 'bg-primary text-white shadow-sm shadow-primary/20' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('users.index') }}" data-sidebar-item data-sidebar-tip="Create users and manage permissions and scopes.">
        <span class="material-symbols-outlined text-[20px]">group</span>
        <span class="text-sm" data-sidebar-label>Users</span>
    </a>

    <a class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition-colors {{ $settingsActive ? 'bg-primary text-white shadow-sm shadow-primary/20' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('settings.index') }}" data-sidebar-item data-sidebar-tip="System-wide settings, backups, and cleanup tools.">
        <span class="material-symbols-outlined text-[20px]">tune</span>
        <span class="text-sm" data-sidebar-label>Settings</span>
    </a>
</nav>

