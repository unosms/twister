#!/usr/bin/env bash
set -euo pipefail

IP="${SWITCH_IP:-}"
PASS="${SWITCH_PASS:-}"
ENA="${SWITCH_ENA:-}"
NAME="${SWITCH_NAME:-}"
IFACE="${INTERFACE:-}"

[[ -n "$IP"    ]] || { echo "Missing SWITCH_IP" >&2; exit 2; }
[[ -n "$IFACE" ]] || { echo "Missing INTERFACE" >&2; exit 3; }

echo "Disabling interface '$IFACE' on '$NAME' ($IP)"

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

send "terminal length 0\r"
expect "#"

send "configure terminal\r"
expect "(config)#"
send "interface $env(INTERFACE)\r"
expect "(config-if)#"
send "shutdown\r"
expect "(config-if)#"

send "end\r"
expect "#"
send "show interface $env(INTERFACE) status\r"
expect "#"

send "exit\r"
expect eof
EOF
