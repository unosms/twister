#!/bin/bash
#
# === Config Variables ===
SWITCH_IP="192.168.200.197"
TELNET_PASSWORD="mega"
ENABLE_PASSWORD="WPA_()#@!"
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
send "show log\r"
expect "#"
send "\r"
send "\r"
send "\r"
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
