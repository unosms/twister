<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <meta name="app-base" content="{{ url('/') }}"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Virtual Cabinet Room | Device Control Manager</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#135bec',
                        'background-light': '#f6f6f8',
                        'background-dark': '#101622',
                    },
                    fontFamily: {
                        display: ['Inter'],
                    },
                    borderRadius: {
                        DEFAULT: '0.25rem',
                        lg: '0.5rem',
                        xl: '0.75rem',
                        full: '9999px',
                    },
                },
            },
        };
    </script>
    <style>
        .material-symbols-outlined {
            font-family: 'Material Symbols Outlined';
            font-weight: normal;
            font-style: normal;
            font-size: 24px;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            direction: ltr;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
            font-feature-settings: 'liga';
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        body {
            font-family: 'Inter', sans-serif;
        }

        details > summary {
            list-style: none;
        }

        details > summary::-webkit-details-marker {
            display: none;
        }

        .cabinet-room-panel {
            border: 1px solid #d7dfef;
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 10px 25px rgba(13, 18, 27, 0.04);
        }

        .cabinet-room-shell {
            background:
                radial-gradient(circle at top left, rgba(19, 91, 236, 0.08), transparent 28rem),
                radial-gradient(circle at top right, rgba(14, 165, 233, 0.08), transparent 26rem),
                linear-gradient(180deg, #f4f7fc 0%, #eef2f8 100%);
        }

        .cabinet-room-hero {
            border: 1px solid rgba(255, 255, 255, 0.75);
            border-radius: 1.5rem;
            background:
                linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(246, 249, 255, 0.96) 100%);
            box-shadow:
                0 18px 40px rgba(15, 23, 42, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        .cabinet-room-kicker {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            color: rgba(19, 91, 236, 0.78);
        }

        .cabinet-room-summary-card {
            border: 1px solid rgba(215, 223, 239, 0.85);
            border-radius: 1.1rem;
            background: rgba(255, 255, 255, 0.92);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }

        .cabinet-room-section-card {
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 1.15rem;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.94));
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.88);
        }

        .cabinet-room-muted-card {
            border: 1px dashed rgba(148, 163, 184, 0.4);
            border-radius: 1rem;
            background: rgba(248, 250, 252, 0.82);
        }

        .cabinet-room-toolbar-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border-radius: 999px;
            border: 1px solid rgba(214, 222, 238, 0.88);
            background: rgba(255, 255, 255, 0.88);
            padding: 0.55rem 0.9rem;
            font-size: 0.76rem;
            font-weight: 700;
            color: #475569;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.04);
        }

        .cabinet-room-panel-title {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
        }

        .cabinet-room-panel-copy {
            margin-top: 0.3rem;
            font-size: 0.86rem;
            line-height: 1.45;
            color: #64748b;
        }

        .cabinet-room-rack-stage {
            border-radius: 1.6rem;
            background:
                radial-gradient(circle at top center, rgba(37, 99, 235, 0.12), transparent 24rem),
                linear-gradient(180deg, rgba(2, 6, 23, 0.98) 0%, rgba(5, 10, 24, 0.98) 100%);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.06),
                0 26px 46px rgba(2, 6, 23, 0.22);
        }

        .cabinet-room-rack-stage[data-fullscreen="true"] {
            min-height: 100vh;
            border-radius: 0;
            padding: 1rem;
        }

        .cabinet-room-rack-stage[data-fullscreen="true"] .cabinet-room-rack-viewport {
            max-height: calc(100vh - 10rem);
            overflow: auto;
            padding-right: 0.5rem;
        }

        .cabinet-room-rack-note {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            padding: 0.45rem 0.8rem;
            font-size: 0.72rem;
            font-weight: 700;
            color: rgba(226, 232, 240, 0.86);
        }

        .cabinet-room-rack-scene {
            position: relative;
            padding: 0.5rem 0.35rem 0.75rem;
        }

        .cabinet-room-rack-frame {
            position: relative;
            display: grid;
            grid-template-columns: 28px minmax(240px, 1fr) 28px;
            gap: 0.75rem;
            align-items: stretch;
        }

        .cabinet-room-rack-rail {
            position: relative;
            border-radius: 0.75rem;
            background:
                radial-gradient(circle at center, rgba(209, 218, 232, 0.95) 0 1px, transparent 1.5px 100%),
                linear-gradient(180deg, #2b3445 0%, #1a2130 100%);
            background-size: 8px 18px, 100% 100%;
            background-position: center top, center;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
        }

        .cabinet-room-rack-bay {
            position: relative;
            height: calc(var(--rack-size-u) * var(--slot-height));
            border-radius: 1rem;
            background:
                linear-gradient(180deg, rgba(25, 33, 47, 0.98) 0%, rgba(10, 14, 21, 0.98) 100%);
            overflow: hidden;
            box-shadow:
                inset 0 0 0 1px rgba(255, 255, 255, 0.06),
                0 18px 40px rgba(7, 10, 16, 0.32);
        }

        .cabinet-room-slot {
            position: relative;
            display: grid;
            grid-template-columns: 44px 1fr;
            min-height: var(--slot-height);
            border-bottom: 1px solid rgba(118, 134, 158, 0.16);
        }

        .cabinet-room-slot-number {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(0.58rem, 1vw, 0.72rem);
            font-weight: 700;
            color: rgba(210, 219, 233, 0.8);
            border-right: 1px solid rgba(118, 134, 158, 0.18);
            background: rgba(30, 39, 56, 0.68);
        }

        .cabinet-room-slot-dropzone {
            position: relative;
            width: 100%;
            height: 100%;
            background:
                linear-gradient(90deg, rgba(255, 255, 255, 0.025) 0, rgba(255, 255, 255, 0.01) 50%, rgba(255, 255, 255, 0.025) 100%);
        }

        .cabinet-room-slot-dropzone[data-drop-state="valid"] {
            background:
                linear-gradient(90deg, rgba(38, 211, 134, 0.18) 0, rgba(38, 211, 134, 0.08) 100%);
        }

        .cabinet-room-slot-dropzone[data-drop-state="invalid"] {
            background:
                linear-gradient(90deg, rgba(239, 68, 68, 0.18) 0, rgba(239, 68, 68, 0.08) 100%);
        }

        .cabinet-room-placement-layer {
            position: absolute;
            inset: 0 0 0 44px;
            pointer-events: none;
        }

        .cabinet-room-device {
            position: absolute;
            left: 0.55rem;
            right: 0.55rem;
            pointer-events: auto;
            display: flex;
            min-height: calc(var(--slot-height) - 8px);
            cursor: grab;
            overflow: hidden;
            border-radius: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background:
                linear-gradient(180deg, rgba(28, 37, 51, 0.98) 0%, rgba(16, 22, 31, 0.98) 100%);
            box-shadow:
                inset 0 0 0 1px rgba(255, 255, 255, 0.03),
                0 8px 20px rgba(0, 0, 0, 0.24);
        }

        .cabinet-room-device::before {
            content: '';
            position: absolute;
            inset: 0.2rem;
            border-radius: 0.55rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            pointer-events: none;
        }

        .cabinet-room-device.is-selected {
            box-shadow:
                inset 0 0 0 1px rgba(94, 163, 255, 0.5),
                0 0 0 2px rgba(19, 91, 236, 0.35),
                0 12px 26px rgba(10, 14, 21, 0.32);
        }

        .cabinet-room-device-rail {
            width: 0.4rem;
            flex-shrink: 0;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.16) 0%, rgba(255, 255, 255, 0.04) 100%);
        }

        .cabinet-room-device-body {
            display: flex;
            width: 100%;
            align-items: stretch;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.65rem 0.8rem;
        }

        .cabinet-room-device-face {
            display: grid;
            grid-template-columns: minmax(7rem, 1.15fr) minmax(6rem, 1fr) auto;
            width: 100%;
            align-items: stretch;
            gap: 0.7rem;
        }

        .cabinet-room-device-brand {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-width: 0;
        }

        .cabinet-room-device-model {
            margin-top: 0.2rem;
            font-size: 0.66rem;
            color: rgba(186, 197, 214, 0.72);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cabinet-room-device-facade {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 0;
        }

        .cabinet-room-device-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: space-between;
            gap: 0.35rem;
        }

        .cabinet-room-port-bank,
        .cabinet-room-drive-bays,
        .cabinet-room-sfp-bank,
        .cabinet-room-vent-bank {
            display: grid;
            width: 100%;
            gap: 0.18rem;
        }

        .cabinet-room-port-bank {
            grid-template-columns: repeat(12, minmax(0, 1fr));
        }

        .cabinet-room-port {
            position: relative;
            aspect-ratio: 1 / 1;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            overflow: hidden;
            border-radius: 0.14rem;
            background:
                linear-gradient(180deg, rgba(164, 176, 194, 0.16), rgba(60, 71, 86, 0.92));
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.04);
            transition: transform 0.12s ease, box-shadow 0.12s ease, border-color 0.12s ease;
        }

        .cabinet-room-port::before {
            content: '';
            position: absolute;
            left: 0.16rem;
            right: 0.16rem;
            top: 0.2rem;
            bottom: 0.16rem;
            border-radius: 0.08rem;
            background: rgba(15, 23, 42, 0.6);
        }

        .cabinet-room-port.is-lit {
            box-shadow:
                inset 0 -1px 0 rgba(255, 255, 255, 0.04),
                0 0 0 1px rgba(34, 197, 94, 0.18);
        }

        .cabinet-room-port:hover {
            transform: translateY(-1px);
            border-color: rgba(191, 219, 254, 0.35);
            box-shadow:
                inset 0 -1px 0 rgba(255, 255, 255, 0.04),
                0 0 0 1px rgba(96, 165, 250, 0.28);
        }

        .cabinet-room-port[data-status-tone="online"] {
            border-color: rgba(34, 197, 94, 0.18);
        }

        .cabinet-room-port[data-status-tone="offline"] {
            border-color: rgba(148, 163, 184, 0.18);
            opacity: 0.88;
        }

        .cabinet-room-port[data-status-tone="unknown"] {
            border-color: rgba(148, 163, 184, 0.14);
        }

        .cabinet-room-port.is-lit::after,
        .cabinet-room-sfp.is-lit::after {
            content: '';
            display: block;
            width: 0.24rem;
            height: 0.24rem;
            margin: 0.05rem auto 0;
            border-radius: 999px;
            background: #22c55e;
            box-shadow: 0 0 6px #22c55e;
        }

        .cabinet-room-port-label {
            position: relative;
            z-index: 1;
            margin-bottom: 0.08rem;
            font-size: 0.34rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: 0.01em;
            color: rgba(226, 232, 240, 0.92);
            text-shadow: 0 1px 1px rgba(2, 6, 23, 0.9);
            pointer-events: none;
        }

        .cabinet-room-port-link {
            position: absolute;
            top: 0.16rem;
            right: 0.16rem;
            width: 0.18rem;
            height: 0.18rem;
            border-radius: 999px;
            background: rgba(100, 116, 139, 0.5);
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.4);
            pointer-events: none;
        }

        .cabinet-room-port[data-status-tone="online"] .cabinet-room-port-link {
            background: #22c55e;
            box-shadow:
                0 0 0 1px rgba(15, 23, 42, 0.4),
                0 0 6px rgba(34, 197, 94, 0.82);
        }

        .cabinet-room-port[data-status-tone="offline"] .cabinet-room-port-link {
            background: rgba(148, 163, 184, 0.7);
        }

        .cabinet-room-drive-bays {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .cabinet-room-drive-bay,
        .cabinet-room-sfp,
        .cabinet-room-screen,
        .cabinet-room-module {
            position: relative;
            border-radius: 0.2rem;
            border: 1px solid rgba(255, 255, 255, 0.06);
            background: linear-gradient(180deg, rgba(53, 63, 78, 0.95), rgba(19, 25, 34, 0.95));
        }

        .cabinet-room-drive-bay {
            min-height: 1.05rem;
        }

        .cabinet-room-drive-bay::before {
            content: '';
            position: absolute;
            left: 0.18rem;
            right: 0.18rem;
            top: 0.18rem;
            height: 0.16rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
        }

        .cabinet-room-drive-bay::after {
            content: '';
            position: absolute;
            width: 0.2rem;
            height: 0.2rem;
            right: 0.16rem;
            bottom: 0.16rem;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.55);
        }

        .cabinet-room-drive-bay.is-lit::after {
            background: #22c55e;
            box-shadow: 0 0 7px rgba(34, 197, 94, 0.72);
        }

        .cabinet-room-sfp-bank {
            grid-template-columns: repeat(8, minmax(0, 1fr));
        }

        .cabinet-room-sfp {
            min-height: 0.78rem;
        }

        .cabinet-room-sfp::before {
            content: '';
            position: absolute;
            left: 0.14rem;
            right: 0.14rem;
            top: 0.18rem;
            bottom: 0.18rem;
            border-radius: 0.08rem;
            background: rgba(15, 23, 42, 0.52);
        }

        .cabinet-room-vent-bank {
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }

        .cabinet-room-vent {
            min-height: 0.22rem;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.16);
        }

        .cabinet-room-screen {
            min-width: 2rem;
            min-height: 0.9rem;
            background:
                linear-gradient(180deg, rgba(35, 68, 95, 0.95), rgba(10, 26, 45, 0.95));
            box-shadow: inset 0 0 0 1px rgba(110, 192, 255, 0.16);
        }

        .cabinet-room-module {
            min-width: 2.2rem;
            min-height: 1.2rem;
            background:
                linear-gradient(180deg, rgba(65, 76, 92, 0.95), rgba(24, 31, 43, 0.95));
        }

        .cabinet-room-equipment-stack {
            display: grid;
            width: 100%;
            gap: 0.28rem;
        }

        .cabinet-room-panel-strip,
        .cabinet-room-button-bank,
        .cabinet-room-led-bank,
        .cabinet-room-socket-bank,
        .cabinet-room-power-supplies {
            display: flex;
            align-items: center;
            gap: 0.22rem;
            min-width: 0;
            flex-wrap: wrap;
        }

        .cabinet-room-panel-strip {
            justify-content: space-between;
        }

        .cabinet-room-port-group {
            display: grid;
            gap: 0.16rem;
        }

        .cabinet-room-port-legend {
            font-size: 0.48rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(148, 163, 184, 0.76);
        }

        .cabinet-room-mini-led {
            position: relative;
            width: 0.38rem;
            height: 0.38rem;
            border-radius: 999px;
            background: rgba(100, 116, 139, 0.42);
            box-shadow: 0 0 8px rgba(100, 116, 139, 0.28);
        }

        .cabinet-room-mini-led.is-lit {
            background: #22c55e;
            box-shadow: 0 0 10px rgba(34, 197, 94, 0.75);
        }

        .cabinet-room-mini-led.is-amber.is-lit {
            background: #f59e0b;
            box-shadow: 0 0 10px rgba(245, 158, 11, 0.75);
        }

        .cabinet-room-mini-led.is-blue.is-lit {
            background: #38bdf8;
            box-shadow: 0 0 10px rgba(56, 189, 248, 0.72);
        }

        .cabinet-room-button,
        .cabinet-room-socket,
        .cabinet-room-psu {
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: linear-gradient(180deg, rgba(67, 78, 94, 0.95), rgba(24, 31, 43, 0.95));
        }

        .cabinet-room-button {
            width: 0.78rem;
            height: 0.78rem;
            border-radius: 999px;
            box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.08);
        }

        .cabinet-room-button::after {
            content: '';
            position: absolute;
            inset: 0.22rem;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .cabinet-room-button.is-power::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 0.12rem;
            width: 0.12rem;
            height: 0.28rem;
            transform: translateX(-50%);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.5);
        }

        .cabinet-room-button.is-reset {
            width: 0.62rem;
            height: 0.62rem;
        }

        .cabinet-room-socket {
            width: 0.88rem;
            height: 0.62rem;
            border-radius: 0.18rem;
        }

        .cabinet-room-socket::before {
            content: '';
            position: absolute;
            inset: 0.16rem;
            border-radius: 0.1rem;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .cabinet-room-socket.is-console::after,
        .cabinet-room-socket.is-usb::after,
        .cabinet-room-socket.is-mgmt::after,
        .cabinet-room-socket.is-power::after {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.34rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            color: rgba(226, 232, 240, 0.84);
        }

        .cabinet-room-socket.is-console::after {
            content: 'C';
        }

        .cabinet-room-socket.is-usb::after {
            content: 'U';
        }

        .cabinet-room-socket.is-mgmt::after {
            content: 'M';
        }

        .cabinet-room-socket.is-power::after {
            content: 'P';
        }

        .cabinet-room-psu {
            min-width: 1.15rem;
            min-height: 0.72rem;
            border-radius: 0.22rem;
        }

        .cabinet-room-psu::before {
            content: '';
            position: absolute;
            left: 0.12rem;
            right: 0.12rem;
            top: 0.16rem;
            height: 0.16rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
        }

        .cabinet-room-psu::after {
            content: '';
            position: absolute;
            right: 0.14rem;
            bottom: 0.14rem;
            width: 0.18rem;
            height: 0.18rem;
            border-radius: 999px;
            background: #22c55e;
            box-shadow: 0 0 7px rgba(34, 197, 94, 0.72);
        }

        .cabinet-room-switch-panel {
            display: grid;
            width: 100%;
            gap: 0.28rem;
        }

        .cabinet-room-switch-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.45rem;
        }

        .cabinet-room-switch-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.08rem 0.42rem;
            font-size: 0.55rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(226, 232, 240, 0.88);
            background: rgba(255, 255, 255, 0.08);
        }

        .cabinet-room-switch-label {
            font-size: 0.52rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: rgba(148, 163, 184, 0.78);
        }

        .cabinet-room-switch-rows {
            display: grid;
            gap: 0.18rem;
        }

        .cabinet-room-port-row {
            display: grid;
            gap: 0.18rem;
        }

        .cabinet-room-port-row.is-uplink .cabinet-room-port,
        .cabinet-room-port.is-uplink {
            background:
                linear-gradient(180deg, rgba(160, 116, 36, 0.22), rgba(65, 45, 18, 0.92));
            box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.04);
        }

        .cabinet-room-hover-card {
            position: fixed;
            z-index: 80;
            max-width: 18rem;
            pointer-events: none;
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: rgba(15, 23, 42, 0.96);
            box-shadow: 0 18px 42px rgba(2, 6, 23, 0.32);
            padding: 0.85rem 0.95rem;
            color: #e2e8f0;
            backdrop-filter: blur(10px);
        }

        .cabinet-room-hover-card[hidden] {
            display: none !important;
        }

        .cabinet-room-hover-card-title {
            font-size: 0.82rem;
            font-weight: 800;
            line-height: 1.2;
            color: #f8fafc;
        }

        .cabinet-room-hover-card-part {
            margin-top: 0.28rem;
            font-size: 0.74rem;
            font-weight: 700;
            color: #cbd5e1;
        }

        .cabinet-room-hover-card-meta {
            margin-top: 0.4rem;
            font-size: 0.68rem;
            line-height: 1.4;
            color: rgba(203, 213, 225, 0.88);
        }

        .cabinet-room-hover-card-status {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 0.55rem;
            border-radius: 999px;
            padding: 0.28rem 0.55rem;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            background: rgba(255, 255, 255, 0.08);
            color: #e2e8f0;
        }

        .cabinet-room-hover-card-status-dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 999px;
            background: #94a3b8;
            box-shadow: 0 0 8px rgba(148, 163, 184, 0.5);
        }

        .cabinet-room-hover-card-status[data-tone="online"] .cabinet-room-hover-card-status-dot {
            background: #22c55e;
            box-shadow: 0 0 8px rgba(34, 197, 94, 0.75);
        }

        .cabinet-room-hover-card-status[data-tone="warning"] .cabinet-room-hover-card-status-dot {
            background: #f59e0b;
            box-shadow: 0 0 8px rgba(245, 158, 11, 0.75);
        }

        .cabinet-room-hover-card-status[data-tone="error"] .cabinet-room-hover-card-status-dot {
            background: #ef4444;
            box-shadow: 0 0 8px rgba(239, 68, 68, 0.72);
        }

        .cabinet-room-device[data-equipment-kind="switch"] {
            background:
                linear-gradient(180deg, rgba(31, 41, 59, 0.98), rgba(13, 17, 26, 0.98));
        }

        .cabinet-room-device[data-equipment-kind="server"] {
            background:
                linear-gradient(180deg, rgba(43, 35, 28, 0.98), rgba(17, 15, 13, 0.98));
        }

        .cabinet-room-device[data-equipment-kind="router"] {
            background:
                linear-gradient(180deg, rgba(28, 44, 45, 0.98), rgba(12, 20, 22, 0.98));
        }

        .cabinet-room-device[data-equipment-kind="optical"] {
            background:
                linear-gradient(180deg, rgba(44, 34, 52, 0.98), rgba(17, 12, 23, 0.98));
        }

        .cabinet-room-device[data-equipment-kind="wireless"] {
            background:
                linear-gradient(180deg, rgba(34, 40, 56, 0.98), rgba(15, 18, 28, 0.98));
        }

        .cabinet-room-device[data-equipment-kind="generic"] {
            background:
                linear-gradient(180deg, rgba(37, 40, 46, 0.98), rgba(15, 18, 22, 0.98));
        }

        .cabinet-room-rack-bay[data-density="compact"] .cabinet-room-device-body {
            gap: 0.45rem;
            padding: 0.35rem 0.5rem;
        }

        .cabinet-room-rack-bay[data-density="compact"] .cabinet-room-device-face {
            gap: 0.45rem;
            grid-template-columns: minmax(5.5rem, 1fr) minmax(4.25rem, 0.95fr) auto;
        }

        .cabinet-room-rack-bay[data-density="compact"] .cabinet-room-switch-badge,
        .cabinet-room-rack-bay[data-density="compact"] .cabinet-room-switch-label {
            font-size: 0.48rem;
        }

        .cabinet-room-rack-bay[data-density="compact"] .cabinet-room-device-chip {
            padding: 0.08rem 0.35rem;
            font-size: 0.58rem;
        }

        .cabinet-room-rack-bay[data-density="compact"] .cabinet-room-device-body .text-sm {
            font-size: 0.68rem;
            line-height: 1rem;
        }

        .cabinet-room-rack-bay[data-density="compact"] .cabinet-room-device-body .text-xs,
        .cabinet-room-rack-bay[data-density="compact"] .cabinet-room-device-body .text-\[10px\] {
            font-size: 0.55rem;
            line-height: 0.8rem;
        }

        .cabinet-room-rack-bay[data-density="compact"] .cabinet-room-port-legend {
            font-size: 0.42rem;
        }

        .cabinet-room-rack-bay[data-density="compact"] .cabinet-room-port-label {
            font-size: 0.3rem;
        }

        .cabinet-room-rack-bay[data-density="compact"] .cabinet-room-button,
        .cabinet-room-rack-bay[data-density="compact"] .cabinet-room-socket {
            transform: scale(0.92);
            transform-origin: left center;
        }

        .cabinet-room-rack-bay[data-density="ultra-compact"] .cabinet-room-device-body {
            padding: 0.25rem 0.4rem;
        }

        .cabinet-room-rack-bay[data-density="ultra-compact"] .cabinet-room-device-face {
            grid-template-columns: minmax(4.25rem, 1fr) minmax(3.5rem, 0.85fr) auto;
            gap: 0.35rem;
        }

        .cabinet-room-rack-bay[data-density="ultra-compact"] .cabinet-room-switch-header {
            display: none;
        }

        .cabinet-room-rack-bay[data-density="ultra-compact"] .cabinet-room-device-body .text-xs,
        .cabinet-room-rack-bay[data-density="ultra-compact"] .cabinet-room-device-body .text-\[10px\] {
            display: none;
        }

        .cabinet-room-rack-bay[data-density="ultra-compact"] .cabinet-room-device-chip:nth-child(2) {
            display: none;
        }

        .cabinet-room-rack-bay[data-density="ultra-compact"] .cabinet-room-port-bank {
            grid-template-columns: repeat(8, minmax(0, 1fr));
        }

        .cabinet-room-rack-bay[data-density="ultra-compact"] .cabinet-room-port-label {
            display: none;
        }

        .cabinet-room-rack-bay[data-density="ultra-compact"] .cabinet-room-drive-bays {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .cabinet-room-rack-bay[data-density="ultra-compact"] .cabinet-room-port-legend,
        .cabinet-room-rack-bay[data-density="ultra-compact"] .cabinet-room-button-bank,
        .cabinet-room-rack-bay[data-density="ultra-compact"] .cabinet-room-socket-bank,
        .cabinet-room-rack-bay[data-density="ultra-compact"] .cabinet-room-power-supplies {
            display: none;
        }

        .cabinet-room-device-led {
            display: inline-flex;
            width: 0.72rem;
            height: 0.72rem;
            border-radius: 999px;
            box-shadow: 0 0 14px currentColor;
        }

        .cabinet-room-device[data-status-tone="online"] .cabinet-room-device-led {
            color: #22c55e;
            background: #22c55e;
        }

        .cabinet-room-device[data-status-tone="warning"] .cabinet-room-device-led {
            color: #f59e0b;
            background: #f59e0b;
        }

        .cabinet-room-device[data-status-tone="offline"] .cabinet-room-device-led,
        .cabinet-room-device[data-status-tone="unknown"] .cabinet-room-device-led {
            color: #94a3b8;
            background: #94a3b8;
        }

        .cabinet-room-device[data-status-tone="error"] .cabinet-room-device-led {
            color: #ef4444;
            background: #ef4444;
        }

        .cabinet-room-device-chip {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.1rem 0.5rem;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            background: rgba(255, 255, 255, 0.08);
            color: rgba(228, 233, 241, 0.86);
        }

        .cabinet-room-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.5) transparent;
        }

        .cabinet-room-scrollbar::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        .cabinet-room-scrollbar::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.55);
        }
    </style>
    @include('partials.admin_sidebar_styles')
    <script src="{{ asset('js/actions.js') . '?v=' . filemtime(public_path('js/actions.js')) }}" defer></script>
    <script src="{{ asset('js/cabinet-room.js') . '?v=' . filemtime(public_path('js/cabinet-room.js')) }}" defer></script>
