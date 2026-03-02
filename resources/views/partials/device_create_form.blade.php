@php
$serverServiceOptions = [
    'web',
    'astra',
    'hls_restream',
    'xtream',
    'log',
    'middleware',
    'radius',
    'vertiofiber',
    'netplay',
    'speedtest',
    'tftp',
    'storage',
    'rsyslog',
    'dns',
    'voip',
    'stock_management',
    'crm',
    'vmware',
    'vnc',
];
$serverWebCredentialServicesLabel = 'Web, Log, Middleware, Radius, Vertiofiber, Netplay, Hls Restream, Xtream, VoIP, Stock Management, CRM, VMware';
$serverWebAddressServicesLabel = $serverWebCredentialServicesLabel . ', Speedtest';
$oldServerServices = old('server_service', []);
if (!is_array($oldServerServices)) {
    $oldServerServices = [$oldServerServices];
}
$oldServerServices = array_values(array_filter(array_map(
    static fn ($service) => is_scalar($service)
        ? str_replace('netplat', 'netplay', strtolower(trim((string) $service)))
        : null,
    $oldServerServices
), static fn ($service) => $service !== null && $service !== ''));
@endphp
<form class="space-y-6" method="POST" action="{{ route('devices.store') }}">
@csrf
<div class="grid grid-cols-1 gap-4">
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Device Type</label>
<select class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="type" required data-device-type>
<option value="CISCO">CISCO</option>
<option value="MIMOSA">MIMOSA</option>
<option value="OLT">OLT</option>
<option value="SERVER">SERVER</option>
<option value="MIKROTIK">MIKROTIK</option>
</select>
</div>
</div>
<div class="border-t border-[#e7ebf3] dark:border-gray-800 pt-6 hidden" data-cisco-fields>
<div class="flex flex-col gap-1">
<div class="flex flex-col gap-1">
<h4 class="text-base font-bold">Register Cisco</h4>
<p class="text-xs text-gray-500">Fill switch identity, connectivity, and optional automation settings.</p>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
<div class="flex flex-col gap-2 order-last">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Port</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_port" placeholder="161" type="number" min="1" max="65535" disabled/>
</div>
<div class="flex flex-col gap-2 order-last">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Version</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_version" type="text" value="2c"/>
</div>
<div class="flex flex-col gap-2 order-last">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Community</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_community" placeholder="e.g., public" type="text"/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Device Name *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="cisco_name" placeholder="e.g. core-switch-01" type="text" data-cisco-name data-cisco-required/>
<p class="text-[11px] text-gray-400">Used to auto-fill Backup/Exec URLs and Location.</p>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Switch Model</label>
<select class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="switch_model" data-cisco-switch-model>
<option value="">--Select Model--</option>
<option value="Nexus">Nexus</option>
<option value="4948">4948</option>
<option value="3560">3560</option>
<option value="Other">Other</option>
</select>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">IP Address *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="ip_address" placeholder="e.g., 192.168.1.10" type="text" data-cisco-required/>
</div>
<div class="flex flex-col gap-2" data-cisco-username-field>
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Username *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="cisco_username" placeholder="e.g., admin" type="text" data-cisco-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Password *</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="cisco_password" placeholder="********" type="password" data-cisco-required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Temp Poll Minutes (default 1)</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="temp_poll_minutes" type="number" min="1" max="1440" value="1"/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Enable Password</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="enable_password" placeholder="********" type="password"/>
</div>
<div class="flex flex-col gap-2 md:col-span-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Folder Location</label>
<input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="folder_location" placeholder="uno/<switch_name>" type="text" data-cisco-folder/>
<p class="text-[11px] text-gray-400">Default: uno/&lt;switch_name&gt;</p>
</div>
</div>
</div>
<div class="border-t border-[#e7ebf3] dark:border-gray-800 pt-6 hidden" data-mimosa-fields>
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div class="flex flex-col gap-1">
      <h4 class="text-base font-bold">Register Mimosa</h4>
      <p class="text-xs text-gray-500">Select a model, then fill only the model-specific fields below.</p>
    </div>
    <div class="flex items-center gap-2">
      <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Model</label>
      <select class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-10 text-sm" name="mimosa_model" data-mimosa-model>
        <option value="C5C">C5C</option>
        <option value="C5X">C5X</option>
        <option value="B11">B11</option>
      </select>
    </div>
  </div>
  <div class="mt-5 space-y-6">
    <div class="hidden" data-mimosa-form="C5C">
      <h5 class="text-lg font-bold text-primary underline">Admin Panel - Add C5C Device</h5>
      <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Add C5C Device (C5C)</p>
      <div class="mt-4 grid grid-cols-1 gap-4">
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Device Name *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_name" type="text" data-mimosa-required/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">IP *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_ip" placeholder="e.g., 192.168.1.10" type="text" data-mimosa-required/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Username *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_username" placeholder="e.g., admin" type="text" data-mimosa-required/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Password *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_password" type="password" data-mimosa-required/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5C MAC Address:</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_mac_address" type="text"/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5C URL:</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_url" type="text"/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5C Station:</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_station" type="text"/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5C Switch ID:</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_switch_id" type="text"/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5C Switch Port:</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_switch_port" type="text"/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5C VLAN:</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5c_vlan" type="text"/></div>
      </div>
    </div>
    <div class="hidden" data-mimosa-form="C5X">
      <h5 class="text-lg font-bold text-primary underline">Admin Panel - Add C5X Device</h5>
      <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Add C5X Device (C5X)</p>
      <div class="mt-4 grid grid-cols-1 gap-4">
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Device Name *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_name" type="text" data-mimosa-required/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">IP *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_ip" placeholder="e.g., 192.168.1.10" type="text" data-mimosa-required/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Username *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_username" placeholder="e.g., admin" type="text" data-mimosa-required/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Password *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_password" type="password" data-mimosa-required/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5X MAC Address:</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_mac_address" type="text"/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5X URL:</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_url" type="text"/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5X Station:</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_station" type="text"/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5X Switch ID:</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_switch_id" type="text"/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5X Switch Port:</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_switch_port" type="text"/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">C5X VLAN:</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_c5x_vlan" type="text"/></div>
      </div>
    </div>
    <div class="hidden" data-mimosa-form="B11">
      <h5 class="text-lg font-bold text-primary underline">Admin Panel - Add B11 Device</h5>
      <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Add B11 Device (B11)</p>
      <div class="mt-4 grid grid-cols-1 gap-4">
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Device Name *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_name" type="text" data-mimosa-required/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">IP *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_ip" placeholder="e.g., 192.168.1.10" type="text" data-mimosa-required/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Username *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_username" placeholder="e.g., admin" type="text" data-mimosa-required/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Password *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_password" type="password" data-mimosa-required/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">B11 MAC Address:</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_mac_address" type="text"/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">B11 URL:</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_url" type="text"/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">B11 Station:</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_station" type="text"/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">B11 Switch ID:</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_switch_id" type="text"/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">B11 Switch Port:</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_switch_port" type="text"/></div>
        <div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">B11 VLAN:</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mimosa_b11_vlan" type="text"/></div>
      </div>
    </div>
