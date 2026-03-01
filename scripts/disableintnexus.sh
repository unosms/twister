#!/usr/bin/env bash
# /etc/scripts/disableintnexus.sh
# Env: SWITCH_IP, SWITCH_USERNAME, SWITCH_PASS, INTERFACE
set -euo pipefail
: "${SWITCH_IP:?SWITCH_IP is required}"
: "${SWITCH_USERNAME:?SWITCH_USERNAME is required}"
: "${SWITCH_PASS:?SWITCH_PASS is required}"
: "${INTERFACE:?INTERFACE is required}"

expect <<'EXP'
    if {[info exists env(DEBUG)] && $env(DEBUG) == 1} { exp_internal 1 }
    log_user 1
    set timeout 60

    set ip    $env(SWITCH_IP)
    set user  $env(SWITCH_USERNAME)
    set pass  $env(SWITCH_PASS)
    set iface $env(INTERFACE)

    spawn telnet $ip
    expect {
        -re {User Access Verification} { send -- "\r"; exp_continue }
        -re {Escape character is}      { send -- "\r"; exp_continue }
        -re {login:}                   {}
        -re {Username:}                {}
        -re {Password:}                {}
        timeout                        { send -- "\r"; exp_continue }
        eof                            { puts "ERROR: Connection closed before login."; exit 1 }
    }

    set authed 0
    while {!$authed} {
        expect {
            -re {login:}     { send -- "$user\r"; exp_continue }
            -re {Username:}  { send -- "$user\r"; exp_continue }
            -re {Password:}  { send -- "$pass\r"; exp_continue }
            -re {Login invalid|Login incorrect|Authentication failed} { puts "ERROR: Login failed."; exit 2 }
            -re {[\r\n].*[>#] *$} { set authed 1 }
            timeout { send -- "\r" }
            eof { puts "ERROR: Connection closed during login."; exit 1 }
        }
    }

    set prompt {[\r\n].*[>#] *$}

    send -- "terminal length 0\r"
    expect -re $prompt

    send -- "configure terminal\r"
    expect -re $prompt

    send -- "interface $iface\r"
    expect -re $prompt

    send -- "shutdown\r"
    expect -re $prompt

    send -- "end\r"
    expect -re $prompt

    send -- "exit\r"
    expect eof
EXP
