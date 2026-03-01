#!/usr/bin/env bash
set -euo pipefail


/usr/bin/expect <<'EOF'
set timeout 30
spawn telnet 192.168.200.251
expect "Password:"
send "mega\r"
expect ">"
send "enable\r"
expect "Password:"
send "WPA_()#@!\r"
expect "#"

send "terminal length 0\r"
expect "#"

send "configure terminal\r"
expect "(config)#"
send "interface gi1/23\r"
expect "(config-if)#"
send "no shutdown\r"
send "end\r"

send "exit\r"
expect eof
EOF

sh /etc/scripts/disablehusseinghostport5.sh
