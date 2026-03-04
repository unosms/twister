#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   ./olt_backup.sh <OLT_IP> <USERNAME> <PASSWORD> <DEST_DIR> [TFTP_SERVER]

OLT_IP="${1:-}"
OLT_USER="${2:-}"
OLT_PASS="${3:-}"
DEST_DIR="${4:-}"
TFTP_SERVER="${5:-${BACKUP_TFTP_SERVER:-${TFTP_SERVER:-192.168.88.57}}}"

TELNET_TIMEOUT="${OLT_TELNET_TIMEOUT:-80}"
WAIT_FOR_FILE_SECONDS="${OLT_BACKUP_WAIT_SECONDS:-90}"

log_step() {
    printf '[backup-step] %s\n' "$1"
}

fail_step() {
    printf '[backup-step] %s\n' "$1" >&2
    exit "${2:-1}"
}

if [[ -z "$OLT_IP" || -z "$OLT_USER" || -z "$OLT_PASS" || -z "$DEST_DIR" ]]; then
    fail_step "usage: $0 <OLT_IP> <USERNAME> <PASSWORD> <DEST_DIR> [TFTP_SERVER]" 1
fi

if [[ ! -d "$DEST_DIR" ]]; then
    fail_step "destination directory does not exist: $DEST_DIR" 2
fi

if ! command -v expect >/dev/null 2>&1; then
    fail_step "expect is required but not installed" 3
fi

if ! command -v telnet >/dev/null 2>&1; then
    fail_step "telnet is required but not installed" 4
fi

TFTP_ROOT=""
if [[ -n "${BACKUP_ROOT:-}" && -d "${BACKUP_ROOT:-}" ]]; then
    TFTP_ROOT="$BACKUP_ROOT"
else
    for candidate in /srv/tftp /srv/tftpboot /var/lib/tftpboot /var/tftpboot /tftpboot; do
        if [[ -d "$candidate" ]]; then
            TFTP_ROOT="$candidate"
            break
        fi
    done
fi

if [[ -z "$TFTP_ROOT" ]]; then
    fail_step "unable to resolve TFTP root directory" 5
fi

DATE_STR="$(date +%F_%H-%M-%S)"
FILENAME="${DATE_STR}.txt"
SRC_PATH="$TFTP_ROOT/$FILENAME"
DEST_PATH="$DEST_DIR/$FILENAME"

log_step "argument validation passed for OLT $OLT_IP"
log_step "resolved backup destination tftp://$TFTP_SERVER/$FILENAME"
log_step "tftp root resolved as $TFTP_ROOT"
log_step "final destination path $DEST_PATH"

export OLT_IP OLT_USER OLT_PASS TFTP_SERVER TELNET_TIMEOUT FILENAME

/usr/bin/expect <<'EOF'
log_user 1
set timeout $env(TELNET_TIMEOUT)

set OLT_IP $env(OLT_IP)
set OLT_USER $env(OLT_USER)
set OLT_PASS $env(OLT_PASS)
set TFTP_SERVER $env(TFTP_SERVER)
set FILENAME $env(FILENAME)
set prompt {.*[>#\]] ?$}

proc fail_step {message code} {
    send_user "[backup-step] $message\n"
    exit $code
}

send_user "[backup-step] opening Telnet session to $OLT_IP\n"
spawn telnet $OLT_IP

set logged 0
while {!$logged} {
    expect {
        -re {(?i)(user ?name|login)\s*:?\s*$} {
            send_user "[backup-step] login prompt received; sending username\n"
            send -- "$OLT_USER\r"
            exp_continue
        }
        -re {(?i)password\s*:?\s*$} {
            send_user "[backup-step] password prompt received; sending password\n"
            send -- "$OLT_PASS\r"
            exp_continue
        }
        -re {(?i)press (return|enter) to continue} {
            send -- "\r"
            exp_continue
        }
        -re $prompt {
            set logged 1
        }
        timeout { fail_step "timed out waiting for OLT login prompt" 10 }
        eof { fail_step "connection closed during OLT login" 11 }
    }
}

send_user "[backup-step] login complete; starting backup command\n"
send -- "backup configuration tftp $TFTP_SERVER $FILENAME\r"

set finished 0
set loop_guard 0
while {!$finished && $loop_guard < 120} {
    incr loop_guard
    expect {
        -re {(?i)address or name of remote host.*} {
            send -- "$TFTP_SERVER\r"
            exp_continue
        }
        -re {(?i)destination filename.*} {
            send -- "$FILENAME\r"
            exp_continue
        }
        -re {(?i)source filename.*} {
            send -- "\r"
            exp_continue
        }
        -re {(?i)vrf.*} {
            send -- "\r"
            exp_continue
        }
        -re {(?i)(overwrite|replace|already exists).*} {
            send -- "y\r"
            exp_continue
        }
        -re {(?i)(are you sure|confirm|continue|yes/no|\(y/n\)|\[y/n\]).*} {
            send -- "y\r"
            exp_continue
        }
        -re {(?i)(copy complete|backup complete|success|completed|done|bytes copied|copied in)} {
            set finished 1
            exp_continue
        }
        -re $prompt {
            set finished 1
        }
        timeout { fail_step "timed out waiting for OLT backup completion" 12 }
        eof { fail_step "connection closed during OLT backup execution" 13 }
    }
}

if {!$finished} {
    fail_step "backup did not report completion" 14
}

send_user "[backup-step] backup command completed; closing session\n"
send -- "quit\r"
expect eof
EOF

log_step "waiting for backup file to appear in TFTP root"

found=0
for ((i=1; i<=WAIT_FOR_FILE_SECONDS; i++)); do
    if [[ -f "$SRC_PATH" || -f "$DEST_PATH" ]]; then
        found=1
        break
    fi
    sleep 1
done

if [[ "$found" -ne 1 ]]; then
    fail_step "backup file not found after ${WAIT_FOR_FILE_SECONDS}s (expected $SRC_PATH)" 15
fi

if [[ -f "$SRC_PATH" && "$SRC_PATH" != "$DEST_PATH" ]]; then
    mv -f "$SRC_PATH" "$DEST_PATH"
    log_step "moved backup artifact to destination directory"
fi

if [[ ! -f "$DEST_PATH" ]]; then
    fail_step "backup file exists in TFTP root but destination write failed ($DEST_PATH)" 16
fi

log_step "backup completed successfully at $DEST_PATH"
