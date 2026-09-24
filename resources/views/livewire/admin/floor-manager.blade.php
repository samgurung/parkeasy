<div class="relative min-h-[calc(100dvh_-_4rem)] w-full overflow-hidden bg-[#070312] text-white flex flex-col">

    {{-- Background gradient & orbs --}}
    <div class="kiosk-bg absolute inset-0"></div>
    <div class="kiosk-orb pointer-events-none absolute -top-24 -left-24 h-96 w-96 rounded-full bg-teal-400 opacity-30 blur-[100px]"></div>
    <div class="kiosk-orb pointer-events-none absolute top-1/3 -right-32 h-[28rem] w-[28rem] rounded-full bg-fuchsia-500 opacity-25 blur-[120px]"></div>
    <div class="kiosk-orb pointer-events-none absolute -bottom-32 left-1/4 h-96 w-96 rounded-full bg-amber-500 opacity-20 blur-[110px]"></div>
    <div class="pointer-events-none absolute inset-0 kiosk-grain opacity-30"></div>

    <div class="relative z-10 flex w-full max-w-5xl flex-1 flex-col mx-auto px-6 py-6">

        {{-- Page header --}}
        <header class="flex flex-col gap-4 mb-8 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-black uppercase tracking-widest">
                    <span class="text-sky-400">Setup</span> — Floors &amp; Slots
                </h1>
                <x-setup-steps current="floors" />
            </div>
        </header>

        {{-- Add floor form --}}
        <section class="rounded-3xl border border-white/15 bg-white/10 px-6 py-6 shadow-2xl backdrop-blur-xl mb-8">
            <h2 class="mb-4 text-xs font-bold uppercase tracking-[0.3em] text-white/50 flex items-center gap-2">
                <i class="fas fa-plus-circle text-sky-400"></i> Add New Floor
            </h2>
            <form wire:submit="addFloor" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60" for="lotId">Parking Lot</label>
                    <select id="lotId" wire:model="lotId"
                            class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-white outline-none focus:border-sky-400 focus:bg-white/15">
                        <option value="">— Choose a parking lot —</option>
                        @foreach ($lots as $lot)
                            <option value="{{ $lot->id }}">#{{ $lot->lot_number }} — {{ $lot->name }}</option>
                        @endforeach
                    </select>
                    @error('lotId') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60" for="name">Floor Name</label>
                    <input id="name" wire:model="name" type="text" placeholder="e.g. Ground Floor"
                           class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                    @error('name') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60" for="slotCount">Slot Count</label>
                    <input id="slotCount" wire:model="slotCount" type="number" min="1" max="500" placeholder="e.g. 20"
                           class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                    @error('slotCount') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <p class="text-xs text-white/40 sm:col-span-2">
                    <i class="fas fa-circle-info mr-1 text-sky-400"></i>
                    Floor numbers are assigned automatically within the chosen parking lot: first floor = 1, second = 2, and so on.
                </p>
                <div class="sm:col-span-2">
                    <button type="submit"
                            class="rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 px-6 py-3 text-sm font-black uppercase tracking-[0.2em] text-white shadow-lg hover:brightness-110 transition">
                        <i class="fas fa-plus mr-2"></i> Add Floor
                    </button>
                </div>
            </form>
        </section>

        {{-- Floors list --}}
        <section>
            <div class="mb-3 flex items-end justify-between gap-3">
            <h2 class="text-xs font-bold uppercase tracking-[0.3em] text-white/50 flex items-center gap-2">
                <i class="fas fa-layer-group text-teal-400"></i>
                {{ $filterLotId && $selectedLot ? "Floors — Lot #{$selectedLot->lot_number}" : 'Configured Floors' }}
            </h2>
            <label class="flex items-center gap-2 rounded-xl bg-white/10 border border-white/20 px-3 py-2 text-xs font-bold text-white/80">
                <i class="fas fa-location-dot text-teal-300"></i>
                <select wire:model.live.change="filterLotId" title="Filter the floors list by lot"
                        class="bg-transparent text-sm font-bold text-white outline-none [&>option]:text-black">
                    <option value="">All lots</option>
                    @foreach ($lots as $lot)
                        <option value="{{ $lot->id }}">#{{ $lot->lot_number }} — {{ $lot->name }} ({{ $lot->floors_count }} {{ $lot->floors_count === 1 ? 'floor' : 'floors' }})</option>
                    @endforeach
                </select>
            </label>
        </div>

            @forelse ($floorGroups as $group)
                @unless ($filterLotId)
                    {{-- Lot group header --}}
                    <div class="mb-3 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-5 py-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-teal-400 to-emerald-600 text-sm font-black text-white shadow">
                            {{ $group['lot']->lot_number }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-white">{{ $group['lot']->name }}</div>
                            <div class="text-xs text-white/50">
                                {{ $group['floors']->count() }} {{ $group['floors']->count() === 1 ? 'floor' : 'floors' }}
                                &bull; {{ $group['floors']->sum('slots_count') }} slots
                            </div>
                        </div>
                    </div>
                @endunless

                <div class="space-y-4">
                    @foreach ($group['floors'] as $floor)
                        <div class="rounded-2xl border border-white/15 bg-white/10 px-6 py-5 shadow-xl backdrop-blur-xl">

                    @if ($editingId === $floor->id)
                        {{-- Inline edit form --}}
                        <form wire:submit="saveEdit" class="grid grid-cols-1 gap-4 sm:grid-cols-2 items-end">
                            <div>
                                <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">Parking Lot</label>
                                <select wire:model="editLotId"
                                        class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-white outline-none focus:border-sky-400 focus:bg-white/15">
                                    @foreach ($lots as $lot)
                                        <option value="{{ $lot->id }}">#{{ $lot->lot_number }} — {{ $lot->name }}</option>
                                    @endforeach
                                </select>
                                @error('editLotId') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">Floor Name</label>
                                <input wire:model="editName" type="text"
                                       class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-white outline-none focus:border-sky-400 focus:bg-white/15" />
                                @error('editName') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">Floor #</label>
                                <input type="text" value="{{ $floor->floor_number }}" disabled
                                       class="mt-1 w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-white/40 cursor-not-allowed" />
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">Slot Count</label>
                                <input wire:model="editSlotCount" type="number" min="1" max="500"
                                       class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-white outline-none focus:border-sky-400 focus:bg-white/15" />
                                @error('editSlotCount') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2 flex gap-3">
                                <button type="submit"
                                        class="rounded-xl bg-gradient-to-br from-emerald-400 to-teal-600 px-5 py-2 text-sm font-black uppercase tracking-[0.2em] text-white shadow hover:brightness-110 transition">
                                    <i class="fas fa-check mr-1"></i> Save
                                </button>
                                <button type="button" wire:click="cancelEdit"
                                        class="rounded-xl border border-white/20 bg-white/10 px-5 py-2 text-sm font-bold text-white/70 hover:bg-white/20 transition">
                                    Cancel
                                </button>
                            </div>
                        </form>

                    @else
                        {{-- Read view --}}
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-4">
                                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 text-xl font-black text-white shadow">
                                    {{ $floor->floor_number }}
                                </span>
                                <div>
                                    <div class="text-lg font-bold">
                                        {{ $floor->name }}
                                    </div>
                                    <div class="text-xs text-white/50">
                                        {{ $floor->slot_count }} slots &bull;
                                        <span class="text-sky-300">{{ $floor->two_wheeler_slots_count }} two-wheeler</span> &bull;
                                        <span class="text-teal-300">{{ $floor->slot_count - $floor->two_wheeler_slots_count }} four-wheeler</span> &bull;
                                        <span class="text-rose-400">{{ $floor->occupied_slots_count }} occupied</span> &bull;
                                        <span class="text-emerald-400">{{ $floor->slot_count - $floor->occupied_slots_count }} free</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <button wire:click="startEdit({{ $floor->id }})"
                                        class="rounded-xl border border-sky-400/40 bg-sky-500/10 px-4 py-2 text-xs font-bold uppercase tracking-widest text-sky-300 hover:bg-sky-500/20 transition">
                                    <i class="fas fa-pen mr-1"></i> Edit
                                </button>
                                <button wire:click="confirmDelete({{ $floor->id }})"
                                        class="rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-2 text-xs font-bold uppercase tracking-widest text-rose-300 hover:bg-rose-500/20 transition">
                                    <i class="fas fa-trash mr-1"></i> Delete
                                </button>
                            </div>
                        </div>

                        {{-- Slot type designation chips --}}
                        <div class="mt-4 border-t border-white/10 pt-4">
                            <div class="mb-2 flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.2em] text-white/40">
                                <i class="fas fa-motorcycle text-sky-300"></i>
                                @php
                                    $floorPendingCount = $floor->slots
                                        ->filter(fn ($slot) => array_key_exists($slot->id, $pendingTypeChanges))
                                        ->count();
                                @endphp
                                @if ($floorPendingCount > 0)
                                    <span class="text-amber-300">{{ $floorPendingCount }} {{ $floorPendingCount === 1 ? 'change' : 'changes' }} pending on this floor — tap Apply to save</span>
                                @else
                                    Tap a slot to stage a 2W / 4W change, then Apply to save it
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($floor->slots as $slot)
                                    @php
                                        $isPending = array_key_exists($slot->id, $pendingTypeChanges);
                                        $isTwoWheeler = $isPending
                                            ? $pendingTypeChanges[$slot->id] === \App\Models\ParkingLot::VEHICLE_TWO_WHEELER
                                            : $slot->vehicle_type === \App\Models\ParkingLot::VEHICLE_TWO_WHEELER;
                                    @endphp
                                    <button wire:click="toggleSlotType({{ $slot->id }})"
                                            title="{{ $isPending ? 'Pending — will become ' . ($isTwoWheeler ? '2W' : '4W') . '. Tap again to undo.' : 'Tap to stage a type change' }}"
                                            class="flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[11px] font-bold transition hover:brightness-125 {{ $isPending ? 'border-amber-400/60 bg-amber-500/15 text-amber-300' : ($isTwoWheeler ? 'border-sky-400/50 bg-sky-500/15 text-sky-300' : 'border-teal-400/40 bg-teal-500/10 text-teal-300') }}">
                                        <i class="fas {{ $isTwoWheeler ? 'fa-motorcycle' : 'fa-car' }} text-[10px]"></i>
                                        <span>{{ $slot->displayLabel() }}</span>
                                        <span class="text-[9px] uppercase {{ $isPending ? 'text-amber-200/80' : ($isTwoWheeler ? 'text-sky-200/70' : 'text-teal-200/70') }}">
                                            {{ $isTwoWheeler ? '2W' : '4W' }}{{ $isPending ? ' ⇄' : '' }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="rounded-2xl border border-white/10 bg-white/5 px-6 py-10 text-center text-white/40 italic">
                    {{ $filterLotId && $selectedLot ? "No floors configured for {$selectedLot->name} yet. Add one above." : 'No floors configured yet. Add one above.' }}
                </div>
            @endforelse
        </section>

    </div>

    {{-- Confirm slot type changes bar --}}
    @if (count($pendingTypeChanges) > 0)
        <div class="fixed bottom-6 left-1/2 z-[60] flex -translate-x-1/2 items-center gap-4 rounded-2xl border border-amber-400/40 bg-black/80 px-6 py-4 shadow-2xl backdrop-blur-xl">
            <span class="whitespace-nowrap text-sm font-bold uppercase tracking-widest text-amber-300">
                <i class="fas fa-triangle-exclamation mr-2"></i>
                {{ count($pendingTypeChanges) }} {{ count($pendingTypeChanges) === 1 ? 'change' : 'changes' }}
            </span>
            <button wire:click="confirmTypeChanges"
                    class="rounded-xl bg-gradient-to-br from-emerald-400 to-teal-600 px-5 py-2.5 text-sm font-black uppercase tracking-widest text-white shadow hover:brightness-110 transition">
                <i class="fas fa-check mr-1"></i> Apply
            </button>
            <button wire:click="discardTypeChanges"
                    class="rounded-xl border border-white/20 bg-white/10 px-5 py-2.5 text-sm font-bold text-white/70 hover:bg-white/20 transition">
                Discard
            </button>
        </div>
    @endif

    {{-- Delete confirmation modal --}}
    @if ($confirmDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md">
            <div class="result-pop relative w-full max-w-sm rounded-[2rem] border border-rose-400/30 bg-white/15 p-10 text-center shadow-2xl backdrop-blur-2xl">
                <i class="fas fa-triangle-exclamation text-6xl text-rose-400 mb-4"></i>
                <div class="text-2xl font-black uppercase tracking-widest mb-2">Delete Floor?</div>
                <p class="text-sm text-white/60 mb-6">This will permanently delete the floor and all its slot data.</p>
                <div class="flex gap-4 justify-center">
                    <button wire:click="deleteFloor"
                            class="rounded-xl bg-gradient-to-br from-rose-500 to-orange-500 px-6 py-3 text-sm font-black uppercase tracking-widest text-white shadow hover:brightness-110 transition">
                        <i class="fas fa-trash mr-1"></i> Delete
                    </button>
                    <button wire:click="cancelDelete"
                            class="rounded-xl border border-white/20 bg-white/10 px-6 py-3 text-sm font-bold text-white/70 hover:bg-white/20 transition">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif

    <style>
        .kiosk-bg {
            background: linear-gradient(135deg, #14104a 0%, #341a7a 22%, #7a1f6b 46%, #bd3a3a 70%, #f26b1d 100%);
            background-size: 350% 350%;
            animation: kioskShift 16s ease-in-out infinite;
        }
        .kiosk-grain {
            background-image: radial-gradient(rgba(255,255,255,0.12) 1px, transparent 1px);
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
        @keyframes kioskPop {
            0%   { transform: scale(0.78); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .result-pop { animation: kioskPop 0.35s cubic-bezier(0.2, 0.9, 0.3, 1.2) both; }
    </style>

</div>
