{{-- Global sticky navbar rendered by every page via the app layout.
     Organization: LIVE (monitor) group, then a SETUP workflow group whose
     numbered steps mirror the lot -> floors/slots -> kiosks setup order. --}}

<header class="sticky top-0 z-40 border-b border-white/10 bg-[#070312]/85 backdrop-blur-xl">
    <div class="flex h-16 w-full items-center justify-between gap-3 px-4 sm:px-6 lg:px-10">

        {{-- Brand -> kiosk terminal --}}
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5"
           title="ParkEasy kiosk terminal" aria-label="ParkEasy – kiosk terminal">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 text-sm text-white shadow-lg shadow-blue-500/30">
                <i class="fas fa-car-side"></i>
            </span>
            <span class="hidden text-base font-black uppercase leading-none tracking-widest sm:inline">Park<span class="text-sky-400">E</span>asy</span>
        </a>

        {{-- Desktop nav --}}
        <nav aria-label="Main" class="hidden items-center gap-1.5 lg:flex">
            <span class="mr-1 text-[10px] font-black uppercase tracking-[0.25em] text-white/35">Live</span>
            <x-nav-link route="lots.overview" icon="fas fa-chart-line" label="Lot Report" />
            <x-nav-link route="slots.dashboard" icon="fas fa-map-location-dot" label="Slot Monitor" />

            <span class="mx-2 h-6 w-px bg-white/15" role="separator"></span>

            {{-- Setup is staff-only, and each step is shown only if that capability is
                 actually held. A nav link is gated on being able to *open* the list, not
                 on being able to administer the thing: a lot admin is sent to /admin/lots
                 at sign-in and may read it, so gating that link on lots.manage left them
                 able to land on the page with no way back to it. --}}
            @auth
                @if (auth()->user()->canAny([
                        \App\Models\Access::MANAGE_LOTS,
                        \App\Models\Access::MANAGE_FLOORS,
                        \App\Models\Access::MANAGE_KIOSKS,
                        \App\Models\Access::VIEW_VEHICLES,
                    ]))
                    <span class="mx-2 h-6 w-px bg-white/15" role="separator"></span>

                    <span class="mr-1 text-[10px] font-black uppercase tracking-[0.25em] text-white/35">Setup</span>
                    @can('viewAny', \App\Models\ParkingLot::class)
                        <x-nav-link route="admin.lots" icon="fas fa-building" label="Lots" step="1" />
                    @endcan
                    @can(\App\Models\Access::MANAGE_FLOORS)
                        <i class="fas fa-chevron-right text-[9px] text-white/25"></i>
                        <x-nav-link route="admin.floors" icon="fas fa-layer-group" label="Floors" step="2" />
                    @endcan
                    @can(\App\Models\Access::MANAGE_KIOSKS)
                        <i class="fas fa-chevron-right text-[9px] text-white/25"></i>
                        <x-nav-link route="admin.kiosks" icon="fas fa-id-card" label="Kiosks" step="3" />
                    @endcan
                    @can(\App\Models\Access::VIEW_VEHICLES)
                        <i class="fas fa-chevron-right text-[9px] text-white/25"></i>
                        <x-nav-link route="admin.vehicles" icon="fas fa-car-side" label="Vehicles" step="4" />
                    @endcan
                @endif
            @endauth
        </nav>

        {{-- Staff sign-in / sign-out. The mobile menu carries the fuller version. --}}
        <div class="flex shrink-0 items-center gap-2">
            @auth
                @php
                    $navUser = auth()->user();

                    // Which lot an account is in, since everything they can reach is scoped
                    // to it. They may hold more than one, so name the first and count the
                    // rest rather than truncating a list into a mystery.
                    $myLots = $navUser->lots()->pluck('name');

                    // An account may hold several roles, so the badge names the one that
                    // decides what it can actually reach, in that order of precedence.
                    $navRole = match (true) {
                        $navUser->isSuperAdmin() => 'Super admin',
                        $navUser->isLotAdmin() => 'Lot admin',
                        $navUser->isOperator() => 'Operator',
                        default => null,
                    };
                @endphp
                @if ($navUser->isSuperAdmin())
                    <span class="hidden rounded-lg border border-amber-400/30 bg-amber-500/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-amber-300 sm:inline">
                        Super admin
                    </span>
                @else
                    <span class="hidden max-w-[12rem] truncate rounded-lg border border-white/15 bg-white/5 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-white/60 sm:inline"
                          title="{{ $navRole }} — {{ $myLots->join(', ') }}">
                        {{ $myLots->isEmpty() ? $navRole : $myLots->first().($myLots->count() > 1 ? ' +'.($myLots->count() - 1) : '') }}
                    </span>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-white/20 px-3 py-1.5 text-[11px] font-bold uppercase tracking-widest text-white/70 hover:bg-white/10">
                        Sign out
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="rounded-lg border border-sky-400/40 bg-sky-500/10 px-3 py-1.5 text-[11px] font-bold uppercase tracking-widest text-sky-300 hover:bg-sky-500/20">
                    Sign in
                </a>
            @endauth
        </div>

        {{-- Mobile hamburger --}}
        <button id="site-nav-toggle" type="button" aria-label="Toggle menu" aria-expanded="false"
                class="flex h-11 w-11 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-lg text-white/80 transition hover:bg-white/10 lg:hidden">
            <i id="site-nav-icon" class="fas fa-bars"></i>
        </button>
    </div>

    {{-- Mobile menu --}}
    <div id="site-nav-menu" class="hidden border-t border-white/10 px-4 py-4 sm:px-6 lg:hidden">
        <div class="mb-2 text-[10px] font-black uppercase tracking-[0.25em] text-white/35">Live monitoring</div>
        <div class="grid gap-2">
            <x-nav-link route="lots.overview" icon="fas fa-chart-line" label="Lot Report" />
            <x-nav-link route="slots.dashboard" icon="fas fa-map-location-dot" label="Slot Monitor" />
        </div>

        @auth
            @if (auth()->user()->canAny([
                    \App\Models\Access::MANAGE_LOTS,
                    \App\Models\Access::MANAGE_FLOORS,
                    \App\Models\Access::MANAGE_KIOSKS,
                    \App\Models\Access::VIEW_VEHICLES,
                ]))
                <div class="mb-2 mt-5 text-[10px] font-black uppercase tracking-[0.25em] text-white/35">Setup – in order</div>
                <div class="grid gap-2">
                    @can('viewAny', \App\Models\ParkingLot::class)
                        <x-nav-link route="admin.lots" label="Parking Lots" step="1" />
                    @endcan
                    @can(\App\Models\Access::MANAGE_FLOORS)
                        <x-nav-link route="admin.floors" label="Floors & Slots" step="2" />
                    @endcan
                    @can(\App\Models\Access::MANAGE_KIOSKS)
                        <x-nav-link route="admin.kiosks" label="Kiosks" step="3" />
                    @endcan
                    @can(\App\Models\Access::VIEW_VEHICLES)
                        <x-nav-link route="admin.vehicles" label="Vehicles & Cards" step="4" />
                    @endcan
                </div>

                <p class="mt-4 flex items-start gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-xs text-white/50">
                    <i class="fas fa-circle-info mt-0.5 text-sky-300"></i>
                    <span>Set up in order: add parking lots, then floors &amp; slot counts, then attach RFID kiosks to lots,
                        then bind each RFID card to its vehicle.</span>
                </p>
            @endif

            {{-- Who is signed in, and the way out. --}}
            <div class="mt-5 flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                <div class="min-w-0">
                    <div class="truncate text-sm font-bold text-white">{{ auth()->user()->name }}</div>
                    <div class="truncate text-[11px] text-white/45">
                        {{ auth()->user()->isSuperAdmin()
                            ? 'Super admin — all lots'
                            : $navRole.' — '.auth()->user()->lots()->pluck('name')->join(', ') }}
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="rounded-lg border border-white/20 px-3 py-1.5 text-xs font-bold uppercase tracking-widest text-white/70 hover:bg-white/10">
                        Sign out
                    </button>
                </form>
            </div>
        @else
            <div class="mt-5">
                <a href="{{ route('login') }}" class="block rounded-xl border border-sky-400/40 bg-sky-500/10 px-4 py-3 text-center text-xs font-bold uppercase tracking-widest text-sky-300">
                    <i class="fas fa-user-shield mr-1"></i> Staff sign in
                </a>
            </div>
        @endauth
    </div>
</header>

<script>
    (function () {
        const toggle = document.getElementById('site-nav-toggle');
        const menu = document.getElementById('site-nav-menu');
        const icon = document.getElementById('site-nav-icon');
        if (!toggle || !menu || !icon) return;

        const close = () => {
            menu.classList.add('hidden');
            menu.classList.remove('grid');
            toggle.setAttribute('aria-expanded', 'false');
            icon.classList.add('fa-bars');
            icon.classList.remove('fa-xmark');
        };

        toggle.addEventListener('click', () => {
            const isHidden = menu.classList.toggle('hidden');
            menu.classList.toggle('grid', !isHidden);
            toggle.setAttribute('aria-expanded', String(!isHidden));
            icon.classList.toggle('fa-bars', isHidden);
            icon.classList.toggle('fa-xmark', !isHidden);
        });

        document.addEventListener('click', (e) => {
            if (!toggle.contains(e.target) && !menu.classList.contains('hidden')) close();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !menu.classList.contains('hidden')) close();
        });
    })();
</script>
