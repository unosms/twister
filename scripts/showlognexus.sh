#!/usr/bin/env bash
# /etc/scripts/showlognexus.sh
# Env: SWITCH_IP, SWITCH_USERNAME, SWITCH_PASS
set -euo pipefail
: "${SWITCH_IP:?SWITCH_IP is required}"
: "${SWITCH_USERNAME:?SWITCH_USERNAME is required}"
: "${SWITCH_PASS:?SWITCH_PASS is required}"

expect <<'EXP'
    # Enable with: DEBUG=1 /etc/scripts/showlognexus.sh
    if {[info exists env(DEBUG)] && $env(DEBUG) == 1} { exp_internal 1 }

    log_user 1
    set timeout 60

    # Read env vars
    set ip        $env(SWITCH_IP)
    set username  $env(SWITCH_USERNAME)
    set password  $env(SWITCH_PASS)
    set iface     ""
    if {[info exists env(INTERFACE)]} { set iface [string trim $env(INTERFACE)] }

    # Connect via telnet (tell me if you prefer SSH)
    spawn telnet $ip

    # --- UNIVERSAL LOGIN PROLOGUE (handles both variants) ---
    # Eat banners until we see a login/username/password prompt.
    expect {
        -re {User Access Verification} { send -- "\r"; exp_continue }
        -re {Escape character is}      { send -- "\r"; exp_continue }
        -re {Nexus .* Switch}          {            exp_continue }
        -re {Press RETURN to get started} { send -- "\r"; exp_continue }
        -re {login:\s*$}               { }
        -re {Login:\s*$}               { }
        -re {Username:\s*$}            { }
        -re {Password:\s*$}            { }
        timeout                        { send -- "\r"; exp_continue }
        eof                            { puts "ERROR: Connection closed before login."; exit 1 }
    }

    # Send credentials until we reach a CLI prompt (> or #)
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

            -re {[\r\n].*?[>#] ?$}  { set authed 1 }   ;# got prompt
            timeout                 { send -- "\r" }   ;# nudge device
            eof                     { puts "ERROR: Connection closed during login."; exit 1 }
        }
    }

    # --- COMMANDS ---
    set prompt {[\r\n].*?[>#] ?$}

    send -- "terminal length 0\r"
    expect -re $prompt

    if {$iface ne ""} {
        send -- "show logging | include $iface\r"
    } else {
        send -- "show logging\r"
    }
    expect -re $prompt

    send -- "exit\r"
    expect eof
EXP
