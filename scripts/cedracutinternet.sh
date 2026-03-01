#!/bin/bash

SWITCH_IP="192.168.88.170"
USERNAME="admin"
PASSWORD="andromeda_#@!"

# single-quoted heredoc keeps backslashes intact for Expect/Tcl
expect <<'EOF'
set timeout 15
log_user 1

# bash vars are not expanded in a single-quoted heredoc; hardcode or pass via -c if needed
spawn telnet 192.168.88.170
expect "Login:"           { send -- "admin\r" }
expect "Password:"        { send -- "andromeda_#@!\r" }

# MikroTik prompt looks like: [admin@HOST] >
# Use a regex and escape the brackets.
#expect -re {\[[^\]]+@[^\]]+\]\s*>\s*$}

expect "HOME] > \r"


#send -- "ip firewall filter enable \[find comment=\"cedra iphone enable/disable\"\]\r"
send "log print\r"
# Wait for prompt again (optional)
expect -re {\[[^\]]+@[^\]]+\]\s*>\s*$}

send -- "quit\r"
expect eof
EOF
