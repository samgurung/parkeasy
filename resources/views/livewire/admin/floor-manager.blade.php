<div class="relative min-h-[100dvh_-_4rem] w-full overflow-hidden bg-[#070312] text-white flex flex-col">

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

            @if ($lotId && $selectedLot)
                <div class="flex items-center gap-3 rounded-xl border border-teal-400/30 bg-teal-500/10 px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-teal-200">
                    <i class="fas fa-filter text-teal-300"></i>
                    <span>
                        <span class="text-white/50">Showing</span>
                        Lot #{{ $selectedLot->lot_number }} — {{ $selectedLot->name }}
                    </span>
                    <button wire:click="$set('lotId', null)"
                            class="rounded-lg bg-white/10 px-2 py-1 text-[10px] font-black text-white/70 transition hover:bg-white/20 hover:text-white"
                            title="Show floors of all parking lots">All lots</button>
                </div>
            @endif
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
            <h2 class="mb-3 text-xs font-bold uppercase tracking-[0.3em] text-white/50 flex items-center gap-2">
                <i class="fas fa-layer-group text-teal-400"></i>
                {{ $lotId && $selectedLot ? "Floors — Lot #{$selectedLot->lot_number}" : 'Configured Floors' }}
            </h2>

            @forelse ($floors as $floor)
                <div class="mb-4 rounded-2xl border border-white/15 bg-white/10 px-6 py-5 shadow-xl backdrop-blur-xl">

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
                                        @unless ($lotId)
                                            <span class="ml-2 rounded-full bg-teal-500/20 border border-teal-400/40 px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest text-teal-300">
                                                Parking Lot #{{ $floor->lot->lot_number }} — {{ $floor->lot->name }}
                                            </span>
                                        @endunless
                                    </div>
                                    <div class="text-xs text-white/50">
                                        {{ $floor->slot_count }} slots &bull;
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
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-white/10 bg-white/5 px-6 py-10 text-center text-white/40 italic">
                    {{ $lotId && $selectedLot ? "No floors configured for {$selectedLot->name} yet. Add one above." : 'No floors configured yet. Add one above.' }}
                </div>
            @endforelse
        </section>

    </div>

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
