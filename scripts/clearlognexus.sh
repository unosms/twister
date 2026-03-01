#!/usr/bin/env bash
# /etc/scripts/clearlognexus.sh
# Env: SWITCH_IP, SWITCH_USERNAME, SWITCH_PASS
# Example:
#   SWITCH_IP=192.168.200.244 SWITCH_USERNAME=admin SWITCH_PASS='pass' /etc/scripts/clearlognexus.sh

set -euo pipefail
: "${SWITCH_IP:?SWITCH_IP is required}"
: "${SWITCH_USERNAME:?SWITCH_USERNAME is required}"
: "${SWITCH_PASS:?SWITCH_PASS is required}"

expect <<'EXP'
    # Enable with DEBUG=1 /etc/scripts/clearlognexus.sh
    if {[info exists env(DEBUG)] && $env(DEBUG) == 1} { exp_internal 1 }

    log_user 1
    set timeout 90

    set ip        $env(SWITCH_IP)
    set username  $env(SWITCH_USERNAME)
    set password  $env(SWITCH_PASS)

    spawn telnet $ip

    # Universal login prologue – handles both variants
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

    # No paging
    send -- "terminal length 0\r"
    expect -re $prompt

    # Try NX-OS canonical first
    proc do_clear {} {
        # handle the usual confirms
        expect {
            -re {\[confirm\]}                      { send -- "\r"; exp_continue }
            -re {\(y/n\)\s*\[?[yn]\]?}             { send -- "y\r"; exp_continue }
            -re {Proceed.*\(y/n\)}                 { send -- "y\r"; exp_continue }
            -re {Are you sure.*\(y/n\)}            { send -- "y\r"; exp_continue }
            -re {Are you sure.*\(yes/no\)}         { send -- "yes\r"; exp_continue }
            -re {[\r\n].*?[>#] ?$}                 { return }   ;# back at prompt = done
            timeout                                { send -- "\r"; exp_continue }
        }
    }

    # 1) clear logging logfile  (NX-OS)
    send -- "clear logging logfile\r"
    expect {
        -re {Unknown command|Unrecognized|Invalid|% Invalid} {
            # 2) Fallback: classic
            send -- "clear logging\r"
            do_clear
        }
        default {
            do_clear
        }
    }

    # Exit
    send -- "exit\r"
    expect eof
EXP
