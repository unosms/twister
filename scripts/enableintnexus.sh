#!/usr/bin/env bash
# /etc/scripts/restartintnexus.sh
# Env: SWITCH_IP, SWITCH_USERNAME, SWITCH_PASS, INTERFACE
# Example:
#   SWITCH_IP=192.168.200.244 SWITCH_USERNAME=admin SWITCH_PASS='pass' INTERFACE='Eth1/1' /etc/scripts/restartintnexus.sh

set -euo pipefail
: "${SWITCH_IP:?SWITCH_IP is required}"
: "${SWITCH_USERNAME:?SWITCH_USERNAME is required}"
: "${SWITCH_PASS:?SWITCH_PASS is required}"
: "${INTERFACE:?INTERFACE is required}"

expect <<'EXP'
    # Enable internal debug: DEBUG=1 /etc/scripts/restartintnexus.sh
    if {[info exists env(DEBUG)] && $env(DEBUG) == 1} { exp_internal 1 }

    log_user 1
    set timeout 60

    # Read env
    set ip        $env(SWITCH_IP)
    set username  $env(SWITCH_USERNAME)
    set password  $env(SWITCH_PASS)
    set iface     $env(INTERFACE)

    # Connect (telnet). Say the word if you want SSH.
    spawn telnet $ip

    # --- UNIVERSAL LOGIN PROLOGUE ---
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

    # Credentials → wait for CLI prompt (> or #)
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

    # --- COMMANDS ---
    set prompt {[\r\n].*?[>#] ?$}

    # No paging
    send -- "terminal length 0\r"
    expect -re $prompt

    # Bounce the interface
    send -- "configure terminal\r"
    expect -re $prompt

    send -- "interface $iface\r"
    expect -re $prompt

    send -- "no shutdown\r"
    expect -re $prompt

    send -- "end\r"
    expect -re $prompt

    # Exit
    send -- "exit\r"
    expect eof
EXP
