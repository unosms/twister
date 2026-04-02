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
    </style>
    @include('partials.admin_sidebar_styles')
    <script src="{{ asset('js/actions.js') . '?v=' . filemtime(public_path('js/actions.js')) }}" defer></script>
</head>
<body class="bg-background-light dark:bg-background-dark text-[#0d121b] dark:text-gray-100 h-screen overflow-hidden">
@php
    $filters = is_array($filters ?? null) ? $filters : [];
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

    <main class="flex-1 flex flex-col overflow-y-auto">
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

            <form method="get" action="{{ route('devices.events.index') }}" class="rounded-xl border border-[#cfd7e7] bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-7">
                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Device</span>
                        <select class="rounded-lg border-slate-300 text-sm dark:border-gray-700 dark:bg-gray-800" name="device_id">
                            <option value="0" @selected((int) ($filters['device_id'] ?? 0) === 0)>All devices</option>
                            @foreach ($devices as $deviceOption)
                                <option value="{{ (int) $deviceOption->id }}" @selected((int) ($filters['device_id'] ?? 0) === (int) $deviceOption->id)>
                                    {{ $deviceOption->name ?: ('Device #' . $deviceOption->id) }}{{ $deviceOption->type ? ' (' . $deviceOption->type . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Source</span>
                        <select class="rounded-lg border-slate-300 text-sm dark:border-gray-700 dark:bg-gray-800" name="source">
                            <option value="all" @selected(($filters['source'] ?? 'all') === 'all')>All</option>
                            <option value="interface" @selected(($filters['source'] ?? 'all') === 'interface')>Interface events</option>
                            <option value="device" @selected(($filters['source'] ?? 'all') === 'device')>Device events</option>
                        </select>
                    </label>

                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Status</span>
                        <select class="rounded-lg border-slate-300 text-sm dark:border-gray-700 dark:bg-gray-800" name="status">
                            <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>All</option>
                            <option value="open" @selected(($filters['status'] ?? 'all') === 'open')>Open</option>
                            <option value="resolved" @selected(($filters['status'] ?? 'all') === 'resolved')>Resolved</option>
                        </select>
                    </label>

                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Severity</span>
                        <select class="rounded-lg border-slate-300 text-sm dark:border-gray-700 dark:bg-gray-800" name="severity">
                            <option value="all" @selected(($filters['severity'] ?? 'all') === 'all')>All</option>
                            @foreach ($severityOptions as $severityOption)
                                <option value="{{ $severityOption }}" @selected(($filters['severity'] ?? 'all') === $severityOption)>{{ ucfirst($severityOption) }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Event Type</span>
                        <select class="rounded-lg border-slate-300 text-sm dark:border-gray-700 dark:bg-gray-800" name="event_type">
                            <option value="all" @selected(($filters['event_type'] ?? 'all') === 'all')>All</option>
                            @foreach ($eventTypeOptions as $eventTypeOption)
                                <option value="{{ $eventTypeOption }}" @selected(($filters['event_type'] ?? 'all') === $eventTypeOption)>{{ $eventTypeOption }}</option>
                            @endforeach
                        </select>
                    </label>

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

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button class="inline-flex items-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" type="submit">
                        Apply Filters
                    </button>
                    <a class="inline-flex items-center rounded-lg border border-[#cfd7e7] bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700" href="{{ route('devices.events.index') }}">
                        Clear All
                    </a>
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
                                    $severityValue = trim((string) ($event->severity ?? ''));
                                    $isResolved = $resolvedAtTs !== null;

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
                                        $interfaceLabel .= ' • ' . $ifAlias;
                                    }

                                    $details = [];
                                    if ($sourceValue === 'device') {
                                        $ipValue = trim((string) ($event->device_ip ?? ''));
                                        if ($ipValue !== '') {
                                            $details[] = 'IP ' . $ipValue;
                                        }
                                    }

                                    if (
                                        strtolower(trim((string) ($event->event_type ?? ''))) === 'speed_changed'
                                        && (is_numeric($event->old_speed_mbps ?? null) || is_numeric($event->new_speed_mbps ?? null))
                                    ) {
                                        $oldSpeed = is_numeric($event->old_speed_mbps ?? null) ? (string) ((int) $event->old_speed_mbps) : '-';
                                        $newSpeed = is_numeric($event->new_speed_mbps ?? null) ? (string) ((int) $event->new_speed_mbps) : '-';
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
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $isResolved ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                            {{ $isResolved ? 'Resolved' : 'Open' }}
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
</body>
</html>
