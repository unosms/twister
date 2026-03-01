#!/usr/bin/env bash
# /etc/scripts/showintstatusnexus.sh
# Env: SWITCH_IP, SWITCH_USERNAME, SWITCH_PASS
# Example:
#   SWITCH_IP=192.168.200.244 SWITCH_USERNAME=admin SWITCH_PASS='pass' /etc/scripts/showintstatusnexus.sh
set -euo pipefail

: "${SWITCH_IP:?SWITCH_IP is required}"
: "${SWITCH_USERNAME:?SWITCH_USERNAME is required}"
: "${SWITCH_PASS:?SWITCH_PASS is required}"
: "${INTERFACE:?INTERFACE is required}"
#: "${DESCRIPTION:?DESCRIPTION is required}"

export IP="$SWITCH_IP"
export USERNAME="$SWITCH_USERNAME"
export PASSWORD="$SWITCH_PASS"

# Enable debug by running: DEBUG=1 /etc/scripts/showintstatusnexus.sh
/usr/bin/expect <<'EXP'
if {[info exists env(DEBUG)] && $env(DEBUG) == 1} { exp_internal 1 }
log_user 1
#set timeout 40

   set iface     $env(INTERFACE)
#   set desc      $env(DESCRIPTION)
# Common patterns
set prompt {[\r\n].*?[>#] ?$}

proc do_login {} {
    global prompt
    set authed 0
    # Loop until we reach a CLI prompt
    while {!$authed} {
        expect {
            -re {Press RETURN to get started} { send -- "\r" }
            -re {User Access Verification}    { send -- "\r" }
            -re {Escape character is}         { send -- "\r" }
            -re {Nexus .* Switch}             { }
            -re {Username:\s*$}               { send -- "$::env(USERNAME)\r" }
            -re {Login:\s*$}                  { send -- "$::env(USERNAME)\r" }
            -re {login:\s*$}                  { send -- "$::env(USERNAME)\r" }
            -re {Password:\s*$}               { send -- "$::env(PASSWORD)\r" }

            -re {Authentication failed|Authorization failed|Login invalid|Login incorrect|Invalid.*password} {
                puts "ERROR: Login failed"
                exit 4
            }

            -re $prompt { set authed 1 }
            timeout { send -- "\r" }
            eof     { return -code error "EOF during login" }
        }
    }
    return 0
}

# Try TELNET first
set use_ssh 0
catch {
    spawn telnet $::env(IP)
    set rc [do_login]
} resultTelnet

if {[string match "EOF during login" $resultTelnet] || [info exists errorCode]} {
    # Telnet died early — try SSH as a fallback
    set use_ssh 1
}

if {$use_ssh} {
    # SSH fallback (password auth). -oKexAlgorithms/+cipher switches can be added if needed.
    # If port is non-standard, add: -p <port>
    spawn ssh -o StrictHostKeyChecking=no -o PreferredAuthentications=password -l $::env(USERNAME) $::env(IP)
    # Accept new host keys automatically
    expect {
        -re {Are you sure you want to continue connecting} {
            send -- "yes\r"
            exp_continue
        }
        -re {password:\s*$} {
            send -- "$::env(PASSWORD)\r"
        }
        timeout { send_user "SSH timeout\n"; exit 5 }
    }
    # Now ensure we are at a prompt
    set authed 0
    while {!$authed} {
        expect {
            -re $prompt { set authed 1 }
            -re {Password:\s*$} { send -- "$::env(PASSWORD)\r" }
            timeout { send -- "\r" }
            eof     { puts "ERROR: SSH closed during login"; exit 6 }
        }
    }
}

# Disable paging and run the command
send -- "terminal length 0\r"
expect -re $prompt
expect "#"
#send -- "conf t\r"
#expect "#"
send -- "show interface $iface transceiver detail\r"
#expect "#"
#send -- "\r"
expect "#"
expect -re $prompt

send -- "exit\r"
expect eof
EXP
