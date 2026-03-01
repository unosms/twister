@php
$credentialMode = $credentialMode ?? 'password_enable';
$primaryCredentialLabel = $credentialMode === 'username_password' ? 'Username' : 'Password';
$secondaryCredentialLabel = $credentialMode === 'username_password' ? 'Password' : 'Enable Password';
@endphp
<div class="overflow-x-auto">
<table class="min-w-[1200px] w-full text-left border-collapse">
<thead class="bg-gray-50 dark:bg-gray-800/50 border-b border-[#cfd7e7] dark:border-gray-800">
<tr>
<th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">ID</th>
<th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Switch Name</th>
<th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Type</th>
<th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Subtype</th>
<th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">IP</th>
<th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Alive</th>
<th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">{{ $primaryCredentialLabel }}</th>
<th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">{{ $secondaryCredentialLabel }}</th>
<th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Temperature</th>
<th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Uptime</th>
<th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Actions</th>
<th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Graphs</th>
</tr>
</thead>
<tbody class="divide-y divide-[#cfd7e7] dark:divide-gray-800">
@forelse ($groupDevices as $device)
@include('partials.device_details_row', ['device' => $device, 'credentialMode' => $credentialMode])
@empty
<tr>
<td class="px-6 py-6 text-sm text-gray-500" colspan="12">{{ $emptyMessage ?? 'No devices found.' }}</td>
</tr>
@endforelse
</tbody>
</table>
</div>
