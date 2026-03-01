#!/usr/bin/env bash
# /etc/scripts/shrunexus.sh
# Env: SWITCH_IP, SWITCH_USERNAME, SWITCH_PASS
# Example:
#   SWITCH_IP=192.168.200.244 SWITCH_USERNAME=admin SWITCH_PASS='pass' /etc/scripts/shrunexus.sh

set -euo pipefail
: "${SWITCH_IP:?SWITCH_IP is required}"
: "${SWITCH_USERNAME:?SWITCH_USERNAME is required}"
: "${SWITCH_PASS:?SWITCH_PASS is required}"

expect <<'EXP'
    if {[info exists env(DEBUG)] && $env(DEBUG) == 1} { exp_internal 1 }
    log_user 1
    set timeout 90

    set ip        $env(SWITCH_IP)
    set username  $env(SWITCH_USERNAME)
    set password  $env(SWITCH_PASS)

    spawn telnet $ip

    # Universal login (handles both variants)
    expect {
        -re {User Access Verification}     { send -- "\r"; exp_continue }
        -re {Escape character is}          { send -- "\r"; exp_continue }
        -re {Nexus .* Switch}              {                exp_continue }
        -re {Press RETURN to get started}  { send -- "\r"; exp_continue }
        -re {login:\s*$}                   { }
        -re {Login:\s*$}                   { }
        -re {Username:\s*$}                { }
        -re {Password:\s*$}                { }
        timeout                            { send -- "\r"; exp_continue }
        eof                                { puts "ERROR: Connection closed before login."; exit 1 }
    }

    set authed 0
    while {!$authed} {
        expect {
            -re {login:\s*$}        { send -- "$username\r"; exp_continue }
            -re {Login:\s*$}        { send -- "$username\r"; exp_continue }
            -re {Username:\s*$}     { send -- "$username\r"; exp_continue }
            -re {Password:\s*$}     { send -- "$password\r"; exp_continue }
            -re {Login invalid|Login incorrect|Authentication failed|Invalid.*password|Authorization failed} {
                puts "ERROR: Login failed (device rejected credentials)."
                exit 2
            }
            -re {[\r\n].*?[>#] ?$}  { set authed 1 }
            timeout                 { send -- "\r" }
            eof                     { puts "ERROR: Connection closed during login."; exit 1 }
        }
    }

    set prompt {[\r\n].*?[>#] ?$}

    # No paging, then show run
    send -- "terminal length 0\r"
    expect -re $prompt

    # NX-OS canonical is 'show running-config' (alias of running-configuration)
    send -- "show running-config\r"
    expect -re $prompt

    send -- "exit\r"
    expect eof
EXP