</div>
  <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="flex flex-col gap-2 md:col-span-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Port</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_port" placeholder="161" type="number" min="1" max="65535" disabled/></div>
    <div class="flex flex-col gap-2 md:col-span-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Community</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_community" placeholder="e.g., public" type="text" disabled/></div>
  </div>
</div>
<div class="border-t border-[#e7ebf3] dark:border-gray-800 pt-6 hidden" data-server-fields>
<div class="flex flex-col gap-1">
<h4 class="text-base font-bold">Register Server</h4>
<p class="text-xs text-gray-500">Start with server role and specs, then fill access and network details.</p>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
<div class="flex flex-col gap-2 order-last"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Port</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_port" type="number" min="1" max="65535" placeholder="161" disabled/></div>
<div class="flex flex-col gap-2 order-last"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Community</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_community" placeholder="e.g., public" type="text" disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Server Type</label><select class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_type" data-server-type disabled><option value="virtual_server">Virtual Server</option><option value="stand_alone_server">Stand Alone Server</option></select></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Hardware Specs</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_hardware_specs" placeholder="e.g., 8 vCPU, 32GB RAM, 500GB NVMe" type="text" disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Device Name *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_name" placeholder="e.g., app-node-01" type="text" data-server-required disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Username *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_username" placeholder="e.g., root" type="text" data-server-required disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Password *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_password" placeholder="********" type="password" data-server-required disabled/></div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Services</label>
<div class="rounded-lg border border-[#cfd7e7] dark:border-gray-700 bg-white dark:bg-gray-800 p-3 max-h-44 overflow-y-auto space-y-2" data-server-service>
@foreach ($serverServiceOptions as $serviceOption)
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
<input class="rounded border-[#cfd7e7] text-primary focus:ring-primary" type="checkbox" name="server_service[]" value="{{ $serviceOption }}" data-server-service-option @checked(in_array($serviceOption, $oldServerServices, true)) disabled/>
<span>{{ $serviceOption === 'vnc' ? 'VNC' : ucwords(str_replace('_', ' ', $serviceOption)) }}</span>
</label>
@endforeach
</div>
<p class="text-[11px] text-gray-400">Select one or more services.</p>
</div>
<div class="flex flex-col gap-2 hidden" data-server-vnc-field><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">VNC IP (VNC)</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_vnc_address_port" placeholder="e.g., 192.168.1.20" type="text" disabled/></div>
<div class="flex flex-col gap-2 hidden" data-server-vnc-field><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">VNC Password (VNC)</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_vnc_password" placeholder="********" type="password" disabled/></div>
<div class="flex flex-col gap-2 hidden" data-server-web-address-field><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Server Web Address and Port ({{ $serverWebAddressServicesLabel }})</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_web_address_port" placeholder="e.g., https://example.com:8080" type="text" data-server-web-address-required disabled/></div>
<div class="flex flex-col gap-2 hidden" data-server-web-auth-field><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Server Web Username ({{ $serverWebCredentialServicesLabel }})</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_web_username" placeholder="e.g., admin" type="text" disabled/></div>
<div class="flex flex-col gap-2 hidden" data-server-web-auth-field><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Server Web Password ({{ $serverWebCredentialServicesLabel }})</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_web_password" placeholder="********" type="password" disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Server SSH Port</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_ssh_port" type="number" min="1" max="65535" placeholder="22" disabled/></div>
<div class="flex flex-col gap-2 hidden" data-server-standalone-field><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Cabinet ID</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_cabinet_id" type="text" placeholder="e.g., CAB-01" disabled/></div>
<div class="flex flex-col gap-2 hidden" data-server-standalone-field><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Rack UID</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="server_rack_uid" type="text" placeholder="e.g., RACK-U12" disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">IP Address *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="ip_address" placeholder="e.g., 192.168.1.10" type="text" data-server-required disabled/></div>
</div>
</div>
<div class="border-t border-[#e7ebf3] dark:border-gray-800 pt-6 hidden" data-olt-fields>
<div class="flex flex-col gap-1">
<h4 class="text-base font-bold">Register OLT</h4>
<p class="text-xs text-gray-500">Add OLT identity, capacity, and access details.</p>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Device Name *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="name" placeholder="e.g. OLT-01" type="text" data-olt-required disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Model</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_model" placeholder="e.g., MA5800-X17" type="text" disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Number of Ports</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_number_of_ports" type="number" min="1" max="4096" placeholder="e.g., 16" disabled/></div>
<div class="flex flex-col gap-2 order-last"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Port</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="snmp_port" placeholder="161" type="number" min="1" max="65535" disabled/></div>
<div class="flex flex-col gap-2 order-last"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Community</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_snmp_community" placeholder="e.g., public" type="text" disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">IP *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_ip_address" placeholder="e.g., 192.168.1.20" type="text" data-olt-required disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Username *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_username" placeholder="e.g., admin" type="text" data-olt-required disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Password *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_password" placeholder="********" type="password" data-olt-required disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Web Address</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="olt_web_address" placeholder="e.g., http://192.168.1.20" type="text" disabled/></div>
</div>
</div>
<div class="border-t border-[#e7ebf3] dark:border-gray-800 pt-6 hidden" data-mikrotik-fields>
<div class="flex flex-col gap-1">
<h4 class="text-base font-bold">Register MikroTik</h4>
<p class="text-xs text-gray-500">Add MikroTik connectivity, access, and rack details.</p>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Device Name *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="name" placeholder="e.g. mikrotik-01" type="text" data-mikrotik-required disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">IP *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_ip_address" placeholder="e.g., 192.168.88.1" type="text" data-mikrotik-required disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Username *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_username" placeholder="e.g., admin" type="text" data-mikrotik-required disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Password *</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_password" placeholder="********" type="password" data-mikrotik-required disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Location</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_location" placeholder="e.g., POP-A / Floor 2" type="text" disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Cabinet ID</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_cabinet_id" placeholder="e.g., CAB-03" type="text" disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Rack UID</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_rack_uid" placeholder="e.g., U18" type="text" disabled/></div>
<div class="flex flex-col gap-2 order-last"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Community</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_snmp_community" placeholder="e.g., public" type="text" disabled/></div>
<div class="flex flex-col gap-2 order-last"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SNMP Port</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_snmp_port" type="number" min="1" max="65535" placeholder="161" disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Winbox Port</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_winbox_port" type="number" min="1" max="65535" placeholder="8291" disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">SSH Port</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_ssh_port" type="number" min="1" max="65535" placeholder="22" disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Telnet Port</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_telnet_port" type="number" min="1" max="65535" placeholder="23" disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">API Port</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_api_port" type="number" min="1" max="65535" placeholder="8728" disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">API SSL Port</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_api_ssl_port" type="number" min="1" max="65535" placeholder="8729" disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">FTP Port</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_ftp_port" type="number" min="1" max="65535" placeholder="21" disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">HTTP Port</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_http_port" type="number" min="1" max="65535" placeholder="80" disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Web Address</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_web_address" placeholder="e.g., http://router.local:80" type="text" disabled/></div>
<div class="flex flex-col gap-2"><label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Winbox Address</label><input class="rounded-lg border-[#cfd7e7] dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary focus:ring-primary h-11" name="mikrotik_winbox_address" placeholder="e.g., 192.168.88.1:8291" type="text" disabled/></div>
</div>
</div>
<div class="flex flex-wrap items-center justify-end gap-3">
<a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-950/20 font-medium transition-colors" href="{{ route('auth.logout') }}">
<span class="material-symbols-outlined text-[20px]">logout</span>
<span class="text-sm">Logout</span>
</a>
<button class="px-6 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary/90" type="submit">Create Device</button>
</div>
</form>
