#!/usr/bin/env bash
set -euo pipefail

# Inputs from env (set by exec_cmd.php)
IP="${SWITCH_IP:-}"
PASS="${SWITCH_PASS:-}"
ENA="${SWITCH_ENA:-}"
NAME="${SWITCH_NAME:-}"
IFACE="${INTERFACE:-}"   # comes from ?iface=...

[[ -n "$IP"    ]] || { echo "Missing SWITCH_IP" >&2; exit 2; }
[[ -n "$IFACE" ]] || { echo "Missing INTERFACE" >&2; exit 3; }

echo "Running showmac on '$NAME' ($IP) for interface '$IFACE'"

/usr/bin/expect <<'EOF'
set timeout 20

# Telnet and strict prompt order:
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

# run command with the provided interface
send "show mac address-table interface $env(INTERFACE)\r"
expect "#"

send "exit\r"
expect eof
EOF
