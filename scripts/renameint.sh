#!/usr/bin/env bash
set -euo pipefail

IP="${SWITCH_IP:-}"
PASS="${SWITCH_PASS:-}"
ENA="${SWITCH_ENA:-}"
NAME="${SWITCH_NAME:-}"
IFACE="${INTERFACE:-}"
DESC="${DESCRIPTION:-}"

[[ -n "$IP"    ]] || { echo "Missing SWITCH_IP" >&2; exit 2; }
[[ -n "$IFACE" ]] || { echo "Missing INTERFACE" >&2; exit 3; }
[[ -n "$DESC"  ]] || { echo "Missing DESCRIPTION" >&2; exit 4; }

echo "Renaming interface '$IFACE' on '$NAME' ($IP) with description '$DESC'"

/usr/bin/expect <<EOF
set timeout 30
spawn telnet $IP
expect "Password:"
send "$PASS\r"
expect ">"
send "enable\r"
expect "Password:"
send "$ENA\r"
expect "#"

send "terminal length 0\r"
expect "#"

send "configure terminal\r"
expect "(config)#"
send "interface $IFACE\r"
expect "(config-if)#"
send "description $DESC\r"
expect "(config-if)#"

send "end\r"
expect "#"
send "show run interface $IFACE\r"
expect "#"

send "exit\r"
expect eof
EOF
