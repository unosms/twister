<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="app-base" content="{{ url('/') }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Device Events | Device Control Manager</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
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
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        body {
            font-family: 'Inter', sans-serif;
        }

        details[data-filter-collapsible] > summary::-webkit-details-marker {
            display: none;
        }

        details[data-filter-collapsible] > summary .material-symbols-outlined {
            transition: transform 0.18s ease;
        }

        details[data-filter-collapsible][open] > summary .material-symbols-outlined {
            transform: rotate(180deg);
        }
    </style>
    @include('partials.admin_sidebar_styles')
    <script src="{{ asset('js/actions.js') . '?v=' . filemtime(public_path('js/actions.js')) }}" defer></script>
</head>
<body class="bg-background-light dark:bg-background-dark text-[#0d121b] dark:text-gray-100 h-screen overflow-hidden">
@php
    $filters = is_array($filters ?? null) ? $filters : [];
    $selectedDeviceIds = array_values(array_unique(array_map(
        static fn ($value): int => is_numeric($value) ? (int) $value : 0,
        (array) ($filters['device_id'] ?? [])
    )));
    $selectedDeviceIds = array_values(array_filter($selectedDeviceIds, static fn (int $value): bool => $value > 0));
    $selectedDeviceLookup = array_fill_keys($selectedDeviceIds, true);

    $selectedSourceValues = array_values(array_unique(array_map(
        static fn ($value): string => strtolower(trim((string) $value)),
        (array) ($filters['source'] ?? [])
    )));
    $selectedSourceLookup = array_fill_keys($selectedSourceValues, true);

    $selectedStatusValues = array_values(array_unique(array_map(
        static fn ($value): string => strtolower(trim((string) $value)),
        (array) ($filters['status'] ?? [])
    )));
    $selectedStatusLookup = array_fill_keys($selectedStatusValues, true);

    $selectedSeverityValues = array_values(array_unique(array_map(
        static fn ($value): string => strtolower(trim((string) $value)),
        (array) ($filters['severity'] ?? [])
    )));
    $selectedSeverityLookup = array_fill_keys($selectedSeverityValues, true);

    $selectedEventTypeValues = array_values(array_unique(array_map(
        static fn ($value): string => strtolower(trim((string) $value)),
        (array) ($filters['event_type'] ?? [])
    )));
    $selectedEventTypeLookup = array_fill_keys($selectedEventTypeValues, true);

    $sourceOptions = [
        'interface' => 'Interface events',
        'device' => 'Device events',
    ];
    $statusOptions = [
        'open' => 'Open',
        'resolved' => 'Resolved',
    ];

    $severityOptions = collect($severityOptions ?? []);
    $eventTypeOptions = collect($eventTypeOptions ?? []);
    $windowLabels = [
        'all' => 'All history',
        '1h' => 'Last 1 hour',
        '3h' => 'Last 3 hours',
        '6h' => 'Last 6 hours',
        '12h' => 'Last 12 hours',
        '24h' => 'Last 24 hours',
        '72h' => 'Last 72 hours',
    ];

    $durationLabel = static function (?int $startTs, ?int $endTs): string {
        if (!$startTs || $startTs <= 0) {
            return '-';
        }

        $endValue = $endTs && $endTs > 0 ? $endTs : now()->timestamp;
        $seconds = max(0, $endValue - $startTs);

        $days = intdiv($seconds, 86400);
        $seconds %= 86400;
        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);
        $seconds %= 60;

        if ($days > 0) {
            return $days . 'd ' . $hours . 'h ' . $minutes . 'm';
        }

        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }

        if ($minutes > 0) {
            return $minutes . 'm ' . $seconds . 's';
        }

        return $seconds . 's';
    };

    $severityBadgeClass = static function (string $severity): string {
        return match (strtolower(trim($severity))) {
            'info' => 'bg-slate-100 text-slate-700',
            'average' => 'bg-amber-100 text-amber-700',
            'high' => 'bg-orange-100 text-orange-700',
            'disaster' => 'bg-rose-100 text-rose-700',
            default => 'bg-slate-100 text-slate-700',
        };
    };

    $sourceBadgeClass = static function (string $source): string {
        return $source === 'interface'
            ? 'bg-blue-100 text-blue-700'
            : 'bg-emerald-100 text-emerald-700';
    };
