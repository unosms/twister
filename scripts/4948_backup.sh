#!/bin/bash

set -euo pipefail

log_step() {
    printf '[backup-step] %s\n' "$1"
}

# === Get variables from script arguments ===
SWITCH_IP="$1"
PASSWORD="$2"
ENABLE_PASSWORD="$3"
LOCATION="$4"
# Fixed TFTP server
TFTP_SERVER="192.168.88.57"

# Date and file name
DATE_STR=$(date +"%F_%H-%M-%S")
RENAMED_FILE="${DATE_STR}.txt"

# Check if all args were provided
if [ -z "$SWITCH_IP" ] || [ -z "$PASSWORD" ] || [ -z "$ENABLE_PASSWORD" ] || [ -z "$LOCATION" ]; then
    echo "Usage: $0 <SWITCH_IP> <PASSWORD> <ENABLE_PASSWORD> <LOCATION>"
    exit 1
fi

log_step "argument validation passed for switch ${SWITCH_IP}"
log_step "resolved backup destination tftp://${TFTP_SERVER}/${LOCATION}/${RENAMED_FILE}"
log_step "starting Telnet automation for Catalyst backup"

export SWITCH_IP PASSWORD ENABLE_PASSWORD LOCATION TFTP_SERVER RENAMED_FILE

# === Spawn Telnet and Automate ===
expect <<'EOF'
log_user 1
set timeout 30

set switch_ip $env(SWITCH_IP)
set password $env(PASSWORD)
set enable_password $env(ENABLE_PASSWORD)
set location $env(LOCATION)
set tftp_server $env(TFTP_SERVER)
set renamed_file $env(RENAMED_FILE)
set step_prefix {[backup-step]}

proc fail_step {message code} {
    global step_prefix
    send_user "$step_prefix $message\n"
    exit $code
}

send_user "$step_prefix opening Telnet session to $switch_ip\n"
spawn telnet $switch_ip
expect {
    -re {(?i)password:\s*} {}
    -re {(?i)(user access verification|escape character is.*)} {
        exp_continue
    }
    -re {(?i)(refused|unreachable|unknown host|no route to host|closed by foreign host)} {
        fail_step "telnet session failed before switch password prompt" 10
    }
    timeout { fail_step "timed out waiting for switch password prompt" 11 }
    eof { fail_step "telnet session closed before switch password prompt" 12 }
}
send_user "$step_prefix password prompt received; sending device password\n"
send -- "$password\r"

set login_prompt ""
expect {
    -re {> ?$} { set login_prompt ">" }
    -re {# ?$} { set login_prompt "#" }
    -re {(?i)(user access verification|escape character is.*|last login|warning.*|notice.*)} {
        exp_continue
    }
    -re {(?i)(login|username)[: ]*$} {
        fail_step "switch returned to a login prompt after device password submission" 13
    }
    -re {(?i)(password:\s*|authentication failed|login invalid|denied|failed)} {
        fail_step "switch rejected the device password" 13
    }
    timeout { fail_step "timed out waiting for switch prompt after password login" 14 }
    eof { fail_step "telnet session closed after password login" 14 }
}

if {$login_prompt eq ">"} {
    send_user "$step_prefix user exec prompt received; entering enable mode\n"
    send -- "enable\r"
    expect {
        -re {(?i)password:\s*} {}
        -re {# ?$} {
            send_user "$step_prefix privileged prompt received without an enable password challenge\n"
            set login_prompt "#"
        }
        -re {(?i)(warning.*|notice.*)} {
            exp_continue
        }
        timeout { fail_step "timed out waiting for enable password prompt" 15 }
        eof { fail_step "telnet session closed before enable password prompt" 16 }
    }
}

if {$login_prompt ne "#"} {
    send_user "$step_prefix enable prompt received; sending enable password\n"
    send -- "$enable_password\r"

    expect {
        -re {# ?$} {}
        -re {(?i)(warning.*|notice.*|last login)} {
            exp_continue
        }
        -re {(?i)(password:\s*|authentication failed|denied|failed)} {
            fail_step "switch rejected the enable password" 17
        }
        timeout { fail_step "timed out waiting for privileged prompt after enable login" 18 }
        eof { fail_step "telnet session closed after enable login" 19 }
    }
}

send_user "$step_prefix privileged prompt received; saving running configuration\n"
send -- "write memory\r"
sleep 5
expect {
    -re {# ?$} {}
    -re {(?i)(error|failed|denied|invalid input)} {
        fail_step "write memory failed before TFTP copy" 20
    }
    timeout { fail_step "timed out waiting for prompt after write memory" 21 }
    eof { fail_step "telnet session closed after write memory" 22 }
}
send_user "$step_prefix requesting TFTP copy to $location/$renamed_file\n"
send -- "copy running-config tftp://$tftp_server/$location/$renamed_file\r"

expect {
    -re {(?i)address or name of remote host.*} {
        send_user "$step_prefix remote host confirmation requested; accepting configured TFTP server\n"
        send -- "\r"
        exp_continue
    }
    -re {(?i)destination filename.*} {
        send_user "$step_prefix destination filename confirmation requested; accepting generated filename\n"
        send -- "\r"
        exp_continue
    }
    -re {(?i)overwrite.*} {
        send_user "$step_prefix overwrite confirmation requested; accepting generated filename\n"
        send -- "\r"
        exp_continue
    }
    -re {(?i)(bytes copied|copied in|copy complete|transferred successfully)} {
        send_user "$step_prefix switch reported successful TFTP copy\n"
        exp_continue
    }
    -re {(?i)(error|timed out|permission denied|unreachable|refused|failed|no such file|cannot access)} {
        fail_step "switch reported a Catalyst TFTP copy failure" 23
    }
    -re {# ?$} {}
    timeout { fail_step "timed out waiting for privileged prompt after TFTP copy request" 24 }
    eof { fail_step "telnet session closed before TFTP copy completed" 25 }
}
send_user "$step_prefix transfer finished; closing Telnet session\n"
send -- "exit\r"
EOF

log_step "backup script completed"
