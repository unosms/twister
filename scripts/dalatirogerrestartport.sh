#!/bin/bash
#
# === Config Variables ===
SWITCH_IP="192.168.200.254"
TELNET_PASSWORD="mega"
ENABLE_PASSWORD="andromeda_#@!"
TFTP_SERVER="192.168.88.110"
FILE_NAME="config.text"
#DATE_STR=$(date +%F)
DATE_STR=$(date +"%F_%H-%M-%S")
RENAMED_FILE="${DATE_STR}"

# === Spawn Telnet and Automate ===
expect <<EOF
spawn telnet $SWITCH_IP
expect "Password:"
send "$TELNET_PASSWORD\r"

expect ">"
send "enable\r"
expect "Password:"
send "$ENABLE_PASSWORD\r"

expect "#"
#send "write memory\r"
expect "#"
#send "copy startup-config tftp:\r"

#send "copy flash:config.text tftp://192.168.88.110/rabih/$RENAMED_FILE\r"

#expect "Address or name of remote host"
#send "$TFTP_SERVER\r"
send " interface gigabitethernet 1/44 \r"
expect "#"
send "shut\r"
expect "#"
send "no shut\r"
send "\r"

expect "#"
send "exit\r"
EOF

# === Rename file on TFTP server (if local server) ===
# Only works if TFTP server is the local machine
#if [[ "$TFTP_SERVER" == "127.0.0.1" || "$TFTP_SERVER" == "localhost" || "$TFTP_SERVER" == "$(hostname -I | awk '{print $1}')" ]]; then
#send 
#    mv "/srv/tftpboot/$FILE_NAME" "/srv/tftpboot/$RENAMED_FILE"
#    echo "Configuration saved as $RENAMED_FILE"
#else
#    echo "Config uploaded to TFTP server. Rename it there to: $RENAMED_FILE"
#fi
