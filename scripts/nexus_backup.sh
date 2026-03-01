#!/bin/bash

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
    echo "Usage: $0 <SWITCH_IP> <PASSWORD> <LOCATION>"
    exit 1
fi

# === Spawn Telnet and Automate ===
expect <<EOF
spawn telnet $SWITCH_IP
expect "in:"
send "$USERNAME\r"

expect "Password:"
send "$PASSWORD\r"

expect "#"
#sleep 5
#expect "#"
send "copy running-config tftp://$TFTP_SERVER/$4/$RENAMED_FILE\r"

expect "Enter vrf (If no input, current vrf 'default' is considered):"
send "\r"
expect "Destination filename"
send "\r"

expect "#"
send "exit\r"
EOF
