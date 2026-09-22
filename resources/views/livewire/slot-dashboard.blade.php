<div class="relative min-h-screen w-full overflow-hidden bg-[#070312] text-white flex flex-col" data-kiosk-root>

    <div class="kiosk-bg absolute inset-0"></div>
    <div class="kiosk-orb pointer-events-none absolute -top-24 -left-24 h-96 w-96 rounded-full bg-teal-400 opacity-30 blur-[100px]"></div>
    <div class="kiosk-orb pointer-events-none absolute top-1/3 -right-32 h-[28rem] w-[28rem] rounded-full bg-fuchsia-500 opacity-25 blur-[120px]"></div>
    <div class="kiosk-orb pointer-events-none absolute -bottom-32 left-1/4 h-96 w-96 rounded-full bg-amber-500 opacity-20 blur-[110px]"></div>
    <div class="pointer-events-none absolute inset-0 kiosk-grain opacity-30"></div>

    <div class="relative z-10 flex w-full max-w-6xl flex-1 flex-col mx-auto px-6 py-6">

        {{-- Page header --}}
        <header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-black uppercase tracking-widest">
                    <span class="text-teal-400">Slot</span> Monitor
                </h1>
                <p class="mt-1 text-sm text-white/60">Live parking occupancy from IR slot sensors</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span id="live-chip"
                      class="hidden items-center gap-2 rounded-full border border-emerald-400/40 bg-emerald-500/10 px-4 py-2 text-xs font-black uppercase tracking-[0.2em] text-emerald-300">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                    </span>
                    <span id="live-chip-label">Live · listening</span>
                </span>
                <label class="flex items-center gap-2 rounded-xl bg-white/10 border border-white/20 px-4 py-2 text-sm font-bold text-white/80">
                    <i class="fas fa-map-location-dot"></i>
                    <select wire:model="lotId" wire:change="refresh"
                            class="bg-transparent text-sm font-bold text-white outline-none [&>option]:text-black">
                        @forelse ($lots as $lot)
                            <option value="{{ $lot->id }}">#{{ $lot->lot_number }} — {{ $lot->name }}</option>
                        @empty
                            <option value="">No parking lots</option>
                        @endforelse
                    </select>
                </label>
                <a href="{{ route('admin.floors') }}"
                   class="flex items-center gap-2 rounded-xl bg-white/10 border border-white/20 px-4 py-2 text-sm font-bold text-white/80 hover:bg-white/20 transition">
                    <i class="fas fa-gear"></i> Configure
                </a>
                <button wire:click="refresh"
                        class="flex items-center gap-2 rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 px-4 py-2 text-sm font-black uppercase tracking-widest text-white shadow hover:brightness-110 transition">
                    <i class="fas fa-rotate"></i> Refresh
                </button>
            </div>
        </header>

        {{-- Summary stats --}}
        <section class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-3xl border border-white/15 bg-white/10 px-6 py-5 shadow-xl backdrop-blur-xl">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-[0.25em] text-white/50">Total Slots</div>
                        <div id="stat-total" class="mt-1 text-4xl font-black">{{ $totalSlots }}</div>
                    </div>
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-500/15 text-xl text-sky-300">
                        <i class="fas fa-square-parking"></i>
                    </span>
                </div>
            </div>

            <div class="rounded-3xl border border-white/15 bg-white/10 px-6 py-5 shadow-xl backdrop-blur-xl">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-[0.25em] text-white/50">Occupied</div>
                        <div id="stat-occupied" class="mt-1 text-4xl font-black text-rose-300">{{ $occupiedSlots }}</div>
                    </div>
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-500/15 text-xl text-rose-300">
                        <i class="fas fa-car"></i>
                    </span>
                </div>
            </div>

            <div class="rounded-3xl border border-white/15 bg-white/10 px-6 py-5 shadow-xl backdrop-blur-xl">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-[0.25em] text-white/50">Free</div>
                        <div id="stat-free" class="mt-1 text-4xl font-black text-emerald-300">{{ $freeSlots }}</div>
                    </div>
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/15 text-xl text-emerald-300">
                        <i class="fas fa-car-side"></i>
                    </span>
                </div>
            </div>

            <div class="rounded-3xl border border-white/15 bg-white/10 px-6 py-5 shadow-xl backdrop-blur-xl">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-xs font-bold uppercase tracking-[0.25em] text-white/50">Occupancy</div>
                        <div id="stat-pct" class="mt-1 text-4xl font-black text-amber-300">{{ $occupancyPct }}%</div>
                    </div>
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-500/15 text-xl text-amber-300">
                        <i class="fas fa-gauge-high"></i>
                    </span>
                </div>
                <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-white/10">
                    <div id="stat-pct-bar"
                         class="h-full rounded-full bg-gradient-to-r from-amber-400 to-orange-500 transition-all duration-500"
                         style="width: {{ $occupancyPct }}%"></div>
                </div>
            </div>
        </section>

        {{-- Floors / slot schematic --}}
        <section class="space-y-8">
            @forelse ($floors as $floor)
                <div wire:key="floor-{{ $floor->id }}"
                     data-floor-section
                     data-floor-number="{{ $floor->floor_number }}"
                     data-lot="{{ $floor->lot->lot_number }}"
                     class="rounded-3xl border border-white/15 bg-white/10 px-6 py-6 shadow-2xl backdrop-blur-xl">

                    <header class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-4">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-teal-400 to-emerald-600 text-xl font-black text-white shadow">
                                {{ $floor->floor_number }}
                            </span>
                            <div>
                                <div class="flex items-center gap-2 text-lg font-bold">
                                    {{ $floor->name }}
                                    <span class="rounded-full bg-teal-500/20 border border-teal-400/40 px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest text-teal-300">
                                        Parking Lot #{{ $floor->lot->lot_number }} — {{ $floor->lot->name }}
                                    </span>
                                </div>
                                <div class="text-xs text-white/50">
                                    <span data-occ-count class="font-semibold text-rose-400">
                                        {{ $floor->slots->where('is_occupied', true)->count() }}
                                    </span>
                                    occupied &bull;
                                    <span data-free-count class="font-semibold text-emerald-400">
                                        {{ $floor->slot_count - $floor->slots->where('is_occupied', true)->count() }}
                                    </span>
                                    free &bull; {{ $floor->slot_count }} total
                                </div>
                            </div>
                        </div>
                        <span class="text-xs font-bold uppercase tracking-[0.3em] text-white/40">Floor {{ $floor->floor_number }}</span>
                    </header>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8">
                        @foreach ($floor->slots as $slot)
                            @php
                                $occupied = (bool) $slot->is_occupied;

                                $cellBase = 'slot-cell relative flex flex-col items-center justify-center gap-1.5 rounded-2xl border bg-gradient-to-br px-3 py-4 text-center transition-all';

                                $freeClass = $cellBase . ' border-emerald-500/60 from-emerald-600/30 to-teal-900/30 shadow-[0_0_25px_-8px_rgba(16,185,129,.5)]';

                                $occClass = $cellBase . ' border-rose-500/60 from-rose-600/40 to-rose-900/40 shadow-[0_0_25px_-8px_rgba(244,63,94,.6)]';
                            @endphp
                            <div wire:key="slot-{{ $slot->id }}"
                                 data-slot-cell
                                 data-floor="{{ $floor->floor_number }}"
                                 data-lot="{{ $floor->lot->lot_number }}"
                                 data-slot="{{ $slot->slot_number }}"
                                 data-occupied="{{ $occupied ? 1 : 0 }}"
                                 data-updated="{{ $slot->last_updated_at?->toIso8601String() }}"
                                 data-occ="{{ $occClass }}"
                                 data-free="{{ $freeClass }}"
                                 class="{{ $occupied ? $occClass : $freeClass }}"
                                 title="Floor {{ $floor->floor_number }} · Slot {{ $slot->slot_number }}">
                                <span class="text-[10px] font-bold uppercase tracking-widest text-white/50">
                                    Place {{ $slot->displayLabel() }}
                                </span>
                                <i data-slot-icon
                                   class="fas {{ $occupied ? 'fa-car text-rose-300' : 'fa-square-parking text-emerald-300' }} text-3xl drop-shadow"></i>
                                <span data-slot-status
                                      class="text-[10px] font-black uppercase tracking-[0.25em] {{ $occupied ? 'text-rose-300' : 'text-emerald-300' }}">
                                    {{ $occupied ? 'Occupied' : 'Free' }}
                                </span>
                                <span data-slot-updated class="text-[9px] text-white/40">
                                    {{ $slot->last_updated_at?->diffForHumans() }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-3xl border border-white/10 bg-white/5 px-6 py-16 text-center">
                    <i class="fas fa-map-location-dot text-5xl text-white/25 mb-4"></i>
                    <div class="text-2xl font-black uppercase tracking-widest text-white/50">No floors configured</div>
                    <p class="mt-2 text-sm text-white/40">Add a floor and its slot count to start monitoring occupancy.</p>
                    <a href="{{ route('admin.floors') }}"
                       class="mt-6 inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-teal-400 to-emerald-600 px-6 py-3 text-sm font-black uppercase tracking-widest text-white shadow hover:brightness-110 transition">
                        <i class="fas fa-gear"></i> Configure Floors
                    </a>
                </div>
            @endforelse
        </section>

        <footer class="mt-10 flex items-center justify-between text-xs text-white/50">
            <span><i class="fas fa-bolt text-amber-400"></i> Live via Reverb</span>
            <span><i class="fas fa-signal text-teal-400"></i> IR slot sensors report occupancy</span>
        </footer>
    </div>

    <style>
        .kiosk-bg {
            background: linear-gradient(135deg, #14104a 0%, #341a7a 22%, #7a1f6b 46%, #bd3a3a 70%, #f26b1d 100%);
            background-size: 350% 350%;
            animation: kioskShift 16s ease-in-out infinite;
        }
        .kiosk-grain {
            background-image: radial-gradient(rgba(255, 255, 255, 0.12) 1px, transparent 1px);
            background-size: 5px 5px;
        }
        .kiosk-orb { animation: kioskFloat 12s ease-in-out infinite; }
        .kiosk-orb:nth-of-type(2) { animation-delay: -4s; }
        .kiosk-orb:nth-of-type(3) { animation-delay: -8s; }
        @keyframes kioskShift {
            0%   { background-position: 0% 0%; }
            50%  { background-position: 100% 100%; }
            100% { background-position: 0% 0%; }
        }
        @keyframes kioskFloat {
            0%, 100% { transform: translateY(0) scale(1); }
            50%      { transform: translateY(-26px) scale(1.06); }
        }
    </style>

    <script>
        (function () {
            'use strict';

            function refreshStats() {
                const cells = document.querySelectorAll('[data-slot-cell]');
                const total = cells.length;
                const occupied = [...cells].filter(c => c.dataset.occupied === '1').length;
                const free = total - occupied;
                const pct = total > 0 ? Math.round((occupied / total) * 100) : 0;

                const setText = (id, value) => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = value;
                };

                setText('stat-total', total);
                setText('stat-occupied', occupied);
                setText('stat-free', free);
                setText('stat-pct', pct + '%');

                const bar = document.getElementById('stat-pct-bar');
                if (bar) bar.style.width = pct + '%';

                document.querySelectorAll('[data-floor-section]').forEach(section => {
                    const cells = section.querySelectorAll('[data-slot-cell]');
                    const occupied = [...cells].filter(c => c.dataset.occupied === '1').length;
                    const occEl = section.querySelector('[data-occ-count]');
                    const freeEl = section.querySelector('[data-free-count]');
                    if (occEl) occEl.textContent = occupied;
                    if (freeEl) freeEl.textContent = cells.length - occupied;
                });
            }

            function setSlotCell(cell, occupied, updatedAt) {
                cell.className = occupied ? cell.dataset.occ : cell.dataset.free;
                cell.dataset.occupied = occupied ? '1' : '0';

                const icon = cell.querySelector('[data-slot-icon]');
                if (icon) {
                    icon.className = 'fas ' + (occupied ? 'fa-car text-rose-300' : 'fa-square-parking text-emerald-300') +
                        ' text-3xl drop-shadow';
                }

                const label = cell.querySelector('[data-slot-status]');
                if (label) {
                    label.textContent = occupied ? 'Occupied' : 'Free';
                    label.className = 'text-[10px] font-black uppercase tracking-[0.25em] ' +
                        (occupied ? 'text-rose-300' : 'text-emerald-300');
                }

                const time = cell.querySelector('[data-slot-updated]');
                if (time) {
                    if (updatedAt) {
                        time.textContent = new Date(updatedAt).toLocaleTimeString([], {
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    } else {
                        time.textContent = '';
                    }
                }
            }

            function flashLiveChip(lastUpdatedAt) {
                const chip = document.getElementById('live-chip');
                const label = document.getElementById('live-chip-label');
                if (chip) {
                    chip.classList.remove('hidden');
                    chip.classList.add('inline-flex');
                }
                if (label) {
                    label.textContent = 'Live · ' + (lastUpdatedAt
                        ? new Date(lastUpdatedAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })
                        : new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
                }
            }

            function attachSlotListener() {
                if (window.__parkSlotBound || !window.Echo) {
                    if (window.Echo) window.__parkSlotBound = true;
                    return;
                }
                window.__parkSlotBound = true;

                window.Echo.channel('parking-slots').listen('SlotStatusChanged', (e) => {
                    const cell = document.querySelector(
                        '[data-slot-cell][data-lot="' + e.lot_number + '"][data-floor="' + e.floor_number + '"][data-slot="' + e.slot_number + '"]'
                    );
                    if (cell) setSlotCell(cell, Boolean(e.is_occupied), e.last_updated_at);
                    refreshStats();
                    flashLiveChip(e.last_updated_at);
                });
            }

            (function init() {
                if (window.Echo) {
                    attachSlotListener();
                } else {
                    setTimeout(init, 200);
                }
            })();
        })();
    </script>
</div>