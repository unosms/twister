#!/bin/bash

log_step() {
    printf '[backup-step] %s\n' "$1"
}

# === Get variables from script arguments ===
SWITCH_IP="$1"
PASSWORD="$2"
ENABLE_PASSWORD="$3"
LOCATION="$4"
# Fixed TFTP server
TFTP_SERVER="172.16.203.247"

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

# === Spawn Telnet and Automate ===
expect <<EOF
log_user 1
send_user "[backup-step] opening Telnet session to ${SWITCH_IP}\n"
spawn telnet $SWITCH_IP
expect "Password:"
send_user "[backup-step] password prompt received; sending device password\n"
send "$PASSWORD\r"

expect ">"
send_user "[backup-step] user exec prompt received; entering enable mode\n"
send "enable\r"
expect "Password:"
send_user "[backup-step] enable prompt received; sending enable password\n"
send "$ENABLE_PASSWORD\r"

expect "#"
send_user "[backup-step] privileged prompt received; saving running configuration\n"
send "write memory\r"
sleep 5
expect "#"
send_user "[backup-step] requesting TFTP copy to ${LOCATION}/${RENAMED_FILE}\n"
send "copy running-config tftp://$TFTP_SERVER/$LOCATION/$RENAMED_FILE\r"

expect "Address or name of remote host"
send_user "[backup-step] remote host confirmation requested; accepting configured TFTP server\n"
send "\r"
expect "Destination filename"
send_user "[backup-step] destination filename confirmation requested; accepting generated filename\n"
send "\r"

expect "#"
send_user "[backup-step] transfer finished; closing Telnet session\n"
send "exit\r"
EOF

log_step "backup script completed"
