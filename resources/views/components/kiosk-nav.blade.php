{{-- Responsive nav shared by every page: inline row on lg+, hamburger dropdown below the header on smaller screens. --}}

<nav class="hidden items-stretch gap-2 lg:flex">
    <a href="{{ route('lots.overview') }}"
        class="flex items-center gap-2 rounded-xl bg-white/10 border border-white/20 px-4 py-2 text-sm font-bold text-white/80 hover:bg-white/20 transition">
        <i class="fas fa-chart-line"></i> Lot Report
    </a>
    <a href="{{ route('slots.dashboard') }}"
        class="flex items-center gap-2 rounded-xl bg-white/10 border border-white/20 px-4 py-2 text-sm font-bold text-white/80 hover:bg-white/20 transition">
        <i class="fas fa-map-location-dot"></i> Slot Monitor
    </a>
    <a href="{{ route('admin.lots') }}"
        class="flex items-center gap-2 rounded-xl bg-white/10 border border-white/20 px-4 py-2 text-sm font-bold text-white/80 hover:bg-white/20 transition">
        <i class="fas fa-building"></i> Parking Lots
    </a>
    <a href="{{ route('admin.kiosks') }}"
        class="flex items-center gap-2 rounded-xl bg-white/10 border border-white/20 px-4 py-2 text-sm font-bold text-white/80 hover:bg-white/20 transition">
        <i class="fas fa-id-card"></i> Kiosks
    </a>
    <a href="{{ route('admin.floors') }}"
        class="flex items-center gap-2 rounded-xl bg-white/10 border border-white/20 px-4 py-2 text-sm font-bold text-white/80 hover:bg-white/20 transition">
        <i class="fas fa-gear"></i> Configure
    </a>
</nav>

<button id="kiosk-nav-toggle" type="button" aria-label="Toggle menu" aria-expanded="false"
    class="flex h-11 w-11 items-center justify-center rounded-xl border border-white/20 bg-white/10 text-lg text-white/80 transition hover:bg-white/20 lg:hidden">
    <i id="kiosk-nav-icon" class="fas fa-bars"></i>
</button>

{{-- Dropdown panel anchored to the header the nav lives in (needs a `relative` wrapper). --}}
<div id="kiosk-nav-menu" class="absolute left-4 right-4 top-full hidden flex-col gap-2 lg:hidden">
    <a href="{{ route('lots.overview') }}"
        class="flex items-center justify-center gap-2 rounded-xl bg-white/10 border border-white/20 px-4 py-3 text-sm font-bold text-white/80 hover:bg-white/20 transition">
        <i class="fas fa-chart-line"></i> Lot Report
    </a>
    <a href="{{ route('slots.dashboard') }}"
        class="flex items-center justify-center gap-2 rounded-xl bg-white/10 border border-white/20 px-4 py-3 text-sm font-bold text-white/80 hover:bg-white/20 transition">
        <i class="fas fa-map-location-dot"></i> Slot Monitor
    </a>
    <a href="{{ route('admin.lots') }}"
        class="flex items-center justify-center gap-2 rounded-xl bg-white/10 border border-white/20 px-4 py-3 text-sm font-bold text-white/80 hover:bg-white/20 transition">
        <i class="fas fa-building"></i> Parking Lots
    </a>
    <a href="{{ route('admin.kiosks') }}"
        class="flex items-center justify-center gap-2 rounded-xl bg-white/10 border border-white/20 px-4 py-3 text-sm font-bold text-white/80 hover:bg-white/20 transition">
        <i class="fas fa-id-card"></i> Kiosks
    </a>
    <a href="{{ route('admin.floors') }}"
        class="flex items-center justify-center gap-2 rounded-xl bg-white/10 border border-white/20 px-4 py-3 text-sm font-bold text-white/80 hover:bg-white/20 transition">
        <i class="fas fa-gear"></i> Configure
    </a>
</div>

<script>
    (function () {
        const toggle = document.getElementById('kiosk-nav-toggle');
        const menu = document.getElementById('kiosk-nav-menu');
        const icon = document.getElementById('kiosk-nav-icon');
        if (!toggle || !menu || !icon) return;

        const close = () => {
            menu.classList.add('hidden');
            menu.classList.remove('flex');
            toggle.setAttribute('aria-expanded', 'false');
            icon.classList.add('fa-bars');
            icon.classList.remove('fa-xmark');
        };

        toggle.addEventListener('click', () => {
            const isHidden = menu.classList.toggle('hidden');
            menu.classList.toggle('flex', !isHidden);
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