@endphp
<div class="flex h-screen overflow-hidden">
    @include('partials.admin_sidebar', ['sidebarAuthUser' => $authUser ?? null])

    <main class="flex-1 flex flex-col overflow-y-auto" data-events-scroll-container>
        <header class="h-16 border-b border-[#e7ebf3] dark:border-gray-800 bg-white dark:bg-background-dark flex items-center justify-between px-4 sm:px-8 shrink-0">
            <div class="flex items-center gap-4 flex-1 min-w-0">
                <button class="flex h-10 w-10 items-center justify-center rounded-lg border border-[#e7ebf3] bg-white text-gray-500 hover:bg-gray-50 dark:border-gray-800 dark:bg-background-dark dark:hover:bg-gray-800" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <div class="min-w-0">
                    <h1 class="truncate text-2xl font-bold tracking-tight">Device Events</h1>
                    <p class="text-sm text-gray-500">Unified event table for all devices with live filters.</p>
                </div>
            </div>
            <a class="hidden sm:inline-flex items-center rounded-lg border border-[#cfd7e7] bg-white px-3 py-2 text-xs font-semibold hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800" href="{{ route('devices.events.index') }}">
                Reset Filters
            </a>
        </header>

        <section class="flex-1 p-4 sm:p-6 lg:p-8 space-y-5">
            @if (!($hasEventTables ?? false))
                <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Event tables are not available yet. Run the poller once (`php artisan events:poll`) to populate `interface_events` / `device_events`.
                </div>
            @endif

            <form method="get" action="{{ route('devices.events.index') }}" class="rounded-xl border border-[#cfd7e7] bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900" data-events-filter-form>
                <div class="mb-4">
                    <div class="inline-flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800/60">
                        <button class="inline-flex items-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" type="submit">
                            Apply Filters
                        </button>
                        <a class="inline-flex items-center rounded-lg border border-[#cfd7e7] bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700" href="{{ route('devices.events.index') }}">
                            Clear All
                        </a>
                    </div>
                </div>

                <div class="grid items-start gap-3 md:grid-cols-2 xl:grid-cols-7">
                    <details class="rounded-lg border border-slate-200 bg-slate-50/70 p-2 dark:border-gray-700 dark:bg-gray-800/60" data-filter-collapsible>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 rounded-md px-2 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            <span>Device</span>
                            <span class="text-[10px] normal-case tracking-normal text-slate-500 dark:text-slate-400">
                                {{ count($selectedDeviceIds) > 0 ? count($selectedDeviceIds) . ' selected' : 'All' }}
                            </span>
                            <span class="material-symbols-outlined text-[16px]">expand_more</span>
                        </summary>
                        <div class="mt-2 max-h-44 space-y-1 overflow-y-auto rounded-md border border-slate-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-900">
                            @foreach ($devices as $deviceOption)
                                @php
                                    $deviceId = (int) $deviceOption->id;
                                    $deviceLabel = ($deviceOption->name ?: ('Device #' . $deviceId))
                                        . ($deviceOption->type ? ' (' . $deviceOption->type . ')' : '');
                                @endphp
                                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                    <input class="rounded border-slate-300 text-primary focus:ring-primary dark:border-gray-600" type="checkbox" name="device_id[]" value="{{ $deviceId }}" @checked(isset($selectedDeviceLookup[$deviceId])) />
                                    <span>{{ $deviceLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-[10px] text-slate-400">Leave empty for all devices.</p>
                    </details>

                    <details class="rounded-lg border border-slate-200 bg-slate-50/70 p-2 dark:border-gray-700 dark:bg-gray-800/60" data-filter-collapsible>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 rounded-md px-2 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            <span>Source</span>
                            <span class="text-[10px] normal-case tracking-normal text-slate-500 dark:text-slate-400">
                                {{ count($selectedSourceValues) > 0 ? count($selectedSourceValues) . ' selected' : 'All' }}
                            </span>
                            <span class="material-symbols-outlined text-[16px]">expand_more</span>
                        </summary>
                        <div class="mt-2 max-h-44 space-y-1 overflow-y-auto rounded-md border border-slate-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-900">
                            @foreach ($sourceOptions as $sourceKey => $sourceLabel)
                                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                    <input class="rounded border-slate-300 text-primary focus:ring-primary dark:border-gray-600" type="checkbox" name="source[]" value="{{ $sourceKey }}" @checked(isset($selectedSourceLookup[$sourceKey])) />
                                    <span>{{ $sourceLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-[10px] text-slate-400">Leave empty for all sources.</p>
                    </details>

                    <details class="rounded-lg border border-slate-200 bg-slate-50/70 p-2 dark:border-gray-700 dark:bg-gray-800/60" data-filter-collapsible>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 rounded-md px-2 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            <span>Status</span>
                            <span class="text-[10px] normal-case tracking-normal text-slate-500 dark:text-slate-400">
                                {{ count($selectedStatusValues) > 0 ? count($selectedStatusValues) . ' selected' : 'All' }}
                            </span>
                            <span class="material-symbols-outlined text-[16px]">expand_more</span>
                        </summary>
                        <div class="mt-2 max-h-44 space-y-1 overflow-y-auto rounded-md border border-slate-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-900">
                            @foreach ($statusOptions as $statusKey => $statusLabel)
                                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                    <input class="rounded border-slate-300 text-primary focus:ring-primary dark:border-gray-600" type="checkbox" name="status[]" value="{{ $statusKey }}" @checked(isset($selectedStatusLookup[$statusKey])) />
                                    <span>{{ $statusLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-[10px] text-slate-400">Leave empty for both.</p>
                    </details>

                    <details class="rounded-lg border border-slate-200 bg-slate-50/70 p-2 dark:border-gray-700 dark:bg-gray-800/60" data-filter-collapsible>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 rounded-md px-2 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            <span>Severity</span>
                            <span class="text-[10px] normal-case tracking-normal text-slate-500 dark:text-slate-400">
                                {{ count($selectedSeverityValues) > 0 ? count($selectedSeverityValues) . ' selected' : 'All' }}
                            </span>
                            <span class="material-symbols-outlined text-[16px]">expand_more</span>
                        </summary>
                        <div class="mt-2 max-h-44 space-y-1 overflow-y-auto rounded-md border border-slate-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-900">
                            @foreach ($severityOptions as $severityOption)
                                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                    <input class="rounded border-slate-300 text-primary focus:ring-primary dark:border-gray-600" type="checkbox" name="severity[]" value="{{ $severityOption }}" @checked(isset($selectedSeverityLookup[(string) $severityOption])) />
                                    <span>{{ ucfirst((string) $severityOption) }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-[10px] text-slate-400">Leave empty for all severities.</p>
                    </details>

                    <details class="rounded-lg border border-slate-200 bg-slate-50/70 p-2 dark:border-gray-700 dark:bg-gray-800/60" data-filter-collapsible>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 rounded-md px-2 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            <span>Event Type</span>
                            <span class="text-[10px] normal-case tracking-normal text-slate-500 dark:text-slate-400">
                                {{ count($selectedEventTypeValues) > 0 ? count($selectedEventTypeValues) . ' selected' : 'All' }}
                            </span>
                            <span class="material-symbols-outlined text-[16px]">expand_more</span>
                        </summary>
                        <div class="mt-2 max-h-44 space-y-1 overflow-y-auto rounded-md border border-slate-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-900">
                            @foreach ($eventTypeOptions as $eventTypeOption)
                                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                    <input class="rounded border-slate-300 text-primary focus:ring-primary dark:border-gray-600" type="checkbox" name="event_type[]" value="{{ $eventTypeOption }}" @checked(isset($selectedEventTypeLookup[(string) $eventTypeOption])) />
                                    <span>{{ $eventTypeOption }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-[10px] text-slate-400">Leave empty for all event types.</p>
                    </details>

                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Time Window</span>
                        <select class="rounded-lg border-slate-300 text-sm dark:border-gray-700 dark:bg-gray-800" name="window">
                            @foreach ($windowLabels as $windowValue => $windowLabel)
                                <option value="{{ $windowValue }}" @selected(($filters['window'] ?? 'all') === $windowValue)>{{ $windowLabel }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Search</span>
                        <input class="rounded-lg border-slate-300 text-sm dark:border-gray-700 dark:bg-gray-800" name="search" type="text" value="{{ $filters['search'] ?? '' }}" placeholder="Device, interface, type, event id" />
                    </label>
                </div>

            </form>

            <div class="bg-white dark:bg-gray-900 border border-[#cfd7e7] dark:border-gray-800 rounded-xl overflow-hidden shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#e7ebf3] px-4 py-3 dark:border-gray-800">
                    <h2 class="text-lg font-semibold">Event Timeline</h2>
                    <span class="text-xs font-semibold text-slate-500">
                        Showing {{ $events->count() }} of {{ number_format($events->total()) }} events
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[1180px] w-full text-left border-collapse">
                        <thead class="bg-slate-50 dark:bg-gray-800/60 border-b border-[#cfd7e7] dark:border-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-slate-500">Detected At</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-slate-500">Device</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-slate-500">Source</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-slate-500">Event</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-slate-500">Interface</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-slate-500">Severity</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-slate-500">Status</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-slate-500">Duration</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-slate-500">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e7ebf3] dark:divide-gray-800">
                            @forelse ($events as $event)
                                @php
                                    $openedAtTs = is_numeric($event->opened_at ?? null) ? (int) $event->opened_at : null;
                                    $resolvedAtTs = is_numeric($event->resolved_at ?? null) ? (int) $event->resolved_at : null;
                                    $openedAt = $openedAtTs ? \Carbon\Carbon::createFromTimestamp($openedAtTs) : null;
                                    $resolvedAt = $resolvedAtTs ? \Carbon\Carbon::createFromTimestamp($resolvedAtTs) : null;
                                    $sourceValue = strtolower(trim((string) ($event->source ?? 'device')));
                                    $eventTypeValue = strtolower(trim((string) ($event->event_type ?? '')));
                                    $isSpeedChanged = $eventTypeValue === 'speed_changed';
                                    $severityValue = trim((string) ($event->severity ?? ''));
                                    $isResolved = $resolvedAtTs !== null;
                                    $oldSpeedValue = is_numeric($event->old_speed_mbps ?? null) ? (int) $event->old_speed_mbps : null;
                                    $newSpeedValue = is_numeric($event->new_speed_mbps ?? null) ? (int) $event->new_speed_mbps : null;

                                    if ($isSpeedChanged) {
                                        if ($oldSpeedValue !== null && $newSpeedValue !== null) {
                                            if ($newSpeedValue > $oldSpeedValue) {
                                                $statusLabel = 'Speed Up';
                                                $statusBadgeClass = 'bg-emerald-100 text-emerald-700';
                                            } elseif ($newSpeedValue < $oldSpeedValue) {
                                                $statusLabel = 'Speed Down';
                                                $statusBadgeClass = 'bg-rose-100 text-rose-700';
                                            } else {
                                                $statusLabel = 'No Change';
                                                $statusBadgeClass = 'bg-slate-100 text-slate-700';
                                            }
                                        } else {
                                            $statusLabel = 'Speed Changed';
                                            $statusBadgeClass = 'bg-blue-100 text-blue-700';
                                        }
                                    } else {
                                        $statusLabel = $isResolved ? 'Resolved' : 'Open';
                                        $statusBadgeClass = $isResolved ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700';
                                    }

                                    $ifIndex = is_numeric($event->interface_index ?? null) ? (int) $event->interface_index : 0;
                                    $ifName = trim((string) ($event->interface_name ?? ''));
                                    if ($ifName === '' && $ifIndex > 0) {
                                        $ifName = 'ifIndex ' . $ifIndex;
                                    }
                                    $ifAlias = trim((string) ($event->interface_alias ?? ''));
                                    $interfaceLabel = $sourceValue === 'interface'
                                        ? ($ifName !== '' ? $ifName : '-')
                                        : '-';
                                    if ($sourceValue === 'interface' && $ifAlias !== '') {
                                        $interfaceLabel .= ' | ' . $ifAlias;
                                    }

                                    $details = [];
                                    if ($sourceValue === 'device') {
                                        $ipValue = trim((string) ($event->device_ip ?? ''));
                                        if ($ipValue !== '') {
                                            $details[] = 'IP ' . $ipValue;
                                        }
                                    }

                                    if ($isSpeedChanged && ($oldSpeedValue !== null || $newSpeedValue !== null)) {
                                        $oldSpeed = $oldSpeedValue !== null ? (string) $oldSpeedValue : '-';
                                        $newSpeed = $newSpeedValue !== null ? (string) $newSpeedValue : '-';
                                        $details[] = 'Speed ' . $oldSpeed . ' -> ' . $newSpeed . ' Mbps';
                                    }

                                    if (
                                        $sourceValue === 'interface'
                                        && is_numeric($event->old_status ?? null)
                                        && is_numeric($event->new_status ?? null)
                                    ) {
                                        $details[] = 'State ' . ((int) $event->old_status) . ' -> ' . ((int) $event->new_status);
                                    }

                                    $detailsText = $details ? implode(' | ', $details) : '-';
                                @endphp
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-gray-800/40">
                                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
                                        <div>{{ $openedAt?->format('Y-m-d H:i:s') ?? '-' }}</div>
                                        <div class="text-[11px] text-slate-400">
                                            {{ $resolvedAt ? 'Resolved ' . $resolvedAt->format('Y-m-d H:i:s') : 'Still open' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
                                        <div class="font-semibold">{{ $event->device_name ?: ('Device #' . ($event->device_id ?? '-')) }}</div>
                                        <div class="text-[11px] text-slate-400">ID {{ $event->device_id ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $sourceBadgeClass($sourceValue) }}">
                                            {{ ucfirst($sourceValue) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
                                        <div class="font-semibold">{{ $event->event_type ?: '-' }}</div>
                                        <div class="text-[11px] text-slate-400">Event #{{ $event->event_id ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $interfaceLabel }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $severityBadgeClass($severityValue) }}">
                                            {{ $severityValue !== '' ? $severityValue : '-' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusBadgeClass }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
                                        {{ $durationLabel($openedAtTs, $resolvedAtTs) }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                                        {{ $detailsText }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-6 text-sm text-slate-500" colspan="9">
                                        No events match the current filters.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($events->hasPages())
                    <div class="border-t border-[#e7ebf3] px-4 py-3 dark:border-gray-800">
                        {{ $events->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>
        </section>
    </main>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var scrollContainer = document.querySelector('[data-events-scroll-container]');
        if (!scrollContainer) {
            return;
        }
        var filterForm = document.querySelector('[data-events-filter-form]');

        var pageKey = window.location.pathname + window.location.search;
        var scrollKey = 'device-events-scroll:' + pageKey;
        var scrollTsKey = scrollKey + ':ts';
        var maxRestoreAgeMs = 20000;
        var refreshIntervalMs = 5000;
        var refreshTimerId = null;

        var saveScrollPosition = function () {
            try {
                sessionStorage.setItem(scrollKey, String(Math.max(0, scrollContainer.scrollTop || 0)));
                sessionStorage.setItem(scrollTsKey, String(Date.now()));
            } catch (error) {
                // Ignore storage failures and continue refreshing.
            }
        };

        var restoreScrollPosition = function () {
            try {
                var savedTop = Number.parseInt(sessionStorage.getItem(scrollKey) || '', 10);
                var savedTs = Number.parseInt(sessionStorage.getItem(scrollTsKey) || '', 10);
                if (Number.isNaN(savedTop) || Number.isNaN(savedTs)) {
                    return;
                }

                if ((Date.now() - savedTs) > maxRestoreAgeMs) {
                    return;
                }

                scrollContainer.scrollTop = Math.max(0, savedTop);
            } catch (error) {
                // Ignore storage failures and continue without restoration.
            }
        };

        var canRefreshNow = function () {
            var anyFilterOpen = document.querySelector('[data-filter-collapsible][open]');
            if (anyFilterOpen) {
                return false;
            }

            var active = document.activeElement;
            if (!active) {
                return true;
            }

            var tagName = (active.tagName || '').toLowerCase();
            return !(tagName === 'input' || tagName === 'select' || tagName === 'textarea');
        };

        var buildRefreshUrl = function () {
            var url = new URL(window.location.href);
            if (!filterForm) {
                return url.toString();
            }

            var params = new URLSearchParams(url.search);
            var fieldNames = [];
            var elements = filterForm.elements ? Array.from(filterForm.elements) : [];

            elements.forEach(function (element) {
                if (!element || !element.name || element.disabled) {
                    return;
                }

                fieldNames.push(element.name);
                if (element.name.endsWith('[]')) {
                    fieldNames.push(element.name.slice(0, -2));
                } else {
                    fieldNames.push(element.name + '[]');
                }
            });

            fieldNames = Array.from(new Set(fieldNames));
            fieldNames.forEach(function (name) {
                params.delete(name);
            });

            var formData = new FormData(filterForm);
            formData.forEach(function (rawValue, key) {
                var value = (typeof rawValue === 'string') ? rawValue : String(rawValue || '');
                if (value === '') {
                    return;
                }

                params.append(key, value);
            });

            url.search = params.toString();
            return url.toString();
        };

        var scheduleRefresh = function (delayMs) {
            if (refreshTimerId !== null) {
                window.clearTimeout(refreshTimerId);
            }

            refreshTimerId = window.setTimeout(function () {
                if (!canRefreshNow()) {
                    scheduleRefresh(1000);
                    return;
                }

                saveScrollPosition();
                window.location.replace(buildRefreshUrl());
            }, delayMs);
        };

        restoreScrollPosition();
        window.setTimeout(restoreScrollPosition, 0);
        scrollContainer.addEventListener('scroll', saveScrollPosition, { passive: true });
        window.addEventListener('beforeunload', saveScrollPosition);
        scheduleRefresh(refreshIntervalMs);
    });
</script>
</body>
</html>
