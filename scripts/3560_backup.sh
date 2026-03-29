#!/usr/bin/env bash
set -euo pipefail

IP="${1:-}"
PASS="${2:-}"
ENA="${3:-}"
LOCATION="${4:-}"
USER="${5:-${SWITCH_USER:-${USERNAME:-}}}"
BACKUP_FILENAME="${6:-}"
LOCAL_BACKUP_TARGET="${7:-}"
TFTP_SERVER="${BACKUP_TFTP_SERVER:-192.168.88.57}"

DATE_STR=$(date +"%F_%H-%M-%S")
RENAMED_FILE="${DATE_STR}.txt"
if [[ -n "$BACKUP_FILENAME" ]]; then
    RENAMED_FILE="$(basename "$BACKUP_FILENAME")"
fi

if [[ -z "$IP" || -z "$PASS" || -z "$ENA" || -z "$LOCATION" ]]; then
    echo "Usage: $0 <SWITCH_IP> <PASSWORD> <ENABLE_PASSWORD> <LOCATION> [USERNAME] [BACKUP_FILENAME] [LOCAL_BACKUP_TARGET]"
    exit 1
fi

export IP PASS ENA LOCATION USER TFTP_SERVER RENAMED_FILE LOCAL_BACKUP_TARGET

/usr/bin/expect <<'EOF'
log_user 1
set timeout 40

