<section class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/60">
    <h3 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400" data-sidebar-heading>Severity</h3>
    <div class="mt-4 flex flex-col gap-1">
        @php
            $severityOptions = [
                'all' => ['label' => 'All', 'count' => $severityCounts['all'] ?? 0, 'dot' => 'bg-primary'],
                'critical' => ['label' => 'Critical', 'count' => $severityCounts['critical'] ?? 0, 'dot' => 'bg-red-500'],
                'warning' => ['label' => 'Warning', 'count' => $severityCounts['warning'] ?? 0, 'dot' => 'bg-amber-500'],
                'info' => ['label' => 'Info', 'count' => $severityCounts['info'] ?? 0, 'dot' => 'bg-sky-500'],
            ];
            $selectedSeverity = $filters['severity'] ?? 'all';
        @endphp

        @foreach ($severityOptions as $severityKey => $severityOption)
            <a class="flex items-center justify-between rounded-xl px-3 py-2 text-sm font-medium transition-colors {{ $selectedSeverity === $severityKey ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-800 dark:text-slate-100' : 'text-slate-500 hover:bg-white hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100' }}" href="{{ route('notifications.index', array_merge(request()->except('page'), ['severity' => $severityKey])) }}">
                <span class="flex items-center gap-3">
                    <span class="h-2.5 w-2.5 rounded-full {{ $severityOption['dot'] }}"></span>
                    <span>{{ $severityOption['label'] }}</span>
                </span>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500 dark:bg-slate-900 dark:text-slate-300">{{ $severityOption['count'] }}</span>
            </a>
        @endforeach
    </div>
</section>

<section class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/60">
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400" data-sidebar-heading>Devices</h3>
        <span class="text-[11px] font-semibold text-slate-400">{{ count($filters['device_ids'] ?? []) }} selected</span>
    </div>

    <form class="mt-4 flex flex-col gap-2" method="GET" action="{{ route('notifications.index') }}">
        <input type="hidden" name="severity" value="{{ $filters['severity'] ?? 'all' }}"/>
        <input type="hidden" name="status" value="{{ $filters['status'] ?? 'all' }}"/>

        <div class="max-h-64 space-y-1 overflow-y-auto pr-1">
            @forelse (($devices ?? collect()) as $deviceOption)
                @php
                    $selectedDeviceIds = $filters['device_ids'] ?? [];
                    $checked = in_array((int) $deviceOption->id, array_map('intval', $selectedDeviceIds), true);
                @endphp
                <label class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-slate-600 transition-colors hover:bg-white dark:text-slate-300 dark:hover:bg-slate-800">
                    <input class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" type="checkbox" name="device_ids[]" value="{{ $deviceOption->id }}" @checked($checked) />
                    <span class="min-w-0 truncate">{{ $deviceOption->name }}</span>
                </label>
            @empty
                <p class="rounded-xl bg-white px-3 py-2 text-sm text-slate-500 dark:bg-slate-800 dark:text-slate-400">No devices available.</p>
            @endforelse
        </div>

        <div class="mt-3 flex gap-2">
            <button class="flex-1 rounded-xl bg-primary px-3 py-2 text-sm font-semibold text-white hover:bg-primary/90" type="submit">Apply</button>
            <a class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800" href="{{ route('notifications.index', ['severity' => $filters['severity'] ?? 'all', 'status' => $filters['status'] ?? 'all']) }}">Clear</a>
        </div>
    </form>
</section>
