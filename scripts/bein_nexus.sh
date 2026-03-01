#!/bin/bash
#
# === Config Variables ===
SWITCH_IP="192.168.200.246"
TELNET_PASSWORD="admin"
ENABLE_PASSWORD="wavenet2024"
TFTP_SERVER="192.168.88.57"
FILE_NAME="config.text"
#DATE_STR=$(date +%F)
DATE_STR=$(date +"%F_%H-%M-%S")
RENAMED_FILE="${DATE_STR}"

# === Spawn Telnet and Automate ===
expect <<EOF
spawn telnet $SWITCH_IP
expect "login:"
send "$TELNET_PASSWORD\r"
expect "Password:"
send "$ENABLE_PASSWORD\r"
#expect ">"
#send "enable\r"
#expect "Password:"
#send "$ENABLE_PASSWORD\r"

expect "#"
send "copy running-config startup-config\r"
expect "#"
#send "copy startup-config tftp:\r"

#send "copy flash:config.text tftp://192.168.88.110/rabih/$RENAMED_FILE\r"
send "copy running-config tftp://192.168.88.110/$RENAMED_FILE\r"
expect "Enter vrf (If no input, current vrf 'default' is considered):"
#send "$TFTP_SERVER\r"
send "\r"
#expect "Destination filename"
#send "$FILE_NAME\r"
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
