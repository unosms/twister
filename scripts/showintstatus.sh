#!/usr/bin/env bash
set -euo pipefail

IP="${SWITCH_IP:-}"
PASS="${SWITCH_PASS:-}"
ENA="${SWITCH_ENA:-}"
NAME="${SWITCH_NAME:-}"

[[ -n "$IP" ]] || { echo "Missing SWITCH_IP" >&2; exit 2; }

echo "Running showintstatus on '$NAME' ($IP)"

/usr/bin/expect <<'EOF'
set timeout 20

spawn telnet $env(SWITCH_IP)
expect "Password:"
send "$env(SWITCH_PASS)\r"
expect ">"
send "enable\r"
expect "Password:"
send "$env(SWITCH_ENA)\r"
expect "#"

send "terminal length 0\r"
expect "#"

send "show interface status\r"
expect "#"

send "exit\r"
expect eof
EOF
