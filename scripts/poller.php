<?php
//**
// * poller.php Ã¢â‚¬â€ SNMP poller with Telegram events + robust counter fallback (64-bit Ã¢â€ â€™ 32-bit)
// * CRON:
// *   */1 * * * * /usr/bin/php /var/www/html/poller.php >/dev/null 2>&1
// */

declare(strict_types=1);
date_default_timezone_set('Asia/Beirut');

require __DIR__ . '/db.php';     // provides $conn (mysqli)
require __DIR__ . '/config.php'; // TG_BOT_TOKEN, TG_CHAT_DEV_IDS, TG_CHAT_IFACE_IDS

$NOW = time();

/* ============================================================
   Logger Ã¢â€ â€™ ./poller.log (append)
   ============================================================ */
function log_poll(string $msg): void {
  $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
  @file_put_contents(__DIR__ . '/poller.log', $line, FILE_APPEND);
  if (getenv('PROVISIONING_TRACE_STDOUT') === '1') {
    echo $msg . PHP_EOL;
  }
}
function provisioning_event(string $message, array $event = []): void {
  if (getenv('PROVISIONING_LOG_ENABLED') !== '1') {
    return;
  }

  $path = getenv('PROVISIONING_EVENTS_LOG') ?: '';
  if ($path === '') {
    return;
  }

  $event = array_filter(array_merge([
    'id' => uniqid('poller_', true),
    'timestamp' => gmdate('c'),
    'message' => $message,
    'state' => 'info',
    'trace' => 'Poller',
    'layer' => 'Internal',
    'protocol' => 'SNMP',
  ], $event), static fn ($value) => $value !== null && $value !== []);

  $dir = dirname($path);
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }

  @file_put_contents($path, json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL, FILE_APPEND | LOCK_EX);
}
function fmt_dur(int $secs): string {
  $secs = max(0, $secs);
  $d = intdiv($secs, 86400); $secs %= 86400;
  $h = intdiv($secs, 3600);  $secs %= 3600;
  $m = intdiv($secs, 60);    $s = $secs % 60;
  return "{$d}d {$h}h {$m}m {$s}s";
}

function decode_device_meta($raw): array {
  if (is_array($raw)) return $raw;
  if (!is_string($raw) || trim($raw) === '') return [];
  $decoded = json_decode($raw, true);
  if (is_string($decoded)) {
    $decoded = json_decode($decoded, true);
  }
  return is_array($decoded) ? $decoded : [];
}

