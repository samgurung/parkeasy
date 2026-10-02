{{-- What a signed-out visitor gets at /. Explains the app and offers the way in, because
     "redirect to /login" is a wall with no explanation on it: it tells a first-time visitor
     what they may not see, but not what they are signing in to.

     Deliberately short. A visitor who came from a link or a QR code wants to know what this
     is and how to get in, not to be walked through the feature set - the app itself is the
     demonstration, and a long pitch is the thing that gets in the way of signing in.

     Copy stays confined to what the app actually does. In particular the fee is *computed*
     per stay from the lot's rate, not collected - there is no payment, so nothing here
     promises one. --}}

<div class="relative flex min-h-[calc(100dvh_-_4rem)] w-full flex-col overflow-hidden bg-[#070312] text-white">
    <div class="kiosk-bg absolute inset-0"></div>
    <div class="kiosk-orb pointer-events-none absolute -top-32 -left-24 h-96 w-96 rounded-full bg-teal-400 opacity-20 blur-[120px]"></div>
    <div class="kiosk-orb pointer-events-none absolute -bottom-40 -right-40 h-[30rem] w-[30rem] rounded-full bg-sky-500 opacity-15 blur-[130px]"></div>

    <div class="relative mx-auto flex w-full max-w-3xl flex-1 flex-col justify-center px-4 py-16">
        <span class="inline-flex w-fit items-center gap-2 rounded-full border border-teal-300/30 bg-teal-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-widest text-teal-200">
            <i class="fas fa-id-card"></i> RFID parking management
        </span>

        <h1 class="mt-6 text-4xl font-black tracking-tight text-white sm:text-6xl">
            Every gate, every slot,<br class="hidden sm:block" /> every card.
        </h1>

        <p class="mt-6 max-w-xl text-lg leading-relaxed text-white/60">
            RFID cards in and out at the gate, live slot occupancy, and the registry behind both.
        </p>

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

        <p class="mt-8 max-w-xl border-l-2 border-white/10 pl-4 text-sm leading-relaxed text-white/40">
            The terminal at each gate is the entry and exit screen, so it sits behind this
            login. The occupancy dashboards are public &mdash; a wall monitor has no keyboard.
        </p>
    </div>
</div>