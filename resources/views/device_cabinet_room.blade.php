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
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700,0..1&display=swap" rel="stylesheet"/>
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
            aspect-ratio: 1 / 1;
            border-radius: 0.14rem;
            background:
                linear-gradient(180deg, rgba(164, 176, 194, 0.16), rgba(60, 71, 86, 0.92));
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.04);
        }

        .cabinet-room-port.is-lit {
            box-shadow:
                inset 0 -1px 0 rgba(255, 255, 255, 0.04),
                0 0 0 1px rgba(34, 197, 94, 0.18);
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

        .cabinet-room-sfp-bank {
            grid-template-columns: repeat(8, minmax(0, 1fr));
        }

        .cabinet-room-sfp {
            min-height: 0.78rem;
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

        .cabinet-room-rack-bay[data-density="ultra-compact"] .cabinet-room-device-body {
            padding: 0.25rem 0.4rem;
        }

        .cabinet-room-rack-bay[data-density="ultra-compact"] .cabinet-room-device-face {
            grid-template-columns: minmax(4.25rem, 1fr) minmax(3.5rem, 0.85fr) auto;
            gap: 0.35rem;
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

        .cabinet-room-rack-bay[data-density="ultra-compact"] .cabinet-room-drive-bays {
            grid-template-columns: repeat(3, minmax(0, 1fr));
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
<body class="bg-background-light dark:bg-background-dark text-[#0d121b] dark:text-gray-100 min-h-screen overflow-x-hidden">
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

<div class="flex min-h-screen">
    @include('partials.admin_sidebar', ['sidebarAuthUser' => $authUser ?? null])

    <main class="flex-1 flex flex-col overflow-visible">
        <header class="h-16 border-b border-[#e7ebf3] dark:border-gray-800 bg-white dark:bg-background-dark flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-4 flex-1">
                <button class="flex h-10 w-10 items-center justify-center rounded-lg border border-[#e7ebf3] bg-white text-gray-500 hover:bg-gray-50 dark:border-gray-800 dark:bg-background-dark dark:hover:bg-gray-800" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-primary/80">Devices</p>
                    <h1 class="text-2xl font-bold tracking-tight">Virtual Cabinet Room</h1>
                </div>
            </div>
            <div class="flex items-center gap-3 text-sm text-slate-500">
                <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 font-medium text-slate-600">
                    <span class="material-symbols-outlined text-[18px]">drag_indicator</span>
                    Drag devices into rack units
                </span>
            </div>
        </header>

        <section class="flex-1 overflow-visible p-4">
            <div class="grid items-start gap-4 xl:grid-cols-[18rem_minmax(0,1.75fr)_19rem] 2xl:grid-cols-[19rem_minmax(0,2.15fr)_20rem]">
                <aside class="cabinet-room-panel flex max-h-[calc(100vh-6rem)] flex-col overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Rooms and Cabinets</h2>
                        <p class="mt-1 text-sm text-slate-500">Create rack rooms, add cabinets, and stage unplaced equipment for drag-and-drop placement.</p>
                    </div>
                    <div class="cabinet-room-scrollbar flex-1 overflow-y-auto px-5 py-4 space-y-5" data-cabinet-room-app>
                        <section class="space-y-3">
                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Search rooms and cabinets</span>
                                <input type="search" class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:border-primary focus:ring-primary" placeholder="Search Datacenter 1, Rack A..." data-room-search/>
                            </label>
                            <div class="grid grid-cols-3 gap-3 text-center">
                                <div class="rounded-2xl bg-slate-50 px-3 py-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Rooms</div>
                                    <div class="mt-1 text-xl font-bold text-slate-900" data-stats-rooms>0</div>
                                </div>
                                <div class="rounded-2xl bg-slate-50 px-3 py-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Cabinets</div>
                                    <div class="mt-1 text-xl font-bold text-slate-900" data-stats-cabinets>0</div>
                                </div>
                                <div class="rounded-2xl bg-slate-50 px-3 py-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Unplaced</div>
                                    <div class="mt-1 text-xl font-bold text-slate-900" data-stats-unplaced>0</div>
                                </div>
                            </div>
                        </section>

                        <section class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Room List</h3>
                                <span class="text-xs text-slate-400" data-rooms-summary>No rooms yet</span>
                            </div>
                            <div class="space-y-2" data-room-list></div>
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/70 p-4">
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

                        <section class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Cabinets</h3>
                                <span class="text-xs text-slate-400" data-cabinets-summary>Select a room</span>
                            </div>
                            <div class="space-y-2" data-cabinet-list></div>
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/70 p-4">
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

                        <section class="space-y-3">
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
                                    <span data-selected-room-name>No room selected</span>
                                    <span class="text-slate-300">/</span>
                                    <span data-selected-cabinet-name>No cabinet selected</span>
                                </div>
                                <h2 class="mt-2 text-2xl font-bold text-slate-900" data-rack-title>Select a cabinet to start</h2>
                                <p class="mt-1 text-sm text-slate-500" data-rack-subtitle>Choose a room and cabinet from the left to view rack units and placements.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="inline-flex rounded-full border border-slate-200 bg-slate-50 p-1">
                                    <button class="rounded-full px-3 py-1.5 text-xs font-semibold text-slate-500 transition data-[active=true]:bg-white data-[active=true]:text-slate-900 data-[active=true]:shadow-sm" type="button" data-face-toggle data-face="front" data-active="true">Front</button>
                                    <button class="rounded-full px-3 py-1.5 text-xs font-semibold text-slate-500 transition data-[active=true]:bg-white data-[active=true]:text-slate-900 data-[active=true]:shadow-sm" type="button" data-face-toggle data-face="back" data-active="false">Back</button>
                                </div>
                                <button class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100" type="button" data-refresh-rack>
                                    <span class="material-symbols-outlined text-[18px]">refresh</span>
                                    Refresh Rack
                                </button>
                            </div>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl bg-slate-50 px-4 py-3">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Cabinet Size</div>
                                <div class="mt-1 text-lg font-bold text-slate-900" data-cabinet-size>0U</div>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-4 py-3">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Occupied</div>
                                <div class="mt-1 text-lg font-bold text-slate-900" data-cabinet-occupied>0U</div>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-4 py-3">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Placements</div>
                                <div class="mt-1 text-lg font-bold text-slate-900" data-cabinet-placement-count>0</div>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-visible p-4">
                        <div class="hidden rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" data-page-error></div>
                        <div class="mt-3 overflow-visible rounded-[1.5rem] bg-slate-950/95 p-4 text-white shadow-2xl shadow-slate-900/25">
                            <div class="mb-3 flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Rack Visualizer</p>
                                    <p class="mt-1 text-sm text-slate-300">Drop unplaced devices into the selected face. Drag placed equipment to move it. Rack numbering starts at U1 on the bottom.</p>
                                </div>
                                <div class="rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold text-slate-200" data-rack-face-badge>Front Face</div>
                            </div>
                            <div class="overflow-visible pr-1" data-rack-viewport>
                                <div data-rack-view></div>
                            </div>
                        </div>
                    </div>
                </section>

                <aside class="cabinet-room-panel flex max-h-[calc(100vh-6rem)] flex-col overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Device Details</h2>
                        <p class="mt-1 text-sm text-slate-500">Click equipment in the rack or from the unplaced list to view live status and placement controls.</p>
                    </div>
                    <div class="cabinet-room-scrollbar flex-1 overflow-y-auto px-5 py-4" data-device-drawer></div>
                </aside>
            </div>
        </section>
    </main>
</div>
</body>
</html>
