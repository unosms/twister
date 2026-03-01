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
set timeout 30
set IP $env(IP)
set PASS $env(PASS)
set ENA $env(ENA)
set USER ""
if {[info exists env(USER)]} { set USER $env(USER) }
set CMD $env(CMD)

set prompt {.*[>#] ?$}
spawn telnet $IP

set authed 0
while {!$authed} {
    expect {
        -re {Press RETURN to get started} { send -- "\r" }
        -re {(U|u)sername:} {
            if {$USER != ""} { send -- "$USER\r" }
            exp_continue
        }
        -re {(L|l)ogin:} {
            if {$USER != ""} { send -- "$USER\r" }
            exp_continue
        }
        -re {(P|p)assword:} {
            send -- "$PASS\r"
            exp_continue
        }
        -re $prompt { set authed 1 }
        timeout { send_user "Login timeout\n"; exit 5 }
        eof { send_user "Connection closed\n"; exit 10 }
    }
}

set priv 0
if {[string match *# $expect_out(buffer)]} { set priv 1 }

if {!$priv} {
    send -- "enable\r"
    expect {
        -re {(P|p)assword:} { send -- "$ENA\r"; exp_continue }
        -re {#} { set priv 1 }
        -re $prompt {}
        timeout { send_user "Enable timeout\n"; exit 7 }
        eof { send_user "Connection closed\n"; exit 10 }
    }
}

send -- "terminal length 0\r"
expect {
    -re $prompt {}
    timeout { send_user "Paging disable timeout\n"; exit 8 }
    eof { send_user "Connection closed\n"; exit 10 }
}

send -- "$CMD\r"
expect {
    -re $prompt {}
    timeout { send_user "Command timeout\n"; exit 9 }
    eof { send_user "Connection closed\n"; exit 10 }
}

send -- "exit\r"
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

OUT=$(run_cmd "show environment temperature") || true
if [[ "$DEBUG" == "1" ]]; then
    echo "--- DEBUG show environment temperature ---" >&2
    printf "%s\n" "$OUT" >&2
fi
if RESULT=$(parse_inlet_outlet "$OUT"); then
    echo "$RESULT"
    exit 0
fi

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

echo "N/A"