set IP $env(IP)
set PASS $env(PASS)
set ENA $env(ENA)
set LOCATION $env(LOCATION)
set USER ""
if {[info exists env(USER)]} { set USER $env(USER) }
set TFTP_SERVER $env(TFTP_SERVER)
set RENAMED_FILE $env(RENAMED_FILE)
set LOCAL_BACKUP_TARGET ""
if {[info exists env(LOCAL_BACKUP_TARGET)]} { set LOCAL_BACKUP_TARGET $env(LOCAL_BACKUP_TARGET) }
set prompt {.*[>#] ?$}

proc fail_step {message code} {
    send_user "$message\n"
    exit $code
}

spawn telnet $IP

set authed 0
while {!$authed} {
    expect {
        -re {Press RETURN to get started} { send -- "\r" }
        -re {(U|u)sername:} {
            if {$USER ne ""} {
                send -- "$USER\r"
            } else {
                send -- "\r"
            }
            exp_continue
        }
        -re {(L|l)ogin:} {
            if {$USER ne ""} {
                send -- "$USER\r"
            } else {
                send -- "\r"
            }
            exp_continue
        }
        -re {(P|p)assword:} {
            send -- "$PASS\r"
            exp_continue
        }
        -re $prompt { set authed 1 }
        timeout { fail_step "Login timeout" 5 }
        eof { fail_step "Connection closed during login" 10 }
    }
}

set priv 0
if {[string match *# $expect_out(buffer)]} {
    set priv 1
}

if {!$priv} {
    send -- "enable\r"
    set sawEnablePasswordPrompt 0
    expect {
        -re {(P|p)assword:} {
            set sawEnablePasswordPrompt 1
            send -- "$ENA\r"
            exp_continue
        }
        -re {#} { set priv 1 }
        -re {>} {
            if {$sawEnablePasswordPrompt} {
                fail_step "Enable failed: invalid enable password or insufficient privilege." 7
            }
            fail_step "Enable failed: privileged prompt not reached." 7
        }
        -re {(%\s*Bad secrets|invalid enable password|insufficient privilege)} {
            fail_step "Enable failed: invalid enable password or insufficient privilege." 7
        }
        timeout { fail_step "Enable timeout" 7 }
        eof { fail_step "Connection closed during enable" 10 }
    }
}

if {!$priv} {
    fail_step "Enable failed: privileged prompt not reached." 7
}

send -- "terminal length 0\r"
expect {
    -re $prompt {}
    timeout { fail_step "Paging disable timeout" 8 }
    eof { fail_step "Connection closed while disabling paging" 10 }
}

send -- "write memory\r"
expect {
    -re $prompt {}
    timeout { fail_step "Write memory timeout" 11 }
    eof { fail_step "Connection closed during write memory" 10 }
}

proc run_tftp_copy {prompt tftpServer destination} {
    send -- "copy running-config tftp:\r"
    set sawSuccess 0
    set sawDestination 0
    set lastError ""

    while {1} {
        expect {
            -re {(A|a)ddress or name of remote host[^\r\n]*} {
                send -- "$tftpServer\r"
                exp_continue
            }
            -re {(S|s)ource filename[^\r\n]*} {
                send -- "\r"
                exp_continue
            }
            -re {(D|d)estination filename[^\r\n]*} {
                set sawDestination 1
                send -- "$destination\r"
                exp_continue
            }
            -re {(O|o)verwrite[^\r\n]*} {
                send -- "y\r"
                exp_continue
            }
            -re {(bytes copied|copied in|Copy complete|copied successfully)} {
                set sawSuccess 1
                exp_continue
            }
            -re {(%\s*(Error|Invalid)[^\r\n]*|TFTP put operation failed[^\r\n]*|Error opening tftp[^\r\n]*)} {
                set lastError [string trim $expect_out(0,string)]
                exp_continue
            }
            -re $prompt {
                if {$lastError ne ""} {
                    return [list 0 $lastError]
                }

                if {$sawSuccess} {
                    return [list 1 ""]
                }

                # Some IOS variants return directly to prompt without "bytes copied";
                # let Laravel artifact verification decide final success.
                if {$sawDestination} {
                    return [list 1 ""]
                }

                return [list 0 "Backup copy returned to prompt without success confirmation"]
            }
            timeout {
                return [list 0 "Backup copy timeout"]
            }
            eof {
                return [list 0 "Connection closed during backup copy"]
            }
        }
    }
}

proc capture_local_backup {prompt localTargetPath} {
    if {$localTargetPath eq ""} {
        return [list 0 "Direct backup target path is missing"]
    }

    set localDirectory [file dirname $localTargetPath]
    if {$localDirectory eq "" || $localDirectory eq "."} {
        return [list 0 "Direct backup target directory is invalid"]
    }

    if {[catch {file mkdir $localDirectory} errorMessage]} {
        return [list 0 "Failed to create direct backup directory: $errorMessage"]
    }

    if {[file exists $localTargetPath]} {
        catch {file delete -force $localTargetPath}
    }

    set previousTimeout $::timeout
    set ::timeout 180
    catch {log_file}

    if {[catch {log_file -noappend $localTargetPath} errorMessage]} {
        set ::timeout $previousTimeout
        return [list 0 "Failed to open direct backup file: $errorMessage"]
    }

    send -- "show running-config\r"
    set captureOk 0
    set captureError ""
    expect {
        -re $prompt { set captureOk 1 }
        timeout { set captureError "Timed out while capturing running-config" }
        eof { set captureError "Connection closed while capturing running-config" }
    }

    catch {log_file}
    set ::timeout $previousTimeout

    if {!$captureOk} {
        catch {file delete -force $localTargetPath}
        return [list 0 $captureError]
    }

    if {![file exists $localTargetPath]} {
        return [list 0 "Direct backup file was not created"]
    }

    set capturedSize 0
    if {[catch {set capturedSize [file size $localTargetPath]} errorMessage]} {
        return [list 0 "Failed to inspect direct backup file size: $errorMessage"]
    }

    if {$capturedSize < 128} {
        return [list 0 "Direct backup file is too small ($capturedSize bytes)"]
    }

    return [list 1 ""]
}

set primaryDestination "$LOCATION/$RENAMED_FILE"
set primaryResult [run_tftp_copy $prompt $TFTP_SERVER $primaryDestination]
if {[lindex $primaryResult 0] != 1} {
    set primaryError [string trim [lindex $primaryResult 1]]
    send_user "Primary destination failed: $primaryError\n"
    if {$LOCAL_BACKUP_TARGET ne ""} {
        send_user "TFTP upload failed; attempting direct CLI capture to local file.\n"
        set localResult [capture_local_backup $prompt $LOCAL_BACKUP_TARGET]
        if {[lindex $localResult 0] != 1} {
            set localError [string trim [lindex $localResult 1]]
            fail_step "$primaryError | Direct fallback failed: $localError" 12
        }
        send_user "Direct CLI capture succeeded: $LOCAL_BACKUP_TARGET\n"
    } else {
        fail_step $primaryError 12
    }
}

send -- "exit\r"
expect eof
EOF