</head>
<body class="cabinet-room-shell font-display text-slate-900 h-screen overflow-hidden">
@php
    $cabinetRoomConfig = [
        'initialRooms' => $initialRooms,
        'initialRoomId' => $initialRoomId,
        'initialCabinetId' => $initialCabinetId,
        'routes' => [
            'rooms' => route('devices.cabinet-room.rooms.index'),
            'storeRoom' => route('devices.cabinet-room.rooms.store'),
            'cabinets' => url('/rooms/__ROOM__/cabinets'),
            'placements' => url('/cabinets/__CABINET__/placements'),
            'placement' => url('/placements/__PLACEMENT__'),
            'unplacedDevices' => route('devices.index', ['unplaced' => 1]),
            'deviceDetails' => url('/cabinet-room/devices/__DEVICE__'),
            'deviceStream' => url('/cabinet-room/devices/__DEVICE__/stream'),
        ],
    ];
@endphp
<script id="cabinet-room-config" type="application/json">@json($cabinetRoomConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>

<div class="flex h-screen overflow-hidden">
    @include('partials.admin_sidebar', ['sidebarAuthUser' => $authUser ?? null])

    <main class="flex-1 min-w-0 flex flex-col overflow-y-auto overflow-x-hidden">
        <header class="sticky top-0 z-10 border-b border-[#e7ebf3] bg-white/85 backdrop-blur px-6 py-4 shrink-0">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-4 flex-1 min-w-0">
                <button class="flex h-10 w-10 items-center justify-center rounded-lg border border-[#e7ebf3] bg-white text-gray-500 hover:bg-gray-50 dark:border-gray-800 dark:bg-background-dark dark:hover:bg-gray-800" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <div>
                    <p class="cabinet-room-kicker">Devices</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Virtual Cabinet Room</h1>
                    <p class="mt-1 text-sm text-slate-500">Place devices, track rack space, and inspect live status.</p>
                </div>
            </div>
                <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <span class="cabinet-room-toolbar-pill">
                    <span class="material-symbols-outlined text-[18px]">drag_indicator</span>
                    Drag to place
                </span>
                <span class="cabinet-room-toolbar-pill">
                    <span class="material-symbols-outlined text-[18px]">stacked_bar_chart</span>
                    Live rack status
                </span>
                </div>
            </div>
        </header>

        <section class="flex-1 overflow-visible p-4 lg:p-5">
            <div class="cabinet-room-hero px-5 py-5 lg:px-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="max-w-3xl">
                        <p class="cabinet-room-kicker">Rack Operations</p>
                        <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Plan rooms, place equipment, and work the rack live</h2>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Build rooms on the left, place devices in the rack, and manage details on the right.</p>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        <span class="cabinet-room-toolbar-pill">
                            <span class="material-symbols-outlined text-[18px]">view_in_ar</span>
                            Rack placement
                        </span>
                        <span class="cabinet-room-toolbar-pill">
                            <span class="material-symbols-outlined text-[18px]">sync</span>
                            Live refresh
                        </span>
                        <span class="cabinet-room-toolbar-pill">
                            <span class="material-symbols-outlined text-[18px]">shield</span>
                            Safe U placement
                        </span>
                    </div>
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="cabinet-room-summary-card px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Rooms</div>
                        <div class="mt-2 flex items-end justify-between gap-3">
                            <div class="text-2xl font-bold text-slate-950" data-stats-rooms>0</div>
                            <span class="text-xs font-semibold text-slate-400" data-rooms-summary>No rooms yet</span>
                        </div>
                    </div>
                    <div class="cabinet-room-summary-card px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Cabinets</div>
                        <div class="mt-2 flex items-end justify-between gap-3">
                            <div class="text-2xl font-bold text-slate-950" data-stats-cabinets>0</div>
                            <span class="text-xs font-semibold text-slate-400" data-cabinets-summary>Select a room</span>
                        </div>
                    </div>
                    <div class="cabinet-room-summary-card px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Unplaced Devices</div>
                        <div class="mt-2 flex items-end justify-between gap-3">
                            <div class="text-2xl font-bold text-slate-950" data-stats-unplaced>0</div>
                            <span class="text-xs font-semibold text-slate-400">Ready</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-5 grid items-start gap-4 xl:grid-cols-[17rem_minmax(0,1.95fr)_18rem] 2xl:grid-cols-[18rem_minmax(0,2.3fr)_19rem]">
                <aside class="cabinet-room-panel flex max-h-[calc(100vh-6rem)] flex-col overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="cabinet-room-panel-title">Rooms and Cabinets</h2>
                        <p class="cabinet-room-panel-copy">Create rooms, add cabinets, and stage devices.</p>
                    </div>
                    <div class="cabinet-room-scrollbar flex-1 overflow-y-auto px-5 py-4 space-y-5" data-cabinet-room-app>
                        <section class="cabinet-room-section-card space-y-3 p-4">
                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Search rooms and cabinets</span>
                                <input type="search" class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:border-primary focus:ring-primary" placeholder="Search Datacenter 1, Rack A..." data-room-search/>
                            </label>
                            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                                <div class="rounded-2xl bg-slate-50 px-3 py-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Flow</div>
                                    <div class="mt-1 text-sm font-semibold text-slate-900">1. Room  2. Cabinet  3. Place</div>
                                </div>
                                <div class="rounded-2xl bg-slate-50 px-3 py-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Selection</div>
                                    <div class="mt-1 text-sm font-semibold text-slate-900"><span data-selected-room-name>No room selected</span> / <span data-selected-cabinet-name>No cabinet selected</span></div>
                                </div>
                            </div>
                        </section>

                        <section class="cabinet-room-section-card space-y-3 p-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Rooms</h3>
                                <span class="text-xs text-slate-400">Create and switch</span>
                            </div>
                            <div class="space-y-2" data-room-list></div>
                            <div class="cabinet-room-muted-card p-4">
                                <h4 class="text-sm font-semibold text-slate-900">Create room</h4>
                                <form class="mt-3 space-y-3" data-room-form>
                                    <input class="w-full rounded-xl border-slate-200 px-3 py-2.5 text-sm focus:border-primary focus:ring-primary" name="name" type="text" placeholder="Datacenter 1" required/>
                                    <input class="w-full rounded-xl border-slate-200 px-3 py-2.5 text-sm focus:border-primary focus:ring-primary" name="location" type="text" placeholder="London HQ"/>
                                    <textarea class="w-full rounded-xl border-slate-200 px-3 py-2.5 text-sm focus:border-primary focus:ring-primary" name="notes" rows="3" placeholder="Power feed notes, aisle, access details"></textarea>
                                    <button class="inline-flex items-center justify-center rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition hover:bg-primary/90" type="submit">
                                        Add Room
                                    </button>
                                </form>
                            </div>
                        </section>

                        <section class="cabinet-room-section-card space-y-3 p-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Cabinets</h3>
                                <span class="text-xs text-slate-400">Rack hardware</span>
                            </div>
                            <div class="space-y-2" data-cabinet-list></div>
                            <div class="cabinet-room-muted-card p-4">
                                <h4 class="text-sm font-semibold text-slate-900">Add cabinet</h4>
                                <form class="mt-3 space-y-3" data-cabinet-form>
                                    <input class="w-full rounded-xl border-slate-200 px-3 py-2.5 text-sm focus:border-primary focus:ring-primary" name="name" type="text" placeholder="Rack A" required/>
                                    <div class="grid grid-cols-2 gap-3">
                                        <input class="w-full rounded-xl border-slate-200 px-3 py-2.5 text-sm focus:border-primary focus:ring-primary" name="size_u" type="number" min="1" max="60" value="42" required/>
                                        <input class="w-full rounded-xl border-slate-200 px-3 py-2.5 text-sm focus:border-primary focus:ring-primary" name="manufacturer" type="text" placeholder="APC"/>
                                    </div>
                                    <input class="w-full rounded-xl border-slate-200 px-3 py-2.5 text-sm focus:border-primary focus:ring-primary" name="model" type="text" placeholder="NetShelter SX"/>
                                    <button class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50" type="submit" data-cabinet-submit>
                                        Add Cabinet
                                    </button>
                                </form>
                            </div>
                        </section>

                        <section class="cabinet-room-section-card space-y-3 p-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Unplaced Devices</h3>
                                <button class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100" type="button" data-refresh-unplaced>
                                    <span class="material-symbols-outlined text-[16px]">refresh</span>
                                    Refresh
                                </button>
                            </div>
                            <label class="block">
                                <span class="sr-only">Search unplaced devices</span>
                                <input type="search" class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:border-primary focus:ring-primary" placeholder="Filter unplaced devices..." data-device-search/>
                            </label>
                            <div class="space-y-2" data-unplaced-devices></div>
                        </section>
                    </div>
                </aside>

                <section class="cabinet-room-panel flex flex-col overflow-visible">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                    <span>Rack Workspace</span>
                                    <span class="text-slate-300">/</span>
                                    <span data-rack-face-badge>Front Face</span>
                                </div>
                                <h2 class="mt-2 text-2xl font-bold text-slate-900" data-rack-title>Select a cabinet to start</h2>
                                <p class="mt-1 text-sm text-slate-500" data-rack-subtitle>Select a room and cabinet to load the rack.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="inline-flex rounded-full border border-slate-200 bg-slate-50 p-1">
                                    <button class="rounded-full px-3 py-1.5 text-xs font-semibold text-slate-500 transition data-[active=true]:bg-white data-[active=true]:text-slate-900 data-[active=true]:shadow-sm" type="button" data-face-toggle data-face="front" data-active="true">Front</button>
                                    <button class="rounded-full px-3 py-1.5 text-xs font-semibold text-slate-500 transition data-[active=true]:bg-white data-[active=true]:text-slate-900 data-[active=true]:shadow-sm" type="button" data-face-toggle data-face="back" data-active="false">Back</button>
                                </div>
                                <div class="inline-flex rounded-full border border-slate-200 bg-slate-50 p-1">
                                    <button class="rounded-full px-3 py-1.5 text-xs font-semibold text-slate-500 transition data-[active=true]:bg-white data-[active=true]:text-slate-900 data-[active=true]:shadow-sm" type="button" data-rack-zoom="1" data-active="false">1x</button>
                                    <button class="rounded-full px-3 py-1.5 text-xs font-semibold text-slate-500 transition data-[active=true]:bg-white data-[active=true]:text-slate-900 data-[active=true]:shadow-sm" type="button" data-rack-zoom="2" data-active="false">2x</button>
                                    <button class="rounded-full px-3 py-1.5 text-xs font-semibold text-slate-500 transition data-[active=true]:bg-white data-[active=true]:text-slate-900 data-[active=true]:shadow-sm" type="button" data-rack-zoom="4" data-active="true">4x</button>
                                    <button class="rounded-full px-3 py-1.5 text-xs font-semibold text-slate-500 transition data-[active=true]:bg-white data-[active=true]:text-slate-900 data-[active=true]:shadow-sm" type="button" data-rack-zoom="6" data-active="false">6x</button>
                                </div>
                                <button class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100" type="button" data-refresh-rack>
                                    <span class="material-symbols-outlined text-[18px]">refresh</span>
                                    Refresh Rack
                                </button>
                                <button class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 data-[active=true]:bg-slate-900 data-[active=true]:text-white" type="button" data-rack-fullscreen-toggle data-active="false" aria-pressed="false">
                                    <span class="material-symbols-outlined text-[18px]" data-rack-fullscreen-icon>fullscreen</span>
                                    <span data-rack-fullscreen-label>Fullscreen</span>
                                </button>
                            </div>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="cabinet-room-summary-card px-4 py-3">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Cabinet Size</div>
                                <div class="mt-1 text-lg font-bold text-slate-900" data-cabinet-size>0U</div>
                            </div>
                            <div class="cabinet-room-summary-card px-4 py-3">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Occupied</div>
                                <div class="mt-1 text-lg font-bold text-slate-900" data-cabinet-occupied>0U</div>
                            </div>
                            <div class="cabinet-room-summary-card px-4 py-3">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Free U</div>
                                <div class="mt-1 text-lg font-bold text-slate-900" data-cabinet-free>0U</div>
                            </div>
                            <div class="cabinet-room-summary-card px-4 py-3">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Devices</div>
                                <div class="mt-1 text-lg font-bold text-slate-900" data-cabinet-placement-count>0</div>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-visible p-4">
                        <div class="hidden rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" data-page-error></div>
                        <div class="cabinet-room-rack-stage mt-3 overflow-visible p-5 text-white" data-rack-stage data-fullscreen="false">
                            <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Rack Visualizer</p>
                                    <p class="mt-1 max-w-2xl text-sm text-slate-300">Drop devices into the selected face or drag placed equipment to move it.</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <span class="cabinet-room-rack-note">
                                        <span class="material-symbols-outlined text-[16px]">south</span>
                                        U1 at bottom
                                    </span>
                                    <span class="cabinet-room-rack-note">
                                        <span class="material-symbols-outlined text-[16px]">open_with</span>
                                        Drag and place
                                    </span>
                                </div>
                            </div>
                            <div class="cabinet-room-rack-viewport overflow-visible pr-1" data-rack-viewport>
                                <div data-rack-view></div>
                            </div>
                        </div>
                    </div>
                </section>

                <aside class="cabinet-room-panel flex max-h-[calc(100vh-6rem)] flex-col overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="cabinet-room-panel-title">Device Details</h2>
                        <p class="cabinet-room-panel-copy">View status and manage placement.</p>
                    </div>
                    <div class="px-5 pt-4">
                        <div class="cabinet-room-muted-card p-4">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Notes</div>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Select a device to inspect it, then place, move, resize, or remove it.</p>
                        </div>
                    </div>
                    <div class="cabinet-room-scrollbar flex-1 overflow-y-auto px-5 py-4" data-device-drawer></div>
                </aside>
            </div>
        </section>
    </main>
</div>
<div class="cabinet-room-hover-card" data-rack-hover-card hidden></div>
</body>
</html>
