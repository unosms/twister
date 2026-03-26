#!/usr/bin/env bash
set -euo pipefail

IP="${1:-}"
PASS="${2:-}"
ENA="${3:-}"
LOCATION="${4:-}"
TFTP_SERVER="${BACKUP_TFTP_SERVER:-192.168.88.57}"

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

proc run_tftp_copy {prompt tftpServer destination} {
    send -- "copy running-config tftp:\r"
    set sawSuccess 0

    while {1} {
        expect {
            -re {(A|a)ddress or name of remote host[^\r\n]*} {
                send -- "$tftpServer\r"
                exp_continue
            }
            -re {(S|s)ource filename[^\r\n]*} {
                send -- "\r"
                exp_continue
            }
            -re {(D|d)estination filename[^\r\n]*} {
                send -- "$destination\r"
                exp_continue
            }
            -re {(O|o)verwrite[^\r\n]*} {
                send -- "y\r"
                exp_continue
            }
            -re {(bytes copied|copied in|Copy complete|copied successfully)} {
                set sawSuccess 1
                exp_continue
            }
            -re {(%Error[^\r\n]*|TFTP put operation failed[^\r\n]*|Error opening tftp[^\r\n]*)} {
                return [list 0 [string trim $expect_out(0,string)]]
            }
            -re $prompt {
                if {$sawSuccess} {
                    return [list 1 ""]
                }

                return [list 0 "Backup copy returned to prompt without success confirmation"]
            }
            timeout {
                return [list 0 "Backup copy timeout"]
            }
            eof {
                return [list 0 "Connection closed during backup copy"]
            }
        }
    }
}

set primaryDestination "$LOCATION/$RENAMED_FILE"
set primaryResult [run_tftp_copy $prompt $TFTP_SERVER $primaryDestination]
if {[lindex $primaryResult 0] != 1} {
    set primaryError [string trim [lindex $primaryResult 1]]
    set primaryErrorLower [string tolower $primaryError]

    if {[string match "*permission denied*" $primaryErrorLower] || [string match "*no such file*" $primaryErrorLower] || [string match "*access violation*" $primaryErrorLower]} {
        send_user "Primary destination failed: $primaryError\n"
        send_user "Retrying using TFTP root destination: $RENAMED_FILE\n"

        set fallbackResult [run_tftp_copy $prompt $TFTP_SERVER $RENAMED_FILE]
        if {[lindex $fallbackResult 0] != 1} {
            fail_step [lindex $fallbackResult 1] 12
        }
    } else {
        fail_step $primaryError 12
    }
}

send -- "exit\r"
expect eof
EOF
