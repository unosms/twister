#!/usr/bin/env bash
set -euo pipefail

IP="${SWITCH_IP:-}"
PASS="${SWITCH_PASS:-}"
ENA="${SWITCH_ENA:-}"
NAME="${SWITCH_NAME:-}"
IFACE="${INTERFACE:-}"

[[ -n "$IP"    ]] || { echo "Missing SWITCH_IP" >&2; exit 2; }
[[ -n "$IFACE" ]] || { echo "Missing INTERFACE" >&2; exit 3; }

echo "Running show spanning-tree on '$NAME' ($IP) for interface '$IFACE'"

/usr/bin/expect <<'EOF'
set timeout 30

spawn telnet $env(SWITCH_IP)
expect "Password:"
send "$env(SWITCH_PASS)\r"
expect ">"
send "enable\r"
expect "Password:"
send "$env(SWITCH_ENA)\r"
expect "#"

# no paging
send "terminal length 0\r"
expect "#"

# spanning-tree per interface
send "show spanning-tree interface $env(INTERFACE)\r"
expect "#"

send "exit\r"
expect eof
EOF
