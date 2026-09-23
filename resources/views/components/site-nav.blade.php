{{-- Global sticky navbar rendered by every page via the app layout.
     Organization: LIVE (monitor) group, then a SETUP workflow group whose
     numbered steps mirror the lot -> floors/slots -> kiosks setup order. --}}

<header class="sticky top-0 z-40 border-b border-white/10 bg-[#070312]/85 backdrop-blur-xl">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6">

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

            <span class="mr-1 text-[10px] font-black uppercase tracking-[0.25em] text-white/35">Setup</span>
            <x-nav-link route="admin.lots" icon="fas fa-building" label="Lots" step="1" />
            <i class="fas fa-chevron-right text-[9px] text-white/25"></i>
            <x-nav-link route="admin.floors" icon="fas fa-layer-group" label="Floors" step="2" />
            <i class="fas fa-chevron-right text-[9px] text-white/25"></i>
            <x-nav-link route="admin.kiosks" icon="fas fa-id-card" label="Kiosks" step="3" />
        </nav>

        {{-- Mobile hamburger --}}
        <button id="site-nav-toggle" type="button" aria-label="Toggle menu" aria-expanded="false"
                class="flex h-11 w-11 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-lg text-white/80 transition hover:bg-white/10 lg:hidden">
            <i id="site-nav-icon" class="fas fa-bars"></i>
        </button>
    </div>

    {{-- Mobile menu --}}
    <div id="site-nav-menu" class="hidden border-t border-white/10 px-4 py-4 lg:hidden">
        <div class="mb-2 text-[10px] font-black uppercase tracking-[0.25em] text-white/35">Live monitoring</div>
        <div class="grid gap-2">
            <x-nav-link route="lots.overview" icon="fas fa-chart-line" label="Lot Report" />
            <x-nav-link route="slots.dashboard" icon="fas fa-map-location-dot" label="Slot Monitor" />
        </div>

        <div class="mb-2 mt-5 text-[10px] font-black uppercase tracking-[0.25em] text-white/35">Setup – in order</div>
        <div class="grid gap-2">
            <x-nav-link route="admin.lots" label="Parking Lots" step="1" />
            <x-nav-link route="admin.floors" label="Floors & Slots" step="2" />
            <x-nav-link route="admin.kiosks" label="Kiosks" step="3" />
        </div>

        <p class="mt-4 flex items-start gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-xs text-white/50">
            <i class="fas fa-circle-info mt-0.5 text-sky-300"></i>
            <span>Set up in order: add parking lots, then floors &amp; slot counts, then attach RFID kiosks to lots.</span>
        </p>
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