<div class="relative min-h-screen w-full overflow-hidden bg-[#070312] text-white flex flex-col" wire:poll.10s>

    {{-- Background gradient & orbs --}}
    <div class="kiosk-bg absolute inset-0"></div>
    <div class="kiosk-orb pointer-events-none absolute -top-24 -left-24 h-[22rem] w-[22rem] rounded-full bg-teal-400 opacity-30 blur-[110px]"></div>
    <div class="kiosk-orb pointer-events-none absolute top-1/4 -right-32 h-[26rem] w-[26rem] rounded-full bg-fuchsia-500 opacity-25 blur-[130px]"></div>
    <div class="kiosk-orb pointer-events-none absolute -bottom-32 left-1/4 h-96 w-96 rounded-full bg-amber-500 opacity-20 blur-[120px]"></div>
    <div class="pointer-events-none absolute inset-0 kiosk-grain opacity-30"></div>

    <div class="relative z-10 w-full max-w-7xl mx-auto px-6 py-6 flex flex-1 flex-col">

        {{-- Header --}}
        <header class="flex flex-col gap-5 mb-8">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <a href="{{ route('home') }}"
                       class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 text-lg text-white/70 transition hover:bg-white/20 hover:text-white"
                       title="Go to home screen" aria-label="Go to home screen">
                        <i class="fas fa-house"></i>
                    </a>
                    <div>
                        <h1 class="text-3xl font-black uppercase tracking-widest">
                            <span class="text-teal-400">Live</span> Parking Lot Report
                        </h1>
                        <p class="mt-1 text-sm text-white/60">Real-time occupancy across all parking lots — pick the one with room near you.</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('slots.dashboard') }}"
                       class="flex items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white/80 hover:bg-white/20 transition">
                        <i class="fas fa-tower-observation"></i> Slot Monitor
                    </a>
                    <button wire:click="refresh"
                            class="flex items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white/80 hover:bg-white/20 transition">
                        <i class="fas fa-rotate"></i> Refresh
                    </button>
                </div>
            </div>

            {{-- Search + sort controls --}}
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <label class="flex items-center gap-3 rounded-2xl border border-white/20 bg-white/10 px-4 py-3 sm:max-w-xl sm:flex-1">
                    <i class="fas fa-magnifying-glass-location text-teal-300"></i>
                    <input wire:model.live="search" type="text" placeholder="Search your address or parking lot name…"
                           class="w-full bg-transparent text-sm font-bold text-white placeholder-white/40 outline-none" />
                    @if ($matchedAddress)
                        <button wire:click="$set('search', '')" type="button"
                                class="text-white/50 hover:text-white transition" title="Clear search">
                            <i class="fas fa-xmark"></i>
                        </button>
                    @endif
                </label>
                <label class="flex items-center gap-2 rounded-2xl border border-white/20 bg-white/10 px-4 py-3 text-sm font-bold text-white/80">
                    <i class="fas fa-arrow-down-wide-short text-sky-300"></i>
                    <span class="text-xs uppercase tracking-widest text-white/50">Sort</span>
                    <select wire:model.live="sortBy" class="bg-transparent text-sm font-bold text-white outline-none [&>option]:text-black">
                        <option value="free">Most free spots</option>
                        <option value="occupancy">Lowest occupancy %</option>
                        <option value="lot">Parking lot number</option>
                    </select>
                </label>
            </div>
        </header>

        @if ($matchedAddress)
            <p class="mb-6 flex items-center gap-2 rounded-2xl border border-teal-400/30 bg-teal-500/10 px-4 py-3 text-sm text-teal-100">
                <i class="fas fa-circle-info text-teal-300"></i>
                Showing parking lots matching <strong class="font-black uppercase tracking-wide">“{{ $matchedAddress }}”</strong>
                — best options first.
            </p>
        @endif

        {{-- Lots grid --}}
        <section class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($lots as $lot)
                <article @class([
                    'relative overflow-hidden rounded-3xl border bg-white/10 p-6 shadow-2xl backdrop-blur-xl transition',
                    'border-teal-400/40' => $loop->first && count($lots) > 1,
                    'border-white/15' => !($loop->first && count($lots) > 1),
                ])>
                    @if ($loop->first && count($lots) > 1)
                        <span class="absolute right-0 top-4 rounded-l-full bg-gradient-to-br from-teal-400 to-emerald-500 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-black shadow">
                            Best Pick
                        </span>
                    @endif

                    {{-- Status pill --}}
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-black uppercase tracking-wider">{{ $lot->name }}</h2>
                            <p class="mt-1 text-xs text-white/60">
                                Parking Lot #{{ $lot->lot_number }}
                                @if ($lot->address) · {{ $lot->address }} @endif
                            </p>
                        </div>
                        <span @class([
                            'rounded-full px-3 py-1 text-xs font-black uppercase tracking-widest',
                            'border border-emerald-400/40 bg-emerald-500/10 text-emerald-300' => $lot->free_slots > 0,
                            'border border-rose-400/40 bg-rose-500/10 text-rose-300' => $lot->free_slots === 0,
                        ])>
                            <i @class([
                                'fas mr-1',
                                'fa-circle-check' => $lot->free_slots > 0,
                                'fa-circle-xmark' => $lot->free_slots === 0,
                            ])></i>
                            {{ $lot->free_slots > 0 ? 'Open' : 'Full' }}
                        </span>
                    </div>

                    {{-- Occupancy bar --}}
                    <div class="mt-5 h-3 w-full overflow-hidden rounded-full bg-black/40">
                        <div class="h-full rounded-full transition-all"
                             style="width: {{ $lot->occupancy_pct }}%; background: linear-gradient(90deg, #5eead4, #22d3ee);"></div>
                    </div>
                    <p class="mt-2 text-right text-xs font-black text-white/70">
                        {{ $lot->occupied_slots_count }}/{{ $lot->total_slots }} used · {{ $lot->occupancy_pct }}% full
                    </p>

                    {{-- Stats --}}
                    <dl class="mt-5 grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-xl bg-black/30 p-3">
                            <dt class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/40">Occupied</dt>
                            <dd class="mt-1 text-xl font-black text-amber-300">{{ $lot->occupied_slots_count }}</dd>
                        </div>
                        <div class="rounded-xl bg-black/30 p-3">
                            <dt class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/40">Free</dt>
                            <dd class="mt-1 text-xl font-black text-emerald-400">{{ $lot->free_slots }}</dd>
                        </div>
                        <div class="rounded-xl bg-black/30 p-3">
                            <dt class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/40">Total</dt>
                            <dd class="mt-1 text-xl font-black text-sky-400">{{ $lot->total_slots }}</dd>
                        </div>
                    </dl>
                </article>
            @empty
                <div class="md:col-span-2 xl:col-span-3 rounded-3xl border border-white/15 bg-white/10 p-10 text-center backdrop-blur-xl">
                    <p class="text-2xl font-black uppercase tracking-widest text-white/70">
                        {{ $matchedAddress ? 'No parking lots match your search' : 'No parking lots yet' }}
                    </p>
                    <p class="mt-2 text-sm text-white/50">
                        @if ($matchedAddress)
                            Try a different address, name, or parking lot number.
                        @else
                            Configure a parking lot and its floors in the admin panel first.
                        @endif
                    </p>
                </div>
            @endforelse
        </section>

        <footer class="mt-10 flex items-center justify-between text-xs text-white/50">
            <span><i class="fas fa-bolt text-amber-400"></i> Live occupancy · auto-updates every 10s</span>
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
</div>