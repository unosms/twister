#!/bin/bash

set -euo pipefail

log_step() {
    printf '[backup-step] %s\n' "$1"
}

# === Get variables from script arguments ===
SWITCH_IP="$1"
USERNAME="$2"
PASSWORD="$3"
LOCATION="$4"
# Fixed TFTP server
TFTP_SERVER="192.168.88.57"

# Date and file name
DATE_STR=$(date +"%F_%H-%M-%S")
RENAMED_FILE="${DATE_STR}.txt"

# Check if all args were provided
if [ -z "$SWITCH_IP" ] || [ -z "$USERNAME" ] || [ -z "$PASSWORD" ] || [ -z "$LOCATION" ]; then
    echo "Usage: $0 <SWITCH_IP> <USERNAME> <PASSWORD> <LOCATION>"
    exit 1
fi

log_step "argument validation passed for Nexus switch ${SWITCH_IP}"
log_step "resolved backup destination tftp://${TFTP_SERVER}/${LOCATION}/${RENAMED_FILE}"
log_step "starting Telnet automation for Nexus backup"

export SWITCH_IP USERNAME PASSWORD LOCATION TFTP_SERVER RENAMED_FILE

# === Spawn Telnet and Automate ===
expect <<'EOF'
log_user 1
set timeout 30

set switch_ip $env(SWITCH_IP)
set username $env(USERNAME)
set password $env(PASSWORD)
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
    -nocase -re {(login|username)[: ]*$} {}
    -nocase -re {(user access verification|escape character is.*|nexus [0-9]+ switch)} {
        exp_continue
    }
    -nocase -re {(refused|unreachable|unknown host|no route to host|closed by foreign host)} {
        fail_step "telnet session failed before login prompt" 30
    }
    timeout { fail_step "timed out waiting for login prompt" 31 }
    eof { fail_step "telnet session closed before login prompt" 32 }
}
send_user "$step_prefix login prompt received; sending username\n"
send -- "$username\r"

expect {
    -nocase -re {password:\s*} {}
    -nocase -re {(user access verification|escape character is.*|nexus [0-9]+ switch)} {
        exp_continue
    }
    -nocase -re {(login|username)[: ]*$} {
        fail_step "switch returned to the Nexus login prompt after username submission" 33
    }
    -nocase -re {(login invalid|login incorrect|authentication failed|denied|failed)} {
        fail_step "switch rejected the Nexus username" 33
    }
    timeout { fail_step "timed out waiting for password prompt after username" 33 }
    eof { fail_step "telnet session closed before password prompt" 34 }
}
send_user "$step_prefix password prompt received; sending device password\n"
send -- "$password\r"

set prompt ""
expect {
    -re {# ?$} { set prompt "#" }
    -re {> ?$} { set prompt ">" }
    -nocase -re {(last login|user access verification|warning.*|notice.*|nexus [0-9]+ switch)} {
        exp_continue
    }
    -nocase -re {(login|username)[: ]*$} {
        fail_step "switch returned to the Nexus login prompt after password submission" 35
    }
    -nocase -re {password:\s*} {
        fail_step "switch rejected the Nexus password" 35
    }
    -nocase -re {(login invalid|login incorrect|authentication failed|denied|failed)} {
        fail_step "switch rejected the Nexus password" 35
    }
    timeout { fail_step "timed out waiting for Nexus prompt after password login" 36 }
    eof { fail_step "telnet session closed after password login" 36 }
}

if {$prompt ne "#"} {
    fail_step "login completed without a privileged Nexus prompt" 37
}

send_user "$step_prefix privileged prompt received; requesting TFTP copy\n"
send -- "terminal length 0\r"
expect {
    -re {# ?$} {}
    -nocase -re {(invalid command|permission denied|denied|failed)} {
        fail_step "terminal length command was rejected by the switch" 38
    }
    timeout { fail_step "timed out waiting for prompt after terminal length command" 39 }
    eof { fail_step "telnet session closed after terminal length command" 40 }
}
send_user "$step_prefix requesting TFTP copy to $location/$renamed_file\n"
send -- "copy running-config tftp://$tftp_server/$location/$renamed_file\r"

expect {
    -nocase -re {enter vrf .*} {
        send_user "$step_prefix VRF prompt received; accepting default VRF\n"
        send -- "\r"
        exp_continue
    }
    -nocase -re {destination filename.*} {
        send_user "$step_prefix destination filename confirmation requested; accepting generated filename\n"
        send -- "\r"
        exp_continue
    }
    -nocase -re {(copy complete|bytes copied|transferred successfully)} {
        send_user "$step_prefix switch reported successful TFTP copy\n"
        exp_continue
    }
    -nocase -re {(error|timed out|permission denied|unreachable|refused|failed|no such file|cannot access)} {
        fail_step "switch reported a Nexus TFTP copy failure" 41
    }
    -re {# ?$} {}
    timeout { fail_step "timed out waiting for Nexus prompt after TFTP copy request" 42 }
    eof { fail_step "telnet session closed before TFTP copy completed" 43 }
}
send_user "$step_prefix transfer finished; closing Telnet session\n"
send -- "exit\r"
EOF

log_step "backup script completed"
