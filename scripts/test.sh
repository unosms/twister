#!/usr/bin/env bash
set -euo pipefail

# Read inputs from environment
#IP=""
#PASS="${SWITCH_PASS:-}"
#ENA="${SWITCH_ENA:-}"
#NAME="${SWITCH_NAME:-}"
#CMD="${CMD:-showlog}"

#if [[ -z "$IP" ]]; then
#  echo "Missing SWITCH_IP" >&2
#  exit 2
#fi

#echo "Running $CMD on switch '$NAME' ($IP)"

/usr/bin/expect <<'EOF'
   set timeout 20
   spawn telnet  192.168.200.252
   expect "Password:"
   send "mega\r"
   expect ">"
   send "enable\r"
   expect "Password:"
   send "WPA_()#@!\r"
   expect "#"
   send "show logging\r"
   expect "More--"
   send "\r"
   send "\r"
   send "\r"
   send "\r"
   send "\r"
   send "\r"
   send "\r"
   send "\r"
   send "\r"
   send "\r"
   send "\r"
   send "\r"
   send "\r"
   send "\r"
   send "\r"

   send "exit\r"
   expect eof
EOF
