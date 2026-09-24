<div class="relative min-h-[calc(100dvh_-_4rem)] w-full overflow-hidden bg-[#070312] text-white flex flex-col">

    {{-- Background gradient & orbs --}}
    <div class="kiosk-bg absolute inset-0"></div>
    <div class="kiosk-orb pointer-events-none absolute -top-24 -left-24 h-96 w-96 rounded-full bg-teal-400 opacity-30 blur-[100px]"></div>
    <div class="kiosk-orb pointer-events-none absolute top-1/3 -right-32 h-[28rem] w-[28rem] rounded-full bg-fuchsia-500 opacity-25 blur-[120px]"></div>
    <div class="kiosk-orb pointer-events-none absolute -bottom-32 left-1/4 h-96 w-96 rounded-full bg-amber-500 opacity-20 blur-[110px]"></div>
    <div class="pointer-events-none absolute inset-0 kiosk-grain opacity-30"></div>

    <div class="relative z-10 flex w-full max-w-5xl flex-1 flex-col mx-auto px-6 py-6">

        {{-- Page header --}}
        <header class="flex items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-black uppercase tracking-widest">
                    <span class="text-sky-400">Setup</span> — Kiosks
                </h1>
                <x-setup-steps current="kiosks" />
            </div>
            <p class="hidden max-w-xs text-right text-xs text-white/50 sm:block">
                <i class="fas fa-circle-info mr-1 text-sky-400"></i>
                Step 3 of 3 — attach each RFID kiosk to the parking lot it guards.
            </p>
        </header>

        {{-- Add kiosk form --}}
        <section class="rounded-3xl border border-white/15 bg-white/10 px-6 py-6 shadow-2xl backdrop-blur-xl mb-8">
            <h2 class="mb-4 text-xs font-bold uppercase tracking-[0.3em] text-white/50 flex items-center gap-2">
                <i class="fas fa-plus-circle text-sky-400"></i> Register New Kiosk
            </h2>
            <form wire:submit="add" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60" for="name">Kiosk Name</label>
                    <input id="name" wire:model="name" type="text" placeholder="e.g. Main Gate A"
                           class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                    @error('name') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60" for="lotId">Parking Lot</label>
                    <select id="lotId" wire:model="lotId"
                            class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-white outline-none focus:border-sky-400 focus:bg-white/15">
                        <option value="">Select a lot…</option>
                        @foreach ($lots as $lot)
                            <option value="{{ $lot->id }}" class="text-white bg-slate-800">Lot #{{ $lot->lot_number }} — {{ $lot->name }}</option>
                        @endforeach
                    </select>
                    @error('lotId') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-end">
                    <button type="submit"
                            class="w-full rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 px-6 py-3 text-sm font-black uppercase tracking-[0.2em] text-white shadow-lg hover:brightness-110 transition">
                        <i class="fas fa-plus mr-2"></i> Add Kiosk
                    </button>
                </div>
                <p class="text-xs text-white/40 sm:col-span-3">
                    <i class="fas fa-circle-info mr-1 text-sky-400"></i>
                    Open the kiosk at <span class="font-mono text-sky-300">/ ?kiosk=&lt;key&gt;</span> so it knows which parking lot to report
                    entry and exit scans against. The RFID reader must send the same <span class="font-mono text-sky-300">lot</span> number.
                </p>
            </form>
        </section>

        {{-- Kiosks list --}}
        <section>
            <h2 class="mb-3 text-xs font-bold uppercase tracking-[0.3em] text-white/50 flex items-center gap-2">
                <i class="fas fa-qrcode text-teal-400"></i> Registered Kiosks
            </h2>

            @forelse ($kiosks as $kiosk)
                <div class="mb-4 rounded-2xl border border-white/15 bg-white/10 px-6 py-5 shadow-xl backdrop-blur-xl">

                    @if ($editingId === $kiosk->id)
                        {{-- Inline edit form --}}
                        <form wire:submit="save" class="grid grid-cols-1 gap-4 sm:grid-cols-2 items-end">
                            <div>
                                <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">Kiosk Name</label>
                                <input wire:model="editName" type="text"
                                       class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-white outline-none focus:border-sky-400 focus:bg-white/15" />
                                @error('editName') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">Parking Lot</label>
                                <select wire:model="editLotId"
                                        class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-white outline-none focus:border-sky-400 focus:bg-white/15">
                                    <option value="">No lot</option>
                                    @foreach ($lots as $lot)
                                        <option value="{{ $lot->id }}" class="text-white bg-slate-800">Lot #{{ $lot->lot_number }} — {{ $lot->name }}</option>
                                    @endforeach
                                </select>
                                @error('editLotId') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2 flex gap-3">
                                <button type="submit"
                                        class="rounded-xl bg-gradient-to-br from-emerald-400 to-teal-600 px-5 py-2 text-sm font-black uppercase tracking-[0.2em] text-white shadow hover:brightness-110 transition">
                                    <i class="fas fa-check mr-1"></i> Save
                                </button>
                                <button type="button" wire:click="cancel"
                                        class="rounded-xl border border-white/20 bg-white/10 px-5 py-2 text-sm font-bold text-white/70 hover:bg-white/20 transition">
                                    Cancel
                                </button>
                            </div>
                        </form>

                    @else
                        {{-- Read view --}}
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-4">
                                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-teal-400 to-emerald-600 text-xl font-black text-white shadow">
                                    <i class="fas fa-id-card"></i>
                                </span>
                                <div>
                                    <div class="text-lg font-bold">{{ $kiosk->name }}</div>
                                    <div class="text-xs text-white/50">
                                        <span class="font-mono text-sky-300">KEY: {{ $kiosk->key }}</span>
                                        @if ($kiosk->parkingLot)
                                            &bull;
                                            <span class="text-teal-300">PARKING LOT #{{ $kiosk->parkingLot->lot_number }}</span>
                                            &bull; {{ $kiosk->parkingLot->name }}
                                        @else
                                            &bull; <span class="text-amber-300">NOT LINKED TO A LOT</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                @php
                                    $lot = $kiosk->parkingLot;
                                    $lotReady = $lot && $lot->floors_count > 0 && $lot->slots_count > 0;
                                    $lotReadyReason = !$lot
                                        ? 'Kiosk is not linked to a parking lot.'
                                        : ($lot->floors_count === 0
                                            ? 'This lot has no floors configured yet.'
                                            : ($lot->slots_count === 0 ? 'This lot has no slots configured yet.' : null));
                                @endphp
                                <a href="{{ route('home') }}?kiosk={{ $kiosk->key }}" target="_blank" rel="noopener"
                                   title="{{ $lotReady ? 'Open kiosk terminal' : $lotReadyReason }}"
                                   @class([
                                       'rounded-xl border px-4 py-2 text-xs font-black uppercase tracking-widest transition',
                                       'border-emerald-400/40 bg-emerald-500/10 text-emerald-300 hover:bg-emerald-500/20' => $lotReady,
                                       'border-white/10 bg-white/5 text-white/30 cursor-not-allowed pointer-events-none' => !$lotReady,
                                   ])>
                                    <i class="fas fa-up-right-from-square mr-1"></i> Open kiosk
                                </a>
                                <button wire:click="edit({{ $kiosk->id }})"
                                        class="rounded-xl border border-sky-400/40 bg-sky-500/10 px-4 py-2 text-xs font-bold uppercase tracking-widest text-sky-300 hover:bg-sky-500/20 transition">
                                    <i class="fas fa-pen mr-1"></i> Edit
                                </button>
                                @if ($kiosk->parking_lot_id)
                                    <button wire:click="delink({{ $kiosk->id }})"
                                            class="rounded-xl border border-amber-400/40 bg-amber-500/10 px-4 py-2 text-xs font-bold uppercase tracking-widest text-amber-300 hover:bg-amber-500/20 transition">
                                        <i class="fas fa-link-slash mr-1"></i> Delink
                                    </button>
                                @endif
                                <button wire:click="confirmDelete({{ $kiosk->id }})"
                                        class="rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-2 text-xs font-bold uppercase tracking-widest text-rose-300 hover:bg-rose-500/20 transition">
                                    <i class="fas fa-trash mr-1"></i> Delete
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-white/10 bg-white/5 px-6 py-10 text-center text-white/40 italic">
                    No kiosks registered yet. Add one above.
                </div>
            @endforelse
        </section>

    </div>

    {{-- Delete confirmation modal --}}
    @if ($deletingId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md">
            <div class="result-pop relative w-full max-w-sm rounded-[2rem] border border-rose-400/30 bg-white/15 p-10 text-center shadow-2xl backdrop-blur-2xl">
                <i class="fas fa-triangle-exclamation text-6xl text-rose-400 mb-4"></i>
                <div class="text-2xl font-black uppercase tracking-widest mb-2">Delete Kiosk?</div>
                <p class="text-sm text-white/60 mb-6">The kiosk will stop reporting the parking lot for new scans.</p>
                <div class="flex gap-4 justify-center">
                    <button wire:click="delete"
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