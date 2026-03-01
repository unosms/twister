#!/bin/bash
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
DEBUG="${DEBUG:-0}"

[[ -n "$IP"  ]] || { echo "SWITCH_IP is required" >&2; exit 2; }
[[ -n "$PASS" ]] || { echo "SWITCH_PASS is required" >&2; exit 3; }

export IP USER PASS DEBUG

OUT=$(/usr/bin/expect <<'EXP' || true
    set timeout 20
    match_max 200000
    set ip    $env(IP)
    set user  $env(USER)
    set pass  $env(PASS)
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
            -re {(U|u)sername:}               { send -- "$user\r"; exp_continue }
            -re {(L|l)ogin:}                  { send -- "$user\r"; exp_continue }
            -re {(P|p)assword:}               { send -- "$pass\r"; exp_continue }
            -re $prompt { set authed 1 }
            timeout { send -- "\r" }
            eof     { puts "ERROR: Connection closed during login"; exit 5 }
        }
    }

    expect -timeout 10 -re $prompt { } timeout { }

    send -- "terminal length 0\r"
    expect -timeout 10 -re $prompt { } timeout { }

    # Prefer full environment output (some Nexus devices don't support show env).
    send -- "show environment\r"
    expect -timeout 12 -re $prompt { } timeout { }

    # Fallback for older syntax.
    send -- "show env\r"
    expect -timeout 12 -re $prompt { } timeout { }

    send -- "exit\r"
    expect eof
EXP
)

OUT=$(printf "%s" "$OUT" | tr "\r" "\n" | sed -r 's/\x1B\[[0-9;]*[mK]//g')

if [[ "$DEBUG" == "1" ]]; then
  echo "----- RAW OUTPUT (sanitized) -----" >&2
  printf "%s\n" "$OUT" >&2
fi

TEMP=$(printf "%s\n" "$OUT" \
    | awk '
        /^[[:space:]]*[Tt]emperature[[:space:]]*:?/ { inblock=1; next }
        inblock && /^-+/ { next }
        inblock && /CurTemp/ { next }
        inblock && /Power[[:space:]]+Supply/ { inblock=0 }
        inblock && /(ok|OK|Ok|fail|Failure|shutdown|Shut)/ {
            last=""
            for (i=1; i<=NF; i++) {
                if ($i ~ /^[0-9]+$/) { last=$i }
            }
            if (last != "") { temps[++n]=last }
            next
        }
        END { if (n>0) { max=temps[1]; for (i=2;i<=n;i++) if (temps[i]>max) max=temps[i]; print max } }
    ')

if [[ -n "$TEMP" ]]; then
  echo "temp: ${TEMP}C"
else
  echo "N/A"
fi
