<?php
$meta = $device->metadata ?? [];
$cisco = data_get($meta, 'cisco', []);
$mimosa = data_get($meta, 'mimosa', []);
$olt = data_get($meta, 'olt', []);
$mikrotik = data_get($meta, 'mikrotik', []);
$server = data_get($meta, 'server', []);
$credentialMode = $credentialMode ?? 'password_enable';
$typeKey = strtoupper((string) ($device->type ?? ''));
$decryptValue = static function ($value) {
    if (empty($value)) {
        return null;
    }

    try {
        return decrypt($value);
    } catch (\Throwable $e) {
        return $value;
    }
};
$mimosaName = data_get($mimosa, 'name')
    ?? data_get($mimosa, 'mimosa_c5c_name')
    ?? data_get($mimosa, 'mimosa_c5x_name')
    ?? data_get($mimosa, 'mimosa_b11_name');
$mimosaIp = data_get($mimosa, 'ip')
    ?? data_get($mimosa, 'mimosa_c5c_ip')
    ?? data_get($mimosa, 'mimosa_c5x_ip')
    ?? data_get($mimosa, 'mimosa_b11_ip');
$mimosaUsername = data_get($mimosa, 'username')
    ?? data_get($mimosa, 'mimosa_c5c_username')
    ?? data_get($mimosa, 'mimosa_c5x_username')
    ?? data_get($mimosa, 'mimosa_b11_username');
$mimosaPasswordEncrypted = data_get($mimosa, 'password')
    ?? data_get($mimosa, 'mimosa_c5c_password')
    ?? data_get($mimosa, 'mimosa_c5x_password')
    ?? data_get($mimosa, 'mimosa_b11_password');
$switchName = data_get($cisco, 'name') ?? $mimosaName ?? data_get($olt, 'name') ?? data_get($mikrotik, 'name') ?? data_get($server, 'server_name') ?? $device->name;
$typeDisplay = $device->type ?? data_get($cisco, 'type') ?? '-';
$subtypeDisplay = $device->model ?? data_get($cisco, 'switch_model') ?? '-';
$ipAddress = data_get($cisco, 'ip_address')
    ?? data_get($olt, 'ip_address')
    ?? data_get($mikrotik, 'ip_address')
    ?? data_get($server, 'ip_address')
    ?? $device->ip_address
    ?? $mimosaIp;
