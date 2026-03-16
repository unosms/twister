<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-base" content="{{ url('/') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Show Graphs - {{ $device->name }}</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="{{ asset('js/actions.js') . '?v=' . filemtime(public_path('js/actions.js')) }}" defer></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#135bec",
                        "background-dark": "#101622",
                    },
                },
            },
        };
    </script>
    <style>
        body { font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .chart-canvas { height: 320px; }
        .chart-canvas canvas { width: 100% !important; height: 100% !important; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    @include('partials.admin_sidebar_styles')
</head>
<body class="h-screen overflow-hidden bg-slate-100 text-slate-900">
@php
    $queryBase = request()->query();
    $queryBase['id'] = $device->id;
    $queryBase['iface'] = $selectedIfaceId;
    $queryBase['trend_mode'] = $trendMode;
    $queryBase['trend_start'] = $trendStartDate;
    $queryBase['trend_end'] = $trendEndDate;

    $sel = $selectedInterface;
    $ifIndex = $sel ? (int) ($sel->ifIndex ?? 0) : 0;
    $ifName = $sel ? trim((string) ($sel->ifName ?? '')) : '';
    if ($ifName === '' && $sel) {
        $ifName = 'ifIndex ' . $ifIndex;
    }
    $ifDesc = $sel ? trim((string) ($sel->ifAlias ?? '')) : '';
    if ($ifDesc === '' && $sel) {
        $ifDesc = trim((string) ($sel->ifDescr ?? ''));
    }
    $speedBps = $sel ? (float) ($sel->speed_bps ?? 0) : 0.0;
    $speedText = $speedBps > 0
        ? rtrim(rtrim(number_format($speedBps / 1000000, 2, '.', ''), '0'), '.') . ' Mbit/s'
        : 'unknown';
    $isUp = $sel ? ((int) ($sel->is_up ?? 2) === 1) : false;
    $sessionRole = strtolower((string) session('auth.role', ''));
    $isAdminRole = $sessionRole === 'admin';
    $backRouteLabel = $isAdminRole ? 'Back to Devices' : 'Back to Portal';
    $backRouteUrl = $isAdminRole ? route('devices.details') : route('portal.index');
    $chartPayload = $chartPayload ?? [];
@endphp

<div class="flex h-screen overflow-hidden">
    @include('partials.admin_sidebar')
    <main class="flex-1 overflow-y-auto">
        <header class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-slate-200 bg-white/90 px-4 py-3 backdrop-blur sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <button class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary">Devices</p>
                    <h1 class="truncate text-xl font-bold tracking-tight sm:text-2xl">Show Graphs</h1>
                    <p class="mt-1 text-sm text-slate-500">Interactive traffic history for {{ $device->name }}.</p>
                </div>
            </div>
            <a class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold hover:bg-slate-50" href="{{ $backRouteUrl }}">{{ $backRouteLabel }}</a>
        </header>

        <div class="mx-auto max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8">

    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-xl shadow-slate-200/50">
        <div class="bg-gradient-to-r from-blue-600 via-blue-500 to-emerald-500 px-6 py-10 text-white lg:px-8">
            <p class="text-xs font-black uppercase tracking-[0.28em] text-white/80">Interactive Graphs</p>
            <h1 class="mt-4 text-4xl font-black tracking-tight lg:text-5xl">Show Graphs</h1>
            <p class="mt-4 max-w-3xl text-sm text-white/85">
                Separate interactive traffic charts for 5 minutes, 1 hour, 24 hours, and 7 days, plus a custom weekly,
                monthly, or yearly trend with date filtering.
            </p>
        </div>
        <div class="grid gap-4 px-6 py-6 lg:grid-cols-4 lg:px-8">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Device</p>
                <p class="mt-2 text-lg font-black">{{ $device->name }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $device->type ?? 'Device' }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Interface</p>
                <p class="mt-2 text-lg font-black">{{ $sel ? ('[#' . $ifIndex . '] ' . $ifName) : 'Not selected' }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $ifDesc !== '' ? $ifDesc : 'Choose an interface below.' }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Link Status</p>
                <p class="mt-2 text-lg font-black {{ $isUp ? 'text-emerald-600' : 'text-amber-600' }}">{{ $sel ? ($isUp ? 'Up' : 'Down') : 'Unknown' }}</p>
                <p class="mt-1 text-sm text-slate-500">Speed {{ $speedText }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Units</p>
                <p class="mt-2 text-lg font-black">{{ $unitLabel }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ count($interfaces) }} discovered interfaces</p>
            </div>
        </div>
    </section>

    @if($error)
        <div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ $error }}</div>
    @endif
    @if(!empty($note))
        <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700">{{ $note }}</div>
    @endif

    <form class="mt-5 rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm" method="get" action="{{ route('devices.graphs') }}" data-graph-filter-form>
        <div class="grid gap-4 lg:grid-cols-12">
            <div class="lg:col-span-3">
                <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Device</label>
                <select class="w-full rounded-2xl border-slate-200 bg-white text-sm font-semibold" name="id" data-graph-auto-submit>
                    @foreach($deviceOptions as $deviceOption)
                        <option value="{{ (int) $deviceOption->id }}" @selected((int) $device->id === (int) $deviceOption->id)>{{ $deviceOption->name }}{{ $deviceOption->type ? ' - ' . $deviceOption->type : '' }}</option>
                    @endforeach
                </select>
            </div>

            <div class="lg:col-span-3">
                <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Interface</label>
                <select class="w-full rounded-2xl border-slate-200 bg-white text-sm font-semibold" name="iface" data-graph-auto-submit>
                    @foreach($interfaces as $iface)
                        @php
                            $ifaceName = trim((string) ($iface->ifName ?? 'ifIndex ' . (int) ($iface->ifIndex ?? 0)));
                            $ifaceDesc = trim((string) ($iface->ifAlias ?? ''));
                            if ($ifaceDesc === '') {
                                $ifaceDesc = trim((string) ($iface->ifDescr ?? ''));
                            }
                            $ifaceLabel = '[#' . (int) ($iface->ifIndex ?? 0) . '] ' . $ifaceName;
                            if ($ifaceDesc !== '') {
                                $ifaceLabel .= ' - ' . $ifaceDesc;
                            }
                        @endphp
                        <option value="{{ (int) $iface->id }}" @selected((int) $selectedIfaceId === (int) $iface->id)>{{ $ifaceLabel }}</option>
                    @endforeach
                </select>
            </div>

            <div class="lg:col-span-2">
                <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Trend Mode</label>
                <select class="w-full rounded-2xl border-slate-200 bg-white text-sm font-semibold" name="trend_mode" data-graph-auto-submit>
                    @foreach($trendModes as $modeKey => $mode)
                        <option value="{{ $modeKey }}" @selected($trendMode === $modeKey)>{{ $mode['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="lg:col-span-2">
                <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Start Date</label>
                <input class="w-full rounded-2xl border-slate-200 bg-white text-sm font-semibold" type="date" name="trend_start" value="{{ $trendStartDate }}" data-graph-auto-submit>
            </div>

            <div class="lg:col-span-2">
                <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">End Date</label>
                <input class="w-full rounded-2xl border-slate-200 bg-white text-sm font-semibold" type="date" name="trend_end" value="{{ $trendEndDate }}" data-graph-auto-submit>
            </div>

            <div class="lg:col-span-2">
                <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Refresh</label>
                <div class="flex gap-2">
                    <input type="hidden" name="unit" value="{{ $unit }}">
                    <div class="flex-1 rounded-2xl bg-slate-100 px-4 py-3 text-center text-sm font-black text-slate-500">Auto refresh on change</div>
                    <button class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 hover:bg-slate-50" type="submit" name="poll" value="1">Poll</button>
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            @foreach($unitOptions as $unitKey => $unitOption)
                <a class="rounded-full px-4 py-2 text-xs font-black {{ $unit === $unitKey ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}" href="{{ route('devices.graphs') . '?' . http_build_query(array_merge($queryBase, ['unit' => $unitKey])) }}">{{ $unitOption['label'] }}</a>
            @endforeach
            @if($isAdminRole)
                <a class="rounded-full bg-slate-100 px-4 py-2 text-xs font-black text-slate-600 hover:bg-slate-200" href="{{ route('devices.events.show', ['device' => $device->id]) }}">Back to Events</a>
            @endif
        </div>
    </form>

    <div class="mt-8 flex items-end justify-between gap-4">
        <div>
            <h2 class="text-3xl font-black tracking-tight">Fixed Time Windows</h2>
            <p class="mt-2 text-sm text-slate-500">Separate charts for the most useful short and medium range traffic views.</p>
        </div>
        <span class="rounded-full bg-slate-200 px-4 py-2 text-xs font-black text-slate-600">Hover to inspect values</span>
    </div>

    <section class="mt-5 grid gap-5 xl:grid-cols-2">
        @foreach($fixedGraphs as $graph)
            <article class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 px-5 py-5">
                    <div>
                        <h3 class="text-2xl font-black tracking-tight">{{ $graph['title'] }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $graph['subtitle'] }}</p>
                    </div>
                    <div class="text-right">
                        <div class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">{{ $graph['sample_count'] }} samples</div>
                        <p class="mt-2 text-xs font-bold uppercase tracking-[0.18em] text-slate-400">{{ $graph['unit_label'] }}</p>
                    </div>
                </div>
                <div class="px-5 py-5">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
                        <span>{{ $graph['from_text'] }} to {{ $graph['to_text'] }}</span>
                        <button class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-600 hover:bg-slate-50" type="button" data-chart-reset="{{ $graph['dom_id'] }}">Reset</button>
                    </div>
                    <div class="chart-canvas overflow-hidden rounded-3xl border border-slate-200 bg-slate-50 p-3">
                        @if($graph['has_data'])
                            <canvas id="{{ $graph['dom_id'] }}"></canvas>
                        @else
                            <div class="flex h-full items-center justify-center px-6 text-center text-sm font-medium text-slate-500">{{ $graph['empty_message'] }}</div>
                        @endif
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Inbound</p>
                            <div class="mt-3 grid grid-cols-3 gap-2">
                                <div><p class="text-lg font-black text-emerald-700">{{ number_format((float) ($graph['stats']['in']['current'] ?? 0), 2) }}</p><p class="text-xs text-emerald-600">Current</p></div>
                                <div><p class="text-lg font-black text-emerald-700">{{ number_format((float) ($graph['stats']['in']['average'] ?? 0), 2) }}</p><p class="text-xs text-emerald-600">Average</p></div>
                                <div><p class="text-lg font-black text-emerald-700">{{ number_format((float) ($graph['stats']['in']['maximum'] ?? 0), 2) }}</p><p class="text-xs text-emerald-600">Peak</p></div>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-blue-700">Outbound</p>
                            <div class="mt-3 grid grid-cols-3 gap-2">
                                <div><p class="text-lg font-black text-blue-700">{{ number_format((float) ($graph['stats']['out']['current'] ?? 0), 2) }}</p><p class="text-xs text-blue-600">Current</p></div>
                                <div><p class="text-lg font-black text-blue-700">{{ number_format((float) ($graph['stats']['out']['average'] ?? 0), 2) }}</p><p class="text-xs text-blue-600">Average</p></div>
                                <div><p class="text-lg font-black text-blue-700">{{ number_format((float) ($graph['stats']['out']['maximum'] ?? 0), 2) }}</p><p class="text-xs text-blue-600">Peak</p></div>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        @endforeach
    </section>

    <div class="mt-10 flex items-end justify-between gap-4">
        <div>
            <h2 class="text-3xl font-black tracking-tight">Weekly, Monthly, or Yearly Trend</h2>
            <p class="mt-2 text-sm text-slate-500">Use the filter above to switch mode and adjust the date range for the custom trend graph.</p>
        </div>
        <span class="rounded-full bg-blue-100 px-4 py-2 text-xs font-black text-blue-700">{{ ucfirst($trendModes[$trendMode]['label'] ?? 'Weekly') }} mode</span>
    </div>

    <section class="mt-5 overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 px-5 py-5">
            <div>
                <h3 class="text-2xl font-black tracking-tight">{{ $trendGraph['title'] }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ $trendGraph['subtitle'] }}</p>
            </div>
            <div class="text-right">
                <div class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">{{ $trendGraph['sample_count'] }} samples</div>
                <p class="mt-2 text-xs font-bold uppercase tracking-[0.18em] text-slate-400">{{ $trendGraph['unit_label'] }}</p>
            </div>
        </div>
        <div class="px-5 py-5">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
                <span>{{ $trendGraph['from_text'] }} to {{ $trendGraph['to_text'] }}</span>
                <button class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-600 hover:bg-slate-50" type="button" data-chart-reset="{{ $trendGraph['dom_id'] }}">Reset</button>
            </div>
            <div class="chart-canvas overflow-hidden rounded-3xl border border-slate-200 bg-slate-50 p-3">
                @if($trendGraph['has_data'])
                    <canvas id="{{ $trendGraph['dom_id'] }}"></canvas>
                @else
                    <div class="flex h-full items-center justify-center px-6 text-center text-sm font-medium text-slate-500">{{ $trendGraph['empty_message'] }}</div>
                @endif
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Inbound</p>
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <div><p class="text-lg font-black text-emerald-700">{{ number_format((float) ($trendGraph['stats']['in']['current'] ?? 0), 2) }}</p><p class="text-xs text-emerald-600">Current</p></div>
                        <div><p class="text-lg font-black text-emerald-700">{{ number_format((float) ($trendGraph['stats']['in']['average'] ?? 0), 2) }}</p><p class="text-xs text-emerald-600">Average</p></div>
                        <div><p class="text-lg font-black text-emerald-700">{{ number_format((float) ($trendGraph['stats']['in']['maximum'] ?? 0), 2) }}</p><p class="text-xs text-emerald-600">Peak</p></div>
                    </div>
                </div>
                <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-blue-700">Outbound</p>
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <div><p class="text-lg font-black text-blue-700">{{ number_format((float) ($trendGraph['stats']['out']['current'] ?? 0), 2) }}</p><p class="text-xs text-blue-600">Current</p></div>
                        <div><p class="text-lg font-black text-blue-700">{{ number_format((float) ($trendGraph['stats']['out']['average'] ?? 0), 2) }}</p><p class="text-xs text-blue-600">Average</p></div>
                        <div><p class="text-lg font-black text-blue-700">{{ number_format((float) ($trendGraph['stats']['out']['maximum'] ?? 0), 2) }}</p><p class="text-xs text-blue-600">Peak</p></div>
                    </div>
                </div>
            </div>
            <p class="mt-4 text-xs font-medium text-slate-500">Tip: hover the chart for exact values. Click the legend to hide inbound or outbound traffic and isolate one series.</p>
        </div>
    </section>
</div>

@if(!empty($chartPayload))
<script>
(function () {
    const payload = @json($chartPayload);
    if (!Array.isArray(payload) || !window.Chart) {
        return;
    }

    const charts = new Map();
    const resolvePointRadius = (context) => {
        const values = context && context.dataset && Array.isArray(context.dataset.data)
            ? context.dataset.data
            : [];

        return values.length <= 1 ? 4 : 0;
    };

    const resolvePointHoverRadius = (context) => {
        const values = context && context.dataset && Array.isArray(context.dataset.data)
            ? context.dataset.data
            : [];

        return values.length <= 1 ? 6 : 4;
    };

    payload.forEach((graph) => {
        if (!graph || !graph.domId || !graph.hasData) {
            return;
        }

        const canvas = document.getElementById(graph.domId);
        if (!canvas) {
            return;
        }

        const chart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: Array.isArray(graph.labels) ? graph.labels : [],
                datasets: [
                    {
                        label: 'Inbound',
                        data: Array.isArray(graph.inSeries) ? graph.inSeries : [],
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.16)',
                        fill: true,
                        borderWidth: 2.2,
                        tension: 0.28,
                        pointRadius: resolvePointRadius,
                        pointHoverRadius: resolvePointHoverRadius,
                        pointHitRadius: 16,
                    },
                    {
                        label: 'Outbound',
                        data: Array.isArray(graph.outSeries) ? graph.outSeries : [],
                        borderColor: '#1d4ed8',
                        backgroundColor: 'rgba(29, 78, 216, 0.12)',
                        fill: true,
                        borderWidth: 2.2,
                        tension: 0.28,
                        pointRadius: resolvePointRadius,
                        pointHoverRadius: resolvePointHoverRadius,
                        pointHitRadius: 16,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 10,
                            boxHeight: 10,
                            color: '#334155',
                            padding: 18,
                            font: {
                                family: 'Inter',
                                size: 12,
                                weight: '700',
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.94)',
                        titleColor: '#f8fafc',
                        bodyColor: '#f8fafc',
                        padding: 12,
                        cornerRadius: 12,
                        callbacks: {
                            label: (context) => {
                                const value = Number(context.parsed.y || 0);
                                return `${context.dataset.label}: ${value.toFixed(2)} ${graph.unitLabel}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(148, 163, 184, 0.14)' },
                        ticks: {
                            color: '#64748b',
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 8,
                            font: {
                                family: 'Inter',
                                size: 11,
                                weight: '700',
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(148, 163, 184, 0.14)' },
                        ticks: {
                            color: '#64748b',
                            font: {
                                family: 'Inter',
                                size: 11,
                                weight: '700',
                            },
                            callback: (value) => `${value} ${graph.unitLabel}`
                        }
                    }
                }
            }
        });

        charts.set(graph.domId, chart);
    });

    document.querySelectorAll('[data-chart-reset]').forEach((button) => {
        button.addEventListener('click', () => {
            const chartId = button.getAttribute('data-chart-reset') || '';
            const chart = charts.get(chartId);
            if (!chart) {
                return;
            }
            chart.data.datasets.forEach((dataset) => {
                dataset.hidden = false;
            });
            chart.update();
        });
    });
})();
</script>
@endif
<script>
(function () {
    const form = document.querySelector('[data-graph-filter-form]');
    if (!form) {
        return;
    }

    const fields = Array.from(form.querySelectorAll('[data-graph-auto-submit]'));
    if (!fields.length) {
        return;
    }

    let submitTimer = null;
    const scheduleSubmit = () => {
        if (submitTimer !== null) {
            window.clearTimeout(submitTimer);
        }

        submitTimer = window.setTimeout(() => {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
                return;
            }

            form.submit();
        }, 60);
    };

    fields.forEach((field) => {
        field.addEventListener('change', scheduleSubmit);
    });
})();
</script>
        </div>
    </main>
</div>
</body>
</html>
