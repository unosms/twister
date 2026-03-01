#!/usr/bin/env bash
set -u -o pipefail

if ! command -v expect >/dev/null 2>&1; then
  echo "ERROR: expect not installed" >&2
  exit 4
fi
if ! command -v telnet >/dev/null 2>&1; then
  echo "ERROR: telnet not installed" >&2
  exit 4
fi

IP="${SWITCH_IP:-${1:-}}"
USER="${SWITCH_USER:-${2:-admin}}"
PASS="${SWITCH_PASS:-${3:-}}"
ENA="${SWITCH_ENA:-${4:-}}"
DEBUG="${DEBUG:-0}"

if [[ -z "$IP" ]]; then
  echo "ERROR: missing IP" >&2
  exit 2
fi
if [[ -z "$PASS" ]]; then
  echo "ERROR: missing PASSWORD" >&2
  exit 3
fi

export IP USER PASS ENA DEBUG

OUT=$(/usr/bin/expect <<'EXP' || true
    if {[info exists env(DEBUG)] && $env(DEBUG) == 1} { exp_internal 1 }

    set timeout 20
    match_max 200000
    set ip    $env(IP)
    set user  $env(USER)
    set pass  $env(PASS)
    set ena   $env(ENA)
    set prompt {.*[>#] ?}

    spawn telnet $ip

    set authed 0
    while {!$authed} {
        expect {
            -re {Press RETURN to get started} { send -- "\r" }
            -re {Nexus 3000 Switch}           { exp_continue }
            -re {Nexus .* Switch}             { exp_continue }
            -re {User Access Verification}    { }
            -re {login:\s*$}                  { send -- "$user\r"; exp_continue }
            -re {Username:\s*$}               { send -- "$user\r"; exp_continue }
            -re {(U|u)sername:}               { send -- "$user\r"; exp_continue }
            -re {(L|l)ogin:}                  { send -- "$user\r"; exp_continue }
            -re {Password:\s*$}               { send -- "$pass\r"; exp_continue }

            -re {Login invalid|Authentication failed|Authorization failed|Invalid.*password} {
                puts "ERROR: Login failed"
                exit 4
            }

            -re $prompt { set authed 1 }
            timeout { send -- "\r" }
            eof     {
                puts "ERROR: Connection closed during login"
                exit 5
            }
        }
    }

    expect -timeout 10 -re $prompt { } timeout { }

    if {$ena != ""} {
        send -- "enable\r"
        expect {
            -re {Password:\s*$} { send -- "$ena\r"; exp_continue }
            -re {#} {}
            -re $prompt {}
            timeout { puts "ERROR: Enable timeout"; exit 7 }
            eof { puts "ERROR: Connection closed during enable"; exit 10 }
        }
    }

    send -- "terminal length 0\r"
    expect -timeout 10 -re $prompt { } timeout { }

    send -- "show version\r"
    expect -timeout 10 -re $prompt { } timeout { }
    send -- "exit\r"
    expect eof
EXP
)

OUT=$(printf "%s" "$OUT" | tr -d "\r")

if [[ "$DEBUG" == "1" ]]; then
    echo "--- DEBUG show version ---" >&2
    printf "%s\n" "$OUT" >&2
fi

UPTIME=$(printf "%s\n" "$OUT" \
    | awk 'tolower($0) ~ /(kernel uptime is|uptime is)/ { sub(/.*(kernel uptime is|uptime is) /, "", $0); print; exit }')

if [[ -n "$UPTIME" ]]; then
    echo "$UPTIME"
else
    echo "N/A"
fi
