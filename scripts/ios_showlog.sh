#!/usr/bin/env bash
set -euo pipefail

# Read inputs from environment
IP="${SWITCH_IP:-}"
PASS="${SWITCH_PASS:-}"
ENA="${SWITCH_ENA:-}"
NAME="${SWITCH_NAME:-}"
CMD="${CMD:-showlog}"

if [[ -z "$IP" ]]; then
  echo "Missing SWITCH_IP" >&2
  exit 2
fi

echo "Running $CMD on switch '$NAME' ($IP)"

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
   send "show logging\r"
   expect "#"
   send "exit\r"
   expect eof
EOF
