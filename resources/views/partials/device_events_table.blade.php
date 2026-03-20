<div class="overflow-x-auto">
    <table class="min-w-[880px] w-full text-left border-collapse">
        <thead class="bg-gray-50 dark:bg-gray-800/50 border-b border-[#cfd7e7] dark:border-gray-800">
            <tr>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">ID</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Device</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Type</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Subtype</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">IP</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[#cfd7e7] dark:divide-gray-800">
            @forelse ($groupDevices as $device)
                @php
                    $meta = $device->metadata ?? [];
                    $cisco = data_get($meta, 'cisco', []);
                    $mimosa = data_get($meta, 'mimosa', []);
                    $olt = data_get($meta, 'olt', []);
                    $mikrotik = data_get($meta, 'mikrotik', []);
                    $server = data_get($meta, 'server', []);

                    $mimosaName = data_get($mimosa, 'name')
                        ?? data_get($mimosa, 'mimosa_c5c_name')
                        ?? data_get($mimosa, 'mimosa_c5x_name')
                        ?? data_get($mimosa, 'mimosa_b11_name');
                    $mimosaIp = data_get($mimosa, 'ip')
                        ?? data_get($mimosa, 'mimosa_c5c_ip')
                        ?? data_get($mimosa, 'mimosa_c5x_ip')
                        ?? data_get($mimosa, 'mimosa_b11_ip');

                    $switchName = data_get($cisco, 'name')
                        ?? $mimosaName
                        ?? data_get($olt, 'name')
                        ?? data_get($mikrotik, 'name')
                        ?? data_get($server, 'server_name')
                        ?? $device->name;
                    $typeDisplay = $device->type ?? data_get($cisco, 'type') ?? '-';
                    $subtypeDisplay = $device->model ?? data_get($cisco, 'switch_model') ?? '-';
                    $ipAddress = data_get($cisco, 'ip_address')
                        ?? data_get($olt, 'ip_address')
                        ?? data_get($mikrotik, 'ip_address')
                        ?? data_get($server, 'ip_address')
                        ?? $device->ip_address
                        ?? $mimosaIp
                        ?? '-';

                    $isOnline = strtolower((string) ($device->status ?? '')) === 'online';
                @endphp
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                    <td class="px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-100">{{ $device->id }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-100">
                        <div class="font-semibold">{{ $switchName ?: '-' }}</div>
                        @if (!empty($device->serial_number))
                            <div class="text-[11px] text-gray-400">{{ $device->serial_number }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $typeDisplay ?: '-' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $subtypeDisplay ?: '-' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $ipAddress }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $isOnline ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $isOnline ? 'Online' : (ucfirst((string) ($device->status ?? 'offline')) ?: 'Offline') }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            <a class="px-2 py-1 text-[11px] font-semibold border border-slate-200 rounded hover:bg-slate-50" href="{{ route('devices.events.show', ['device' => $device->id]) }}">Events</a>
                            <a class="px-2 py-1 text-[11px] font-semibold border border-slate-200 rounded hover:bg-slate-50" href="{{ route('devices.graphs', ['device' => $device->id]) }}">Graphs</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="px-6 py-6 text-sm text-gray-500" colspan="7">{{ $emptyMessage ?? 'No devices found.' }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
