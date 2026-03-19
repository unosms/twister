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

$hours = 1;
$sinceEpoch = time() - ($hours * 3600);
$deviceFilter = safe_str('device');
$typeFilter = safe_str('type'); // '', 'iface'
if (!in_array($typeFilter, ['', 'iface'], true)) {
    $typeFilter = '';
}

$sqlErrors = [];

$whereIface = "((ie.event_type='link_down' AND (ie.opened_at >= ? OR (ie.resolved_at IS NOT NULL AND ie.resolved_at >= ?))) OR (ie.event_type='speed_changed' AND ie.opened_at >= ?))";

$ifaceRows = [];
if ($typeFilter === '' || $typeFilter === 'iface') {
    if ($deviceFilter !== '') {
        $ifaceSql = "SELECT ie.*, NULL AS cur_alias
                     FROM interface_events ie
                     WHERE {$whereIface} AND ie.device_name = ?
                     ORDER BY ie.opened_at DESC, ie.id DESC
                     LIMIT 5000";
        $ifaceRows = query_rows($conn, $ifaceSql, 'iiis', [$sinceEpoch, $sinceEpoch, $sinceEpoch, $deviceFilter], $sqlErrors);
    } else {
        $ifaceSql = "SELECT ie.*, NULL AS cur_alias
                     FROM interface_events ie
                     WHERE {$whereIface}
                     ORDER BY ie.opened_at DESC, ie.id DESC
                     LIMIT 5000";
        $ifaceRows = query_rows($conn, $ifaceSql, 'iii', [$sinceEpoch, $sinceEpoch, $sinceEpoch], $sqlErrors);
    }
}

$titleSuffix = $deviceFilter !== '' ? ' &bull; ' . esc($deviceFilter) : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Events (last <?= (int) $hours ?> hour<?= $titleSuffix ?>)</title>
    <meta http-equiv="refresh" content="60">
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
            grid-template-columns: 1fr 220px auto;
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
<div class="wrap">
    <div class="page-header">
        <div class="page-header-top">
            <p class="kicker">Device Monitoring</p>
            <h1>Interface Events (last <?= (int) $hours ?> hour<?= $titleSuffix ?>)</h1>
            <p class="page-subtitle">Consistent event timeline with quick filtering and graph shortcuts.</p>
        </div>
        <div class="page-header-meta">
            <span class="pill">Auto-refresh every 1 minute</span>
            <span class="pill"><?= count($ifaceRows) ?> event<?= count($ifaceRows) === 1 ? '' : 's' ?> in current window</span>
            <?php if ($deviceFilter !== ''): ?>
                <span class="pill">Device: <?= esc($deviceFilter) ?></span>
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
        <form class="filters" method="get" action="">
            <input type="hidden" name="hours" value="<?= (int) $hours ?>">

            <label class="field">
                <span class="field-label">Device</span>
                <input type="text" name="device" value="<?= esc($deviceFilter) ?>" placeholder="switch_name (optional)">
            </label>

            <label class="field">
                <span class="field-label">Type</span>
                <select name="type">
                    <option value="" <?= $typeFilter === '' ? 'selected' : '' ?>>All</option>
                    <option value="iface" <?= $typeFilter === 'iface' ? 'selected' : '' ?>>Interface</option>
                </select>
            </label>

            <div class="filters-actions">
                <button class="btn btn-primary" type="submit">Apply</button>
                <button class="btn" type="submit" name="poll" value="1">Apply + Poll</button>
                <?php if ($deviceFilter !== ''): ?>
                    <a class="btn" href="/devices/graphs?device=<?= urlencode($deviceFilter) ?>&window=3600" target="_blank" rel="noopener">Graphs</a>
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
</body>
</html>
