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
    $deviceCabinetActive = request()->routeIs('devices.cabinet-room.*');
    $deviceDetailsActive = request()->routeIs('devices.details')
        || request()->routeIs('devices.backups.*')
        || request()->routeIs('devices.graphs');
    $deviceEventsActive = request()->routeIs('devices.events.*');
    $deviceEventsGroup = strtolower(trim((string) request()->query('group', '')));
    $deviceEventTypeLinks = [
        'router_board' => 'Router Board',
        'switches' => 'Switches',
        'fiber_optic' => 'Fiber Optic',
        'wireless' => 'Wireless',
        'servers_standalone' => 'Stand Alone',
        'servers_virtual' => 'Virtual Server',
    ];
    $assignmentsActive = request()->routeIs('devices.wizard');
    $notificationsActive = request()->routeIs('notifications.*');
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
        <h1 class="text-sm font-bold leading-none">Device Control</h1>
        <p class="mt-1 text-[10px] uppercase tracking-widest text-slate-500">Admin Portal</p>
    </div>
</div>

<nav class="flex flex-col gap-1">
    <a class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition-colors {{ $dashboardActive ? 'bg-primary text-white shadow-sm shadow-primary/20' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('dashboard') }}" data-sidebar-item>
        <span class="material-symbols-outlined text-[20px]">dashboard</span>
        <span class="text-sm" data-sidebar-label>Dashboard</span>
    </a>

    <details class="sidebar-collapsible-group flex flex-col gap-1" {{ $deviceNavActive ? 'open' : '' }}>
        <summary class="flex cursor-pointer list-none items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition-colors {{ $deviceNavActive ? 'bg-primary/10 text-primary' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}" data-sidebar-item>
            <span class="material-symbols-outlined text-[20px]">devices</span>
            <span class="text-sm" data-sidebar-label>Devices</span>
            <span class="material-symbols-outlined ml-auto text-[18px] sidebar-collapsible-icon">expand_more</span>
        </summary>
        <div class="sidebar-subnav ml-10 flex flex-col gap-1">
            <a class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $deviceControlActive ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-100 hover:text-primary dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('devices.index') }}">Device Management</a>
            <a class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $deviceCabinetActive ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-100 hover:text-primary dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('devices.cabinet-room.index') }}">Cabinet Room</a>
            <a class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $deviceDetailsActive ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-100 hover:text-primary dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('devices.details') }}">Devices List</a>
            <details class="sidebar-collapsible-group" {{ $deviceEventsActive ? 'open' : '' }}>
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $deviceEventsActive ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-100 hover:text-primary dark:text-slate-400 dark:hover:bg-slate-800' }}">
                    <span>Events</span>
                    <span class="material-symbols-outlined ml-auto text-[16px] sidebar-collapsible-icon">expand_more</span>
                </summary>
                <div class="ml-3 mt-1 flex flex-col gap-1 border-l border-slate-200 pl-3 dark:border-slate-700">
                    @foreach ($deviceEventTypeLinks as $groupKey => $groupLabel)
                        @php
                            $eventTypeActive = request()->routeIs('devices.events.index') && $deviceEventsGroup === $groupKey;
                        @endphp
                        <a class="rounded-lg px-2 py-1 text-[11px] font-semibold transition-colors {{ $eventTypeActive ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-100 hover:text-primary dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('devices.events.index', ['group' => $groupKey]) }}#events-group-{{ $groupKey }}">{{ $groupLabel }}</a>
                    @endforeach
                </div>
            </details>
        </div>
    </details>

    <a class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition-colors {{ $assignmentsActive ? 'bg-primary text-white shadow-sm shadow-primary/20' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('devices.wizard') }}" data-sidebar-item>
        <span class="material-symbols-outlined text-[20px]">assignment</span>
        <span class="text-sm" data-sidebar-label>Assignments</span>
    </a>

    <a class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition-colors {{ $notificationsActive ? 'bg-primary text-white shadow-sm shadow-primary/20' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('notifications.index') }}" data-sidebar-item>
        <span class="material-symbols-outlined text-[20px]">notifications</span>
        <span class="text-sm" data-sidebar-label>Notifications</span>
    </a>

    <details class="sidebar-collapsible-group flex flex-col gap-1" {{ $diagnosticsActive ? 'open' : '' }}>
        <summary class="flex cursor-pointer list-none items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition-colors {{ $diagnosticsActive ? 'bg-primary/10 text-primary' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}" data-sidebar-item>
            <span class="material-symbols-outlined text-[20px]">construction</span>
            <span class="text-sm" data-sidebar-label>Diagnostics</span>
            <span class="material-symbols-outlined ml-auto text-[18px] sidebar-collapsible-icon">expand_more</span>
        </summary>
        <div class="sidebar-subnav ml-10 flex flex-col gap-1">
            <a class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $supportConsoleActive ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-100 hover:text-primary dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('support.index') }}">Support Console</a>
            <a class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $logsActive ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-100 hover:text-primary dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('telemetry.index') }}">Logs</a>
        </div>
    </details>

    <a class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition-colors {{ $usersActive ? 'bg-primary text-white shadow-sm shadow-primary/20' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('users.index') }}" data-sidebar-item>
        <span class="material-symbols-outlined text-[20px]">group</span>
        <span class="text-sm" data-sidebar-label>Users</span>
    </a>

    <a class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition-colors {{ $settingsActive ? 'bg-primary text-white shadow-sm shadow-primary/20' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}" href="{{ route('settings.index') }}" data-sidebar-item>
        <span class="material-symbols-outlined text-[20px]">tune</span>
        <span class="text-sm" data-sidebar-label>Settings</span>
    </a>
</nav>
