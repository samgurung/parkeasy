<div class="relative min-h-[calc(100dvh_-_4rem)] w-full overflow-hidden bg-[#070312] text-white flex flex-col">

    {{-- Background gradient & orbs --}}
    <div class="kiosk-bg absolute inset-0"></div>
    <div class="kiosk-orb pointer-events-none absolute -top-24 -left-24 h-96 w-96 rounded-full bg-teal-400 opacity-30 blur-[100px]"></div>
    <div class="kiosk-orb pointer-events-none absolute top-1/3 -right-32 h-[28rem] w-[28rem] rounded-full bg-fuchsia-500 opacity-25 blur-[120px]"></div>
    <div class="kiosk-orb pointer-events-none absolute -bottom-32 left-1/4 h-96 w-96 rounded-full bg-amber-500 opacity-20 blur-[110px]"></div>
    <div class="pointer-events-none absolute inset-0 kiosk-grain opacity-30"></div>

    <div class="relative z-10 flex w-full max-w-6xl flex-1 flex-col mx-auto px-6 py-6">

        {{-- Page header --}}
        <header class="flex items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-black uppercase tracking-widest">
                    <span class="text-sky-400">Setup</span> — Vehicles
                </h1>
                <x-setup-steps current="vehicles" />
            </div>
            <p class="hidden max-w-xs text-right text-xs text-white/50 sm:block">
                <i class="fas fa-circle-info mr-1 text-sky-400"></i>
                Step 4 of 4 — bind each RFID card to the vehicle it is permanently fitted to.
            </p>
        </header>

        {{-- Register vehicle form --}}
        <section class="rounded-3xl border border-white/15 bg-white/10 px-6 py-6 shadow-2xl backdrop-blur-xl mb-8">
            <h2 class="mb-4 text-xs font-bold uppercase tracking-[0.3em] text-white/50 flex items-center gap-2">
                <i class="fas fa-plus-circle text-sky-400"></i> Register Vehicle &amp; Card
            </h2>
            <form wire:submit="add" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60" for="rfidId">RFID Card Code</label>
                    <input id="rfidId" wire:model="rfidId" type="text" placeholder="e.g. RFID-0001"
                           class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 font-mono text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                    @error('rfidId') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60" for="vehicleNumber">Registration Number</label>
                    <input id="vehicleNumber" wire:model="vehicleNumber" type="text" placeholder="e.g. KA01AB1234"
                           class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 font-mono uppercase text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                    @error('vehicleNumber') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60" for="driverName">Driver Name</label>
                    <input id="driverName" wire:model="driverName" type="text" placeholder="e.g. John Doe"
                           class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                    @error('driverName') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60" for="mobileNumber">Mobile Number</label>
                    <input id="mobileNumber" wire:model="mobileNumber" type="tel" maxlength="10" placeholder="10-digit number"
                           class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 tracking-widest text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                    @error('mobileNumber') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60" for="vehicleType">Vehicle Type</label>
                    <select id="vehicleType" wire:model="vehicleType"
                            class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-white outline-none focus:border-sky-400 focus:bg-white/15">
                        <option value="two_wheeler" class="text-white bg-slate-800">Two-Wheeler</option>
                        <option value="four_wheeler" class="text-white bg-slate-800">Four-Wheeler</option>
                    </select>
                    @error('vehicleType') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    {{-- Provenance, not ownership: this only decides where the card shows up
                         in the "recently added" list. The options are already limited to the
                         lots this user administers. --}}
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60" for="lotId">Registered At</label>
                    <select id="lotId" wire:model="lotId"
                            class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-white outline-none focus:border-sky-400 focus:bg-white/15">
                        <option value="" class="text-white bg-slate-800">Not lot-specific</option>
                        @foreach ($lots as $option)
                            <option value="{{ $option->id }}" class="text-white bg-slate-800">{{ $option->name }}</option>
                        @endforeach
                    </select>
                    @error('lotId') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-end">
                    <button type="submit"
                            class="w-full rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 px-6 py-3 text-sm font-black uppercase tracking-[0.2em] text-white shadow-lg hover:brightness-110 transition">
                        <i class="fas fa-plus mr-2"></i> Add Vehicle
                    </button>
                </div>
            </form>
        </section>

        {{-- Search --}}
        <section class="mb-6">
            <label class="sr-only" for="search">Search vehicles</label>
            <input id="search" wire:model.live.debounce.300ms="search" type="search"
                   placeholder="Search any vehicle by card, registration, driver or mobile…"
                   class="w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
        </section>

        {{-- Vehicle list --}}
        <section>
            <h2 class="mb-3 text-xs font-bold uppercase tracking-[0.3em] text-white/50 flex items-center gap-2">
                <i class="fas {{ $searching ? 'fa-magnifying-glass' : 'fa-clock-rotate-left' }} {{ $searching ? 'text-sky-400' : 'text-teal-400' }}"></i>
                @if ($searching)
                    Matches
                    <span class="text-white/30 normal-case tracking-normal">({{ $matchCount }} of {{ $totalVehicles }})</span>
                @else
                    Recently Added
                    <span class="text-white/30 normal-case tracking-normal">
                        ({{ $vehicles->count() === $totalVehicles ? $totalVehicles : $vehicles->count().' of '.$totalVehicles }})
                    </span>
                @endif
            </h2>

            @forelse ($vehicles as $vehicle)
                <div class="mb-4 rounded-2xl border border-white/15 bg-white/10 px-6 py-5 shadow-xl backdrop-blur-xl">

                    @if ($canEdit && $editingId === $vehicle->id)
                        {{-- Inline edit form --}}
                        <form wire:submit="save" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 items-end">
                            <div>
                                <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">RFID Card Code</label>
                                <input wire:model="editRfidId" type="text"
                                       class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-2 font-mono text-white outline-none focus:border-sky-400 focus:bg-white/15" />
                                @error('editRfidId') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">Registration Number</label>
                                <input wire:model="editVehicleNumber" type="text"
                                       class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-2 font-mono uppercase text-white outline-none focus:border-sky-400 focus:bg-white/15" />
                                @error('editVehicleNumber') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">Driver Name</label>
                                <input wire:model="editDriverName" type="text"
                                       class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-white outline-none focus:border-sky-400 focus:bg-white/15" />
                                @error('editDriverName') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">Mobile Number</label>
                                <input wire:model="editMobileNumber" type="tel" maxlength="10"
                                       class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-2 tracking-widest text-white outline-none focus:border-sky-400 focus:bg-white/15" />
                                @error('editMobileNumber') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">Vehicle Type</label>
                                <select wire:model="editVehicleType"
                                        class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-white outline-none focus:border-sky-400 focus:bg-white/15">
                                    <option value="two_wheeler" class="text-white bg-slate-800">Two-Wheeler</option>
                                    <option value="four_wheeler" class="text-white bg-slate-800">Four-Wheeler</option>
                                </select>
                                @error('editVehicleType') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex gap-3">
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
                                    <i class="fas {{ $vehicle->vehicle_type === 'two_wheeler' ? 'fa-motorcycle' : 'fa-car-side' }}"></i>
                                </span>
                                <div>
                                    <div class="text-lg font-bold font-mono tracking-wider">{{ $vehicle->vehicle_number }}</div>
                                    <div class="text-xs text-white/50">
                                        <span class="font-mono text-sky-300">CARD: {{ $vehicle->rfid_id }}</span>
                                        &bull; {{ $vehicle->driver_name }}
                                        &bull; <span class="tracking-widest">{{ $vehicle->mobile_number }}</span>
                                    </div>
                                    <div class="mt-0.5 text-xs text-white/35">
                                        {{ $vehicle->entries_count }} visit{{ $vehicle->entries_count === 1 ? '' : 's' }} recorded
                                        @if ($vehicle->registeredAtLot)
                                            &bull; registered at {{ $vehicle->registeredAtLot->name }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            {{-- Corrections and removal are super-admin-only: the registry is
                                 site-wide, so changing a row here changes what every other
                                 gate believes about that vehicle. --}}
                            @if ($canEdit || $canDelete)
                                <div class="flex gap-3">
                                    @if ($canEdit)
                                        <button wire:click="edit({{ $vehicle->id }})"
                                                class="rounded-xl border border-sky-400/40 bg-sky-500/10 px-4 py-2 text-xs font-bold uppercase tracking-widest text-sky-300 hover:bg-sky-500/20 transition">
                                            <i class="fas fa-pen mr-1"></i> Edit
                                        </button>
                                    @endif
                                    @if ($canDelete)
                                        <button wire:click="confirmDelete({{ $vehicle->id }})"
                                                class="rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-2 text-xs font-bold uppercase tracking-widest text-rose-300 hover:bg-rose-500/20 transition">
                                            <i class="fas fa-trash mr-1"></i> Delete
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-white/10 bg-white/5 px-6 py-10 text-center text-white/40 italic">
                    @if ($searching)
                        No vehicles match &ldquo;{{ $search }}&rdquo;.
                    @else
                        No vehicles registered yet. Add one above.
                    @endif
                </div>
            @endforelse

            {{-- A very broad term can match far more than is worth rendering, so say so
                 rather than silently truncating the result set. --}}
            @if ($searching && $matchCount > $vehicles->count())
                <p class="rounded-2xl border border-amber-400/30 bg-amber-500/10 px-6 py-4 text-center text-sm text-amber-200">
                    <i class="fas fa-circle-info mr-1"></i> Showing the first {{ $vehicles->count() }} of {{ $matchCount }} matches. Try a fuller card number, registration, name or mobile to narrow it down.
                </p>
            @endif

            @unless ($searching)
                @if ($recentTotal > count($vehicles))
                    <p class="mt-4 text-center text-sm text-white/40">
                        Showing the {{ $vehicles->count() }} most recently added{{ $recentScopeLabel }}. Use the search box to find any other vehicle.
                    </p>
                @elseif ($vehicles->isEmpty())
                    <p class="mt-4 text-center text-sm text-white/40">
                        <i class="fas fa-circle-info mr-1"></i>
                        No cards have been registered{{ $recentScopeLabel }} yet. Search still reaches cards bound at any lot.
                    </p>
                @endif
            @endunless
        </section>

    </div>

    {{-- Delete confirmation modal --}}
    @if ($canDelete && $deletingId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md">
            <div class="result-pop relative w-full max-w-sm rounded-[2rem] border border-rose-400/30 bg-white/15 p-10 text-center shadow-2xl backdrop-blur-2xl">
                <i class="fas fa-triangle-exclamation text-6xl text-rose-400 mb-4"></i>
                <div class="text-2xl font-black uppercase tracking-widest mb-2">Delete Vehicle?</div>
                <p class="text-sm text-white/60 mb-6">
                    Its card will stop working at the gate. Past visits are kept for your records but are no longer
                    linked to a vehicle.
                </p>
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
