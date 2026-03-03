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

            const slotHeight = 38;
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

                return `
                    <button type="button" draggable="true" data-placement-id="${placement.id}" data-device-id="${placement.device_id}" data-height-u="${placement.height_u}" data-status-tone="${tone}" class="cabinet-room-device ${selected ? 'is-selected' : ''}" style="bottom:${bottom}px;height:${Math.max(height, slotHeight - 8)}px" aria-label="${escapeHtml(placement.device?.name || 'Rack device')}">
                        <span class="cabinet-room-device-rail"></span>
                        <div class="cabinet-room-device-body">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="cabinet-room-device-led"></span>
                                    <span class="truncate text-sm font-semibold text-slate-100">${escapeHtml(placement.device?.name || 'Unnamed device')}</span>
                                </div>
                                <div class="mt-2 truncate text-xs text-slate-400">${escapeHtml(subtitle || 'No device metadata')}</div>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="cabinet-room-device-chip">${placement.height_u}U</span>
                                    <span class="cabinet-room-device-chip">U${placement.start_u}-${placement.end_u}</span>
                                </div>
                            </div>
                            <div class="flex flex-col items-end justify-between">
                                <span class="cabinet-room-device-chip">${escapeHtml((placement.device?.status || 'unknown').toUpperCase())}</span>
                                <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">${escapeHtml(face)}</span>
                            </div>
                        </div>
                        <span class="cabinet-room-device-rail"></span>
                    </button>
                `;
            }).join('');

            this.root.innerHTML = `
                <div class="cabinet-room-rack-frame">
                    <div class="cabinet-room-rack-rail"></div>
                    <div class="cabinet-room-rack-bay" style="--rack-size-u:${cabinet.size_u};--slot-height:${slotHeight}px;">
                        <div>${slots.join('')}</div>
                        <div class="cabinet-room-placement-layer">${devices}</div>
                    </div>
                    <div class="cabinet-room-rack-rail"></div>
                </div>
            `;
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
                this.root.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-sm text-slate-500">Loading device details...</div>';
                return;
            }

            if (!state.selectedDevice) {
                this.root.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-sm text-slate-500">Select a placed or unplaced device to inspect live status, metrics, and placement details.</div>';
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
                ? 'Select a cabinet first'
                : placement
                    ? (placement.cabinet_id === currentCabinet.id ? 'Update Placement' : 'Move To Selected Cabinet')
                    : 'Place In Selected Cabinet';

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
                    <div class="mt-1 text-sm font-semibold text-slate-900">${escapeHtml(value || 'N/A')}</div>
                </div>
            `).join('');

            this.root.innerHTML = `
                <div class="space-y-5">
                    <section class="rounded-2xl border border-slate-200 bg-white p-4">
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

                    <section class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Latest Metrics</h3>
                            <span class="text-xs text-slate-400">${escapeHtml(device.last_seen_human || 'live')}</span>
                        </div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            ${metricCards}
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Placement Controls</h3>
                                <p class="mt-1 text-xs text-slate-500">${currentCabinet ? `Working in ${escapeHtml(currentCabinet.name)} (${currentCabinet.size_u}U)` : 'Choose a cabinet to place or move this device.'}</p>
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
                                ${placement ? '<button class="inline-flex items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50" type="button" data-remove-placement>Remove From Cabinet</button>' : ''}
                            </div>
                        </form>
                    </section>

                    <details class="rounded-2xl border border-slate-200 bg-white p-4">
                        <summary class="cursor-pointer text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Raw Metadata</summary>
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
            this.cabinetPlacementCount = document.querySelector('[data-cabinet-placement-count]');
            this.rackFaceBadge = document.querySelector('[data-rack-face-badge]');

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
                    this.render();
                });
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

        render() {
            const room = this.selectedRoom();
            const cabinet = this.selectedCabinet();
            this.state.selectedCabinet = cabinet;

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
            this.cabinetPlacementCount.textContent = cabinet ? String(this.state.placements.filter((placement) => placement.face === this.state.selectedFace).length) : '0';
            this.rackFaceBadge.textContent = `${capitalize(this.state.selectedFace)} Face`;

            this.faceButtons.forEach((button) => {
                button.dataset.active = button.dataset.face === this.state.selectedFace ? 'true' : 'false';
            });

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
