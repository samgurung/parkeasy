<div class="relative flex min-h-[calc(100dvh_-_4rem)] w-full flex-col overflow-hidden bg-[#070312] text-white">
    <div class="kiosk-bg absolute inset-0"></div>
    <div class="kiosk-orb pointer-events-none absolute -top-32 -left-24 h-96 w-96 rounded-full bg-teal-400 opacity-20 blur-[120px]"></div>
    <div class="kiosk-orb pointer-events-none absolute -bottom-40 -right-40 h-[30rem] w-[30rem] rounded-full bg-sky-500 opacity-15 blur-[130px]"></div>

    <div class="relative mx-auto flex w-full max-w-3xl flex-1 flex-col justify-center px-4 py-16">
        <span class="inline-flex w-fit items-center gap-2 rounded-full border border-teal-300/30 bg-teal-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-widest text-teal-200">
            <i class="fas fa-city"></i>  IoT based parking solution
        </span>

        <h1 class="mt-6 text-4xl font-black tracking-tight text-white sm:text-6xl">
            Smart Parking for <br class="hidden sm:block" /> A Smart City.
        </h1>

        <ul class="mt-6 flex max-w-xl flex-col gap-3 text-lg leading-relaxed text-white/60">
            <li class="flex items-start gap-3">
                <i class="fas fa-id-card mt-1 shrink-0 text-teal-300"></i>
                <span>RFID card scans extry and exit</span>
            </li>
            <li class="flex items-start gap-3">
                <i class="fas fa-signal mt-1 shrink-0 text-teal-300"></i>
                <span>Slot occupancy detection via IR sensors</span>
            </li>
            <li class="flex items-start gap-3">
                <i class="fas fa-gauge-high mt-1 shrink-0 text-teal-300"></i>
                <span>Real time parking lot occupancy rate information</span>
            </li>
        </ul>

        <div class="mt-9 flex flex-wrap items-center gap-3">
            <a href="{{ route('login') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 px-6 py-3 font-bold uppercase tracking-widest text-white shadow-lg shadow-blue-500/25 transition hover:brightness-110">
                <i class="fas fa-user-shield"></i> Sign in
            </a>
            <a href="{{ route('lots.overview') }}"
               class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/5 px-6 py-3 font-bold uppercase tracking-widest text-white/70 transition hover:bg-white/10 hover:text-white">
                <i class="fas fa-chart-simple"></i> Live occupancy
            </a>
        </div>

    </div>
</div>
