#!/usr/bin/env bash
set -euo pipefail

IP="${SWITCH_IP:-}"
NAME="${SWITCH_NAME:-}"
USER="${SWITCH_USERNAME:-${SWITCH_USER:-}}"
PASS="${SWITCH_PASS:-}"
ENA="${SWITCH_ENA:-}"

[[ -n "$IP" ]] || { echo "Missing SWITCH_IP" >&2; exit 2; }
[[ -n "$PASS" ]] || { echo "Missing SWITCH_PASS" >&2; exit 3; }

echo "Running showlog on switch '$NAME' ($IP)"

/usr/bin/expect <<'EOF'
set timeout 60
log_user 1
match_max 200000

set ip   $env(SWITCH_IP)
set pass $env(SWITCH_PASS)
set user ""
set ena  ""
if {[info exists env(SWITCH_USERNAME)]} { set user $env(SWITCH_USERNAME) }
if {[info exists env(SWITCH_USER)] && $user eq ""} { set user $env(SWITCH_USER) }
if {[info exists env(SWITCH_ENA)]} { set ena $env(SWITCH_ENA) }

set prompt {.*[>#] ?$}

spawn telnet $ip

set authed 0
set promptchar ""
while {!$authed} {
    expect {
        -re {Press RETURN to get started} { send -- "\r"; exp_continue }
        -re {User Access Verification} { exp_continue }
        -re {Nexus .* Switch} { exp_continue }
        -re {Escape character is} { exp_continue }

        -re {(U|u)sername:} {
            if {$user eq ""} {
                puts "ERROR: Missing SWITCH_USERNAME for this device"
                exit 21
            }
            send -- "$user\r"
            exp_continue
        }
        -re {([Ll]ogin:)} {
            if {$user eq ""} {
                puts "ERROR: Missing SWITCH_USERNAME for this device"
                exit 21
            }
            send -- "$user\r"
            exp_continue
        }
        -re {(P|p)assword:} {
            send -- "$pass\r"
            exp_continue
        }

        -re {Login invalid|Login incorrect|Authentication failed|Invalid.*password|Authorization failed|% Bad passwords} {
            puts "ERROR: Login failed"
            exit 22
        }

        -re {.*# ?$} {
            set authed 1
            set promptchar "#"
        }
        -re {.*> ?$} {
            set authed 1
            set promptchar ">"
        }

        timeout { send -- "\r"; exp_continue }
        eof { puts "ERROR: Connection closed before login finished"; exit 23 }
    }
}

if {$promptchar eq ">" && $ena ne ""} {
    send -- "enable\r"
    expect {
        -re {(P|p)assword:} { send -- "$ena\r" }
        -re {#} {}
        timeout { puts "ERROR: Enable timeout"; exit 24 }
        eof { puts "ERROR: Connection closed during enable"; exit 25 }
    }
    expect {
        -re {#} {}
        timeout { puts "ERROR: Enable prompt not reached"; exit 26 }
    }
}

send -- "terminal length 0\r"
expect {
    -re $prompt {}
    timeout { puts "ERROR: Timeout disabling pagination"; exit 27 }
}

send -- "show logging\r"
expect {
    -re $prompt {}
    timeout { puts "ERROR: Timeout waiting for show logging output"; exit 28 }
}

send -- "exit\r"
expect eof
EOF
