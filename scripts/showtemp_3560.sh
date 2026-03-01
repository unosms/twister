#!/usr/bin/env bash
set -euo pipefail

IP="${SWITCH_IP:-${1:-}}"
PASS="${SWITCH_PASS:-${2:-}}"
ENA="${SWITCH_ENA:-${3:-}}"
NAME="${4:-}"
USER="${SWITCH_USER:-${5:-${USERNAME:-}}}"
DEBUG="${DEBUG:-0}"

if [[ -z "$IP" ]]; then
    echo "Missing SWITCH_IP"
    exit 2
fi

export IP PASS ENA USER

run_cmd() {
    local cmd="$1"
    CMD="$cmd" /usr/bin/expect <<'EOF'
set timeout 20
set IP $env(IP)
set PASS $env(PASS)
set ENA $env(ENA)
set USER ""
if {[info exists env(USER)]} { set USER $env(USER) }
set CMD $env(CMD)

spawn telnet $IP

expect {
    -re "(U|u)sername:" {
        if {$USER != ""} { send "$USER\r" }
        exp_continue
    }
    -re "(L|l)ogin:" {
        if {$USER != ""} { send "$USER\r" }
        exp_continue
    }
    -re "(P|p)assword:" {
        send "$PASS\r"
        exp_continue
    }
    timeout { send_user "Login timeout\n"; exit 5 }
    ">" {}
    "#" {}
}

expect {
    ">" { send "enable\r" }
    "#" {}
    timeout { send_user "No prompt after login\n"; exit 6 }
}

expect {
    -re "(P|p)assword:" { send "$ENA\r"; exp_continue }
    "#" {}
    timeout { send_user "Enable timeout\n"; exit 7 }
}

send "terminal length 0\r"
expect {
    "#" {}
    timeout { send_user "Paging disable timeout\n"; exit 8 }
}

send "$CMD\r"
expect {
    "#" {}
    timeout { send_user "Command timeout\n"; exit 9 }
}

send "exit\r"
expect eof
EOF
}

parse_inlet_outlet() {
    local out="$1"
    local inlet
    local outlet

    inlet=$(printf "%s\n" "$out" | awk '/air inlet/ { if (match($0, /([0-9]+)C/, m)) print m[1] }' | head -1)
    outlet=$(printf "%s\n" "$out" | awk '/air outlet/ { if (match($0, /([0-9]+)C/, m)) print m[1] }' | head -1)

    if [[ -n "$inlet" || -n "$outlet" ]]; then
        echo "inlet: ${inlet:-N/A}°C | outlet: ${outlet:-N/A}°C"
        return 0
    fi

    return 1
}

parse_single_temp() {
    local out="$1"
    local temp

    temp=$(printf "%s\n" "$out" \
        | awk -F: '/System Temperature Value/ { gsub(/[^0-9]/, "", $2); if ($2 != "") { print $2; exit } }')

    if [[ -n "$temp" ]]; then
        echo "temp: ${temp}°C"
        return 0
    fi

    return 1
}

OUT=$(run_cmd "show env all") || true
if [[ "$DEBUG" == "1" ]]; then
    echo "--- DEBUG show env all ---" >&2
    printf "%s\n" "$OUT" >&2
fi
if RESULT=$(parse_inlet_outlet "$OUT"); then
    echo "$RESULT"
    exit 0
fi

if RESULT=$(parse_single_temp "$OUT"); then
    echo "$RESULT"
    exit 0
fi

OUT=$(run_cmd "show environment temperature") || true
if [[ "$DEBUG" == "1" ]]; then
    echo "--- DEBUG show environment temperature ---" >&2
    printf "%s\n" "$OUT" >&2
fi
if RESULT=$(parse_inlet_outlet "$OUT"); then
    echo "$RESULT"
    exit 0
fi

if RESULT=$(parse_single_temp "$OUT"); then
    echo "$RESULT"
    exit 0
fi

echo "N/A"