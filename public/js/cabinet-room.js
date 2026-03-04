(function () {
    const configElement = document.getElementById('cabinet-room-config');
    if (!configElement) {
        return;
    }

    const config = JSON.parse(configElement.textContent || '{}');

    class RoomList {
        constructor(app, root) {
            this.app = app;
            this.root = root;
            this.root.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-room-id]');
                if (!trigger) {
                    return;
                }

                this.app.selectRoom(Number(trigger.dataset.roomId));
            });
        }

        render(rooms, selectedRoomId, query) {
            const filtered = this.filterRooms(rooms, query);
            if (!filtered.length) {
                this.root.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 px-4 py-5 text-sm text-slate-500">No rooms match the current filter.</div>';
                return;
            }

            this.root.innerHTML = filtered.map((room) => {
                const active = room.id === selectedRoomId;
                return `
                    <button type="button" data-room-id="${room.id}" class="flex w-full items-start justify-between rounded-2xl border px-4 py-3 text-left transition ${active ? 'border-primary bg-primary/5 shadow-sm shadow-primary/10' : 'border-slate-200 bg-white hover:border-primary/40 hover:bg-slate-50'}">
                        <div>
                            <div class="text-sm font-semibold text-slate-900">${escapeHtml(room.name)}</div>
                            <div class="mt-1 text-xs text-slate-500">${escapeHtml(room.location || 'No location')}</div>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600">${room.cabinet_count || 0} racks</span>
                    </button>
                `;
            }).join('');
        }

        filterRooms(rooms, query) {
            const needle = query.trim().toLowerCase();
            if (!needle) {
                return rooms;
            }

            return rooms.filter((room) => {
                const roomHaystack = [room.name, room.location, room.notes].filter(Boolean).join(' ').toLowerCase();
                if (roomHaystack.includes(needle)) {
                    return true;
                }

                return (room.cabinets || []).some((cabinet) => {
                    const cabinetHaystack = [cabinet.name, cabinet.model, cabinet.manufacturer].filter(Boolean).join(' ').toLowerCase();
                    return cabinetHaystack.includes(needle);
                });
            });
        }
    }

    class CabinetList {
        constructor(app, root) {
            this.app = app;
            this.root = root;
            this.root.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-cabinet-id]');
                if (!trigger) {
                    return;
                }

                this.app.selectCabinet(Number(trigger.dataset.cabinetId));
            });
        }

        render(room, selectedCabinetId, query) {
            if (!room) {
                this.root.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 px-4 py-5 text-sm text-slate-500">Select a room to see its cabinets.</div>';
                return;
            }

            const needle = query.trim().toLowerCase();
            const cabinets = (room.cabinets || []).filter((cabinet) => {
                if (!needle) {
                    return true;
                }

                return [cabinet.name, cabinet.model, cabinet.manufacturer]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase()
                    .includes(needle);
            });

            if (!cabinets.length) {
                this.root.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 px-4 py-5 text-sm text-slate-500">No cabinets match the current filter.</div>';
                return;
            }

            this.root.innerHTML = cabinets.map((cabinet) => {
                const active = cabinet.id === selectedCabinetId;
                const meta = [cabinet.manufacturer, cabinet.model].filter(Boolean).join(' | ');

                return `
                    <button type="button" data-cabinet-id="${cabinet.id}" class="flex w-full items-start justify-between rounded-2xl border px-4 py-3 text-left transition ${active ? 'border-slate-900 bg-slate-900 text-white shadow-lg shadow-slate-900/10' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'}">
                        <div>
                            <div class="text-sm font-semibold ${active ? 'text-white' : 'text-slate-900'}">${escapeHtml(cabinet.name)}</div>
                            <div class="mt-1 text-xs ${active ? 'text-slate-200' : 'text-slate-500'}">${escapeHtml(meta || 'Rack hardware not set')}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] ${active ? 'text-slate-300' : 'text-slate-400'}">${cabinet.size_u}U</div>
                            <div class="mt-1 text-[11px] ${active ? 'text-slate-300' : 'text-slate-500'}">${cabinet.placements_count || 0} placed</div>
                        </div>
                    </button>
                `;
            }).join('');
        }
    }

    class UnplacedDevices {
        constructor(app, root) {
            this.app = app;
            this.root = root;

            this.root.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-device-card]');
                if (!trigger) {
                    return;
                }

                this.app.selectDevice(Number(trigger.dataset.deviceId), null);
            });

            this.root.addEventListener('dragstart', (event) => {
                const card = event.target.closest('[data-device-card]');
                if (!card) {
                    return;
                }

                this.app.beginDrag({
                    kind: 'device',
                    deviceId: Number(card.dataset.deviceId),
                    heightU: Number(card.dataset.heightU),
                });

                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', card.dataset.deviceId || '');
                }
            });

            this.root.addEventListener('dragend', () => {
                this.app.endDrag();
            });
        }

        render(devices, selectedDeviceId, query) {
            const needle = query.trim().toLowerCase();
            const filtered = devices.filter((device) => {
                if (!needle) {
                    return true;
                }

                return [device.name, device.model, device.type, device.ip_address, device.location]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase()
                    .includes(needle);
            });

            if (!filtered.length) {
                this.root.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 px-4 py-5 text-sm text-slate-500">No unplaced devices are waiting in the staging list.</div>';
                return;
            }

            this.root.innerHTML = filtered.map((device) => {
                const active = device.id === selectedDeviceId;
                const tone = statusTone(device.status);

                return `
                    <div draggable="true" data-device-card data-device-id="${device.id}" data-height-u="${device.default_height_u || 1}" class="rounded-2xl border px-4 py-3 transition ${active ? 'border-primary bg-primary/5 shadow-sm shadow-primary/10' : 'border-slate-200 bg-white hover:border-primary/40 hover:bg-slate-50'} cursor-grab">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-900">${escapeHtml(device.name)}</div>
                                <div class="mt-1 truncate text-xs text-slate-500">${escapeHtml(device.model || device.type || 'Unknown model')}</div>
                            </div>
                            <span class="inline-flex h-3 w-3 rounded-full ${statusDotClass(tone)}"></span>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-[11px] text-slate-500">
                            <span>${escapeHtml(device.ip_address || 'No IP')}</span>
                            <span class="rounded-full bg-slate-100 px-2 py-1 font-semibold text-slate-600">${device.default_height_u || 1}U</span>
                        </div>
                    </div>
                `;
            }).join('');
        }
    }

    class RackView {
        constructor(app, root) {
            this.app = app;
            this.root = root;

            this.root.addEventListener('click', (event) => {
                const placement = event.target.closest('[data-placement-id]');
                if (placement) {
                    this.app.selectDevice(Number(placement.dataset.deviceId), Number(placement.dataset.placementId));
                    return;
                }

                const slot = event.target.closest('[data-slot-u]');
                if (slot) {
                    this.app.setSuggestedStart(Number(slot.dataset.slotU));
                }
            });

            this.root.addEventListener('dragstart', (event) => {
                const placement = event.target.closest('[data-placement-id]');
                if (!placement) {
                    return;
                }

                this.app.beginDrag({
                    kind: 'placement',
                    placementId: Number(placement.dataset.placementId),
                    deviceId: Number(placement.dataset.deviceId),
                    heightU: Number(placement.dataset.heightU),
                });

                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', placement.dataset.placementId || '');
                }
            });

            this.root.addEventListener('dragend', () => {
                this.app.endDrag();
            });

            this.root.addEventListener('dragover', (event) => {
                const slot = event.target.closest('[data-slot-u]');
                if (!slot || !this.app.dragPayload) {
                    return;
                }

                event.preventDefault();
                this.app.previewDrop(Number(slot.dataset.slotU), slot);
            });

            this.root.addEventListener('dragleave', (event) => {
                const slot = event.target.closest('[data-slot-u]');
                if (!slot) {
                    return;
                }

                this.app.clearDropPreview(slot);
            });

            this.root.addEventListener('drop', (event) => {
                const slot = event.target.closest('[data-slot-u]');
                if (!slot || !this.app.dragPayload) {
                    return;
                }

                event.preventDefault();
                this.app.handleDrop(Number(slot.dataset.slotU));
            });
        }

        render(cabinet, placements, face, selectedPlacementId, dragPreview) {
            if (!cabinet) {
                this.root.innerHTML = '<div class="flex min-h-[420px] items-center justify-center rounded-[1.25rem] border border-dashed border-white/20 bg-white/5 px-6 text-center text-sm text-slate-300">Create a room and a cabinet, then drag devices into open rack units.</div>';
                return;
            }

            const slotHeight = this.resolveSlotHeight(cabinet.size_u, this.app.state.rackZoom);
            const density = slotHeight <= 12 ? 'ultra-compact' : (slotHeight <= 18 ? 'compact' : 'regular');
            const facePlacements = placements
                .filter((placement) => placement.face === face)
                .sort((left, right) => right.start_u - left.start_u);

            const slots = [];
            for (let unit = cabinet.size_u; unit >= 1; unit -= 1) {
                const previewState = dragPreview && dragPreview.startU === unit ? dragPreview.state : '';
                slots.push(`
                    <div class="cabinet-room-slot">
                        <div class="cabinet-room-slot-number">${unit}</div>
                        <button type="button" class="cabinet-room-slot-dropzone" data-slot-u="${unit}" data-drop-state="${previewState || ''}" aria-label="U${unit}"></button>
                    </div>
                `);
            }

            const devices = facePlacements.map((placement) => {
                const bottom = (placement.start_u - 1) * slotHeight + 4;
                const height = placement.height_u * slotHeight - 8;
                const selected = placement.id === selectedPlacementId;
                const tone = statusTone(placement.device?.status);
                const subtitle = [placement.device?.model, placement.device?.ip_address].filter(Boolean).join(' | ');
                const equipmentKind = equipmentKindFor(placement.device);
                const facade = buildEquipmentFacade(placement, density);
                const hoverMeta = [
                    placement.device?.model,
                    placement.device?.ip_address,
                    `${placement.height_u}U`,
                    `U${placement.start_u}-${placement.end_u}`,
                    capitalize(face),
                ].filter(Boolean).join(' • ');

                return `
                    <button
                        type="button"
                        draggable="true"
                        data-placement-id="${placement.id}"
                        data-device-id="${placement.device_id}"
                        data-height-u="${placement.height_u}"
                        data-status-tone="${tone}"
                        data-equipment-kind="${equipmentKind}"
                        data-rack-hover
                        data-hover-title="${escapeHtml(placement.device?.name || 'Rack device')}"
                        data-hover-part="${escapeHtml(`${capitalize(placement.device?.type || equipmentKind)} panel`)}"
                        data-hover-status="${escapeHtml(capitalize(placement.device?.status || 'unknown'))}"
                        data-hover-tone="${escapeHtml(tone)}"
                        data-hover-meta="${escapeHtml(hoverMeta)}"
                        class="cabinet-room-device ${selected ? 'is-selected' : ''}"
                        style="bottom:${bottom}px;height:${Math.max(height, slotHeight - 8)}px"
                        aria-label="${escapeHtml(placement.device?.name || 'Rack device')}"
                    >
                        <span class="cabinet-room-device-rail"></span>
                        <div class="cabinet-room-device-body">
                            <div class="cabinet-room-device-face">
                                <div class="cabinet-room-device-brand">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="cabinet-room-device-led"></span>
                                            <span class="truncate text-sm font-semibold text-slate-100">${escapeHtml(placement.device?.name || 'Unnamed device')}</span>
                                        </div>
                                        <div class="cabinet-room-device-model">${escapeHtml(subtitle || 'No device metadata')}</div>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <span class="cabinet-room-device-chip">${placement.height_u}U</span>
                                        <span class="cabinet-room-device-chip">U${placement.start_u}-${placement.end_u}</span>
                                    </div>
                                </div>
                                <div class="cabinet-room-device-facade">
                                    ${facade}
                                </div>
                                <div class="cabinet-room-device-meta">
                                    <span class="cabinet-room-device-chip">${escapeHtml((placement.device?.status || 'unknown').toUpperCase())}</span>
                                    <span class="cabinet-room-device-chip">${escapeHtml((placement.device?.type || equipmentKind).toUpperCase())}</span>
                                    <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">${escapeHtml(face)}</span>
                                </div>
                            </div>
                        </div>
                        <span class="cabinet-room-device-rail"></span>
                    </button>
                `;
            }).join('');

            this.root.innerHTML = `
                <div class="cabinet-room-rack-scene">
                    <div class="cabinet-room-rack-frame">
                        <div class="cabinet-room-rack-rail"></div>
                        <div class="cabinet-room-rack-bay" data-density="${density}" style="--rack-size-u:${cabinet.size_u};--slot-height:${slotHeight}px;">
                            <div>${slots.join('')}</div>
                            <div class="cabinet-room-placement-layer">${devices}</div>
                        </div>
                        <div class="cabinet-room-rack-rail"></div>
                    </div>
                </div>
            `;
        }

        resolveSlotHeight(sizeU, zoomMultiplier = 1) {
            const fitted = Math.floor(920 / Math.max(sizeU, 1));
            const baseHeight = Math.max(14, Math.min(26, fitted));
            const zoom = Number(zoomMultiplier) || 1;
            const scaleBoost = 1.5;
            return Math.round(baseHeight * zoom * scaleBoost);
        }
    }

    class DeviceDetailsDrawer {
        constructor(app, root) {
            this.app = app;
            this.root = root;

            this.root.addEventListener('submit', (event) => {
                const form = event.target.closest('[data-placement-form]');
                if (!form) {
                    return;
                }

                event.preventDefault();
                this.app.submitPlacementForm(new FormData(form));
            });

            this.root.addEventListener('click', (event) => {
                const removeButton = event.target.closest('[data-remove-placement]');
                if (removeButton) {
                    this.app.removeSelectedPlacement();
                }
            });
        }

        render(state) {
            if (state.detailsLoading) {
                this.root.innerHTML = '<div class="cabinet-room-muted-card px-4 py-8 text-sm text-slate-500">Loading details...</div>';
                return;
            }

            if (!state.selectedDevice) {
                this.root.innerHTML = '<div class="cabinet-room-muted-card px-4 py-8 text-sm text-slate-500">Select a device to view status, metrics, and placement.</div>';
                return;
            }

            const device = state.selectedDevice;
            const placement = device.placement;
            const currentCabinet = state.selectedCabinet;
            const startU = placement && placement.cabinet_id === currentCabinet?.id
                ? placement.start_u
                : (state.suggestedStartU || 1);
            const heightU = placement ? placement.height_u : (state.selectedDeviceHeightU || 1);
            const face = placement && placement.cabinet_id === currentCabinet?.id ? placement.face : state.selectedFace;
            const actionLabel = !currentCabinet
                ? 'Select cabinet'
                : placement
                    ? (placement.cabinet_id === currentCabinet.id ? 'Update Placement' : 'Move Here')
                    : 'Place Here';

            const metricCards = [
                ['CPU', device.metrics?.cpu],
                ['Memory', device.metrics?.memory],
                ['Disk', device.metrics?.disk],
                ['Ping', device.metrics?.ping_latency],
                ['Uptime', device.metrics?.uptime],
                ['Temperature', device.metrics?.temperature],
            ].map(([label, value]) => `
                <div class="rounded-2xl bg-slate-50 px-3 py-3">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">${label}</div>
                    <div class="mt-1 text-sm font-semibold leading-5 whitespace-pre-line text-slate-900">${formatMetricValue(label, value)}</div>
                </div>
            `).join('');

            this.root.innerHTML = `
                <div class="space-y-4">
                    <section class="cabinet-room-section-card p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-lg font-bold text-slate-900">${escapeHtml(device.name || 'Unnamed device')}</div>
                                <div class="mt-1 truncate text-sm text-slate-500">${escapeHtml(device.model || device.vendor || 'Unknown model')}</div>
                            </div>
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold ${statusBadgeClass(statusTone(device.status))}">
                                <span class="h-2.5 w-2.5 rounded-full ${statusDotClass(statusTone(device.status))}"></span>
                                ${escapeHtml((device.status || 'unknown').toUpperCase())}
                            </span>
                        </div>
                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                            ${detailRow('IP Address', device.ip_address || 'N/A')}
                            ${detailRow('Vendor', device.vendor || 'N/A')}
                            ${detailRow('Serial', device.serial_number || 'N/A')}
                            ${detailRow('Location', device.location || 'N/A')}
                            ${detailRow('Last Seen', device.last_seen_formatted || device.last_seen_human || 'Never')}
                            ${detailRow('Placement', placement ? `${placement.room_name || 'Unknown room'} / ${placement.cabinet_name || 'Unknown cabinet'} / ${placement.face} U${placement.start_u}-${placement.start_u + placement.height_u - 1}` : 'Unplaced')}
                        </dl>
                    </section>

                    <section class="cabinet-room-section-card p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Latest Metrics</h3>
                            <span class="text-xs text-slate-400">${escapeHtml(device.last_seen_human || 'live')}</span>
                        </div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            ${metricCards}
                        </div>
                    </section>

                    <section class="cabinet-room-section-card p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Placement Controls</h3>
                                <p class="mt-1 text-xs text-slate-500">${currentCabinet ? `${escapeHtml(currentCabinet.name)} • ${currentCabinet.size_u}U` : 'Select a cabinet to place or move this device.'}</p>
                            </div>
                        </div>
                        <form class="mt-4 space-y-3" data-placement-form>
                            <div class="grid grid-cols-3 gap-3">
                                <label class="block text-sm font-medium text-slate-600">
                                    <span class="mb-1 block text-xs uppercase tracking-[0.16em] text-slate-500">Start U</span>
                                    <input name="start_u" type="number" min="1" max="${currentCabinet ? currentCabinet.size_u : 60}" value="${startU}" class="w-full rounded-xl border-slate-200 px-3 py-2.5 focus:border-primary focus:ring-primary" ${currentCabinet ? '' : 'disabled'}/>
                                </label>
                                <label class="block text-sm font-medium text-slate-600">
                                    <span class="mb-1 block text-xs uppercase tracking-[0.16em] text-slate-500">Height</span>
                                    <input name="height_u" type="number" min="1" max="8" value="${heightU}" class="w-full rounded-xl border-slate-200 px-3 py-2.5 focus:border-primary focus:ring-primary" ${currentCabinet ? '' : 'disabled'}/>
                                </label>
                                <label class="block text-sm font-medium text-slate-600">
                                    <span class="mb-1 block text-xs uppercase tracking-[0.16em] text-slate-500">Face</span>
                                    <select name="face" class="w-full rounded-xl border-slate-200 px-3 py-2.5 focus:border-primary focus:ring-primary" ${currentCabinet ? '' : 'disabled'}>
                                        <option value="front" ${face === 'front' ? 'selected' : ''}>Front</option>
                                        <option value="back" ${face === 'back' ? 'selected' : ''}>Back</option>
                                    </select>
                                </label>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <button class="inline-flex items-center justify-center rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50" type="submit" ${currentCabinet ? '' : 'disabled'}>
                                    ${escapeHtml(actionLabel)}
                                </button>
                                ${placement ? '<button class="inline-flex items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50" type="button" data-remove-placement>Remove</button>' : ''}
                            </div>
                        </form>
                    </section>

                    <details class="cabinet-room-section-card p-4">
                        <summary class="cursor-pointer text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Metadata</summary>
                        <pre class="mt-3 overflow-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-200">${escapeHtml(JSON.stringify(device.metadata || {}, null, 2))}</pre>
                    </details>
                </div>
            `;
        }
    }

    class CabinetRoomApp {
        constructor(config) {
            this.config = config;
            this.state = {
                rooms: Array.isArray(config.initialRooms) ? config.initialRooms : [],
                selectedRoomId: Number(config.initialRoomId || 0),
                selectedCabinetId: Number(config.initialCabinetId || 0),
                selectedCabinet: null,
                selectedFace: 'front',
                placements: [],
                unplacedDevices: [],
                selectedDeviceId: null,
                selectedPlacementId: null,
                selectedDevice: null,
                selectedDeviceHeightU: 1,
                roomQuery: '',
                deviceQuery: '',
                suggestedStartU: 1,
                rackZoom: 4,
                rackFullscreen: false,
                dragPayload: null,
                dragPreview: null,
                detailsLoading: false,
            };

            this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            this.stream = null;
            this.pollTimer = null;

            this.roomSearch = document.querySelector('[data-room-search]');
            this.deviceSearch = document.querySelector('[data-device-search]');
            this.roomForm = document.querySelector('[data-room-form]');
            this.cabinetForm = document.querySelector('[data-cabinet-form]');
            this.refreshUnplacedButton = document.querySelector('[data-refresh-unplaced]');
            this.refreshRackButton = document.querySelector('[data-refresh-rack]');
            this.faceButtons = Array.from(document.querySelectorAll('[data-face-toggle]'));
            this.zoomButtons = Array.from(document.querySelectorAll('[data-rack-zoom]'));
            this.fullscreenButton = document.querySelector('[data-rack-fullscreen-toggle]');
            this.fullscreenButtonLabel = document.querySelector('[data-rack-fullscreen-label]');
            this.fullscreenButtonIcon = document.querySelector('[data-rack-fullscreen-icon]');
            this.rackStage = document.querySelector('[data-rack-stage]');
            this.pageError = document.querySelector('[data-page-error]');
            this.statsRooms = document.querySelector('[data-stats-rooms]');
            this.statsCabinets = document.querySelector('[data-stats-cabinets]');
            this.statsUnplaced = document.querySelector('[data-stats-unplaced]');
            this.roomsSummary = document.querySelector('[data-rooms-summary]');
            this.cabinetsSummary = document.querySelector('[data-cabinets-summary]');
            this.selectedRoomName = document.querySelector('[data-selected-room-name]');
            this.selectedCabinetName = document.querySelector('[data-selected-cabinet-name]');
            this.rackTitle = document.querySelector('[data-rack-title]');
            this.rackSubtitle = document.querySelector('[data-rack-subtitle]');
            this.cabinetSize = document.querySelector('[data-cabinet-size]');
            this.cabinetOccupied = document.querySelector('[data-cabinet-occupied]');
            this.cabinetFree = document.querySelector('[data-cabinet-free]');
            this.cabinetPlacementCount = document.querySelector('[data-cabinet-placement-count]');
            this.rackFaceBadge = document.querySelector('[data-rack-face-badge]');
            this.rackHoverCard = document.querySelector('[data-rack-hover-card]');

            this.roomList = new RoomList(this, document.querySelector('[data-room-list]'));
            this.cabinetList = new CabinetList(this, document.querySelector('[data-cabinet-list]'));
            this.unplacedDevices = new UnplacedDevices(this, document.querySelector('[data-unplaced-devices]'));
            this.rackView = new RackView(this, document.querySelector('[data-rack-view]'));
            this.drawer = new DeviceDetailsDrawer(this, document.querySelector('[data-device-drawer]'));
        }

        initialize() {
            this.bindControls();
            this.applyRooms(this.state.rooms);
            this.render();
            this.refreshAll().catch((error) => this.showError(error.message || 'Unable to load cabinet room data.'));
        }

        bindControls() {
            let resizeFrame = null;
            window.addEventListener('resize', () => {
                if (resizeFrame) {
                    window.cancelAnimationFrame(resizeFrame);
                }
                resizeFrame = window.requestAnimationFrame(() => {
                    this.render();
                });
            });

            this.roomSearch?.addEventListener('input', (event) => {
                this.state.roomQuery = event.target.value || '';
                this.render();
            });

            this.deviceSearch?.addEventListener('input', (event) => {
                this.state.deviceQuery = event.target.value || '';
                this.render();
            });

            this.roomForm?.addEventListener('submit', async (event) => {
                event.preventDefault();
                const form = event.currentTarget;
                const formData = new FormData(form);

                try {
                    await this.requestJson(this.config.routes.storeRoom, {
                        method: 'POST',
                        body: {
                            name: formData.get('name'),
                            location: formData.get('location'),
                            notes: formData.get('notes'),
                        },
                    });
                    form.reset();
                    await this.refreshRooms(true);
                    this.hideError();
                } catch (error) {
                    this.showError(error.message);
                }
            });

            this.cabinetForm?.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!this.state.selectedRoomId) {
                    this.showError('Select a room before creating a cabinet.');
                    return;
                }

                const form = event.currentTarget;
                const formData = new FormData(form);
                const endpoint = this.interpolate(this.config.routes.cabinets, this.state.selectedRoomId, '__ROOM__');

                try {
                    await this.requestJson(endpoint, {
                        method: 'POST',
                        body: {
                            name: formData.get('name'),
                            size_u: Number(formData.get('size_u')),
                            manufacturer: formData.get('manufacturer'),
                            model: formData.get('model'),
                        },
                    });
                    form.reset();
                    const sizeField = form.querySelector('[name="size_u"]');
                    if (sizeField) {
                        sizeField.value = '42';
                    }
                    await this.refreshRooms(true);
                    this.hideError();
                } catch (error) {
                    this.showError(error.message);
                }
            });

            this.refreshUnplacedButton?.addEventListener('click', () => {
                this.fetchUnplacedDevices().catch((error) => this.showError(error.message));
            });

            this.refreshRackButton?.addEventListener('click', () => {
                this.refreshPlacements().catch((error) => this.showError(error.message));
            });

            this.faceButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    this.state.selectedFace = button.dataset.face || 'front';
                    this.state.dragPreview = null;
                    this.hideRackHoverCard();
                    this.render();
                });
            });

            this.zoomButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const zoom = Number(button.dataset.rackZoom || 1);
                    if (![1, 2, 4, 6].includes(zoom)) {
                        return;
                    }
                    this.state.rackZoom = zoom;
                    this.render();
                });
            });

            this.fullscreenButton?.addEventListener('click', () => {
                this.toggleRackFullscreen();
            });

            document.addEventListener('fullscreenchange', () => {
                this.state.rackFullscreen = document.fullscreenElement === this.rackStage;
                this.render();
            });

            this.rackView.root?.addEventListener('mousemove', (event) => {
                const target = event.target.closest('[data-rack-hover]');
                if (!target || !this.rackView.root.contains(target)) {
                    this.hideRackHoverCard();
                    return;
                }

                this.showRackHoverCard(target, event.clientX, event.clientY);
            });

            this.rackView.root?.addEventListener('mouseleave', () => {
                this.hideRackHoverCard();
            });
        }

        async refreshAll() {
            await this.refreshRooms(true);
            await Promise.all([
                this.refreshPlacements(),
                this.fetchUnplacedDevices(),
            ]);
        }

        async refreshRooms(loadCabinet) {
            const response = await this.requestJson(this.config.routes.rooms);
            this.applyRooms(response.rooms || []);
            this.render();

            if (loadCabinet) {
                await this.refreshPlacements();
            }
        }

        applyRooms(rooms) {
            this.state.rooms = Array.isArray(rooms) ? rooms : [];

            const roomIds = this.state.rooms.map((room) => room.id);
            if (!roomIds.includes(this.state.selectedRoomId)) {
                this.state.selectedRoomId = roomIds[0] || 0;
            }

            const room = this.selectedRoom();
            const cabinetIds = (room?.cabinets || []).map((cabinet) => cabinet.id);
            if (!cabinetIds.includes(this.state.selectedCabinetId)) {
                this.state.selectedCabinetId = cabinetIds[0] || 0;
            }

            this.state.selectedCabinet = this.selectedCabinet();
            this.syncUrlState();
        }

        selectedRoom() {
            return this.state.rooms.find((room) => room.id === this.state.selectedRoomId) || null;
        }

        selectedCabinet() {
            const room = this.selectedRoom();
            return room?.cabinets?.find((cabinet) => cabinet.id === this.state.selectedCabinetId) || null;
        }

        async selectRoom(roomId) {
            if (roomId === this.state.selectedRoomId) {
                return;
            }

            this.state.selectedRoomId = roomId;
            this.applyRooms(this.state.rooms);
            this.render();
            await this.refreshPlacements();
        }

        async selectCabinet(cabinetId) {
            if (cabinetId === this.state.selectedCabinetId) {
                return;
            }

            this.state.selectedCabinetId = cabinetId;
            this.state.selectedCabinet = this.selectedCabinet();
            this.state.dragPreview = null;
            this.render();
            await this.refreshPlacements();
        }

        async refreshPlacements() {
            const cabinet = this.selectedCabinet();
            this.state.selectedCabinet = cabinet;

            if (!cabinet) {
                this.state.placements = [];
                this.render();
                return;
            }

            const endpoint = this.interpolate(this.config.routes.placements, cabinet.id, '__CABINET__');
            const response = await this.requestJson(endpoint);
            this.state.placements = Array.isArray(response.placements) ? response.placements : [];
            this.render();
        }

        async fetchUnplacedDevices() {
            const response = await this.requestJson(this.config.routes.unplacedDevices);
            this.state.unplacedDevices = Array.isArray(response.devices) ? response.devices : [];

            if (this.state.selectedDeviceId && !this.state.selectedPlacementId) {
                const match = this.state.unplacedDevices.find((device) => device.id === this.state.selectedDeviceId);
                if (match) {
                    this.state.selectedDeviceHeightU = match.default_height_u || 1;
                }
            }

            this.render();
        }

        async selectDevice(deviceId, placementId) {
            this.state.selectedDeviceId = deviceId;
            this.state.selectedPlacementId = placementId;
            this.state.selectedDeviceHeightU = this.lookupDeviceHeight(deviceId);
            this.state.detailsLoading = true;
            this.render();

            try {
                const endpoint = this.interpolate(this.config.routes.deviceDetails, deviceId, '__DEVICE__');
                const response = await this.requestJson(endpoint);
                this.state.selectedDevice = response.device || null;
                this.state.selectedPlacementId = response.device?.placement?.id || placementId;
                this.state.selectedDeviceHeightU = response.device?.placement?.height_u || this.lookupDeviceHeight(deviceId);
                this.state.detailsLoading = false;
                this.render();
                this.startDeviceStream(deviceId);
                this.hideError();
            } catch (error) {
                this.state.detailsLoading = false;
                this.render();
                this.showError(error.message);
            }
        }

        setSuggestedStart(startU) {
            this.state.suggestedStartU = startU;
            if (this.state.selectedDevice) {
                this.render();
            }
        }

        beginDrag(payload) {
            this.state.dragPayload = payload;
        }

        endDrag() {
            this.state.dragPayload = null;
            this.state.dragPreview = null;
            this.render();
        }

        previewDrop(startU, slot) {
            if (!this.state.dragPayload || !this.state.selectedCabinet) {
                return;
            }

            const ignorePlacementId = this.state.dragPayload.kind === 'placement'
                ? this.state.dragPayload.placementId
                : null;
            const valid = this.canPlace(startU, this.state.dragPayload.heightU, this.state.selectedFace, ignorePlacementId);

            this.state.dragPreview = {
                startU,
                state: valid ? 'valid' : 'invalid',
            };

            if (slot) {
                slot.dataset.dropState = valid ? 'valid' : 'invalid';
            }
        }

        clearDropPreview(slot) {
            if (slot) {
                slot.dataset.dropState = '';
            }

            if (this.state.dragPreview) {
                this.state.dragPreview = null;
                this.render();
            }
        }

        async handleDrop(startU) {
            if (!this.state.dragPayload || !this.state.selectedCabinet) {
                return;
            }

            const payload = this.state.dragPayload;
            const ignorePlacementId = payload.kind === 'placement' ? payload.placementId : null;
            if (!this.canPlace(startU, payload.heightU, this.state.selectedFace, ignorePlacementId)) {
                this.showError('The selected U range is not available on this cabinet face.');
                this.endDrag();
                return;
            }

            try {
                if (payload.kind === 'device') {
                    const endpoint = this.interpolate(this.config.routes.placements, this.state.selectedCabinet.id, '__CABINET__');
                    await this.requestJson(endpoint, {
                        method: 'POST',
                        body: {
                            device_id: payload.deviceId,
                            start_u: startU,
                            height_u: payload.heightU,
                            face: this.state.selectedFace,
                        },
                    });
                    await this.selectDevice(payload.deviceId, null);
                } else {
                    const endpoint = this.interpolate(this.config.routes.placement, payload.placementId, '__PLACEMENT__');
                    await this.requestJson(endpoint, {
                        method: 'PATCH',
                        body: {
                            cabinet_id: this.state.selectedCabinet.id,
                            start_u: startU,
                            height_u: payload.heightU,
                            face: this.state.selectedFace,
                        },
                    });
                    await this.selectDevice(payload.deviceId, payload.placementId);
                }

                await Promise.all([
                    this.refreshRooms(false),
                    this.refreshPlacements(),
                    this.fetchUnplacedDevices(),
                ]);
                this.hideError();
            } catch (error) {
                this.showError(error.message);
            } finally {
                this.endDrag();
            }
        }

        async submitPlacementForm(formData) {
            if (!this.state.selectedDeviceId || !this.state.selectedCabinet) {
                this.showError('Select a device and cabinet before saving placement.');
                return;
            }

            const startU = Number(formData.get('start_u'));
            const heightU = Number(formData.get('height_u'));
            const face = String(formData.get('face') || this.state.selectedFace);

            try {
                if (this.state.selectedDevice?.placement) {
                    const endpoint = this.interpolate(this.config.routes.placement, this.state.selectedDevice.placement.id, '__PLACEMENT__');
                    await this.requestJson(endpoint, {
                        method: 'PATCH',
                        body: {
                            cabinet_id: this.state.selectedCabinet.id,
                            start_u: startU,
                            height_u: heightU,
                            face,
                        },
                    });
                } else {
                    const endpoint = this.interpolate(this.config.routes.placements, this.state.selectedCabinet.id, '__CABINET__');
                    await this.requestJson(endpoint, {
                        method: 'POST',
                        body: {
                            device_id: this.state.selectedDeviceId,
                            start_u: startU,
                            height_u: heightU,
                            face,
                        },
                    });
                }

                await Promise.all([
                    this.refreshRooms(false),
                    this.refreshPlacements(),
                    this.fetchUnplacedDevices(),
                ]);
                await this.selectDevice(this.state.selectedDeviceId, this.state.selectedPlacementId);
                this.hideError();
            } catch (error) {
                this.showError(error.message);
            }
        }

        async removeSelectedPlacement() {
            const placementId = this.state.selectedDevice?.placement?.id;
            if (!placementId) {
                return;
            }

            if (!window.confirm('Remove this device from the cabinet?')) {
                return;
            }

            try {
                const endpoint = this.interpolate(this.config.routes.placement, placementId, '__PLACEMENT__');
                await this.requestJson(endpoint, { method: 'DELETE' });
                const currentDeviceId = this.state.selectedDeviceId;
                this.state.selectedPlacementId = null;
                await Promise.all([
                    this.refreshRooms(false),
                    this.refreshPlacements(),
                    this.fetchUnplacedDevices(),
                ]);
                await this.selectDevice(currentDeviceId, null);
                this.hideError();
            } catch (error) {
                this.showError(error.message);
            }
        }

        startDeviceStream(deviceId) {
            this.stopLiveUpdates();

            const streamUrl = this.interpolate(this.config.routes.deviceStream, deviceId, '__DEVICE__');
            if (typeof window.EventSource === 'undefined') {
                this.startPolling(deviceId);
                return;
            }

            const stream = new window.EventSource(streamUrl);
            this.stream = stream;

            stream.addEventListener('device', (event) => {
                try {
                    const payload = JSON.parse(event.data || '{}');
                    if (payload.device && this.state.selectedDeviceId === payload.device.id) {
                        this.state.selectedDevice = payload.device;
                        this.state.selectedPlacementId = payload.device?.placement?.id || null;
                        this.render();
                    }
                } catch (error) {
                    console.error('Cabinet room stream parse failed', error);
                }
            });

            stream.onerror = () => {
                this.stopStream();
                this.startPolling(deviceId);
            };
        }

        startPolling(deviceId) {
            this.pollTimer = window.setInterval(async () => {
                if (this.state.selectedDeviceId !== deviceId) {
                    this.stopPolling();
                    return;
                }

                try {
                    const endpoint = this.interpolate(this.config.routes.deviceDetails, deviceId, '__DEVICE__');
                    const response = await this.requestJson(endpoint);
                    if (response.device && this.state.selectedDeviceId === response.device.id) {
                        this.state.selectedDevice = response.device;
                        this.state.selectedPlacementId = response.device?.placement?.id || null;
                        this.render();
                    }
                } catch (error) {
                    console.error('Cabinet room polling failed', error);
                }
            }, 10000);
        }

        stopLiveUpdates() {
            this.stopStream();
            this.stopPolling();
        }

        stopStream() {
            if (this.stream) {
                this.stream.close();
                this.stream = null;
            }
        }

        stopPolling() {
            if (this.pollTimer) {
                window.clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        }

        lookupDeviceHeight(deviceId) {
            const placed = this.state.placements.find((placement) => placement.device_id === deviceId);
            if (placed) {
                return placed.height_u;
            }

            const unplaced = this.state.unplacedDevices.find((device) => device.id === deviceId);
            return unplaced?.default_height_u || 1;
        }

        canPlace(startU, heightU, face, ignorePlacementId) {
            const cabinet = this.state.selectedCabinet;
            if (!cabinet || startU < 1 || heightU < 1) {
                return false;
            }

            const endU = startU + heightU - 1;
            if (endU > cabinet.size_u) {
                return false;
            }

            return !this.state.placements.some((placement) => {
                if (placement.face !== face) {
                    return false;
                }
                if (ignorePlacementId && placement.id === ignorePlacementId) {
                    return false;
                }

                return placement.start_u <= endU && placement.end_u >= startU;
            });
        }

        async toggleRackFullscreen() {
            if (!this.rackStage) {
                return;
            }

            try {
                if (document.fullscreenElement === this.rackStage) {
                    await document.exitFullscreen();
                    return;
                }

                await this.rackStage.requestFullscreen();
            } catch (error) {
                this.showError('Fullscreen mode is not available in this browser/session.');
            }
        }

        render() {
            const room = this.selectedRoom();
            const cabinet = this.selectedCabinet();
            this.state.selectedCabinet = cabinet;
            this.hideRackHoverCard();

            this.statsRooms.textContent = String(this.state.rooms.length);
            this.statsCabinets.textContent = String(this.state.rooms.reduce((carry, item) => carry + (item.cabinets?.length || 0), 0));
            this.statsUnplaced.textContent = String(this.state.unplacedDevices.length);
            this.roomsSummary.textContent = this.state.rooms.length ? `${this.state.rooms.length} total` : 'No rooms yet';
            this.cabinetsSummary.textContent = room ? `${room.cabinets?.length || 0} in ${room.name}` : 'Select a room';
            this.selectedRoomName.textContent = room?.name || 'No room selected';
            this.selectedCabinetName.textContent = cabinet?.name || 'No cabinet selected';
            this.rackTitle.textContent = cabinet ? cabinet.name : 'Select a cabinet to start';
            this.rackSubtitle.textContent = cabinet
                ? `${cabinet.size_u}U cabinet${cabinet.manufacturer || cabinet.model ? ` | ${[cabinet.manufacturer, cabinet.model].filter(Boolean).join(' ')}` : ''}`
                : 'Choose a room and cabinet from the left to view rack units and placements.';
            this.cabinetSize.textContent = cabinet ? `${cabinet.size_u}U` : '0U';
            this.cabinetOccupied.textContent = cabinet ? `${this.occupiedUnits()}U` : '0U';
            if (this.cabinetFree) {
                this.cabinetFree.textContent = cabinet ? `${Math.max(cabinet.size_u - this.occupiedUnits(), 0)}U` : '0U';
            }
            this.cabinetPlacementCount.textContent = cabinet ? String(this.state.placements.filter((placement) => placement.face === this.state.selectedFace).length) : '0';
            this.rackFaceBadge.textContent = `${capitalize(this.state.selectedFace)} Face`;

            this.faceButtons.forEach((button) => {
                button.dataset.active = button.dataset.face === this.state.selectedFace ? 'true' : 'false';
            });
            this.zoomButtons.forEach((button) => {
                button.dataset.active = Number(button.dataset.rackZoom || 1) === this.state.rackZoom ? 'true' : 'false';
            });
            if (this.rackStage) {
                this.rackStage.dataset.fullscreen = this.state.rackFullscreen ? 'true' : 'false';
            }
            if (this.fullscreenButton) {
                this.fullscreenButton.dataset.active = this.state.rackFullscreen ? 'true' : 'false';
                this.fullscreenButton.setAttribute('aria-pressed', this.state.rackFullscreen ? 'true' : 'false');
            }
            if (this.fullscreenButtonLabel) {
                this.fullscreenButtonLabel.textContent = this.state.rackFullscreen ? 'Exit Fullscreen' : 'Fullscreen';
            }
            if (this.fullscreenButtonIcon) {
                this.fullscreenButtonIcon.textContent = this.state.rackFullscreen ? 'fullscreen_exit' : 'fullscreen';
            }

            this.roomList.render(this.state.rooms, this.state.selectedRoomId, this.state.roomQuery);
            this.cabinetList.render(room, this.state.selectedCabinetId, this.state.roomQuery);
            this.unplacedDevices.render(this.state.unplacedDevices, this.state.selectedDeviceId, this.state.deviceQuery);
            this.rackView.render(cabinet, this.state.placements, this.state.selectedFace, this.state.selectedPlacementId, this.state.dragPreview);
            this.drawer.render(this.state);
        }

        occupiedUnits() {
            return this.state.placements
                .filter((placement) => placement.face === this.state.selectedFace)
                .reduce((carry, placement) => carry + placement.height_u, 0);
        }

        showError(message) {
            if (!this.pageError) {
                return;
            }

            this.pageError.textContent = message;
            this.pageError.classList.remove('hidden');
        }

        hideError() {
            if (!this.pageError) {
                return;
            }

            this.pageError.classList.add('hidden');
            this.pageError.textContent = '';
        }

        showRackHoverCard(target, clientX, clientY) {
            if (!this.rackHoverCard) {
                return;
            }

            const title = target.dataset.hoverTitle || '';
            const part = target.dataset.hoverPart || '';
            const status = target.dataset.hoverStatus || '';
            const tone = target.dataset.hoverTone || 'unknown';
            const meta = target.dataset.hoverMeta || '';

            if (!title && !part && !status) {
                this.hideRackHoverCard();
                return;
            }

            this.rackHoverCard.innerHTML = `
                <div class="cabinet-room-hover-card-title">${escapeHtml(title || 'Device')}</div>
                ${part ? `<div class="cabinet-room-hover-card-part">${escapeHtml(part)}</div>` : ''}
                ${meta ? `<div class="cabinet-room-hover-card-meta">${escapeHtml(meta)}</div>` : ''}
                ${status ? `
                    <div class="cabinet-room-hover-card-status" data-tone="${escapeHtml(tone)}">
                        <span class="cabinet-room-hover-card-status-dot"></span>
                        <span>${escapeHtml(status)}</span>
                    </div>
                ` : ''}
            `;

            this.rackHoverCard.hidden = false;

            const offsetX = 18;
            const offsetY = 18;
            const rect = this.rackHoverCard.getBoundingClientRect();
            const maxLeft = Math.max(window.innerWidth - rect.width - 12, 12);
            const maxTop = Math.max(window.innerHeight - rect.height - 12, 12);
            const left = Math.min(clientX + offsetX, maxLeft);
            const top = Math.min(clientY + offsetY, maxTop);

            this.rackHoverCard.style.left = `${Math.max(left, 12)}px`;
            this.rackHoverCard.style.top = `${Math.max(top, 12)}px`;
        }

        hideRackHoverCard() {
            if (!this.rackHoverCard) {
                return;
            }

            this.rackHoverCard.hidden = true;
            this.rackHoverCard.innerHTML = '';
            this.rackHoverCard.style.left = '';
            this.rackHoverCard.style.top = '';
        }

        async requestJson(url, options = {}) {
            const requestOptions = {
                method: options.method || 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    ...options.headers,
                },
                credentials: 'same-origin',
            };

            if (options.body !== undefined) {
                requestOptions.headers['Content-Type'] = 'application/json';
                requestOptions.body = JSON.stringify(options.body);
            }

            const response = await fetch(url, requestOptions);
            const contentType = response.headers.get('content-type') || '';
            const payload = contentType.includes('application/json')
                ? await response.json()
                : null;

            if (!response.ok) {
                throw new Error(payload?.message || `Request failed with status ${response.status}.`);
            }

            return payload || {};
        }

        interpolate(template, value, token) {
            return String(template || '').replace(token, String(value));
        }

        syncUrlState() {
            const url = new URL(window.location.href);
            if (this.state.selectedRoomId) {
                url.searchParams.set('room', String(this.state.selectedRoomId));
            } else {
                url.searchParams.delete('room');
            }

            if (this.state.selectedCabinetId) {
                url.searchParams.set('cabinet', String(this.state.selectedCabinetId));
            } else {
                url.searchParams.delete('cabinet');
            }

            window.history.replaceState({}, '', url.toString());
        }
    }

    function detailRow(label, value) {
        return `
            <div>
                <dt class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">${escapeHtml(label)}</dt>
                <dd class="mt-1 text-sm font-medium text-slate-900">${escapeHtml(value)}</dd>
            </div>
        `;
    }

    function equipmentKindFor(device) {
        const type = String(device?.type || '').trim().toUpperCase();
        if (type === 'CISCO' || type === 'SWITCH') {
            return 'switch';
        }
        if (type === 'SERVER') {
            return 'server';
        }
        if (type === 'MIKROTIK' || type === 'ROUTER') {
            return 'router';
        }
        if (type === 'OLT') {
            return 'optical';
        }
        if (type === 'MIMOSA' || type === 'WIRELESS') {
            return 'wireless';
        }
        return 'generic';
    }

    function buildEquipmentFacade(placement, density) {
        const equipmentKind = equipmentKindFor(placement.device);

        if (equipmentKind === 'switch') {
            return renderSwitchFacade(placement.device, density);
        }

        if (equipmentKind === 'server') {
            const bayCount = placement.height_u >= 2 ? 8 : 4;
            const psuCount = placement.height_u >= 2 ? 2 : 1;
            return `
                <div class="cabinet-room-equipment-stack">
                    ${renderPanelStrip({
                        leds: [
                            { label: 'Status', lit: true },
                            { label: 'Drive', lit: true, tone: 'amber' },
                            { label: 'ID', lit: placement.height_u >= 2, tone: 'blue' },
                        ],
                        sockets: ['usb', 'console'],
                        buttons: ['power', 'reset'],
                    })}
                    <div class="flex items-center gap-2">
                        ${renderScreen()}
                        <div class="w-full">${renderVents(6)}</div>
                        ${renderPowerSupplies(psuCount)}
                    </div>
                    ${renderPortGroup('Drive bays', renderDriveBays(bayCount, Math.min(2, bayCount)))}
                </div>
            `;
        }

        if (equipmentKind === 'router') {
            return `
                <div class="cabinet-room-equipment-stack">
                    ${renderPanelStrip({
                        leds: [
                            { label: 'PWR', lit: true },
                            { label: 'ACT', lit: true, tone: 'amber' },
                            { label: 'VPN', lit: true, tone: 'blue' },
                        ],
                        sockets: ['console', 'usb', 'mgmt'],
                        buttons: ['power', 'reset'],
                    })}
                    <div class="flex items-center gap-2">
                        ${renderScreen()}
                        ${renderModule()}
                    </div>
                    ${renderPortGroup('WAN / LAN', renderPorts(placement.device, 'Port', density === 'ultra-compact' ? 6 : 10, 2))}
                </div>
            `;
        }

        if (equipmentKind === 'optical') {
            return `
                <div class="cabinet-room-equipment-stack">
                    ${renderPanelStrip({
                        leds: [
                            { label: 'PON', lit: true },
                            { label: 'LOS', lit: false, tone: 'amber' },
                            { label: 'ALM', lit: false, tone: 'amber' },
                        ],
                        sockets: ['console', 'mgmt'],
                        buttons: ['power'],
                    })}
                    ${renderPortGroup('Optical cages', renderSfps(placement.device, 'Optical cage', density === 'ultra-compact' ? 4 : 8, 2))}
                    ${renderPortGroup('Service ports', renderPorts(placement.device, 'Port', density === 'ultra-compact' ? 4 : 8, 1))}
                </div>
            `;
        }

        if (equipmentKind === 'wireless') {
            return `
                <div class="cabinet-room-equipment-stack">
                    ${renderPanelStrip({
                        leds: [
                            { label: 'PWR', lit: true },
                            { label: 'RF', lit: true, tone: 'blue' },
                            { label: 'LAN', lit: true },
                        ],
                        sockets: ['mgmt'],
                        buttons: ['power'],
                    })}
                    <div class="flex items-center gap-2">
                        ${renderScreen()}
                        ${renderVents(5)}
                    </div>
                    ${renderPortGroup('Ethernet', renderPorts(placement.device, 'Port', density === 'ultra-compact' ? 4 : 6, 1))}
                </div>
            `;
        }

        return `
            <div class="cabinet-room-equipment-stack">
                ${renderPanelStrip({
                    leds: [
                        { label: 'Status', lit: true },
                        { label: 'Alarm', lit: false, tone: 'amber' },
                    ],
                    sockets: ['console'],
                    buttons: ['power'],
                })}
                ${renderVents(6)}
                ${renderPortGroup('Interfaces', renderPorts(placement.device, 'Port', density === 'ultra-compact' ? 4 : 8, 1))}
            </div>
        `;
    }

    function renderPorts(device, partLabel, count, litCount) {
        return `<div class="cabinet-room-port-bank">${Array.from({ length: count }, (_, index) => renderSyntheticPortCell(device, `${partLabel} ${index + 1}`, index < litCount, false)).join('')}</div>`;
    }

    function renderPortRow(device, count, litCount, columns, extraClass = '', partLabel = 'Port') {
        const uplink = extraClass.includes('is-uplink');
        return `<div class="cabinet-room-port-row ${extraClass}" style="grid-template-columns: repeat(${columns}, minmax(0, 1fr));">${Array.from({ length: count }, (_, index) => renderSyntheticPortCell(device, `${partLabel} ${index + 1}`, index < litCount, uplink)).join('')}</div>`;
    }

    function renderDriveBays(count, litCount = 0) {
        return `<div class="cabinet-room-drive-bays">${Array.from({ length: count }, (_, index) => `<span class="cabinet-room-drive-bay ${index < litCount ? 'is-lit' : ''}"></span>`).join('')}</div>`;
    }

    function renderSfps(device, partLabel, count, litCount) {
        return `<div class="cabinet-room-sfp-bank">${Array.from({ length: count }, (_, index) => renderSyntheticSfpCell(device, `${partLabel} ${index + 1}`, index < litCount)).join('')}</div>`;
    }

    function renderSyntheticPortCell(device, partName, isLit, uplink) {
        const tone = isLit ? 'online' : 'offline';
        const status = isLit ? 'Online' : 'Offline';
        const meta = [device?.model, device?.ip_address].filter(Boolean).join(' • ');

        return `
            <span
                class="cabinet-room-port ${isLit ? 'is-lit' : ''} ${uplink ? 'is-uplink' : ''}"
                data-status-tone="${tone}"
                data-rack-hover
                data-hover-title="${escapeHtml(device?.name || 'Device')}"
                data-hover-part="${escapeHtml(partName)}"
                data-hover-status="${escapeHtml(status)}"
                data-hover-tone="${escapeHtml(tone)}"
                ${meta ? `data-hover-meta="${escapeHtml(meta)}"` : ''}
                title="${escapeHtml(`${device?.name || 'Device'} | ${partName} | ${status}`)}"
            >
                <span class="cabinet-room-port-link"></span>
                <span class="cabinet-room-port-label">${escapeHtml(String(partName).replace(/^[^\d]*(\d+)$/, '$1'))}</span>
            </span>
        `;
    }

    function renderSyntheticSfpCell(device, partName, isLit) {
        const tone = isLit ? 'online' : 'offline';
        const status = isLit ? 'Online' : 'Offline';
        const meta = [device?.model, device?.ip_address].filter(Boolean).join(' • ');

        return `
            <span
                class="cabinet-room-sfp ${isLit ? 'is-lit' : ''}"
                data-rack-hover
                data-hover-title="${escapeHtml(device?.name || 'Device')}"
                data-hover-part="${escapeHtml(partName)}"
                data-hover-status="${escapeHtml(status)}"
                data-hover-tone="${escapeHtml(tone)}"
                ${meta ? `data-hover-meta="${escapeHtml(meta)}"` : ''}
                title="${escapeHtml(`${device?.name || 'Device'} | ${partName} | ${status}`)}"
            ></span>
        `;
    }

    function renderVents(count) {
        return `<div class="cabinet-room-vent-bank">${Array.from({ length: count }, () => '<span class="cabinet-room-vent"></span>').join('')}</div>`;
    }

    function renderScreen() {
        return '<span class="cabinet-room-screen"></span>';
    }

    function renderModule() {
        return '<span class="cabinet-room-module"></span>';
    }

    function formatMetricValue(label, value) {
        if (value === null || value === undefined || String(value).trim() === '') {
            return 'N/A';
        }

        if (label === 'Uptime') {
            return escapeHtml(formatUptimeValue(value));
        }

        if (label === 'Temperature') {
            return formatTemperatureValue(value);
        }

        return escapeHtml(String(value));
    }

    function formatUptimeValue(value) {
        const raw = String(value).trim();
        const numeric = Number(raw);

        if (raw !== '' && Number.isFinite(numeric) && /^-?\d+(?:\.\d+)?$/.test(raw)) {
            return formatDurationFromMinutes(Math.max(Math.round(numeric / 60), 0));
        }

        const matches = Array.from(raw.toLowerCase().matchAll(/(\d+)\s*(weeks?|days?|hours?|hrs?|hr|minutes?|mins?|min|seconds?|secs?|sec)\b/g));
        if (!matches.length) {
            return raw;
        }

        let totalMinutes = 0;
        matches.forEach((match) => {
            const amount = Number(match[1] || 0);
            const unit = match[2] || '';

            if (unit.startsWith('week')) {
                totalMinutes += amount * 7 * 24 * 60;
                return;
            }

            if (unit.startsWith('day')) {
                totalMinutes += amount * 24 * 60;
                return;
            }

            if (unit.startsWith('hour') || unit.startsWith('hr')) {
                totalMinutes += amount * 60;
                return;
            }

            if (unit.startsWith('min')) {
                totalMinutes += amount;
                return;
            }

            if (unit.startsWith('sec')) {
                totalMinutes += Math.round(amount / 60);
            }
        });

        return formatDurationFromMinutes(totalMinutes);
    }

    function formatDurationFromMinutes(totalMinutes) {
        const safeMinutes = Math.max(Number(totalMinutes) || 0, 0);
        const days = Math.floor(safeMinutes / (24 * 60));
        const hours = Math.floor((safeMinutes % (24 * 60)) / 60);
        const minutes = safeMinutes % 60;

        return `${days} day${days === 1 ? '' : 's'}, ${hours} hour${hours === 1 ? '' : 's'}, ${minutes} minute${minutes === 1 ? '' : 's'}`;
    }

    function formatTemperatureValue(value) {
        const normalized = String(value)
            .replace(/\r\n/g, '\n')
            .replace(/\s*\|\s*/g, '\n')
            .replace(/\s*\/\s*/g, '\n')
            .replace(/(-?\d+(?:\.\d+)?)\s*(?:°|º|â°|Â°|\?)?\s*([cf])\b/gi, '$1°$2')
            .replace(/°([cf])/gi, (_, unit) => `°${unit.toUpperCase()}`);

        const lines = normalized
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean)
            .map((line) => escapeHtml(line));

        return lines.join('<br>');
    }

    function renderPortGroup(label, content) {
        return `
            <div class="cabinet-room-port-group">
                <div class="cabinet-room-port-legend">${escapeHtml(label)}</div>
                ${content}
            </div>
        `;
    }

    function renderPanelStrip(config) {
        return `
            <div class="cabinet-room-panel-strip">
                ${renderLedBank(config.leds || [])}
                ${renderSocketBank(config.sockets || [])}
                ${renderButtonBank(config.buttons || [])}
            </div>
        `;
    }

    function renderLedBank(leds) {
        if (!leds.length) {
            return '';
        }

        return `<div class="cabinet-room-led-bank">${leds.map((led) => `<span class="cabinet-room-mini-led ${led.lit ? 'is-lit' : ''} ${led.tone ? `is-${led.tone}` : ''}" title="${escapeHtml(led.label || 'Indicator')}"></span>`).join('')}</div>`;
    }

    function renderSocketBank(sockets) {
        if (!sockets.length) {
            return '';
        }

        return `<div class="cabinet-room-socket-bank">${sockets.map((socket) => `<span class="cabinet-room-socket is-${socket}" title="${escapeHtml(socket.toUpperCase())}"></span>`).join('')}</div>`;
    }

    function renderButtonBank(buttons) {
        if (!buttons.length) {
            return '';
        }

        return `<div class="cabinet-room-button-bank">${buttons.map((button) => `<span class="cabinet-room-button is-${button}" title="${escapeHtml(button.toUpperCase())}"></span>`).join('')}</div>`;
    }

    function renderPowerSupplies(count) {
        return `<div class="cabinet-room-power-supplies">${Array.from({ length: count }, () => '<span class="cabinet-room-psu"></span>').join('')}</div>`;
    }

    function renderSwitchFacade(device, density) {
        const spec = switchPanelSpec(device);
        const livePorts = normalizeRackInterfaces(device?.rack_interfaces);

        if (livePorts.length) {
            const accessPorts = livePorts.filter((port) => port.family !== 'uplink');
            const uplinkPorts = livePorts.filter((port) => port.family === 'uplink');
            const onlineCount = livePorts.filter((port) => port.statusTone === 'online').length;

            return `
                <div class="cabinet-room-switch-panel">
                    <div class="cabinet-room-switch-header">
                        <span class="cabinet-room-switch-badge">${escapeHtml(`${livePorts.length}-Port`)}</span>
                        <span class="cabinet-room-switch-label">${escapeHtml(`${spec.label} • ${onlineCount}/${livePorts.length} up`)}</span>
                    </div>
                    ${renderPanelStrip({
                        leds: spec.leds,
                        sockets: spec.sockets,
                        buttons: spec.buttons,
                    })}
                    ${accessPorts.length ? renderPortGroup(spec.accessLabel, renderLivePortRows(device, accessPorts, density)) : ''}
                    ${uplinkPorts.length ? renderPortGroup(spec.uplinkLabel, renderLivePortRows(device, uplinkPorts, density, true)) : ''}
                </div>
            `;
        }

        const rows = spec.rows.map((row) => renderPortRow(device, row.count, row.litCount, row.columns, row.uplink ? 'is-uplink' : '', spec.accessLabel.replace(/s$/i, ''))).join('');
        const uplinks = spec.uplinkCount > 0
            ? renderPortRow(device, spec.uplinkCount, Math.min(2, spec.uplinkCount), spec.uplinkCount > 4 ? 4 : spec.uplinkCount, 'is-uplink', spec.uplinkLabel.replace(/s$/i, ''))
            : '';

        return `
            <div class="cabinet-room-switch-panel">
                <div class="cabinet-room-switch-header">
                    <span class="cabinet-room-switch-badge">${escapeHtml(spec.badge)}</span>
                    <span class="cabinet-room-switch-label">${escapeHtml(spec.label)}</span>
                </div>
                ${renderPanelStrip({
                    leds: spec.leds,
                    sockets: spec.sockets,
                    buttons: spec.buttons,
                })}
                ${renderPortGroup(spec.accessLabel, `<div class="cabinet-room-switch-rows">${rows}</div>`)}
                ${uplinks ? renderPortGroup(spec.uplinkLabel, uplinks) : ''}
            </div>
        `;
    }

    function normalizeRackInterfaces(interfaces) {
        if (!Array.isArray(interfaces)) {
            return [];
        }

        return interfaces
            .filter((port) => port && port.name)
            .map((port) => ({
                name: String(port.name),
                shortName: String(port.short_name || port.name),
                family: String(port.family || 'access'),
                status: String(port.status || 'Unknown'),
                statusTone: statusTone(port.status_tone || port.status || ''),
                description: String(port.description || ''),
                alias: String(port.alias || ''),
                speedBps: Number(port.speed_bps || 0) || 0,
            }));
    }

    function renderLivePortRows(device, ports, density, uplink = false) {
        if (!ports.length) {
            return '';
        }

        const rowSize = resolveLivePortRowSize(ports.length, density, uplink);
        const rows = [];

        for (let index = 0; index < ports.length; index += rowSize) {
            const slice = ports.slice(index, index + rowSize);
            rows.push(`
                <div class="cabinet-room-port-row ${uplink ? 'is-uplink' : ''}" style="grid-template-columns: repeat(${slice.length}, minmax(0, 1fr));">
                    ${slice.map((port) => renderLivePortCell(device, port, uplink)).join('')}
                </div>
            `);
        }

        return `<div class="cabinet-room-switch-rows">${rows.join('')}</div>`;
    }

    function resolveLivePortRowSize(portCount, density, uplink) {
        if (uplink) {
            return portCount >= 8 ? 4 : Math.max(portCount, 1);
        }

        if (portCount >= 48) {
            return 24;
        }

        if (portCount >= 24) {
            return 12;
        }

        if (density === 'ultra-compact') {
            return Math.min(portCount, 8);
        }

        return Math.min(portCount, 12);
    }

    function renderLivePortCell(device, port, uplink) {
        const metaParts = [port.alias, port.description, formatPortSpeed(port.speedBps)].filter(Boolean);

        return `
            <span
                class="cabinet-room-port ${port.statusTone === 'online' ? 'is-lit' : ''} ${uplink ? 'is-uplink' : ''}"
                data-status-tone="${escapeHtml(port.statusTone)}"
                data-rack-hover
                data-hover-title="${escapeHtml(device?.name || 'Cisco device')}"
                data-hover-part="${escapeHtml(port.name)}"
                data-hover-status="${escapeHtml(port.status)}"
                data-hover-tone="${escapeHtml(port.statusTone)}"
                ${metaParts.length ? `data-hover-meta="${escapeHtml(metaParts.join(' • '))}"` : ''}
                title="${escapeHtml(`${device?.name || 'Device'} | ${port.name} | ${port.status}`)}"
            >
                <span class="cabinet-room-port-link"></span>
                <span class="cabinet-room-port-label">${escapeHtml(port.shortName)}</span>
            </span>
        `;
    }

    function formatPortSpeed(speedBps) {
        if (!speedBps || !Number.isFinite(speedBps)) {
            return '';
        }

        if (speedBps >= 100000000000) {
            return `${Math.round(speedBps / 1000000000)}G`;
        }

        if (speedBps >= 1000000000) {
            return `${Math.round(speedBps / 1000000000)}G`;
        }

        if (speedBps >= 1000000) {
            return `${Math.round(speedBps / 1000000)}M`;
        }

        return `${speedBps}bps`;
    }

    function switchPanelSpec(device) {
        const model = String(device?.model || '').trim();
        const normalized = model.toLowerCase();
        let totalPorts = parseSwitchPortCount(normalized);
        let uplinkCount = 4;
        let label = 'Switch Panel';
        let badge = model || 'Switch';
        let accessLabel = 'Access ports';
        let uplinkLabel = 'Uplinks';
        let sockets = ['console', 'usb'];
        let buttons = ['power', 'reset'];
        let leds = [
            { label: 'PWR', lit: true },
            { label: 'STAT', lit: true },
            { label: 'SYS', lit: true, tone: 'amber' },
        ];

        if (normalized.includes('4948')) {
            totalPorts = 48;
            uplinkCount = 4;
            label = 'Catalyst 4948';
            badge = '48-Port';
            accessLabel = 'Gigabit copper';
            uplinkLabel = 'SFP uplinks';
        } else if (normalized.includes('3560')) {
            label = 'Catalyst 3560';
            badge = totalPorts >= 48 ? '48-Port' : '24-Port';
            accessLabel = 'Fast/Gigabit access';
        } else if (normalized.includes('2960')) {
            label = 'Catalyst 2960';
            badge = totalPorts >= 48 ? '48-Port' : '24-Port';
            accessLabel = 'Access stack';
        } else if (normalized.includes('3750')) {
            label = 'Catalyst 3750';
            badge = totalPorts >= 48 ? '48-Port' : '24-Port';
            accessLabel = 'Stack members';
        } else if (normalized.includes('3850')) {
            uplinkCount = 8;
            label = 'Catalyst 3850';
            badge = totalPorts >= 48 ? '48-Port' : '24-Port';
            accessLabel = 'Access / PoE';
            uplinkLabel = 'Modular uplinks';
        } else if (normalized.includes('9300')) {
            uplinkCount = 8;
            label = 'Catalyst 9300';
            badge = totalPorts >= 48 ? '48-Port' : '24-Port';
            accessLabel = 'Access / stack';
            uplinkLabel = 'Network module';
        } else if (normalized.includes('nexus') || normalized.includes('n3k') || normalized.includes('n5k') || normalized.includes('n9k')) {
            totalPorts = totalPorts || 48;
            uplinkCount = normalized.includes('n9k') ? 8 : 4;
            label = 'Nexus Fabric';
            badge = `${totalPorts}-Port`;
            accessLabel = 'Leaf interfaces';
            uplinkLabel = 'Fabric uplinks';
            sockets = ['console', 'mgmt', 'usb'];
            leds = [
                { label: 'PWR', lit: true },
                { label: 'STAT', lit: true },
                { label: 'FAN', lit: true, tone: 'blue' },
            ];
        } else {
            badge = totalPorts ? `${totalPorts}-Port` : 'Switch';
            label = 'Switch Panel';
        }

        totalPorts = totalPorts || 24;
        const rowSize = totalPorts >= 48 ? 24 : (totalPorts >= 24 ? 12 : totalPorts);
        const rows = [];
        let remaining = totalPorts;
        while (remaining > 0) {
            const count = Math.min(rowSize, remaining);
            rows.push({
                count,
                columns: count,
                litCount: Math.min(4, count),
            });
            remaining -= count;
        }

        return {
            badge,
            label,
            rows,
            uplinkCount,
            accessLabel,
            uplinkLabel,
            sockets,
            buttons,
            leds,
        };
    }

    function parseSwitchPortCount(normalizedModel) {
        if (normalizedModel.includes('4948')) {
            return 48;
        }

        const matches = normalizedModel.match(/(?:^|[^0-9])(8|12|16|24|32|48)(?:[^0-9]|$)/g) || [];
        const values = matches
            .map((match) => Number((match.match(/\d+/) || [])[0] || 0))
            .filter(Boolean)
            .sort((left, right) => right - left);

        return values[0] || 0;
    }

    function statusTone(status) {
        const normalized = String(status || '').trim().toLowerCase();
        if (!normalized) {
            return 'unknown';
        }
        if (normalized.includes('warn')) {
            return 'warning';
        }
        if (normalized.includes('error') || normalized.includes('fail') || normalized.includes('critical')) {
            return 'error';
        }
        if (normalized.includes('online') || normalized.includes('up') || normalized.includes('active')) {
            return 'online';
        }
        if (normalized.includes('offline') || normalized.includes('down') || normalized.includes('inactive')) {
            return 'offline';
        }
        return 'unknown';
    }

    function statusBadgeClass(tone) {
        return {
            online: 'bg-emerald-50 text-emerald-700',
            warning: 'bg-amber-50 text-amber-700',
            error: 'bg-red-50 text-red-700',
            offline: 'bg-slate-100 text-slate-700',
            unknown: 'bg-slate-100 text-slate-700',
        }[tone] || 'bg-slate-100 text-slate-700';
    }

    function statusDotClass(tone) {
        return {
            online: 'bg-emerald-500',
            warning: 'bg-amber-500',
            error: 'bg-red-500',
            offline: 'bg-slate-400',
            unknown: 'bg-slate-400',
        }[tone] || 'bg-slate-400';
    }

    function capitalize(value) {
        const normalized = String(value || '');
        return normalized.charAt(0).toUpperCase() + normalized.slice(1);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    const app = new CabinetRoomApp(config);
    app.initialize();

    window.addEventListener('beforeunload', () => {
        app.stopLiveUpdates();
    });
})();
