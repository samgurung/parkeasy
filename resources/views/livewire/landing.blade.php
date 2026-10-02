{{-- What a signed-out visitor gets at /. Explains the app and offers the way in, because
     "redirect to /login" is a wall with no explanation on it: it tells a first-time visitor
     what they may not see, but not what they are signing in to.

     Copy is deliberately confined to what the app actually does. In particular the fee is
     *computed* per stay from the lot's rate, not collected - there is no payment, so nothing
     here promises one. --}}

<div class="relative min-h-[calc(100dvh_-_4rem)] w-full overflow-hidden bg-[#070312] text-white">
    <div class="kiosk-bg absolute inset-0"></div>
    <div class="kiosk-orb pointer-events-none absolute -top-32 -left-24 h-96 w-96 rounded-full bg-teal-400 opacity-20 blur-[120px]"></div>
    <div class="kiosk-orb pointer-events-none absolute top-1/2 -right-40 h-[30rem] w-[30rem] rounded-full bg-sky-500 opacity-15 blur-[130px]"></div>

    <div class="relative mx-auto max-w-5xl px-4 py-14 sm:py-20">
        {{-- Hero --}}
        <div class="max-w-2xl">
            <span class="inline-flex items-center gap-2 rounded-full border border-teal-300/30 bg-teal-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-widest text-teal-200">
                <i class="fas fa-id-card"></i> RFID parking management
            </span>

            <h1 class="mt-5 text-4xl font-black tracking-tight text-white sm:text-5xl">
                Every gate, every slot,<br class="hidden sm:block" /> every card.
            </h1>

            <p class="mt-5 text-base leading-relaxed text-white/60 sm:text-lg">
                ParkEasy runs the car park end to end: tablets at each gate that admit and
                release vehicles on an RFID card, live slot occupancy you can hang on a wall,
                and the registry that keeps a card tied to one vehicle.
            </p>

            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a href="{{ route('login') }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 px-6 py-3 font-bold uppercase tracking-widest text-white shadow-lg shadow-blue-500/25 transition hover:brightness-110">
                    <i class="fas fa-user-shield"></i> Sign in
                </a>
                <a href="{{ route('lots.overview') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/5 px-6 py-3 font-bold uppercase tracking-widest text-white/70 transition hover:bg-white/10 hover:text-white">
                    <i class="fas fa-chart-simple"></i> Live occupancy
                </a>
            </div>

            <p class="mt-4 text-xs text-white/35">
                The terminal at each gate is the entry and exit screen, so it is behind this
                login. The occupancy dashboards are public &mdash; a wall monitor has no
                keyboard.
            </p>
        </div>

        {{-- What it does --}}
        <div class="mt-20">
            <h2 class="text-xs font-bold uppercase tracking-[0.25em] text-white/40">What it does</h2>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-400/15 text-teal-300">
                        <i class="fas fa-tower-broadcast"></i>
                    </span>
                    <h3 class="mt-4 font-bold text-white">Gate terminals</h3>
                    <p class="mt-2 text-sm leading-relaxed text-white/50">
                        A tablet at each gate, bound to one entry or one exit. A scan reads the
                        card and opens a stay; a card that has never been seen asks the driver
                        to register once, right there at the gate. One sign-in per shift, and
                        the tablet keeps its own gate across a restart.
                    </p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-400/15 text-sky-300">
                        <i class="fas fa-gauge-high"></i>
                    </span>
                    <h3 class="mt-4 font-bold text-white">Live occupancy</h3>
                    <p class="mt-2 text-sm leading-relaxed text-white/50">
                        Free and occupied counts per lot, and a per-floor schematic of every
                        slot, updating as IR beams across the bays are broken and restored.
                    </p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-400/15 text-amber-300">
                        <i class="fas fa-address-card"></i>
                    </span>
                    <h3 class="mt-4 font-bold text-white">Card registry</h3>
                    <p class="mt-2 text-sm leading-relaxed text-white/50">
                        Every RFID card is bound to exactly one vehicle, type and all. A card
                        that has never been seen asks the driver to register once, at the gate;
                        staff register and correct the records from the admin panel.
                    </p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-fuchsia-400/15 text-fuchsia-300">
                        <i class="fas fa-layer-group"></i>
                    </span>
                    <h3 class="mt-4 font-bold text-white">Lots, floors &amp; fees</h3>
                    <p class="mt-2 text-sm leading-relaxed text-white/50">
                        Each lot has its own floors, slot counts, 2W/4W split and hourly rate.
                        The fee for a stay is worked out from those and from how long the vehicle
                        was in.
                    </p>
                </div>
            </div>
        </div>

        {{-- Roles --}}
        <div class="mt-20">
            <h2 class="text-xs font-bold uppercase tracking-[0.25em] text-white/40">Who signs in</h2>

            <div class="mt-6 divide-y divide-white/10 overflow-hidden rounded-2xl border border-white/10 bg-white/5">
                <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 px-6 py-4">
                    <span class="w-32 shrink-0 font-bold text-white">Super admin</span>
                    <span class="flex-1 text-sm text-white/50">Every lot, plus staff accounts and the corrections that touch other people's records.</span>
                </div>
                <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 px-6 py-4">
                    <span class="w-32 shrink-0 font-bold text-white">Lot admin</span>
                    <span class="flex-1 text-sm text-white/50">The lots they are assigned: floors, slots, kiosks and the card registry.</span>
                </div>
                <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 px-6 py-4">
                    <span class="w-32 shrink-0 font-bold text-white">Operator</span>
                    <span class="flex-1 text-sm text-white/50">Gate staff. They run the terminal for their own lot's gate and nothing else.</span>
                </div>
            </div>
        </div>

        <div class="mt-20 border-t border-white/10 pt-8 text-center text-xs text-white/30">
            ParkEasy &mdash; RFID parking management. Occupancy figures are public; everything else is behind the login.
        </div>
    </div>
</div>