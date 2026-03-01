#!/usr/bin/env bash
set -euo pipefail

IP="${SWITCH_IP:-}"
PASS="${SWITCH_PASS:-}"
ENA="${SWITCH_ENA:-}"
NAME="${SWITCH_NAME:-}"
CMD="${CMD:-showlog}"        # showlog | showmac | showint | showintstatus
IFACE="${INTERFACE:-}"       # used by showmac/showint

[[ -n "$IP" ]] || { echo "Missing SWITCH_IP" >&2; exit 2; }

echo "Running $CMD on '$NAME' ($IP)${IFACE:+, iface '$IFACE'}"

/usr/bin/expect <<'EOF'
set timeout 20

# pull env
if {![info exists env(SWITCH_IP)]}  { puts stderr "Missing env SWITCH_IP"; exit 2 }
set IP    $env(SWITCH_IP)
set PASS  [expr {[info exists env(SWITCH_PASS)] ? $env(SWITCH_PASS) : ""}]
set ENA   [expr {[info exists env(SWITCH_ENA)]  ? $env(SWITCH_ENA)  : ""}]
set CMD   [string tolower [expr {[info exists env(CMD)] ? $env(CMD) : "showlog"}]]
set IFACE [expr {[info exists env(INTERFACE)] ? $env(INTERFACE) : ""}]

# --- Login flow you use: Password: -> '>' -> enable -> Password: -> '#'
spawn telnet $IP
expect "Password:"
send "$PASS\r"
expect ">"
send "enable\r"
expect "Password:"
send "$ENA\r"
expect "#"

# no paging
send "terminal length 0\r"
expect "#"

# Commands
if {$CMD eq "showlog"} {
    send "show logging\r"
    expect "#"
} elseif {$CMD eq "showmac"} {
    if {$IFACE eq ""} { puts stderr "Missing INTERFACE for showmac"; exit 3 }
    send "show mac address-table interface $IFACE\r"
    expect "#"
} elseif {$CMD eq "showint"} {
    if {$IFACE eq ""} { puts stderr "Missing INTERFACE for showint"; exit 4 }
    send "show interface $IFACE\r"
    expect "#"
} elseif {$CMD eq "showintstatus"} {
    send "show interface status\r"
    expect "#"
} else {
    puts stderr "Unknown CMD: $CMD"
    exit 5
}

send "exit\r"
expect eof
exit 0
EOF
