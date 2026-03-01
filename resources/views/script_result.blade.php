<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-base" content="{{ url('/') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Command result</title>
    <script src="{{ asset('js/actions.js') . '?v=' . filemtime(public_path('js/actions.js')) }}" defer></script>
    <style>
        body {
            margin: 0;
            padding: 24px;
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }

        .admin-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 24px;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #ffffff;
        }

        .admin-nav a,
        .admin-nav span {
            display: inline-flex;
            align-items: center;
            height: 38px;
            padding: 0 14px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            color: #475569;
            text-decoration: none;
        }

        .admin-nav a:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .admin-nav span {
            color: #0f172a;
        }
    </style>
</head>
<body>
@php
    $switchName = data_get($device->metadata ?? [], 'cisco.name') ?: ($device->name ?? ('device-' . $device->id));
    $switchIp = data_get($device->metadata ?? [], 'cisco.ip_address') ?: ($device->ip_address ?? '-');
    $statusText = $ok ? 'OK' : 'ERROR';
@endphp

<nav class="admin-nav">
    <a href="{{ route('dashboard') }}">Dashboard</a>
    <a href="{{ route('users.index') }}">Users</a>
    <span>Devices</span>
    <a href="{{ route('devices.index') }}">Device Management</a>
    <a href="{{ route('devices.details') }}">Devices List</a>
    <a href="{{ route('devices.wizard') }}">Assignments</a>
</nav>

<h1>Command result</h1>

<p><strong>Switch:</strong> {{ $switchName }}</p>
<p><strong>IP:</strong> {{ $switchIp }}</p>
<p><strong>Command:</strong> {{ $command }}</p>
<p><strong>Status:</strong> {{ $statusText }}</p>

<details open>
    <summary><strong>Raw output</strong></summary>
    <pre style="white-space: pre; overflow: auto;">{{ $output }}</pre>
</details>

<p><a href="javascript:history.back()">-- back to commands --</a></p>
</body>
</html>
