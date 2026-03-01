#!/usr/bin/env bash
#set -euo pipefail


/usr/bin/expect <<'EOF'
set timeout 30
spawn telnet 192.168.200.244
expect "login:"
send "admin\r"
expect ":"
send "wavenet2024\r"
expect "#"

send "terminal length 0\r"
expect "#"

send "configure terminal\r"
expect "(config)#"
send "interface eth1/5\r"
send "no shutdown\r"
send "end\r"

send "exit\r"
expect eof
EOF

