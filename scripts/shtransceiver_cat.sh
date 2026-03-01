#!/usr/bin/env bash
# Show transceiver info on Catalyst (4948/3560)
# Needs env: SWITCH_IP, SWITCH_PASS, SWITCH_ENA, INTERFACE
# Output includes the line(s) matching the interface.

set -eE -o pipefail

IP="${SWITCH_IP:-}"
PASS="${SWITCH_PASS:-}"
ENA="${SWITCH_ENA:-}"
IFACE="${INTERFACE:-}"

if [[ -z "$IP" || -z "$PASS" || -z "$ENA" || -z "$IFACE" ]]; then
  echo "ERROR: Missing one or more env vars: SWITCH_IP='$IP' SWITCH_PASS set?=$([[ -n $PASS ]] && echo yes || echo no) SWITCH_ENA set?=$([[ -n $ENA ]] && echo yes || echo no) INTERFACE='$IFACE'" >&2
  exit 2
fi

/usr/bin/expect <<'EXP'
  set timeout 40
  # read from exported env
  set ip    $env(SWITCH_IP)
  set pass  $env(SWITCH_PASS)
  set ena   $env(SWITCH_ENA)
  set iface $env(INTERFACE)

  spawn telnet $ip
  expect "Password:"      { send -- "$pass\r" }
  expect ">"              { send -- "enable\r" }
  expect "Password:"      { send -- "$ena\r" }
  expect "#"

  send -- "terminal length 0\r"
  expect "#"

  # Classic IOS
  send -- "show interface transceiver detail | include $iface\r"
  expect "#"

  send -- "exit\r"
  expect eof
EXP
