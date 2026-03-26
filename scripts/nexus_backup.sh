#!/usr/bin/env bash
set -euo pipefail

IP="${1:-}"
USERNAME="${2:-}"
PASS="${3:-}"
LOCATION_RAW="${4:-}"
TFTP_SERVER="${5:-${BACKUP_TFTP_SERVER:-${TFTP_SERVER:-192.168.88.57}}}"

if [[ -z "$IP" || -z "$USERNAME" || -z "$PASS" || -z "$LOCATION_RAW" ]]; then
    echo "Usage: $0 <SWITCH_IP> <USERNAME> <PASSWORD> <LOCATION> [TFTP_SERVER]"
    exit 1
fi

if [[ "$LOCATION_RAW" == *".."* ]]; then
    echo "Invalid backup location."
    exit 2
fi

if ! command -v expect >/dev/null 2>&1; then
    echo "expect is required but not installed"
    exit 3
fi

if ! command -v telnet >/dev/null 2>&1; then
    echo "telnet is required but not installed"
    exit 4
fi

LOCATION="$(printf '%s' "$LOCATION_RAW" | tr '\\' '/' | sed -E 's#^/+##; s#/+$##')"
if [[ -z "$LOCATION" ]]; then
    echo "Invalid backup location."
    exit 2
fi

DATE_STR="$(date +"%F_%H-%M-%S")"
RENAMED_FILE="${DATE_STR}.txt"
DEST_PATH="${LOCATION}/${RENAMED_FILE}"

export IP USERNAME PASS TFTP_SERVER DEST_PATH

/usr/bin/expect <<'EOF'
log_user 1
set timeout 45

set IP $env(IP)
set USERNAME $env(USERNAME)
set PASS $env(PASS)
set TFTP_SERVER $env(TFTP_SERVER)
set DEST_PATH $env(DEST_PATH)
set prompt {.*[>#] ?$}

proc fail_step {message code} {
    send_user "$message\n"
    exit $code
}

spawn telnet $IP

set authed 0
set username_sent 0
set password_attempts 0

while {!$authed} {
    expect {
        -re {Press RETURN to get started} {
            send -- "\r"
            exp_continue
        }
        -re {(U|u)sername:} {
            if {$username_sent} {
                fail_step "Connection closed during login (bad username/password)" 10
            }

            send -- "$USERNAME\r"
            set username_sent 1
            exp_continue
        }
        -re {(L|l)ogin:} {
            if {$username_sent} {
                fail_step "Connection closed during login (bad username/password)" 10
            }

            send -- "$USERNAME\r"
            set username_sent 1
            exp_continue
        }
        -re {(P|p)assword:} {
            if {$password_attempts >= 2} {
                fail_step "Connection closed during login (bad username/password)" 10
            }

            send -- "$PASS\r"
            incr password_attempts
            exp_continue
        }
        -re {(?i)(login incorrect|authentication failed|access denied|invalid password)} {
            fail_step "Connection closed during login (bad username/password)" 10
        }
        -re $prompt {
            set authed 1
        }
        timeout {
            fail_step "Login timeout (check username/password)" 5
        }
        eof {
            fail_step "Connection closed during login (bad username/password)" 10
        }
    }
}

send -- "terminal length 0\r"
expect {
    -re $prompt {}
    -re {(?i)invalid input} {}
    timeout { fail_step "Paging disable timeout" 8 }
    eof { fail_step "Connection closed while disabling paging" 10 }
}

send -- "copy running-config tftp://$TFTP_SERVER/$DEST_PATH\r"

set finished 0
set loop_guard 0
while {!$finished && $loop_guard < 120} {
    incr loop_guard
    expect {
        -re {(?i)enter vrf.*} {
            send -- "\r"
            exp_continue
        }
        -re {(A|a)ddress or name of remote host.*} {
            send -- "$TFTP_SERVER\r"
            exp_continue
        }
        -re {(D|d)estination filename.*} {
            send -- "$DEST_PATH\r"
            exp_continue
        }
        -re {(S|s)ource filename.*} {
            send -- "\r"
            exp_continue
        }
        -re {(?i)(overwrite|replace|already exists).*} {
            send -- "y\r"
            exp_continue
        }
        -re {(?i)(confirm|continue|yes/no|\(y/n\)|\[y/n\]).*} {
            send -- "y\r"
            exp_continue
        }
        -re {(?i)(bytes copied|copied in|copy complete|copied successfully|successfully copied)} {
            set finished 1
            exp_continue
        }
        -re {(?i)(error opening|invalid input|invalid host|permission denied|copy failed|failed)} {
            fail_step "Backup copy failed" 12
        }
        -re $prompt {
            set finished 1
        }
        timeout { fail_step "Backup copy timeout" 12 }
        eof { fail_step "Connection closed during backup copy" 10 }
    }
}

if {!$finished} {
    fail_step "Backup copy did not complete" 12
}

send -- "exit\r"
expect eof
EOF
