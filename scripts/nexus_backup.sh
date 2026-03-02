#!/bin/bash

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

# === Spawn Telnet and Automate ===
expect <<EOF
log_user 1
send_user "[backup-step] opening Telnet session to ${SWITCH_IP}\n"
spawn telnet $SWITCH_IP
expect "in:"
send_user "[backup-step] login prompt received; sending username\n"
send "$USERNAME\r"

expect "Password:"
send_user "[backup-step] password prompt received; sending device password\n"
send "$PASSWORD\r"

expect "#"
send_user "[backup-step] privileged prompt received; requesting TFTP copy\n"
#sleep 5
#expect "#"
send "copy running-config tftp://$TFTP_SERVER/$LOCATION/$RENAMED_FILE\r"

expect "Enter vrf (If no input, current vrf 'default' is considered):"
send_user "[backup-step] VRF prompt received; accepting default VRF\n"
send "\r"
expect "Destination filename"
send_user "[backup-step] destination filename confirmation requested; accepting generated filename\n"
send "\r"

expect "#"
send_user "[backup-step] transfer finished; closing Telnet session\n"
send "exit\r"
EOF

log_step "backup script completed"
