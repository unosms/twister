#!/usr/bin/env bash
set -euo pipefail

IP="${1:-}"
PASS="${2:-}"
ENA="${3:-}"
LOCATION="${4:-}"
USER="${5:-${SWITCH_USER:-${USERNAME:-}}}"
TFTP_SERVER="192.168.88.57"

DATE_STR=$(date +"%F_%H-%M-%S")
RENAMED_FILE="${DATE_STR}.txt"

if [[ -z "$IP" || -z "$PASS" || -z "$ENA" || -z "$LOCATION" ]]; then
    echo "Usage: $0 <SWITCH_IP> <PASSWORD> <ENABLE_PASSWORD> <LOCATION> [USERNAME]"
    exit 1
fi

export IP PASS ENA LOCATION USER TFTP_SERVER RENAMED_FILE

/usr/bin/expect <<'EOF'
log_user 1
set timeout 40

set IP $env(IP)
set PASS $env(PASS)
set ENA $env(ENA)
set LOCATION $env(LOCATION)
set USER ""
if {[info exists env(USER)]} { set USER $env(USER) }
set TFTP_SERVER $env(TFTP_SERVER)
set RENAMED_FILE $env(RENAMED_FILE)
set prompt {.*[>#] ?$}

proc fail_step {message code} {
    send_user "$message\n"
    exit $code
}

spawn telnet $IP

set authed 0
while {!$authed} {
    expect {
        -re {Press RETURN to get started} { send -- "\r" }
        -re {(U|u)sername:} {
            if {$USER ne ""} {
                send -- "$USER\r"
            } else {
                send -- "\r"
            }
            exp_continue
        }
        -re {(L|l)ogin:} {
            if {$USER ne ""} {
                send -- "$USER\r"
            } else {
                send -- "\r"
            }
            exp_continue
        }
        -re {(P|p)assword:} {
            send -- "$PASS\r"
            exp_continue
        }
        -re $prompt { set authed 1 }
        timeout { fail_step "Login timeout" 5 }
        eof { fail_step "Connection closed during login" 10 }
    }
}

set priv 0
if {[string match *# $expect_out(buffer)]} {
    set priv 1
}

if {!$priv} {
    send -- "enable\r"
    expect {
        -re {(P|p)assword:} { send -- "$ENA\r"; exp_continue }
        -re {(?i)% ?(bad secrets|access denied|authorization failed|invalid password)} { set priv 0 }
        -re $prompt {
            if {[string match *#* $expect_out(buffer)]} {
                set priv 1
            } else {
                set priv 0
            }
        }
        timeout { fail_step "Enable timeout" 7 }
        eof { fail_step "Connection closed during enable" 10 }
    }

    if {!$priv} {
        fail_step "Enable failed: invalid enable password or insufficient privilege." 14
    }
}

send -- "terminal length 0\r"
expect {
    -re {(?i)% ?(invalid input detected|insufficient privileges|authorization failed|bad secrets|access denied)} {
        fail_step "Paging disable failed: insufficient privilege or command rejected." 15
    }
    -re $prompt {}
    timeout { fail_step "Paging disable timeout" 8 }
    eof { fail_step "Connection closed while disabling paging" 10 }
}

send -- "write memory\r"
expect {
    -re {(?i)% ?(invalid input detected|insufficient privileges|authorization failed|bad secrets|access denied)} {
        fail_step "Write memory failed: insufficient privilege or command rejected." 16
    }
    -re $prompt {}
    timeout { fail_step "Write memory timeout" 11 }
    eof { fail_step "Connection closed during write memory" 10 }
}

send -- "copy running-config tftp:\r"
expect {
    -re {(A|a)ddress or name of remote host.*} {
        send -- "$TFTP_SERVER\r"
        exp_continue
    }
    -re {(S|s)ource filename.*} {
        send -- "\r"
        exp_continue
    }
    -re {(D|d)estination filename.*} {
        send -- "$LOCATION/$RENAMED_FILE\r"
        exp_continue
    }
    -re {(O|o)verwrite.*} {
        send -- "y\r"
        exp_continue
    }
    -re {(bytes copied|copied in|Copy complete|copied successfully)} {
        exp_continue
    }
    -re {(?i)% ?(invalid input detected|insufficient privileges|authorization failed|bad secrets|access denied)} {
        fail_step "Backup copy failed: insufficient privilege or command rejected." 17
    }
    -re {%Error|TFTP put operation failed|Error opening tftp} {
        fail_step $expect_out(0,string) 12
    }
    -re $prompt {}
    timeout { fail_step "Backup copy timeout" 12 }
    eof { fail_step "Connection closed during backup copy" 10 }
}

send -- "exit\r"
expect eof
EOF
