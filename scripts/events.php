<?php
declare(strict_types=1);

// events.php - viewer for device and interface events

date_default_timezone_set('Asia/Beirut');
require __DIR__ . '/db.php';

function safe_int(string $key, int $default, int $min, int $max): int
{
    $value = isset($_GET[$key]) ? (int) $_GET[$key] : $default;
    if ($value < $min) {
        return $min;
    }
    if ($value > $max) {
        return $max;
    }

    return $value;
}

function safe_str(string $key): string
{
    return isset($_GET[$key]) ? trim((string) $_GET[$key]) : '';
}

function esc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function severity_badge_class(string $severity): string
{
    return match (strtolower(trim($severity))) {
        'info' => 'sev-badge-info',
        'average' => 'sev-badge-average',
        'high' => 'sev-badge-high',
        'disaster' => 'sev-badge-disaster',
        default => 'sev-badge-default',
    };
}

function severity_border_class(string $severity): string
{
    return match (strtolower(trim($severity))) {
        'info' => 'sev-border-info',
        'average' => 'sev-border-average',
        'high' => 'sev-border-high',
        'disaster' => 'sev-border-disaster',
        default => 'sev-border-default',
    };
}

function fmt_duration(int $seconds): string
{
    $seconds = max(0, $seconds);
    $d = intdiv($seconds, 86400);
    $seconds %= 86400;
    $h = intdiv($seconds, 3600);
    $seconds %= 3600;
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;

    return $d . 'd ' . $h . 'h ' . $m . 'm ' . $s . 's';
}

function fmt_when(int $timestamp): string
{
    return 'at ' . date('H:i:s', $timestamp) . ' on ' . date('Y.m.d', $timestamp);
}

function iface_problem_title(array $row): string
{
    $ifName = trim((string) ($row['ifName'] ?? ''));
    if ($ifName === '') {
        $ifName = 'ifIndex ' . (int) ($row['ifIndex'] ?? 0);
    }

    $eventType = (string) ($row['event_type'] ?? '');
    if ($eventType === 'link_down') {
        return 'Interface ' . $ifName . '(): Link down';
    }
    if ($eventType === 'speed_changed') {
        $old = (string) ($row['old_speed_mbps'] ?? '-');
        $new = (string) ($row['new_speed_mbps'] ?? '-');

        return 'Interface ' . $ifName . '(): Speed changed ' . $old . '&rarr;' . $new . ' Mbps';
    }
    if ($eventType === 'link_up') {
        return 'Interface ' . $ifName . '(): Link up';
    }

    return 'Interface ' . $ifName . '(): ' . $eventType;
}

function device_problem_title(array $row): string
{
    $eventType = (string) ($row['event_type'] ?? '');
    if ($eventType === 'device_down') {
        return 'Device unreachable';
    }
    if ($eventType === 'device_up') {
        return 'Device reachable';
    }

    return $eventType;
}