function ensure_poller_tables(mysqli $conn): void {
  $sql = [
    "CREATE TABLE IF NOT EXISTS app_settings (
      k VARCHAR(191) NOT NULL PRIMARY KEY,
      v TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS interfaces (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      device_id BIGINT UNSIGNED NOT NULL,
      ifIndex INT NOT NULL,
      ifName VARCHAR(255) NULL,
      ifDescr TEXT NULL,
      ifAlias TEXT NULL,
      speed_bps DOUBLE NULL,
      is_up TINYINT NULL,
      last_seen_at DATETIME NULL,
      last_in_oct DOUBLE NULL,
      last_out_oct DOUBLE NULL,
      last_counter_ts INT NULL,
      UNIQUE KEY uq_device_ifindex (device_id, ifIndex),
      KEY idx_interfaces_device (device_id),
      KEY idx_interfaces_seen (last_seen_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS interface_samples (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      interface_id BIGINT UNSIGNED NOT NULL,
      ts INT NOT NULL,
      in_bps INT NULL,
      out_bps INT NULL,
      KEY idx_samples_iface_ts (interface_id, ts)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS interface_events (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      device_id BIGINT UNSIGNED NOT NULL,
      interface_id BIGINT UNSIGNED NOT NULL,
      ifIndex INT NOT NULL,
      device_name VARCHAR(255) NOT NULL,
      ifName VARCHAR(255) NULL,
      ifDescr TEXT NULL,
      ifAlias TEXT NULL,
      event_type VARCHAR(64) NOT NULL,
      old_status TINYINT NULL,
      new_status TINYINT NULL,
      old_speed_mbps INT NULL,
      new_speed_mbps INT NULL,
      severity VARCHAR(32) NOT NULL DEFAULT 'Info',
      opened_at INT NOT NULL,
      resolved_at INT NULL,
      KEY idx_iface_events_time (opened_at, resolved_at),
      KEY idx_iface_events_device (device_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS device_events (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      device_id BIGINT UNSIGNED NOT NULL,
      device_name VARCHAR(255) NOT NULL,
      ip VARCHAR(64) NULL,
      event_type VARCHAR(64) NOT NULL,
      severity VARCHAR(32) NOT NULL DEFAULT 'Info',
      opened_at INT NOT NULL,
      resolved_at INT NULL,
      KEY idx_device_events_time (opened_at, resolved_at),
      KEY idx_device_events_device (device_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
  ];

  foreach ($sql as $statement) {
    if (!$conn->query($statement)) {
      log_poll('schema ensure failed: ' . $conn->error);
    }
  }
}

ensure_poller_tables($conn);

/* ============================================================
   Settings
   ============================================================ */
function get_setting(mysqli $conn, string $key, $default = null) {
  $stmt = $conn->prepare("SELECT v FROM app_settings WHERE k=? LIMIT 1");
  if (!$stmt) return $default;
  $stmt->bind_param("s", $key);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  return $row ? $row['v'] : $default;
}
$NOTIFY_DEVICES    = (int)get_setting($conn, 'notify_devices', '1') === 1;
$NOTIFY_INTERFACES = (int)get_setting($conn, 'notify_interfaces', '0') === 1;

/* ============================================================
   Telegram
   ============================================================ */
function tg_send_to($chatIds, string $text): void {
  if (!defined('TG_BOT_TOKEN') || !TG_BOT_TOKEN) { log_poll('tg: missing token'); return; }
  if (!is_array($chatIds) || empty($chatIds)) { log_poll('tg: empty chatIds'); return; }
  $url = "https://api.telegram.org/bot" . TG_BOT_TOKEN . "/sendMessage";

  foreach ($chatIds as $chatId) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => [
        'chat_id' => $chatId,
        'text'    => $text,
        'disable_web_page_preview' => 'true'
      ],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => 5,
      CURLOPT_TIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    log_poll("tg Ã¢â€ â€™ chat={$chatId} http={$http} err=" . ($err?:'-'));
  }
}
function tg_send_dev(string $text): void {
  global $NOTIFY_DEVICES;
  if (!$NOTIFY_DEVICES) { log_poll('tg_dev: disabled'); return; }
  if (defined('TG_CHAT_DEV_IDS')) tg_send_to(TG_CHAT_DEV_IDS, $text);
}
function tg_send_iface(string $text): void {
  global $NOTIFY_INTERFACES;
  if (!$NOTIFY_INTERFACES) { log_poll('tg_iface: disabled'); return; }
  if (defined('TG_CHAT_IFACE_IDS')) tg_send_to(TG_CHAT_IFACE_IDS, $text);
}

/* ============================================================
   SNMP helpers (robust parsing)
   ============================================================ */
function parse_snmp_val($val) {
  $v = preg_replace('/^[A-Za-z0-9\-\s]*:\s*/', '', (string)$val); // strip "Counter64:" etc
  if (preg_match('/\((\-?\d+)\)/', $v, $m)) return (float)$m[1];
  $v = trim($v, "\" \t\r\n");
  if (preg_match('/^\-?\d+(\.\d+)?$/', $v)) return (float)$v;
  return $v;
}
function snmp_walk_map(string $ip, string $comm, string $oid): array {
  $started = microtime(true);
  if (!function_exists('snmp2_real_walk')) {
    provisioning_event('poller trace: SNMP walk skipped because extension is missing', [
      'state' => 'failure',
      'layer' => 'Data Polling',
      'protocol' => 'SNMP',
      'device_ip' => $ip,
      'request' => [
        'operation' => 'snmp_walk',
        'target' => $ip,
        'resource' => $oid,
        'summary' => "SNMP walk {$oid} skipped because snmp2_real_walk() is unavailable",
      ],
      'response' => [
        'status' => 'skipped',
        'summary' => 'PHP SNMP extension is missing on this host.',
      ],
      'reason' => 'snmp2_real_walk() is unavailable.',
      'failure_hints' => ['runtime_extension'],
    ]);
    return [];
  }
  $out = @snmp2_real_walk($ip, $comm, $oid, 800000, 1);
  $latency = (int)round((microtime(true) - $started) * 1000);
  if (!is_array($out)) {
    provisioning_event('poller trace: SNMP walk failed', [
      'state' => 'failure',
      'layer' => 'Data Polling',
      'protocol' => 'SNMP',
      'device_ip' => $ip,
      'latency_ms' => $latency,
      'request' => [
        'operation' => 'snmp_walk',
        'target' => $ip,
        'resource' => $oid,
        'summary' => "SNMP walk {$oid} returned no rows",
      ],
      'response' => [
        'status' => 'failed',
        'summary' => 'SNMP walk returned no data.',
      ],
      'reason' => 'No SNMP walk response was received.',
    ]);
    return [];
  }
  $ret = [];
  foreach ($out as $k => $v) {
    if (preg_match('/\.([0-9]+)$/', $k, $m)) {
      $idx = (int)$m[1];
      $ret[$idx] = parse_snmp_val($v);
    }
  }
  provisioning_event('poller trace: SNMP walk completed', [
    'state' => 'success',
    'layer' => 'Data Polling',
    'protocol' => 'SNMP',
    'device_ip' => $ip,
    'latency_ms' => $latency,
    'request' => [
      'operation' => 'snmp_walk',
      'target' => $ip,
      'resource' => $oid,
      'summary' => "SNMP walk {$oid}",
    ],
    'response' => [
      'status' => 'ok',
      'summary' => 'SNMP walk returned ' . count($ret) . ' row(s).',
      'payload_summary' => count($ret) . ' row(s) normalized from the SNMP response.',
    ],
  ]);
  return $ret;
}
function snmp_get_val(string $ip, string $comm, string $oid) {
  $started = microtime(true);
  if (!function_exists('snmp2_get')) {
    provisioning_event('poller trace: SNMP get skipped because extension is missing', [
      'state' => 'failure',
      'layer' => 'Data Polling',
      'protocol' => 'SNMP',
      'device_ip' => $ip,
      'request' => [
        'operation' => 'snmp_get',
        'target' => $ip,
        'resource' => $oid,
        'summary' => "SNMP GET {$oid} skipped because snmp2_get() is unavailable",
      ],
      'response' => [
        'status' => 'skipped',
        'summary' => 'PHP SNMP extension is missing on this host.',
      ],
      'reason' => 'snmp2_get() is unavailable.',
      'failure_hints' => ['runtime_extension'],
    ]);
    return null;
  }
  $v = @snmp2_get($ip, $comm, $oid, 800000, 1);
  $latency = (int)round((microtime(true) - $started) * 1000);
  if ($v === false || $v === null) {
    provisioning_event('poller trace: SNMP get failed', [
      'state' => 'failure',
      'layer' => 'Data Polling',
      'protocol' => 'SNMP',
      'device_ip' => $ip,
      'latency_ms' => $latency,
      'request' => [
        'operation' => 'snmp_get',
        'target' => $ip,
        'resource' => $oid,
        'summary' => "SNMP GET {$oid} returned no value",
      ],
      'response' => [
        'status' => 'failed',
        'summary' => 'SNMP GET returned no data.',
      ],
      'reason' => 'No SNMP GET response was received.',
    ]);
    return null;
  }
  $parsed = parse_snmp_val($v);
  provisioning_event('poller trace: SNMP get completed', [
    'state' => 'success',
    'layer' => 'Response Parsing',
    'protocol' => 'SNMP',
    'device_ip' => $ip,
    'latency_ms' => $latency,
    'request' => [
      'operation' => 'snmp_get',
      'target' => $ip,
      'resource' => $oid,
      'summary' => "SNMP GET {$oid}",
    ],
    'response' => [
      'status' => 'ok',
      'summary' => is_scalar($parsed) ? ('Parsed value ' . (string)$parsed) : 'SNMP value parsed successfully.',
      'payload_summary' => substr(trim((string)$v), 0, 240),
    ],
  ]);
  return $parsed;
}

/* ============================================================
   DB helpers
   ============================================================ */
function upsert_interface(mysqli $conn, array $r) : array {
  $sel = $conn->prepare("SELECT id, is_up, speed_bps FROM interfaces WHERE device_id=? AND ifIndex=? LIMIT 1");
  $sel->bind_param("ii", $r['device_id'], $r['ifIndex']);
  $sel->execute();
  $res  = $sel->get_result();
  $prev = $res ? $res->fetch_assoc() : null;
  $sel->close();

  $prev_up    = $prev ? (is_null($prev['is_up']) ? null : (int)$prev['is_up']) : null;
  $prev_speed = $prev ? (is_null($prev['speed_bps']) ? null : (float)$prev['speed_bps']) : null;

  $spd = is_null($r['speed_bps']) ? null : (float)$r['speed_bps'];

  if ($prev) {
    $id = (int)$prev['id'];
    $upd = $conn->prepare("UPDATE interfaces
                              SET ifName=?, ifDescr=?, ifAlias=?, speed_bps=?, is_up=?, last_seen_at=FROM_UNIXTIME(?)
                            WHERE id=?");
    $upd->bind_param("sssdiii", $r['ifName'], $r['ifDescr'], $r['ifAlias'], $spd, $r['is_up'], $r['ts'], $id);
    $upd->execute(); $upd->close();
    return ['id'=>$id, 'prev_is_up'=>$prev_up, 'prev_speed_bps'=>$prev_speed];
  } else {
    $ins = $conn->prepare("INSERT INTO interfaces
          (device_id, ifIndex, ifName, ifDescr, ifAlias, speed_bps, is_up, last_seen_at)
          VALUES (?,?,?,?,?,?,?,FROM_UNIXTIME(?))");
    $ins->bind_param("iisssdii", $r['device_id'], $r['ifIndex'], $r['ifName'], $r['ifDescr'], $r['ifAlias'], $spd, $r['is_up'], $r['ts']);
    $ins->execute(); $id = (int)$ins->insert_id; $ins->close();
    return ['id'=>$id, 'prev_is_up'=>null, 'prev_speed_bps'=>null];
  }
}

function update_interface_counters_and_sample(mysqli $conn, int $iface_id, int $now, $in_oct, $out_oct): array {
  $sel = $conn->prepare("SELECT last_in_oct, last_out_oct, last_counter_ts FROM interfaces WHERE id=? LIMIT 1");
  $sel->bind_param("i", $iface_id);
  $sel->execute();
  $row = $sel->get_result()->fetch_assoc();
  $sel->close();

  $bps_in = null; $bps_out = null;
  $prev_in  = $row ? (float)$row['last_in_oct']  : null;
  $prev_out = $row ? (float)$row['last_out_oct'] : null;
  $prev_ts  = $row ? (int)$row['last_counter_ts'] : null;

  if ($prev_in !== null && $prev_out !== null && $prev_ts) {
    $dt   = max(1, $now - $prev_ts);
    $din  = ($in_oct  >= $prev_in)  ? ($in_oct  - $prev_in)  : 0;
    $dout = ($out_oct >= $prev_out) ? ($out_oct - $prev_out) : 0;
    $bps_in  = (int) round(($din  * 8) / $dt);
    $bps_out = (int) round(($dout * 8) / $dt);

    $ins = $conn->prepare("INSERT INTO interface_samples (interface_id, ts, in_bps, out_bps) VALUES (?,?,?,?)");
    $ins->bind_param("iiii", $iface_id, $now, $bps_in, $bps_out);
    $ins->execute(); $ins->close();
  }

  $upd = $conn->prepare("UPDATE interfaces SET last_in_oct=?, last_out_oct=?, last_counter_ts=? WHERE id=?");
  $upd->bind_param("ddii", $in_oct, $out_oct, $now, $iface_id);
  $upd->execute(); $upd->close();

  return [$bps_in, $bps_out];
}

function insert_iface_event(mysqli $conn, array $e) : int {
  $q = sprintf(
    "INSERT INTO interface_events
     (device_id, interface_id, ifIndex, device_name, ifName, ifDescr, ifAlias,
      event_type, old_status, new_status, old_speed_mbps, new_speed_mbps,
      severity, opened_at)
     VALUES (%d, %d, %d, '%s', %s, %s, %s, '%s', %s, %s, %s, %s, '%s', %d)",
    (int)$e['device_id'],
    (int)$e['interface_id'],
    (int)$e['ifIndex'],
    $conn->real_escape_string($e['device_name']),
    isset($e['ifName'])  ? ("'".$conn->real_escape_string($e['ifName'])."'")   : "NULL",
    isset($e['ifDescr']) ? ("'".$conn->real_escape_string($e['ifDescr'])."'")  : "NULL",
    isset($e['ifAlias']) ? ("'".$conn->real_escape_string($e['ifAlias'])."'")  : "NULL",
    $conn->real_escape_string($e['event_type']),
    isset($e['old_status']) ? (int)$e['old_status'] : "NULL",
    isset($e['new_status']) ? (int)$e['new_status'] : "NULL",
    isset($e['old_speed_mbps']) ? (int)$e['old_speed_mbps'] : "NULL",
    isset($e['new_speed_mbps']) ? (int)$e['new_speed_mbps'] : "NULL",
    $conn->real_escape_string($e['severity']),
    (int)$e['opened_at']
  );
  $conn->query($q);
  return (int)$conn->insert_id;
}

function ensure_device_down(mysqli $conn, int $device_id, string $device_name, string $ip, int $now): void {
  // Device reachability events are intentionally not persisted.
  return;
}
function resolve_device_down_and_log_up(mysqli $conn, int $device_id, string $device_name, string $ip, int $now): void {
  // Device reachability events are intentionally not persisted.
  return;
}

/* ============================================================
   Polling
   ============================================================ */
$deviceIdFilter = isset($_GET['device_id']) ? (int)$_GET['device_id'] : 0;
$devStmt = null;
if ($deviceIdFilter > 0) {
  $devStmt = $conn->prepare("SELECT id, name, ip_address, metadata FROM devices WHERE id=? ORDER BY id ASC");
  if (!$devStmt) {
    fwrite(STDERR, "poller SQL prepare error: {$conn->error}\n");
    log_poll('poller SQL prepare error: ' . $conn->error);
    exit(1);
  }
  $devStmt->bind_param("i", $deviceIdFilter);
  if (!$devStmt->execute()) {
    fwrite(STDERR, "poller SQL execute error: {$devStmt->error}\n");
    log_poll('poller SQL execute error: ' . $devStmt->error);
    $devStmt->close();
    exit(1);
  }
  $devQ = $devStmt->get_result();
} else {
  $devQ = $conn->query("SELECT id, name, ip_address, metadata FROM devices ORDER BY id ASC");
}

if (!$devQ) {
  fwrite(STDERR, "poller SQL error: {$conn->error}\n");
  log_poll('poller SQL error: ' . $conn->error);
  if ($devStmt instanceof mysqli_stmt) {
    $devStmt->close();
  }
  exit(1);
}

while ($d = $devQ->fetch_assoc()) {
  $meta = decode_device_meta($d['metadata'] ?? '');
  $cisco = (isset($meta['cisco']) && is_array($meta['cisco'])) ? $meta['cisco'] : [];

  $device_id = (int)$d['id'];
  $device_name = (string)($cisco['name'] ?? $d['name'] ?? ('device-' . $device_id));
  $ip = (string)($cisco['ip_address'] ?? $d['ip_address'] ?? '');
  $comm = trim((string)($cisco['snmp_community'] ?? ($meta['snmp_community'] ?? 'public')));
  if ($comm === '') {
    $comm = 'public';
  }
  $enabled = (!empty($meta['monitoring_disabled']) || !empty($cisco['monitoring_disabled'])) ? 0 : 1;

  if (!$enabled || $ip === '') {
    log_poll("Skip {$device_name} (disabled or no IP)");
    provisioning_event('poller trace: device skipped before polling', [
      'state' => 'warning',
      'layer' => 'Network Discovery',
      'protocol' => 'INTERNAL',
      'device_id' => $device_id,
      'device_name' => $device_name,
      'device_ip' => $ip !== '' ? $ip : null,
      'response' => [
        'status' => 'skipped',
        'summary' => $enabled ? 'No IP address was configured.' : 'Monitoring is disabled for this device.',
      ],
      'reason' => $enabled ? 'Device IP is missing.' : 'Monitoring is disabled for this device.',
    ]);
    continue;
  }

  log_poll("== Polling {$device_name} ({$ip}) ==");
  provisioning_event('poller trace: device polling started', [
    'state' => 'running',
    'layer' => 'Network Discovery',
    'protocol' => 'SNMP',
    'device_id' => $device_id,
    'device_name' => $device_name,
    'device_ip' => $ip,
    'request' => [
      'operation' => 'device_poll',
      'target' => $ip,
      'summary' => "Starting live interface poll for {$device_name}",
    ],
    'response' => [
      'status' => 'running',
      'summary' => 'Beginning SNMP reachability and interface collection.',
    ],
  ]);

  // Reachability by SNMP sysUpTime.0 then ping
  $upticks = snmp_get_val($ip, $comm, '1.3.6.1.2.1.1.3.0');
  if ($upticks !== null) {
    resolve_device_down_and_log_up($conn, $device_id, $device_name, $ip, $NOW);
    provisioning_event('poller trace: device reachability confirmed by SNMP', [
      'state' => 'success',
      'layer' => 'Network Discovery',
      'protocol' => 'SNMP',
      'device_id' => $device_id,
      'device_name' => $device_name,
      'device_ip' => $ip,
      'response' => [
        'status' => 'reachable',
        'summary' => 'SNMP sysUpTime returned a value; continuing with interface polling.',
      ],
    ]);
  } else {
    provisioning_event('poller trace: falling back to ICMP reachability', [
      'state' => 'warning',
      'layer' => 'Error Handling / Retry',
      'protocol' => 'ICMP',
      'device_id' => $device_id,
      'device_name' => $device_name,
      'device_ip' => $ip,
      'reason' => 'SNMP sysUpTime did not respond; attempting ICMP fallback.',
      'response' => [
        'status' => 'retrying',
        'summary' => 'SNMP reachability failed, switching to ICMP.',
      ],
    ]);
    $out=[]; $rc=0;
    @exec('ping -n -c 1 -W 1 ' . escapeshellarg($ip) . ' 2>&1', $out, $rc);
    if ($rc === 0) {
      resolve_device_down_and_log_up($conn, $device_id, $device_name, $ip, $NOW);
      provisioning_event('poller trace: ICMP fallback succeeded', [
        'state' => 'success',
        'layer' => 'Network Discovery',
        'protocol' => 'ICMP',
        'device_id' => $device_id,
        'device_name' => $device_name,
        'device_ip' => $ip,
        'response' => [
          'status' => 'reachable',
          'summary' => 'ICMP fallback succeeded; continuing with interface polling.',
          'payload_summary' => substr(trim(implode(' ', $out)), 0, 240),
        ],
      ]);
    } else {
      ensure_device_down($conn, $device_id, $device_name, $ip, $NOW);
      provisioning_event('poller trace: device unreachable after SNMP and ICMP', [
        'state' => 'failure',
        'layer' => 'Error Handling / Retry',
        'protocol' => 'ICMP',
        'device_id' => $device_id,
        'device_name' => $device_name,
        'device_ip' => $ip,
        'response' => [
          'status' => 'offline',
          'summary' => 'Device did not answer SNMP or ICMP reachability checks.',
          'payload_summary' => substr(trim(implode(' ', $out)), 0, 240),
        ],
        'reason' => 'Device is unreachable after SNMP and ICMP checks.',
        'failure_hints' => ['timeout_or_firewall', 'routing_or_nat'],
      ]);
      continue;
    }
  }

  // OIDs for attributes
  $OID_ifName         = '1.3.6.1.2.1.31.1.1.1.1';
  $OID_ifDescr        = '1.3.6.1.2.1.2.2.1.2';
  $OID_ifAlias        = '1.3.6.1.2.1.31.1.1.1.18';
  $OID_ifOperStatus   = '1.3.6.1.2.1.2.2.1.8';
  $OID_ifSpeed        = '1.3.6.1.2.1.2.2.1.5';
  $OID_ifHighSpeed    = '1.3.6.1.2.1.31.1.1.1.15';  // Mbps

  // Counters (walk both 64-bit and 32-bit; weÃ¢â‚¬â„¢ll choose per-index)
  $HC_IN  = snmp_walk_map($ip, $comm, '1.3.6.1.2.1.31.1.1.1.6');   // ifHCInOctets
  $HC_OUT = snmp_walk_map($ip, $comm, '1.3.6.1.2.1.31.1.1.1.10');  // ifHCOutOctets
  $L32_IN = snmp_walk_map($ip, $comm, '1.3.6.1.2.1.2.2.1.10');     // ifInOctets
  $L32_OUT= snmp_walk_map($ip, $comm, '1.3.6.1.2.1.2.2.1.16');     // ifOutOctets

  $m_name   = snmp_walk_map($ip, $comm, $OID_ifName);
  $m_descr  = snmp_walk_map($ip, $comm, $OID_ifDescr);
  $m_alias  = snmp_walk_map($ip, $comm, $OID_ifAlias);
  $m_oper   = snmp_walk_map($ip, $comm, $OID_ifOperStatus);
  $m_speed  = snmp_walk_map($ip, $comm, $OID_ifSpeed);
  $m_hspeed = snmp_walk_map($ip, $comm, $OID_ifHighSpeed);

  // union of indices seen anywhere (counters OR names)
  $idxs = array_unique(array_map('intval', array_merge(
    array_keys($m_name), array_keys($m_descr), array_keys($m_alias),
    array_keys($m_oper), array_keys($m_speed), array_keys($m_hspeed),
    array_keys($HC_IN), array_keys($HC_OUT), array_keys($L32_IN), array_keys($L32_OUT)
  )));
  sort($idxs);
  provisioning_event('poller trace: interface index discovery completed', [
    'state' => 'success',
    'layer' => 'Response Parsing',
    'protocol' => 'SNMP',
    'device_id' => $device_id,
    'device_name' => $device_name,
    'device_ip' => $ip,
    'response' => [
      'status' => 'ok',
      'summary' => 'Discovered ' . count($idxs) . ' interface index(es) for polling.',
    ],
  ]);

  foreach ($idxs as $idx) {
    $name  = isset($m_name[$idx])  ? (string)$m_name[$idx]  : ("ifIndex {$idx}");
    $descr = isset($m_descr[$idx]) ? (string)$m_descr[$idx] : '';
    $alias = isset($m_alias[$idx]) ? (string)$m_alias[$idx] : '';
    $oper  = isset($m_oper[$idx])  ? (int)$m_oper[$idx]     : 2;

    // Speed in bps (prefer HighSpeed Mbps)
    $spd = null;
    if (isset($m_hspeed[$idx]) && (int)$m_hspeed[$idx] > 0) $spd = (float)$m_hspeed[$idx] * 1_000_000;
    elseif (isset($m_speed[$idx]))                          $spd = (float)$m_speed[$idx];

    $u = upsert_interface($conn, [
      'device_id' => $device_id,
      'ifIndex'   => $idx,
      'ifName'    => $name,
      'ifDescr'   => $descr,
      'ifAlias'   => $alias,
      'speed_bps' => $spd,
      'is_up'     => ($oper === 1 ? 1 : 2), // 1=up, 2=down
      'ts'        => $NOW
    ]);
    $iface_id   = (int)$u['id'];
    $prev_up    = $u['prev_is_up'];
    $prev_speed = $u['prev_speed_bps'];

    // Interface events
    if ($prev_up !== null && $oper !== null && $prev_up !== $oper) {
      if ($oper == 2) {
        $evId = insert_iface_event($conn, [
          'device_id'=>$device_id, 'interface_id'=>$iface_id, 'ifIndex'=>$idx,
          'device_name'=>$device_name, 'ifName'=>$name, 'ifDescr'=>$descr, 'ifAlias'=>$alias,
          'event_type'=>'link_down', 'old_status'=>$prev_up, 'new_status'=>$oper,
          'old_speed_mbps'=>null, 'new_speed_mbps'=>null, 'severity'=>'Average', 'opened_at'=>$NOW
        ]);
        $aliasTxt = $alias ? " Ã¢â‚¬â€ {$alias}" : "";
        tg_send_iface("Ã°Å¸Å¡Â¨ PORT DOWN\nHost: {$device_name}\nInterface: {$name}{$aliasTxt}\nTime: " . date('Y-m-d H:i:s', $NOW) . "\nEvent ID: {$evId}");
      } elseif ($oper == 1) {
        // resolve latest open link_down
        $sel = $conn->prepare("SELECT id, opened_at FROM interface_events WHERE interface_id=? AND event_type='link_down' AND resolved_at IS NULL ORDER BY id DESC LIMIT 1");
        $sel->bind_param("i", $iface_id);
        $sel->execute();
        $ld = $sel->get_result()->fetch_assoc();
        $sel->close();
        if ($ld) {
          $upd = $conn->prepare("UPDATE interface_events SET resolved_at=? WHERE id=?");
          $upd->bind_param("ii", $NOW, $ld['id']);
          $upd->execute(); $upd->close();
          $dur = fmt_dur($NOW - (int)$ld['opened_at']);
          $aliasTxt = $alias ? " Ã¢â‚¬â€ {$alias}" : "";
          tg_send_iface("Ã¢Å“â€¦ PORT UP Ã¢â‚¬â€ Resolved in {$dur}\nHost: {$device_name}\nInterface: {$name}{$aliasTxt}\nResolved: " . date('Y-m-d H:i:s', $NOW) . "\nOriginal problem ID: {$ld['id']}");
        }
        insert_iface_event($conn, [
          'device_id'=>$device_id, 'interface_id'=>$iface_id, 'ifIndex'=>$idx,
          'device_name'=>$device_name, 'ifName'=>$name, 'ifDescr'=>$descr, 'ifAlias'=>$alias,
          'event_type'=>'link_up', 'old_status'=>$prev_up, 'new_status'=>$oper,
          'old_speed_mbps'=>null, 'new_speed_mbps'=>null, 'severity'=>'Info', 'opened_at'=>$NOW
        ]);
      }
    }
    // Speed change
    $new_mbps = $spd !== null        ? (int)round($spd / 1_000_000) : null;
    $old_mbps = $prev_speed !== null ? (int)round($prev_speed / 1_000_000) : null;
    if ($new_mbps !== null && $old_mbps !== null && $new_mbps !== $old_mbps) {
      $evId = insert_iface_event($conn, [
        'device_id'=>$device_id, 'interface_id'=>$iface_id, 'ifIndex'=>$idx,
        'device_name'=>$device_name, 'ifName'=>$name, 'ifDescr'=>$descr, 'ifAlias'=>$alias,
        'event_type'=>'speed_changed', 'old_status'=>null, 'new_status'=>null,
        'old_speed_mbps'=>$old_mbps, 'new_speed_mbps'=>$new_mbps,
        'severity'=>'Info', 'opened_at'=>$NOW
      ]);
      $aliasTxt = $alias ? " Ã¢â‚¬â€ {$alias}" : "";
      tg_send_iface("Ã°Å¸â€Â SPEED CHANGED\nHost: {$device_name}\nInterface: {$name}{$aliasTxt}\n{$old_mbps} Ã¢â€ â€™ {$new_mbps} Mbps\nTime: " . date('Y-m-d H:i:s', $NOW) . "\nEvent ID: {$evId}");
    }

    // === COUNTERS Ã¢â€ â€™ Prefer 64-bit per index; fallback to 32-bit if missing ===
    $has64in  = array_key_exists($idx, $HC_IN)  && is_numeric($HC_IN[$idx]);
    $has64out = array_key_exists($idx, $HC_OUT) && is_numeric($HC_OUT[$idx]);
    $has32in  = array_key_exists($idx, $L32_IN) && is_numeric($L32_IN[$idx]);
    $has32out = array_key_exists($idx, $L32_OUT) && is_numeric($L32_OUT[$idx]);

    $in_oct  = $has64in  ? (float)$HC_IN[$idx]  : ($has32in  ? (float)$L32_IN[$idx]  : null);
    $out_oct = $has64out ? (float)$HC_OUT[$idx] : ($has32out ? (float)$L32_OUT[$idx] : null);

    if ($in_oct !== null && $out_oct !== null) {
      [$bi, $bo] = update_interface_counters_and_sample($conn, $iface_id, $NOW, $in_oct, $out_oct);
      log_poll("iface {$name} idx={$idx} in_oct={$in_oct} out_oct={$out_oct} " . (($bi!==null||$bo!==null) ? "bps_in={$bi} bps_out={$bo}" : "first sample"));
    } else {
      log_poll("iface {$name} idx={$idx} has NO counters (64/32 both missing)");
    }
  } // each interface
} // each device

if ($devStmt instanceof mysqli_stmt) {
  $devStmt->close();
}

echo "OK @ " . date('Y-m-d H:i:s') . PHP_EOL;
?>


