#!/usr/bin/env bash
set -euo pipefail

if ! command -v expect >/dev/null 2>&1; then
    echo "ERROR: expect not installed" >&2
    exit 4
fi
if ! command -v telnet >/dev/null 2>&1; then
    echo "ERROR: telnet not installed" >&2
    exit 4
fi

IP="${SWITCH_IP:-${1:-}}"
PASS="${SWITCH_PASS:-${2:-}}"
ENA="${SWITCH_ENA:-${3:-}}"
USER="${SWITCH_USER:-${4:-${USERNAME:-}}}"
DEBUG="${DEBUG:-0}"

if [[ -z "$IP" ]]; then
    echo "Missing SWITCH_IP"
    exit 2
fi

export IP PASS ENA USER DEBUG

OUT=$(/usr/bin/expect <<'EOF' || true
set timeout 30
set IP $env(IP)
set PASS $env(PASS)
set ENA $env(ENA)
set USER ""
if {[info exists env(USER)]} { set USER $env(USER) }

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

# Determine privilege level from prompt
set priv 0
if {[string match *# $expect_out(buffer)]} { set priv 1 }

if {!$priv} {
    send -- "enable\r"
    expect {
        -re {(P|p)assword:} { send -- "$ENA\r"; exp_continue }
        -re {#} { set priv 1 }
        -re $prompt { }
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

send -- "show version\r"
expect {
    -re $prompt {}
    timeout { send_user "Command timeout\n"; exit 9 }
    eof { send_user "Connection closed\n"; exit 10 }
}

send -- "exit\r"
expect eof
EOF
)

if [[ "$DEBUG" == "1" ]]; then
    echo "--- DEBUG show version ---" >&2
    printf "%s\n" "$OUT" >&2
fi

UPTIME=$(printf "%s\n" "$OUT" \
    | awk 'tolower($0) ~ /uptime is/ { sub(/.*uptime is /, "", $0); print; exit }')

if [[ -n "$UPTIME" ]]; then
    echo "$UPTIME"
else
    echo "N/A"
fi
