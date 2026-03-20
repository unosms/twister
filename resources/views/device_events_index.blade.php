<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="app-base" content="{{ url('/') }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Device Events | Device Control Manager</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
    <script>
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
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                },
            },
        };
    </script>
    <style>
        .material-symbols-outlined {
            font-family: 'Material Symbols Outlined';
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        body { font-family: 'Inter', sans-serif; }
        details > summary { list-style: none; }
        details > summary::-webkit-details-marker { display: none; }
    </style>
    @include('partials.admin_sidebar_styles')
    <script src="{{ asset('js/actions.js') . '?v=' . filemtime(public_path('js/actions.js')) }}" defer></script>
</head>
<body class="bg-background-light dark:bg-background-dark text-[#0d121b] dark:text-gray-100 h-screen overflow-hidden">
@php
    $requestedGroup = strtolower(trim((string) request()->query('group', '')));
    $allowedGroups = ['router_board', 'switches', 'fiber_optic', 'wireless', 'servers_standalone', 'servers_virtual', 'other'];
    if (!in_array($requestedGroup, $allowedGroups, true)) {
        $requestedGroup = '';
    }

    $groupMeta = [
        'router_board' => ['label' => 'Router Board', 'icon' => 'router', 'empty' => 'No Router Board devices.'],
        'switches' => ['label' => 'Switches', 'icon' => 'hub', 'empty' => 'No switch devices.'],
        'fiber_optic' => ['label' => 'Fiber Optic', 'icon' => 'settings_input_hdmi', 'empty' => 'No fiber optic devices.'],
        'wireless' => ['label' => 'Wireless', 'icon' => 'wifi', 'empty' => 'No wireless devices.'],
        'servers_standalone' => ['label' => 'Stand Alone Server', 'icon' => 'dns', 'empty' => 'No stand alone servers.'],
        'servers_virtual' => ['label' => 'Virtual Server', 'icon' => 'dns', 'empty' => 'No virtual servers.'],
        'other' => ['label' => 'Other', 'icon' => 'inventory_2', 'empty' => 'No uncategorized devices.'],
    ];
    $serverTotal = ($deviceGroups['servers_standalone']->count() ?? 0) + ($deviceGroups['servers_virtual']->count() ?? 0);
@endphp
<div class="flex h-screen overflow-hidden">
    @include('partials.admin_sidebar', ['sidebarAuthUser' => $authUser ?? null])
    <main class="flex-1 flex flex-col overflow-y-auto">
        <header class="h-16 border-b border-[#e7ebf3] dark:border-gray-800 bg-white dark:bg-background-dark flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-4 flex-1 min-w-0">
                <button class="flex h-10 w-10 items-center justify-center rounded-lg border border-[#e7ebf3] bg-white text-gray-500 hover:bg-gray-50 dark:border-gray-800 dark:bg-background-dark dark:hover:bg-gray-800" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <div class="min-w-0">
                    <h1 class="truncate text-2xl font-bold tracking-tight">Device Events</h1>
                    <p class="text-sm text-gray-500">Open event timelines quickly, grouped by device type ({{ $totalDevices ?? 0 }} devices).</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a class="inline-flex items-center rounded-lg border border-[#cfd7e7] bg-white px-3 py-2 text-xs font-semibold hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800" href="{{ route('devices.details') }}">Devices List</a>
            </div>
        </header>

        <section class="flex-1 p-8">
            <div class="bg-white dark:bg-gray-900 border border-[#cfd7e7] dark:border-gray-800 rounded-xl overflow-hidden shadow-sm p-4 space-y-4">
                @foreach (['router_board', 'switches', 'fiber_optic', 'wireless'] as $groupKey)
                    @php
                        $devicesInGroup = $deviceGroups[$groupKey] ?? collect();
                        $meta = $groupMeta[$groupKey];
                        $openGroup = $requestedGroup !== ''
                            ? $requestedGroup === $groupKey
                            : $loop->first;
                    @endphp
                    <details class="group border border-[#d9e2f2] dark:border-gray-800 rounded-lg overflow-hidden" id="events-group-{{ $groupKey }}" {{ $openGroup ? 'open' : '' }}>
                        <summary class="flex items-center justify-between px-4 py-3 cursor-pointer bg-slate-50/80 dark:bg-gray-800/60">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-[18px] text-primary">{{ $meta['icon'] }}</span>
                                <span class="text-sm font-semibold">{{ $meta['label'] }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-primary/10 text-primary">{{ $devicesInGroup->count() }}</span>
                            </div>
                            <span class="material-symbols-outlined text-[16px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
                        </summary>
                        <div class="p-3 border-t border-[#e7ebf3] dark:border-gray-800">
                            @include('partials.device_events_table', ['groupDevices' => $devicesInGroup, 'emptyMessage' => $meta['empty']])
                        </div>
                    </details>
                @endforeach

                @php
                    $serversGroupRequested = in_array($requestedGroup, ['servers_standalone', 'servers_virtual'], true);
                @endphp
                <details class="group border border-[#d9e2f2] dark:border-gray-800 rounded-lg overflow-hidden" id="events-group-servers" {{ $serversGroupRequested ? 'open' : '' }}>
                    <summary class="flex items-center justify-between px-4 py-3 cursor-pointer bg-slate-50/80 dark:bg-gray-800/60">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px] text-primary">dns</span>
                            <span class="text-sm font-semibold">Servers</span>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-primary/10 text-primary">{{ $serverTotal }}</span>
                        </div>
                        <span class="material-symbols-outlined text-[16px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
                    </summary>
                    <div class="p-3 border-t border-[#e7ebf3] dark:border-gray-800 space-y-3">
                        @foreach (['servers_standalone', 'servers_virtual'] as $serverGroupKey)
                            @php
                                $devicesInGroup = $deviceGroups[$serverGroupKey] ?? collect();
                                $meta = $groupMeta[$serverGroupKey];
                                $openServerGroup = $requestedGroup === $serverGroupKey;
                            @endphp
                            <details class="group border border-[#e3e9f6] dark:border-gray-800 rounded-lg overflow-hidden" id="events-group-{{ $serverGroupKey }}" {{ $openServerGroup ? 'open' : '' }}>
                                <summary class="flex items-center justify-between px-3 py-2.5 cursor-pointer bg-white dark:bg-gray-900">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-semibold text-slate-700 dark:text-gray-200">{{ $meta['label'] }}</span>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-700 dark:bg-gray-800 dark:text-gray-200">{{ $devicesInGroup->count() }}</span>
                                    </div>
                                    <span class="material-symbols-outlined text-[16px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
                                </summary>
                                <div class="p-3 border-t border-[#e7ebf3] dark:border-gray-800">
                                    @include('partials.device_events_table', ['groupDevices' => $devicesInGroup, 'emptyMessage' => $meta['empty']])
                                </div>
                            </details>
                        @endforeach
                    </div>
                </details>

                @if (($deviceGroups['other']->count() ?? 0) > 0)
                    @php
                        $devicesInGroup = $deviceGroups['other'];
                        $meta = $groupMeta['other'];
                        $openOtherGroup = $requestedGroup === 'other';
                    @endphp
                    <details class="group border border-[#d9e2f2] dark:border-gray-800 rounded-lg overflow-hidden" id="events-group-other" {{ $openOtherGroup ? 'open' : '' }}>
                        <summary class="flex items-center justify-between px-4 py-3 cursor-pointer bg-slate-50/80 dark:bg-gray-800/60">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-[18px] text-primary">{{ $meta['icon'] }}</span>
                                <span class="text-sm font-semibold">{{ $meta['label'] }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-primary/10 text-primary">{{ $devicesInGroup->count() }}</span>
                            </div>
                            <span class="material-symbols-outlined text-[16px] text-slate-500 transition-transform duration-200 group-open:rotate-180">expand_more</span>
                        </summary>
                        <div class="p-3 border-t border-[#e7ebf3] dark:border-gray-800">
                            @include('partials.device_events_table', ['groupDevices' => $devicesInGroup, 'emptyMessage' => $meta['empty']])
                        </div>
                    </details>
                @endif
            </div>
        </section>
    </main>
</div>
</body>
</html>
