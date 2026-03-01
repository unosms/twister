#!/bin/bash

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
if [ -z "$SWITCH_IP" ] || [ -z "$PASSWORD" ] || [ -z "$ENABLE_PASSWORD" ]; then
    echo "Usage: $0 <SWITCH_IP> <PASSWORD> <ENABLE_PASSWORD>"
    exit 1
fi

# === Spawn Telnet and Automate ===
expect <<EOF
spawn telnet $SWITCH_IP
expect "Password:"
send "$PASSWORD\r"

expect ">"
send "enable\r"
expect "Password:"
send "$ENABLE_PASSWORD\r"

expect "#"
send "write memory\r"
sleep 5
expect "#"
send "copy running-config tftp://$TFTP_SERVER/$4/$RENAMED_FILE\r"

expect "Address or name of remote host"
send "\r"
expect "Destination filename"
send "\r"

expect "#"
send "exit\r"
EOF
