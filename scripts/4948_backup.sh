#!/usr/bin/env bash
set -euo pipefail

IP="${1:-}"
PASS="${2:-}"
ENA="${3:-}"
LOCATION="${4:-}"
TFTP_SERVER="172.16.208.2"

DATE_STR=$(date +"%F_%H-%M-%S")
RENAMED_FILE="${DATE_STR}.txt"

if [[ -z "$IP" || -z "$PASS" || -z "$ENA" || -z "$LOCATION" ]]; then
    echo "Usage: $0 <SWITCH_IP> <PASSWORD> <ENABLE_PASSWORD> <LOCATION>"
    exit 1
fi

export IP PASS ENA LOCATION TFTP_SERVER RENAMED_FILE

/usr/bin/expect <<'EOF'
log_user 1
set timeout 60

set IP $env(IP)
set PASS $env(PASS)
set ENA $env(ENA)
set LOCATION $env(LOCATION)
set TFTP_SERVER $env(TFTP_SERVER)
set RENAMED_FILE $env(RENAMED_FILE)
set prompt {.*[>#] ?$}

proc fail_step {message code} {
    send_user "$message\n"
    exit $code
}

spawn telnet $IP

expect {
    -re {(P|p)assword:} { send -- "$PASS\r" }
    timeout { fail_step "Login timeout waiting for switch password prompt" 5 }
    eof { fail_step "Connection closed during login" 10 }
}

expect {
    -re {>} {}
    -re {#} {}
    timeout { fail_step "Login timeout waiting for switch prompt" 6 }
    eof { fail_step "Connection closed after login" 10 }
}

if {![string match *# $expect_out(buffer)]} {
    send -- "enable\r"
    expect {
        -re {(P|p)assword:} { send -- "$ENA\r" }
        timeout { fail_step "Enable timeout waiting for password prompt" 7 }
        eof { fail_step "Connection closed during enable" 10 }
    }

    expect {
        -re {#} {}
        timeout { fail_step "Enable timeout waiting for privileged prompt" 8 }
        eof { fail_step "Connection closed after enable" 10 }
    }
}

send -- "terminal length 0\r"
expect {
    -re $prompt {}
    timeout { fail_step "Paging disable timeout" 9 }
    eof { fail_step "Connection closed while disabling paging" 10 }
}

send -- "write memory\r"
expect {
    -re {(Building configuration|Compressed configuration|bytes copied|Copy complete|copied successfully)} { exp_continue }
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
    -re {%Error|TFTP put operation failed|Error opening tftp} {
        fail_step $expect_out(0,string) 12
    }
    -re $prompt {}
    timeout { fail_step "Backup copy timeout" 13 }
    eof { fail_step "Connection closed during backup copy" 10 }
}

send -- "exit\r"
expect eof
EOF