$password = $decryptValue(data_get($cisco, 'password'));
$enablePasswordEncrypted = data_get($cisco, 'enable_password') ?? data_get($meta, 'enable_password');
$enablePassword = $decryptValue($enablePasswordEncrypted);
$username = match ($typeKey) {
    'MIKROTIK' => data_get($mikrotik, 'username'),
    'OLT' => data_get($olt, 'username'),
    'MIMOSA' => $mimosaUsername,
    'SERVER' => data_get($server, 'username') ?? data_get($server, 'web_username'),
    default => data_get($cisco, 'username'),
};
$typePassword = match ($typeKey) {
    'MIKROTIK' => $decryptValue(data_get($mikrotik, 'password')),
    'OLT' => $decryptValue(data_get($olt, 'password')),
    'MIMOSA' => $decryptValue($mimosaPasswordEncrypted),
    'SERVER' => $decryptValue(data_get($server, 'password') ?? data_get($server, 'root_password') ?? data_get($server, 'web_password')),
    default => $password,
};
$primaryCredential = $credentialMode === 'username_password' ? ($username ?? '-') : ($password ?? '-');
$secondaryCredential = $credentialMode === 'username_password' ? ($typePassword ?? '-') : ($enablePassword ?? '-');
$showExecActions = $typeKey === 'CISCO';
$temperatureValue = data_get($meta, 'temperature');
$deg = "\u{00B0}";
$temperature = is_numeric($temperatureValue) ? $temperatureValue . $deg . 'C' : ($temperatureValue ?? null);
if (is_string($temperature)) {
    $temperature = preg_replace('/\x{FFFD}/u', $deg, $temperature);
    $temperature = str_replace("\u{00C2}\u{00B0}", $deg, $temperature);
    $temperature = str_replace("\u{00C2}", '', $temperature);
    $temperature = str_replace(['$', '?'], $deg, $temperature);
    $temperature = preg_replace('/\b([0-9]+)\s*[^0-9]*C\b/i', '$1' . $deg . 'C', $temperature);
    $temperature = preg_replace('/\b(inlet|outlet|temp):\s*([0-9]+)\s*[^0-9]*C\b/i', '$1: $2' . $deg . 'C', $temperature);
}
$inlet = null;
$outlet = null;
$tempSingle = null;
$temperatureDisplay = $temperature;
if (is_string($temperature)) {
    if (preg_match('/inlet:\s*([0-9]+)/i', $temperature, $match)) {
        $inlet = $match[1] . $deg . 'C';
    }
    if (preg_match('/outlet:\s*([0-9]+)/i', $temperature, $match)) {
        $outlet = $match[1] . $deg . 'C';
    }
    if (preg_match('/temp:\s*([0-9]+)/i', $temperature, $match)) {
        $tempSingle = 'temp: ' . $match[1] . $deg . 'C';
    }
}
if ($inlet || $outlet) {
    $temperatureDisplay = trim(($inlet ? 'inlet: ' . $inlet : '') . ($inlet && $outlet ? ' | ' : '') . ($outlet ? 'outlet: ' . $outlet : ''));
} elseif ($tempSingle) {
    $temperatureDisplay = $tempSingle;
}
$uptime = data_get($meta, 'uptime');
$actionName = trim((string) (data_get($cisco, 'name') ?? $switchName ?? $device->name ?? ''));
$actionSlug = preg_replace('/\s+/', '_', $actionName);
$execPath = data_get($cisco, 'exec_cmd') ?: ($actionSlug !== '' ? 'exec.php?name=' . rawurlencode($actionSlug) : null);
if ($execPath && !str_starts_with($execPath, 'http') && str_starts_with($execPath, 'exec.php') && !preg_match('/(?:^|[?&])id=/', $execPath)) {
    $execPath .= (str_contains($execPath, '?') ? '&' : '?') . 'id=' . $device->id;
}
$execUrl = $execPath ? (str_starts_with($execPath, 'http') ? $execPath : url($execPath)) : null;
$alive = strtolower($device->status ?? '') === 'online';
?>
<tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40" data-device-row data-device-id="<?php echo e($device->id); ?>">
<td class="px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-100"><?php echo e($device->id); ?></td>
<td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-100"><?php echo e($switchName ?? '-'); ?></td>
<td class="px-4 py-3 text-sm text-gray-600"><?php echo e($typeDisplay ?? "-"); ?></td>
<td class="px-4 py-3 text-sm text-gray-600"><?php echo e($subtypeDisplay ?? "-"); ?></td>
<td class="px-4 py-3 text-sm text-gray-600"><?php echo e($ipAddress ?? "-"); ?></td>
<td class="px-4 py-3">
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold <?php echo e($alive ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'); ?>" data-status-badge data-status-mode="yesno" data-status-base="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold" data-status-online="bg-emerald-100 text-emerald-700" data-status-offline="bg-gray-100 text-gray-600">
<span data-status-text><?php echo e($alive ? 'YES' : 'NO'); ?></span>
</span>
</td>
<td class="px-4 py-3 text-sm text-gray-600 font-mono"><?php echo e($primaryCredential); ?></td>
<td class="px-4 py-3 text-sm text-gray-600 font-mono"><?php echo e($secondaryCredential); ?></td>
<td class="px-4 py-3 text-sm text-gray-700">
<div class="font-semibold" data-temp-value><?php echo e($temperatureDisplay ?? '-'); ?></div>
<div class="text-[10px] text-gray-400" data-temp-updated>updated <?php echo e($device->last_seen_at?->format('Y-m-d H:i:s') ?? '-'); ?></div>
</td>
<td class="px-4 py-3 text-sm text-gray-700">
<div class="font-semibold" data-uptime-value><?php echo e($uptime ?? '-'); ?></div>
<div class="text-[10px] text-gray-400" data-uptime-updated>updated <?php echo e($device->last_seen_at?->format('Y-m-d H:i:s') ?? '-'); ?></div>
</td>
<td class="px-4 py-3">
<div class="flex flex-wrap gap-2">
<a class="px-2 py-1 text-[11px] font-semibold border border-slate-200 rounded hover:bg-slate-50 inline-flex" href="<?php echo e(route('devices.backups.show', ['device' => $device->id])); ?>">Open Backup</a>
<?php if($showExecActions && $execUrl): ?>
<details class="w-full group">
<summary class="px-2 py-1 text-[11px] font-semibold border border-slate-200 rounded hover:bg-slate-50 cursor-pointer select-none inline-flex items-center gap-2 dark:border-gray-700 dark:hover:bg-gray-800 group-open:bg-slate-50 dark:group-open:bg-gray-800">
Open exec cmd
<span class="material-symbols-outlined text-[14px] transition-transform duration-200 group-open:rotate-180">expand_more</span>
</summary>
<div class="mt-3 border border-slate-200 dark:border-gray-700 rounded-lg overflow-hidden bg-white dark:bg-gray-900">
<table class="w-full text-left text-[11px]">
<thead class="bg-gray-50 dark:bg-gray-800/60 border-b border-slate-200 dark:border-gray-700">
<tr>
<th class="px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-gray-500">Command</th>
<th class="px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-gray-500">Action</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-200 dark:divide-gray-700">
<tr>
<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">showlog</td>
<td class="px-3 py-2">
<form class="flex flex-wrap items-center gap-2" method="GET" action="<?php echo e($execUrl); ?>" target="_blank">
<input type="hidden" name="cmd" value="showlog"/>
<input type="hidden" name="id" value="<?php echo e($device->id); ?>"/>
<button class="px-2 py-1 text-[11px] font-semibold border border-slate-200 rounded hover:bg-slate-50 dark:border-gray-700 dark:hover:bg-gray-800" type="submit">Run</button>
</form>
</td>
</tr>
<tr>
<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">showmac</td>
<td class="px-3 py-2">
<form class="flex flex-wrap items-center gap-2" method="GET" action="<?php echo e($execUrl); ?>" target="_blank">
<input type="hidden" name="cmd" value="showmac"/>
<input type="hidden" name="id" value="<?php echo e($device->id); ?>"/>
<input class="h-7 w-44 rounded border border-slate-200 bg-white px-2 text-[11px] placeholder:text-gray-400 focus:border-primary focus:ring-primary dark:border-gray-700 dark:bg-gray-800 dark:text-white" name="iface" placeholder="Enter interface (e.g. Gi1/0/1)" required/>
<button class="px-2 py-1 text-[11px] font-semibold border border-slate-200 rounded hover:bg-slate-50 dark:border-gray-700 dark:hover:bg-gray-800" type="submit">Run</button>
</form>
</td>
</tr>
<tr>
<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">showint</td>
<td class="px-3 py-2">
<form class="flex flex-wrap items-center gap-2" method="GET" action="<?php echo e($execUrl); ?>" target="_blank">
<input type="hidden" name="cmd" value="showint"/>
<input type="hidden" name="id" value="<?php echo e($device->id); ?>"/>
<input class="h-7 w-44 rounded border border-slate-200 bg-white px-2 text-[11px] placeholder:text-gray-400 focus:border-primary focus:ring-primary dark:border-gray-700 dark:bg-gray-800 dark:text-white" name="iface" placeholder="Enter interface (e.g. Gi1/0/1)" required/>
<button class="px-2 py-1 text-[11px] font-semibold border border-slate-200 rounded hover:bg-slate-50 dark:border-gray-700 dark:hover:bg-gray-800" type="submit">Run</button>
</form>
</td>
</tr>
<tr>
<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">showintstatus</td>
<td class="px-3 py-2">
<form class="flex flex-wrap items-center gap-2" method="GET" action="<?php echo e($execUrl); ?>" target="_blank">
<input type="hidden" name="cmd" value="showintstatus"/>
<input type="hidden" name="id" value="<?php echo e($device->id); ?>"/>
<button class="px-2 py-1 text-[11px] font-semibold border border-slate-200 rounded hover:bg-slate-50 dark:border-gray-700 dark:hover:bg-gray-800" type="submit">Run</button>
</form>
</td>
</tr>
<tr>
<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">restartint</td>
<td class="px-3 py-2">
<form class="flex flex-wrap items-center gap-2" method="GET" action="<?php echo e($execUrl); ?>" target="_blank">
<input type="hidden" name="cmd" value="restartint"/>
<input type="hidden" name="id" value="<?php echo e($device->id); ?>"/>
<input class="h-7 w-44 rounded border border-slate-200 bg-white px-2 text-[11px] placeholder:text-gray-400 focus:border-primary focus:ring-primary dark:border-gray-700 dark:bg-gray-800 dark:text-white" name="iface" placeholder="Enter interface (e.g. Gi1/0/1)" required/>
<button class="px-2 py-1 text-[11px] font-semibold border border-slate-200 rounded hover:bg-slate-50 dark:border-gray-700 dark:hover:bg-gray-800" type="submit">Run</button>
</form>
</td>
</tr>
<tr>
<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">disableint</td>
<td class="px-3 py-2">
<form class="flex flex-wrap items-center gap-2" method="GET" action="<?php echo e($execUrl); ?>" target="_blank">
<input type="hidden" name="cmd" value="disableint"/>
<input type="hidden" name="id" value="<?php echo e($device->id); ?>"/>
<input class="h-7 w-44 rounded border border-slate-200 bg-white px-2 text-[11px] placeholder:text-gray-400 focus:border-primary focus:ring-primary dark:border-gray-700 dark:bg-gray-800 dark:text-white" name="iface" placeholder="Enter interface (e.g. Gi1/0/1)" required/>
<button class="px-2 py-1 text-[11px] font-semibold border border-slate-200 rounded hover:bg-slate-50 dark:border-gray-700 dark:hover:bg-gray-800" type="submit">Run</button>
</form>
</td>
</tr>
<tr>
<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">enableint</td>
<td class="px-3 py-2">
<form class="flex flex-wrap items-center gap-2" method="GET" action="<?php echo e($execUrl); ?>" target="_blank">
<input type="hidden" name="cmd" value="enableint"/>
<input type="hidden" name="id" value="<?php echo e($device->id); ?>"/>
<input class="h-7 w-44 rounded border border-slate-200 bg-white px-2 text-[11px] placeholder:text-gray-400 focus:border-primary focus:ring-primary dark:border-gray-700 dark:bg-gray-800 dark:text-white" name="iface" placeholder="Enter interface (e.g. Gi1/0/1)" required/>
<button class="px-2 py-1 text-[11px] font-semibold border border-slate-200 rounded hover:bg-slate-50 dark:border-gray-700 dark:hover:bg-gray-800" type="submit">Run</button>
</form>
</td>
</tr>
<tr>
<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">shspantree</td>
<td class="px-3 py-2">
<form class="flex flex-wrap items-center gap-2" method="GET" action="<?php echo e($execUrl); ?>" target="_blank">
<input type="hidden" name="cmd" value="shspantree"/>
<input type="hidden" name="id" value="<?php echo e($device->id); ?>"/>
<input class="h-7 w-44 rounded border border-slate-200 bg-white px-2 text-[11px] placeholder:text-gray-400 focus:border-primary focus:ring-primary dark:border-gray-700 dark:bg-gray-800 dark:text-white" name="iface" placeholder="Enter interface (e.g. Gi1/0/1)" required/>
<button class="px-2 py-1 text-[11px] font-semibold border border-slate-200 rounded hover:bg-slate-50 dark:border-gray-700 dark:hover:bg-gray-800" type="submit">Run</button>
</form>
</td>
</tr>
<tr>
<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">renameint</td>
<td class="px-3 py-2">
<form class="flex flex-wrap items-center gap-2" method="GET" action="<?php echo e($execUrl); ?>" target="_blank">
<input type="hidden" name="cmd" value="renameint"/>
<input type="hidden" name="id" value="<?php echo e($device->id); ?>"/>
<input class="h-7 w-44 rounded border border-slate-200 bg-white px-2 text-[11px] placeholder:text-gray-400 focus:border-primary focus:ring-primary dark:border-gray-700 dark:bg-gray-800 dark:text-white" name="iface" placeholder="Enter interface (e.g. Gi1/0/1)" required/>
<input class="h-7 w-48 rounded border border-slate-200 bg-white px-2 text-[11px] placeholder:text-gray-400 focus:border-primary focus:ring-primary dark:border-gray-700 dark:bg-gray-800 dark:text-white" name="description" placeholder="Enter description" required/>
<button class="px-2 py-1 text-[11px] font-semibold border border-slate-200 rounded hover:bg-slate-50 dark:border-gray-700 dark:hover:bg-gray-800" type="submit">Run</button>
</form>
</td>
</tr>
<tr>
<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">sh fiber signal</td>
<td class="px-3 py-2">
<form class="flex flex-wrap items-center gap-2" method="GET" action="<?php echo e($execUrl); ?>" target="_blank">
<input type="hidden" name="cmd" value="shtransceiver"/>
<input type="hidden" name="id" value="<?php echo e($device->id); ?>"/>
<input class="h-7 w-44 rounded border border-slate-200 bg-white px-2 text-[11px] placeholder:text-gray-400 focus:border-primary focus:ring-primary dark:border-gray-700 dark:bg-gray-800 dark:text-white" name="iface" placeholder="Enter interface (e.g. Gi1/0/1)" required/>
<button class="px-2 py-1 text-[11px] font-semibold border border-slate-200 rounded hover:bg-slate-50 dark:border-gray-700 dark:hover:bg-gray-800" type="submit">Run</button>
</form>
</td>
</tr>
</tbody>
</table>
</div>
</details>
<?php endif; ?>
<a class="px-2 py-1 text-[11px] font-semibold border border-slate-200 rounded hover:bg-slate-50" href="<?php echo e(route('devices.events.show', ['device' => $device->id])); ?>">Events</a>
</div>
</td>
<td class="px-4 py-3">
<a class="px-2 py-1 text-[11px] font-semibold border border-slate-200 rounded hover:bg-slate-50 inline-flex" href="<?php echo e(route('devices.graphs', ['device' => $device->id])); ?>">Show graphs</a>
</td>
</tr>