function query_rows(mysqli $conn, string $sql, string $types, array $params, array &$errors): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $errors[] = 'SQL prepare failed: ' . $conn->error;
        return [];
    }

    if ($types !== '') {
        $bindArgs = [$types];
        foreach ($params as $index => $param) {
            $bindArgs[] = &$params[$index];
        }

        if (!call_user_func_array([$stmt, 'bind_param'], $bindArgs)) {
            $errors[] = 'SQL bind_param failed: ' . $stmt->error;
            $stmt->close();
            return [];
        }
    }

    if (!$stmt->execute()) {
        $errors[] = 'SQL execute failed: ' . $stmt->error;
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

$allowedHours = [1, 3, 6, 9, 12, 24];
$hours = safe_int('hours', 1, 1, 24);
if (!in_array($hours, $allowedHours, true)) {
    $hours = 1;
}
$hoursLabel = (string) $hours . ' hour' . ($hours === 1 ? '' : 's');
$sinceEpoch = time() - ($hours * 3600);
$deviceFilter = safe_str('device');
$ifaceFilterRaw = safe_str('iface');
$ifaceFilter = preg_match('/^\d+$/', $ifaceFilterRaw) === 1 ? (int) $ifaceFilterRaw : 0;
$deviceLocked = safe_str('lock_device') === '1';
$context = strtolower(safe_str('context'));
$isPortalContext = $context === 'portal';
$typeFilter = safe_str('type'); // '', 'iface'
if (!in_array($typeFilter, ['', 'iface'], true)) {
    $typeFilter = '';
}

$sqlErrors = [];
$availableDevices = [];
if (!$deviceLocked) {
    $deviceOptionsSql = "SELECT DISTINCT ie.device_name
                         FROM interface_events ie
                         WHERE ie.device_name IS NOT NULL
                           AND TRIM(ie.device_name) <> ''
                         ORDER BY ie.device_name ASC
                         LIMIT 2000";
    $deviceOptionRows = query_rows($conn, $deviceOptionsSql, '', [], $sqlErrors);
    foreach ($deviceOptionRows as $deviceOptionRow) {
        $deviceName = trim((string) ($deviceOptionRow['device_name'] ?? ''));
        if ($deviceName === '') {
            continue;
        }
        $availableDevices[] = $deviceName;
    }
}

if ($deviceFilter !== '' && !in_array($deviceFilter, $availableDevices, true)) {
    $availableDevices[] = $deviceFilter;
}
if (empty($availableDevices) && $deviceFilter !== '') {
    $availableDevices = [$deviceFilter];
}
sort($availableDevices, SORT_NATURAL | SORT_FLAG_CASE);

$availableInterfaces = [];
if ($deviceFilter !== '') {
    $ifaceOptionsSql = "SELECT ie.ifIndex,
                               MAX(COALESCE(NULLIF(TRIM(ie.ifName), ''), '')) AS ifName,
                               MAX(COALESCE(NULLIF(TRIM(ie.ifAlias), ''), '')) AS ifAlias,
                               MAX(COALESCE(NULLIF(TRIM(ie.ifDescr), ''), '')) AS ifDescr
                        FROM interface_events ie
                        WHERE ie.device_name = ?
                          AND ie.ifIndex IS NOT NULL
                        GROUP BY ie.ifIndex
                        ORDER BY ie.ifIndex ASC
                        LIMIT 2000";
    $ifaceOptionRows = query_rows($conn, $ifaceOptionsSql, 's', [$deviceFilter], $sqlErrors);
    foreach ($ifaceOptionRows as $ifaceOptionRow) {
        $ifIndex = (int) ($ifaceOptionRow['ifIndex'] ?? 0);
        if ($ifIndex <= 0) {
            continue;
        }

        $ifName = trim((string) ($ifaceOptionRow['ifName'] ?? ''));
        $ifAlias = trim((string) ($ifaceOptionRow['ifAlias'] ?? ''));
        if ($ifAlias === '') {
            $ifAlias = trim((string) ($ifaceOptionRow['ifDescr'] ?? ''));
        }

        if ($ifName === '') {
            $ifName = 'ifIndex ' . $ifIndex;
        }

        $ifLabel = '[#' . $ifIndex . '] ' . $ifName;
        if ($ifAlias !== '') {
            $ifLabel .= ' - ' . $ifAlias;
        }

        $availableInterfaces[] = [
            'ifIndex' => $ifIndex,
            'label' => $ifLabel,
        ];
    }
}

$selectedInterfaceLabel = 'All interfaces';
if ($ifaceFilter > 0) {
    foreach ($availableInterfaces as $ifaceOption) {
        if ((int) ($ifaceOption['ifIndex'] ?? 0) !== $ifaceFilter) {
            continue;
        }
        $selectedInterfaceLabel = (string) ($ifaceOption['label'] ?? ('ifIndex ' . $ifaceFilter));
        break;
    }

    if ($selectedInterfaceLabel === 'All interfaces') {
        $selectedInterfaceLabel = 'ifIndex ' . $ifaceFilter;
    }
}

$whereIface = "((ie.event_type='link_down' AND (ie.opened_at >= ? OR (ie.resolved_at IS NOT NULL AND ie.resolved_at >= ?))) OR (ie.event_type='speed_changed' AND ie.opened_at >= ?))";

$ifaceRows = [];
if ($typeFilter === '' || $typeFilter === 'iface') {
    $ifaceWhereParts = [$whereIface];
    $ifaceTypes = 'iii';
    $ifaceParams = [$sinceEpoch, $sinceEpoch, $sinceEpoch];

    if ($deviceFilter !== '') {
        $ifaceWhereParts[] = 'ie.device_name = ?';
        $ifaceTypes .= 's';
        $ifaceParams[] = $deviceFilter;
    }

    if ($ifaceFilter > 0) {
        $ifaceWhereParts[] = 'ie.ifIndex = ?';
        $ifaceTypes .= 'i';
        $ifaceParams[] = $ifaceFilter;
    }

    $ifaceSql = "SELECT ie.*, NULL AS cur_alias
                 FROM interface_events ie
                 WHERE " . implode(' AND ', $ifaceWhereParts) . "
                 ORDER BY ie.opened_at DESC, ie.id DESC
                 LIMIT 5000";
    $ifaceRows = query_rows($conn, $ifaceSql, $ifaceTypes, $ifaceParams, $sqlErrors);
}

$titleSuffix = $deviceFilter !== '' ? ' &bull; ' . esc($deviceFilter) : '';
$graphLink = '/devices/graphs';
if ($deviceFilter !== '') {
    $graphLink .= '?device=' . urlencode($deviceFilter) . '&window=3600';
    if ($ifaceFilter > 0) {
        $graphLink .= '&ifIndex=' . $ifaceFilter;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Events (last <?= esc($hoursLabel) ?><?= $titleSuffix ?>)</title>
    <meta http-equiv="refresh" content="60">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f1f5f9;
            --card: #ffffff;
            --card-soft: #f8fafc;
            --line: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --btn-bg: #ffffff;
            --btn-border: #cbd5e1;
            --primary: #135bec;
            --primary-soft: #e8efff;
            --shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            --sev-info: #0f172a;
            --sev-average: #c26d00;
            --sev-high: #b45309;
            --sev-disaster: #b91c1c;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.45;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            font-size: 20px;
            line-height: 1;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 270px;
            border-right: 1px solid #e2e8f0;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 14px;
            gap: 16px;
            overflow-y: auto;
            flex-shrink: 0;
        }

        .sidebar-main {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px;
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: var(--primary);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .brand-title {
            margin: 0;
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
        }

        .brand-sub {
            margin: 3px 0 0;
            font-size: 10px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 700;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sidebar-link,
        .sidebar-summary,
        .sidebar-sub-link {
            text-decoration: none;
            color: #475569;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            border-radius: 10px;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .sidebar-link,
        .sidebar-summary {
            padding: 10px 12px;
            font-size: 14px;
        }

        .sidebar-link:hover,
        .sidebar-summary:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .sidebar-link.active {
            background: var(--primary);
            color: #ffffff;
            box-shadow: 0 10px 22px rgba(19, 91, 236, 0.24);
        }

        .sidebar-group[open] > .sidebar-summary {
            background: #e8efff;
            color: var(--primary);
        }

        .sidebar-summary {
            list-style: none;
            cursor: pointer;
        }

        .sidebar-summary::-webkit-details-marker {
            display: none;
        }

        .sidebar-summary .arrow {
            margin-left: auto;
            transition: transform 0.16s ease;
        }

        .sidebar-group[open] .sidebar-summary .arrow {
            transform: rotate(180deg);
        }

        .sidebar-subnav {
            margin-left: 34px;
            margin-top: 4px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sidebar-sub-link {
            padding: 7px 10px;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
        }

        .sidebar-sub-link:hover {
            background: #f1f5f9;
            color: var(--primary);
        }

        .sidebar-sub-link.active {
            background: #e8efff;
            color: var(--primary);
        }

        .sidebar-sub-type-link {
            display: block;
            margin-left: 12px;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-decoration: none;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .sidebar-sub-type-link:hover {
            background: #f1f5f9;
            color: var(--primary);
        }

        .sidebar-footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 12px;
        }

        .sidebar-footer-link {
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: #ef4444;
            font-size: 14px;
            font-weight: 700;
            padding: 9px 10px;
            border-radius: 10px;
        }

        .sidebar-footer-link:hover {
            background: #fef2f2;
        }

        .main-pane {
            flex: 1;
            min-width: 0;
            overflow-y: auto;
        }

        .wrap {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }

        .page-header {
            border: 1px solid var(--line);
            border-radius: 22px;
            background: var(--card);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 14px;
        }

        .page-header-top {
            background: linear-gradient(90deg, #135bec 0%, #2563eb 55%, #10b981 100%);
            color: #ffffff;
            padding: 18px 22px;
        }

        .kicker {
            margin: 0;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.78);
        }

        h1 {
            margin: 8px 0 0;
            font-size: 30px;
            line-height: 1.2;
            font-weight: 800;
        }

        .page-subtitle {
            margin: 8px 0 0;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
        }

        .page-header-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            padding: 12px 22px;
            background: var(--card-soft);
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: #ffffff;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 10px;
            white-space: nowrap;
        }

        .filters-card {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: var(--card);
            box-shadow: var(--shadow);
            padding: 16px;
            margin-bottom: 14px;
        }

        .filters {
            display: grid;
            gap: 12px;
            grid-template-columns: minmax(220px, 1fr) minmax(220px, 1fr) 170px 170px auto;
            align-items: end;
        }

        .filters-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field-label {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #64748b;
        }

        input,
        select {
            height: 42px;
            min-width: 0;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 0 12px;
            font-size: 14px;
            color: #0f172a;
            background: white;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #93c5fd;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.16);
        }

        .btn {
            height: 40px;
            border: 1px solid var(--btn-border);
            border-radius: 10px;
            padding: 0 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            color: var(--text);
            background: var(--btn-bg);
            cursor: pointer;
            white-space: nowrap;
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }

        .btn:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        .btn-primary {
            color: #ffffff;
            background: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: #0f4dd1;
            border-color: #0f4dd1;
        }

        .events-section {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: var(--card);
            box-shadow: var(--shadow);
            padding: 16px;
        }

        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }

        h2 {
            margin: 0;
            font-size: 24px;
            line-height: 1.2;
            font-weight: 800;
        }

        .section-meta {
            font-size: 12px;
            color: #475569;
            background: var(--card-soft);
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 5px 10px;
        }

        .events {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .card {
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #ffffff;
            padding: 12px;
        }

        .card.sev-border-info { border-color: #94a3b8; }
        .card.sev-border-average { border-color: #f59e0b; }
        .card.sev-border-high { border-color: #fb923c; }
        .card.sev-border-disaster { border-color: #ef4444; }
        .card.sev-border-default { border-color: var(--line); }

        .title {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .line {
            font-size: 14px;
            line-height: 1.35;
            margin: 2px 0;
        }

        .muted {
            color: var(--muted);
        }

        .sev-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 2px 12px;
            border-radius: 999px;
            border: 1px solid transparent;
            font-size: 12px;
            line-height: 1.2;
            font-weight: 700;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .sev-badge-info {
            color: #0f172a;
            background: #e2e8f0;
            border-color: #cbd5e1;
        }

        .sev-badge-average {
            color: #92400e;
            background: #fef3c7;
            border-color: #fde68a;
        }

        .sev-badge-high {
            color: #9a3412;
            background: #ffedd5;
            border-color: #fdba74;
        }

        .sev-badge-disaster {
            color: #991b1b;
            background: #fee2e2;
            border-color: #fca5a5;
        }

        .sev-badge-default {
            color: #334155;
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .actions {
            margin-top: 8px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .empty {
            color: var(--muted);
            font-size: 14px;
            padding: 6px 0;
        }

        .warn {
            margin: 0 0 14px;
            border: 1px solid #f59e0b;
            background: #fffbeb;
            color: #92400e;
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 13px;
        }

        .warn code {
            display: block;
            white-space: pre-wrap;
            word-break: break-word;
            margin-top: 4px;
        }

        @media (max-width: 960px) {
            .layout {
                display: block;
            }

            .sidebar {
                display: none;
            }

            .main-pane {
                overflow: visible;
            }

            .wrap {
                padding: 14px;
            }

            .page-header-top {
                padding: 16px;
            }

            h1 {
                font-size: 24px;
            }

            .filters {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="layout">
    <?php if (!$isPortalContext): ?>
        <aside class="sidebar">
            <div class="sidebar-main">
                <div class="brand">
                    <span class="brand-icon">
                        <span class="material-symbols-outlined">settings_remote</span>
                    </span>
                    <div>
                        <p class="brand-title">Device Control</p>
                        <p class="brand-sub">Admin Portal</p>
                    </div>
                </div>

                <nav class="sidebar-nav" aria-label="Admin navigation">
                    <a class="sidebar-link" href="/dashboard">
                        <span class="material-symbols-outlined">dashboard</span>
                        <span>Dashboard</span>
                    </a>

                    <details class="sidebar-group" open>
                        <summary class="sidebar-summary">
                            <span class="material-symbols-outlined">devices</span>
                            <span>Devices</span>
                            <span class="material-symbols-outlined arrow">expand_more</span>
                        </summary>
                        <div class="sidebar-subnav">
                            <a class="sidebar-sub-link" href="/devices">Device Management</a>
                            <a class="sidebar-sub-link" href="/devices/cabinet-room">Cabinet Room</a>
                            <a class="sidebar-sub-link" href="/devices/details">Devices List</a>
                            <a class="sidebar-sub-link active" href="/devices/events">Events</a>
                            <a class="sidebar-sub-type-link" href="/devices/events?group=router_board#events-group-router_board">Router Board</a>
                            <a class="sidebar-sub-type-link" href="/devices/events?group=switches#events-group-switches">Switches</a>
                            <a class="sidebar-sub-type-link" href="/devices/events?group=fiber_optic#events-group-fiber_optic">Fiber Optic</a>
                            <a class="sidebar-sub-type-link" href="/devices/events?group=wireless#events-group-wireless">Wireless</a>
                            <a class="sidebar-sub-type-link" href="/devices/events?group=servers_standalone#events-group-servers_standalone">Stand Alone</a>
                            <a class="sidebar-sub-type-link" href="/devices/events?group=servers_virtual#events-group-servers_virtual">Virtual Server</a>
                            <a class="sidebar-sub-type-link" href="/devices/events?group=other#events-group-other">Other</a>
                        </div>
                    </details>

                    <a class="sidebar-link" href="/devices/assign">
                        <span class="material-symbols-outlined">assignment</span>
                        <span>Assignments</span>
                    </a>

                    <a class="sidebar-link" href="/notifications">
                        <span class="material-symbols-outlined">notifications</span>
                        <span>Notifications</span>
                    </a>

                    <details class="sidebar-group">
                        <summary class="sidebar-summary">
                            <span class="material-symbols-outlined">construction</span>
                            <span>Diagnostics</span>
                            <span class="material-symbols-outlined arrow">expand_more</span>
                        </summary>
                        <div class="sidebar-subnav">
                            <a class="sidebar-sub-link" href="/support">Support Console</a>
                            <a class="sidebar-sub-link" href="/telemetry">Logs</a>
                        </div>
                    </details>

                    <a class="sidebar-link" href="/users">
                        <span class="material-symbols-outlined">group</span>
                        <span>Users</span>
                    </a>

                    <a class="sidebar-link" href="/settings">
                        <span class="material-symbols-outlined">tune</span>
                        <span>Settings</span>
                    </a>
                </nav>
            </div>

            <div class="sidebar-footer">
                <a class="sidebar-footer-link" href="/auth/logout">
                    <span class="material-symbols-outlined">logout</span>
                    <span>Logout</span>
                </a>
            </div>
        </aside>
    <?php endif; ?>

    <main class="<?= $isPortalContext ? '' : 'main-pane' ?>">
<div class="wrap">
    <div class="page-header">
        <div class="page-header-top">
            <p class="kicker">Device Monitoring</p>
            <h1>Interface Events (last <?= esc($hoursLabel) ?><?= $titleSuffix ?>)</h1>
            <p class="page-subtitle">Consistent event timeline with quick filtering and graph shortcuts.</p>
        </div>
        <div class="page-header-meta">
            <span class="pill">Auto-refresh every 1 minute</span>
            <span class="pill"><?= count($ifaceRows) ?> event<?= count($ifaceRows) === 1 ? '' : 's' ?> in current window</span>
            <?php if ($deviceFilter !== ''): ?>
                <span class="pill">Device: <?= esc($deviceFilter) ?></span>
            <?php endif; ?>
            <?php if ($ifaceFilter > 0): ?>
                <span class="pill">Interface: <?= esc($selectedInterfaceLabel) ?></span>
            <?php endif; ?>
            <?php if ($deviceLocked): ?>
                <span class="pill">Device locked by portal scope</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($sqlErrors)): ?>
        <div class="warn">
            <strong>SQL warning(s):</strong>
            <?php foreach ($sqlErrors as $error): ?>
                <code><?= esc($error) ?></code>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="filters-card">
        <form class="filters" method="get" action="" data-events-filter-form>
            <?php if ($deviceLocked): ?>
                <input type="hidden" name="device" value="<?= esc($deviceFilter) ?>">
                <input type="hidden" name="lock_device" value="1">
            <?php endif; ?>

            <label class="field">
                <span class="field-label">Device</span>
                <select name="device" data-auto-submit <?= $deviceLocked ? 'disabled' : '' ?>>
                    <?php if (empty($availableDevices)): ?>
                        <option value="">No devices found</option>
                    <?php else: ?>
                        <?php foreach ($availableDevices as $deviceOption): ?>
                            <option value="<?= esc($deviceOption) ?>" <?= $deviceFilter === $deviceOption ? 'selected' : '' ?>>
                                <?= esc($deviceOption) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </label>

            <label class="field">
                <span class="field-label">Interface</span>
                <select name="iface" data-auto-submit>
                    <option value="" <?= $ifaceFilter <= 0 ? 'selected' : '' ?>>All interfaces</option>
                    <?php foreach ($availableInterfaces as $ifaceOption): ?>
                        <?php $ifIndex = (int) ($ifaceOption['ifIndex'] ?? 0); ?>
                        <option value="<?= $ifIndex ?>" <?= $ifaceFilter === $ifIndex ? 'selected' : '' ?>>
                            <?= esc((string) ($ifaceOption['label'] ?? ('ifIndex ' . $ifIndex))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span class="field-label">Type</span>
                <select name="type" data-auto-submit>
                    <option value="" <?= $typeFilter === '' ? 'selected' : '' ?>>All</option>
                    <option value="iface" <?= $typeFilter === 'iface' ? 'selected' : '' ?>>Interface</option>
                </select>
            </label>

            <label class="field">
                <span class="field-label">Window</span>
                <select name="hours" data-auto-submit>
                    <?php foreach ($allowedHours as $hourOption): ?>
                        <option value="<?= (int) $hourOption ?>" <?= $hours === (int) $hourOption ? 'selected' : '' ?>>
                            <?= (int) $hourOption ?> hour<?= (int) $hourOption === 1 ? '' : 's' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="filters-actions">
                <button class="btn" type="submit" name="poll" value="1">Poll now</button>
                <?php if ($deviceFilter !== ''): ?>
                    <a class="btn" href="<?= esc($graphLink) ?>" target="_blank" rel="noopener">Graphs</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($typeFilter === '' || $typeFilter === 'iface'): ?>
        <section class="events-section">
            <div class="section-head">
                <h2>Interface Events</h2>
                <span class="section-meta">Showing the latest <?= count($ifaceRows) ?> item<?= count($ifaceRows) === 1 ? '' : 's' ?></span>
            </div>
            <div class="events">
                <?php if (!$ifaceRows): ?>
                    <div class="empty">No interface events in this window.</div>
                <?php endif; ?>

                <?php foreach ($ifaceRows as $row): ?>
                    <?php
                    $isLinkDown = ((string) ($row['event_type'] ?? '') === 'link_down');
                    $isSpeed = ((string) ($row['event_type'] ?? '') === 'speed_changed');

                    $opened = (int) ($row['opened_at'] ?? 0);
                    $closed = isset($row['resolved_at']) ? (int) $row['resolved_at'] : null;
                    $durSeconds = $closed ? ($closed - $opened) : (time() - $opened);
                    $durText = fmt_duration($durSeconds);

                    $title = iface_problem_title($row);
                    $host = (string) ($row['device_name'] ?? '');
                    $sev = (string) ($row['severity'] ?? 'Info');
                    $sevBorder = severity_border_class($sev);
                    $eventId = (int) ($row['id'] ?? 0);

                    $desc = trim((string) ($row['ifAlias'] ?? ''));
                    if ($desc === '') {
                        $desc = trim((string) ($row['ifDescr'] ?? ''));
                    }

                    if ($isLinkDown) {
                        if ($closed) {
                            $line1 = 'Resolved in ' . $durText . ': ' . $title;
                            $line2 = 'Problem has been resolved in ' . $durText . ' ' . fmt_when($closed);
                            $line3 = 'Problem name: ' . $title;
                            $line4 = 'switch_name: ' . $host;
                            $line5 = 'Severity: ' . $sev;
                            $line6 = 'Original problem ID: ' . $eventId;
                        } else {
                            $line1 = 'Problem ongoing for ' . $durText . ': ' . $title;
                            $line2 = 'Problem started ' . fmt_when($opened);
                            $line3 = 'Problem name: ' . $title;
                            $line4 = 'switch_name: ' . $host;
                            $line5 = 'Severity: ' . $sev;
                            $line6 = 'Original problem ID: ' . $eventId;
                        }
                    } elseif ($isSpeed) {
                        $line1 = 'Speed change: ' . $title;
                        $line2 = 'Detected ' . fmt_when($opened);
                        $line3 = 'switch_name: ' . $host;
                        $line4 = 'Severity: ' . $sev;
                        $line5 = 'Event ID: ' . $eventId;
                        $line6 = '';
                    } else {
                        $line1 = $title;
                        $line2 = 'Detected ' . fmt_when($opened);
                        $line3 = 'switch_name: ' . $host;
                        $line4 = 'Severity: ' . $sev;
                        $line5 = 'Event ID: ' . $eventId;
                        $line6 = '';
                    }
                    ?>
                    <div class="card <?= esc($sevBorder) ?>">
                        <div class="title"><?= $line1 ?></div>
                        <?php if ($desc !== ''): ?>
                            <div class="line muted">Description: <?= esc($desc) ?></div>
                        <?php endif; ?>
                        <div class="line"><?= esc($line2) ?></div>
                        <div class="line"><?= esc($line3) ?></div>
                        <div class="line"><?= esc($line4) ?></div>
                        <div class="line">
                            Severity:
                            <span class="sev-badge <?= esc(severity_badge_class($sev)) ?>"><?= esc($sev) ?></span>
                        </div>
                        <?php if ($line5 !== '' && stripos($line5, 'Severity:') !== 0): ?>
                            <div class="line muted"><?= esc($line5) ?></div>
                        <?php endif; ?>
                        <?php if ($line6 !== ''): ?>
                            <div class="line muted"><?= esc($line6) ?></div>
                        <?php endif; ?>
                        <div class="actions">
                            <a class="btn" href="/devices/graphs?device=<?= urlencode($host) ?>&ifIndex=<?= (int) ($row['ifIndex'] ?? 0) ?>&window=3600" target="_blank" rel="noopener">Graphs</a>
                            <a class="btn" href="/devices/graphs?device=<?= urlencode($host) ?>&ifIndex=<?= (int) ($row['ifIndex'] ?? 0) ?>&window=86400" target="_blank" rel="noopener">Graphs (24h)</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

</div>
    </main>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('[data-events-filter-form]');
        if (!form) {
            return;
        }

        var autoSubmitInputs = form.querySelectorAll('select[data-auto-submit]');
        autoSubmitInputs.forEach(function (input) {
            input.addEventListener('change', function () {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        });
    });
</script>
</body>
</html>
