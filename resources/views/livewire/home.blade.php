<div class="relative min-h-[calc(100dvh_-_4rem)] w-full overflow-hidden bg-[#070312] text-white flex flex-col" data-kiosk-root>

    <div class="kiosk-bg absolute inset-0"></div>
    <div
        class="kiosk-orb pointer-events-none absolute -top-24 -left-24 h-96 w-96 rounded-full bg-teal-400 opacity-30 blur-[100px]">
    </div>
    <div
        class="kiosk-orb pointer-events-none absolute top-1/3 -right-32 h-[28rem] w-[28rem] rounded-full bg-fuchsia-500 opacity-25 blur-[120px]">
    </div>
    <div
        class="kiosk-orb pointer-events-none absolute -bottom-32 left-1/4 h-96 w-96 rounded-full bg-amber-500 opacity-20 blur-[110px]">
    </div>
    <div class="pointer-events-none absolute inset-0 kiosk-grain opacity-30"></div>

    <div class="relative z-10 flex w-full max-w-6xl flex-1 flex-col mx-auto px-6 py-6">

        <header class="relative flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span
                    class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 text-2xl text-white shadow-lg shadow-blue-500/40">
                    <i class="fas fa-car-side"></i>
                </span>
                <div>
                    <h1 class="text-3xl font-black uppercase leading-none tracking-widest">Park<span
                            class="text-sky-400">E</span>asy</h1>
                    <p class="mt-1 text-sm text-white/70">Smart card parking kiosk</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right hidden lg:block">
                    <div id="kiosk-clock" class="text-xl font-bold tabular-nums drop-shadow"></div>
                    <div id="kiosk-date" class="text-xs text-white/60"></div>
                </div>
            </div>
        </header>

        <div class="mt-3 flex items-center justify-between gap-4 lg:hidden">
            <div id="kiosk-clock-mobile" class="text-lg font-bold tabular-nums drop-shadow"></div>
            <div id="kiosk-date-mobile" class="text-xs text-white/60"></div>
        </div>
        @if ($gateChoices->isNotEmpty())
            {{-- An operator with more than one gate has a real choice to make - entry or
                 exit - and the answer has to land in the binding rather than be guessed at.
                 The scanner below is meaningless until a gate is chosen, so it is replaced
                 rather than shown greyed out. --}}
            <main class="my-10 flex flex-1 flex-col items-center justify-center gap-6">
                <div class="text-center">
                    <h2 class="text-2xl font-black uppercase tracking-[0.2em] text-white">Choose your gate</h2>
                    <p class="mt-2 text-sm text-white/60">
                        This terminal stays on the gate you pick, across browser restarts, until you unbind it.
                    </p>
                </div>
                <div class="grid w-full max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($gateChoices as $gate)
                        <a href="{{ route('home', ['kiosk' => $gate->key]) }}"
                            class="flex items-center gap-4 rounded-3xl border border-white/15 bg-white/10 px-6 py-5 text-white shadow-2xl backdrop-blur-xl transition hover:border-sky-400/60 hover:bg-white/15">
                            <span @class([
                                    'flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-xl text-white shadow-lg',
                                    'bg-gradient-to-br from-emerald-400 to-teal-600 shadow-teal-500/30' => $gate->isEntry(),
                                    'bg-gradient-to-br from-rose-500 to-orange-500 shadow-orange-500/30' => $gate->isExit(),
                                    'bg-gradient-to-br from-sky-400 to-blue-600 shadow-blue-500/30' => $gate->type === null,
                                ])>
                                <i @class([
                                        'fas',
                                        'fa-arrow-right-to-bracket' => $gate->isEntry(),
                                        'fa-arrow-right-from-bracket' => $gate->isExit(),
                                        'fa-circle-question' => $gate->type === null,
                                    ])></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-lg font-black uppercase tracking-widest">{{ $gate->name }}</span>
                                <span class="mt-0.5 block text-xs font-bold uppercase tracking-[0.3em] text-white/60">
                                    {{ $gate->parkingLot?->name }} ·
                                    {{ $gate->type === null ? 'No gate type' : \Illuminate\Support\Str::upper($gate->type) }}
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </main>
        @else
            @if (!$kioskLotNumber)
                <p class="mt-2 text-center text-sm font-bold text-amber-300">
                    <i class="fas fa-triangle-exclamation mr-1"></i>
                    This kiosk is not linked to a parking lot. Register it in the admin panel and open it with
                    <span class="font-mono text-amber-200">/?kiosk=&lt;key&gt;</span>
                </p>
            @endif

        @if ($kioskLotNumber)
            <div class="flex justify-center mt-6">
                <div
                    class="flex items-center gap-4 rounded-2xl border border-white/15 bg-white/10 px-8 py-4 shadow-2xl backdrop-blur-xl">
                    <span
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-teal-400 to-emerald-600 text-xl text-white shadow-lg shadow-teal-500/30">
                        <i class="fas fa-id-card"></i>
                    </span>
                    <div class="text-center sm:text-left">
                        <div class="text-xl font-black uppercase tracking-[0.2em] text-teal-200">{{ $kioskName }}
                        </div>
                        <div class="mt-0.5 text-xs font-bold uppercase tracking-[0.3em] text-white/60">
                            {{ $kioskLotName }} · <span class="text-teal-300">PARKING LOT #{{ $kioskLotNumber }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif
        @if ($kioskType)
            <p id="kiosk-hint" class="mt-6 text-center text-lg text-white/85">Scan your card</p>
        @else
            <p id="kiosk-hint" class="mt-6 text-center text-lg font-bold text-amber-300">
                <i class="fas fa-triangle-exclamation mr-1"></i>
                This kiosk has no gate type. Set it to ENTRY or EXIT in the admin panel to start scanning.
            </p>
        @endif

        @php
            $entryActive = $kioskType === 'entry';
            $exitActive = $kioskType === 'exit';
        @endphp
        {{-- These are status indicators, not controls: the kiosk's registered gate type
             decides what a scan does, so there is nothing for a driver to press. --}}
        <main class="grid flex-1 grid-cols-1 items-stretch gap-8 sm:grid-cols-2 my-8" role="status" aria-label="Gate status">
            <div id="scan-entry" @class([
                    'kiosk-gate relative flex flex-col items-center justify-center gap-5 rounded-[2.5rem] p-10 text-white',
                    'bg-gradient-to-br from-emerald-400 to-teal-600 shadow-[0_25px_80px_-15px_rgba(16,185,129,.55)]' => $entryActive,
                    'bg-white/5 text-white/25 border border-white/10' => ! $entryActive,
                ])>
                <i @class([
                        'fas fa-arrow-right-to-bracket kiosk-gate-icon text-7xl sm:text-8xl',
                        'drop-shadow-lg' => $entryActive,
                    ])></i>
                <span class="text-5xl sm:text-6xl font-black tracking-[0.2em]">ENTRY</span>
                <span class="kiosk-gate-tag text-sm font-bold uppercase tracking-[0.35em]">
                    @if ($entryActive)
                        <i class="fas fa-circle-check mr-1"></i> This gate — admits vehicles
                    @else
                        Inactive
                    @endif
                </span>
            </div>

            <div id="scan-exit" @class([
                    'kiosk-gate relative flex flex-col items-center justify-center gap-5 rounded-[2.5rem] p-10 text-white',
                    'bg-gradient-to-br from-rose-500 to-orange-500 shadow-[0_25px_80px_-15px_rgba(244,63,94,.55)]' => $exitActive,
                    'bg-white/5 text-white/25 border border-white/10' => ! $exitActive,
                ])>
                <i @class([
                        'fas fa-arrow-right-from-bracket kiosk-gate-icon text-7xl sm:text-8xl',
                        'drop-shadow-lg' => $exitActive,
                    ])></i>
                <span class="text-5xl sm:text-6xl font-black tracking-[0.2em]">EXIT</span>
                <span class="kiosk-gate-tag text-sm font-bold uppercase tracking-[0.35em]">
                    @if ($exitActive)
                        <i class="fas fa-circle-check mr-1"></i> This gate — releases vehicles
                    @else
                        Inactive
                    @endif
                </span>
            </div>
        </main>

        <section class="rounded-3xl border border-white/15 bg-white/10 px-6 py-5 shadow-2xl backdrop-blur-xl">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-end">
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <div class="text-xs font-bold uppercase tracking-[0.25em] text-white/60">Card</div>
                        <div id="card-readout"
                            class="min-h-[2rem] font-mono text-2xl font-bold tracking-[0.2em] text-emerald-300">&nbsp;
                        </div>
                    </div>
                    <span id="scan-chip"
                        class="hidden rounded-full px-4 py-2 text-xs font-black uppercase tracking-[0.2em] text-white">Idle</span>
                </div>
            </div>
            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                <label class="flex-1 text-xs font-bold uppercase tracking-[0.2em] text-white/50"
                    for="manual-card">Manual card entry</label>
                <input id="manual-card" type="text" autocomplete="off"
                    placeholder="Type a card number, then press Enter"
                    class="w-full sm:w-80 rounded-xl border border-white/20 bg-white/10 px-4 py-3 font-mono text-lg tracking-widest text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
            </div>
        </section>

        <section class="mt-6">
            <h2 class="mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-[0.3em] text-white/50">
                <i class="fas fa-clock-rotate-left"></i> Recent scans
            </h2>
            <ul id="recent-list" class="flex flex-col gap-1.5">
                @forelse ($recentScans as $scan)
                    @include('components.recent-scan-row', ['scan' => $scan])
                @empty
                    <li class="text-sm italic text-white/40">Scans will appear here.</li>
                @endforelse
            </ul>

            {{--
                The live-scan JS clones this and fills text only, so a live row can never
                drift from the server-rendered one. Populated with every slot the row can
                show; pushRecent removes the ones that don't apply.
            --}}
            <template id="recent-row-template">
                <li data-card-code="" data-scan-status="" data-kiosk=""
                    class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold">
                    <i data-slot="icon" class="fas fa-arrow-right-to-bracket text-emerald-300"></i>
                    <span data-slot="status" class="uppercase tracking-widest">Parked</span>
                    <i data-slot="type-icon" class="fas fa-car text-sm text-teal-300"></i>
                    <span data-slot="vehicle-number" class="font-mono tracking-widest text-white/80"></span>
                    <span data-slot="driver-name" class="text-white/60"></span>
                    <span data-slot="card" class="font-mono text-xs text-sky-300/80">Card </span>
                    <span data-slot="amount" class="text-amber-300"></span>
                    <span data-slot="kiosk" class="font-mono text-[0.65rem] uppercase tracking-widest text-white/35"></span>
                    <span data-slot="time" class="ml-auto text-xs text-white/40"></span>
                </li>
            </template>
        </section>

        @endif

        <footer class="mt-6 flex flex-wrap items-center justify-between gap-2 text-xs text-white/50">
            <span><i class="fas fa-bolt text-amber-400"></i> Live via Reverb</span>
            @if ($kioskKey && auth()->user()?->operatesKiosks())
                {{-- The binding is remembered for a year, so a shared tablet needs a way
                     back to the unbound state without clearing site data. An operator needs
                     it too: it is how they move a terminal from one gate to another, which
                     drops them back onto the picker above. Staff previewing a kiosk hold no
                     binding - ResolveKioskBinding releases it - so offering the link to them
                     would be a button that does nothing.

                     The `guest()` arm this used to have is gone with the guest terminal: the
                     route is behind `auth`, so there is no longer an anonymous browser here
                     to unbind. --}}
                <a href="{{ route('kiosk.forget') }}"
                    class="rounded-lg border border-white/15 bg-white/5 px-3 py-1.5 text-white/60 transition hover:bg-white/15 hover:text-white">
                    <i class="fas fa-link-slash mr-1"></i> Not this kiosk? Unbind
                </a>
            @endif
            <span>© {{ date('Y') }} ParkEasy</span>
        </footer>
    </div>

    <div id="result-overlay"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-md">
        <div id="result-card"
            class="result-pop relative w-full max-w-xl rounded-[2.5rem] border border-white/20 bg-white/15 p-12 text-center shadow-2xl backdrop-blur-2xl">
            <button type="button" id="result-close"
                class="absolute right-6 top-6 text-2xl text-white/60 hover:text-white" aria-label="Close">
                <i class="fas fa-xmark"></i>
            </button>
            <i id="result-icon" class="fas fa-arrow-right-to-bracket text-8xl"></i>
            <div id="result-title" class="mt-6 text-5xl font-black uppercase tracking-[0.2em]"></div>
            <div id="result-card-code" class="mt-4 font-mono text-3xl font-bold tracking-[0.35em]"></div>
            <div id="result-vehicle" class="mt-3 text-xl font-bold tracking-wide"></div>
            <div id="result-meta" class="mt-3 text-lg text-white/80"></div>
            <div id="result-fee" class="mt-3 text-4xl font-black text-amber-300"></div>
            <div id="result-sub" class="mt-2 text-sm text-white/50">Preparing for the next scan…</div>
        </div>
    </div>

    {{-- Shown once, the first time a card is seen: it binds the card to a vehicle so every
         later visit is resolved from the card alone. --}}
    <div id="enrol-overlay"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-md p-4">
        <div id="enrol-card"
            class="result-pop relative w-full max-w-2xl rounded-[2.5rem] border border-amber-300/40 bg-slate-900/80 p-8 text-left shadow-2xl backdrop-blur-2xl sm:p-10">
            <button type="button" id="enrol-close"
                class="absolute right-6 top-6 text-2xl text-white/60 hover:text-white" aria-label="Close">
                <i class="fas fa-xmark"></i>
            </button>

            <i class="fas fa-id-card text-5xl text-amber-300"></i>
            <div class="mt-4 text-3xl font-black uppercase tracking-[0.15em] text-white">First time on this card</div>
            <p class="mt-3 text-lg text-white/70">
                Register this card to a vehicle. You only do this once — every later visit is automatic.
            </p>

            <div class="mt-5 flex items-center gap-3 rounded-2xl border border-white/15 bg-white/5 px-5 py-4">
                <span class="text-sm uppercase tracking-widest text-white/50">Card</span>
                <span id="enrol-code" class="font-mono text-2xl font-bold tracking-[0.3em] text-amber-300"></span>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="text-sm uppercase tracking-widest text-white/60">Driver name</span>
                    <input id="enrol-name" type="text" autocomplete="name" placeholder="e.g. Anil Kumar"
                        class="mt-2 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-lg text-white placeholder-white/30 outline-none focus:border-amber-300/70">
                </label>
                <label class="block">
                    <span class="text-sm uppercase tracking-widest text-white/60">Mobile number</span>
                    <input id="enrol-mobile" type="tel" inputmode="numeric" maxlength="10" autocomplete="tel"
                        placeholder="10 digits"
                        class="mt-2 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-lg text-white placeholder-white/30 outline-none focus:border-amber-300/70">
                </label>
                <label class="block">
                    <span class="text-sm uppercase tracking-widest text-white/60">Vehicle number</span>
                    <input id="enrol-vehicle" type="text" autocomplete="off" placeholder="e.g. KA01AB1234"
                        class="mt-2 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-lg uppercase text-white placeholder-white/30 outline-none focus:border-amber-300/70">
                </label>
                <label class="block">
                    <span class="text-sm uppercase tracking-widest text-white/60">Vehicle type</span>
                    <select id="enrol-type"
                        class="mt-2 w-full rounded-xl border border-white/20 bg-slate-800 px-4 py-3 text-lg text-white outline-none focus:border-amber-300/70">
                        <option value="four_wheeler">Four wheeler</option>
                        <option value="two_wheeler">Two wheeler</option>
                    </select>
                </label>
            </div>

            <div id="enrol-error" class="mt-4 hidden rounded-xl border border-rose-400/40 bg-rose-500/15 px-4 py-3 text-rose-100"></div>

            <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                <button type="button" id="enrol-submit"
                    class="flex-1 rounded-2xl bg-amber-400 px-6 py-4 text-lg font-black uppercase tracking-widest text-slate-900 transition hover:bg-amber-300 disabled:opacity-50">
                    Register &amp; park
                </button>
                <button type="button" id="enrol-cancel"
                    class="rounded-2xl border border-white/25 px-6 py-4 text-lg font-bold uppercase tracking-widest text-white/80 transition hover:bg-white/10">
                    Cancel
                </button>
            </div>
        </div>
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

        .kiosk-orb {
            animation: kioskFloat 12s ease-in-out infinite;
        }

        .kiosk-orb:nth-of-type(2) {
            animation-delay: -4s;
        }

        .kiosk-orb:nth-of-type(3) {
            animation-delay: -8s;
        }

        /* Only the live gate breathes; the inactive one stays inert so the operator can
           tell at a glance which way this kiosk scans. */
        .kiosk-gate:has(.fa-circle-check) {
            animation: kioskPulse 2s ease-in-out infinite;
        }

        .kiosk-gate:has(.fa-circle-check) .kiosk-gate-icon {
            animation: kioskWiggle 1.6s ease-in-out infinite;
        }

        @keyframes kioskShift {
            0% {
                background-position: 0% 0%;
            }

            50% {
                background-position: 100% 100%;
            }

            100% {
                background-position: 0% 0%;
            }
        }

        @keyframes kioskFloat {

            0%,
            100% {
                transform: translateY(0) scale(1);
            }

            50% {
                transform: translateY(-26px) scale(1.06);
            }
        }

        @keyframes kioskPulse {

            0%,
            100% {
                box-shadow: 0 0 0 5px rgba(255, 255, 255, 0.25), 0 30px 90px -12px rgba(255, 255, 255, 0.35);
            }

            50% {
                box-shadow: 0 0 0 12px rgba(255, 255, 255, 0.10), 0 30px 110px -10px rgba(255, 255, 255, 0.5);
            }
        }

        @keyframes kioskWiggle {

            0%,
            100% {
                transform: rotate(0deg) scale(1);
            }

            25% {
                transform: rotate(-6deg) scale(1.05);
            }

            75% {
                transform: rotate(6deg) scale(1.05);
            }
        }

        @keyframes kioskPop {
            0% {
                transform: scale(0.78);
                opacity: 0;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .result-pop {
            animation: kioskPop 0.35s cubic-bezier(0.2, 0.9, 0.3, 1.2) both;
        }
    </style>

    <script>
        (function() {
            'use strict';

            const entryGate = document.getElementById('scan-entry');
            const exitGate = document.getElementById('scan-exit');
            const chipEl = document.getElementById('scan-chip');
            const readout = document.getElementById('card-readout');
            const manual = document.getElementById('manual-card');
            const overlay = document.getElementById('result-overlay');
            const resultCard = document.getElementById('result-card');
            const resultClose = document.getElementById('result-close');
            const resultIcon = document.getElementById('result-icon');
            const resultTitle = document.getElementById('result-title');
            const resultCode = document.getElementById('result-card-code');
            const resultVehicle = document.getElementById('result-vehicle');
            const resultMeta = document.getElementById('result-meta');
            const resultFee = document.getElementById('result-fee');
            const resultSub = document.getElementById('result-sub');
            const hintEl = document.getElementById('kiosk-hint');
            const recentList = document.getElementById('recent-list');
            // Server-rendered row markup, cloned for every live scan. Keeping one copy
            // in Blade is what stops live rows drifting out of step with the list.
            const rowTemplate = document.getElementById('recent-row-template');
            const clockEl = document.getElementById('kiosk-clock');
            const dateEl = document.getElementById('kiosk-date');
            const enrolOverlay = document.getElementById('enrol-overlay');
            const enrolClose = document.getElementById('enrol-close');
            const enrolCancel = document.getElementById('enrol-cancel');
            const enrolCode = document.getElementById('enrol-code');
            const enrolName = document.getElementById('enrol-name');
            const enrolMobile = document.getElementById('enrol-mobile');
            const enrolVehicle = document.getElementById('enrol-vehicle');
            const enrolType = document.getElementById('enrol-type');
            const enrolSubmit = document.getElementById('enrol-submit');
            const enrolErrorBox = document.getElementById('enrol-error');

            let buffer = '';
            let busy = false;
            let overlayTimer = null;
            // Card held in the enrolment form while the attendant fills it in.
            let enrolCard = null;

            // Parking lot number this kiosk is registered against (?kiosk=<key> in the URL).
            const kioskLot = @json($kioskLotNumber);
            // Kiosk key from the URL; used to scope broadcasts and attribute the scan.
            const kioskKey = @json($kioskKey);
            // This kiosk's registered gate type. The kiosk decides entry vs exit, so the
            // frontend only needs to know it to label the result. A kiosk with no type
            // cannot scan at all - the backend refuses those.
            const kioskType = @json($kioskType);

            function chipShow(label, bgClass) {
                chipEl.classList.remove('hidden');
                chipEl.className = 'rounded-full px-4 py-2 text-xs font-black uppercase tracking-[0.2em] text-white ' +
                    bgClass;
                chipEl.textContent = label;
            }

            function chipHide() {
                chipEl.classList.add('hidden');
            }

            function hint(text) {
                hintEl.textContent = text;
                hintEl.style.color = '#fcd34d';
                setTimeout(() => {
                    hintEl.textContent = 'Scan your card';
                    hintEl.style.color = '';
                }, 2600);
            }

            function showResult(status, code, time, error, amount, vehicleNumber, driverName) {
                overlay.classList.remove('hidden');
                overlay.classList.add('flex');
                clearTimeout(overlayTimer);

                if (status === 'parked') {
                    resultIcon.className = 'fas fa-square-parking text-8xl text-emerald-300';
                    resultTitle.textContent = 'Vehicle Parked';
                    resultTitle.style.color = '#6ee7b7';
                    resultSub.textContent = 'Welcome — your vehicle is parked.';
                    resultCard.style.borderColor = 'rgba(110, 231, 183, 0.5)';
                } else if (status === 'exit') {
                    resultIcon.className = 'fas fa-arrow-right-from-bracket text-8xl text-rose-300';
                    resultTitle.textContent = 'Exit Recorded';
                    resultTitle.style.color = '#fda4af';
                    resultSub.textContent = 'Goodbye — you are now outside.';
                    resultCard.style.borderColor = 'rgba(253, 164, 175, 0.5)';
                } else {
                    resultIcon.className = 'fas fa-circle-exclamation text-8xl text-amber-300';
                    resultTitle.textContent = error || 'Scan failed';
                    resultTitle.style.color = '#fcd34d';
                    resultSub.textContent = 'Please try again or contact an attendant.';
                    resultCard.style.borderColor = 'rgba(252, 211, 77, 0.5)';
                }

                resultCode.textContent = code || '';
                resultVehicle.textContent = [vehicleNumber, driverName].filter(Boolean).join(' — ');
                resultMeta.textContent = time ? 'On ' + time : '';
                resultFee.textContent = (status === 'exit' && amount != null) ? 'Fee: ₹' + amount : '';

                overlayTimer = setTimeout(() => {
                    overlay.classList.add('hidden');
                    overlay.classList.remove('flex');
                }, 4000);
            }

            function closeResult() {
                clearTimeout(overlayTimer);
                overlay.classList.add('hidden');
                overlay.classList.remove('flex');
            }

            function enrolError(text) {
                if (!text) {
                    enrolErrorBox.classList.add('hidden');
                    enrolErrorBox.textContent = '';
                    return;
                }
                enrolErrorBox.textContent = text;
                enrolErrorBox.classList.remove('hidden');
            }

            // A card we've never seen: collect the driver and vehicle once, then bind it.
            function openEnrolment(code) {
                // The scan response and the kiosk's own broadcast both report this, so the
                // second call would wipe whatever the attendant has typed by then.
                if (enrolCard === code) return;

                enrolCard = code;
                enrolError('');
                enrolCode.textContent = code;
                enrolName.value = '';
                enrolMobile.value = '';
                enrolVehicle.value = '';
                enrolType.value = 'four_wheeler';
                enrolOverlay.classList.remove('hidden');
                enrolOverlay.classList.add('flex');
                enrolName.focus();
            }

            function closeEnrolment() {
                enrolCard = null;
                enrolError('');
                enrolOverlay.classList.add('hidden');
                enrolOverlay.classList.remove('flex');
                if (!busy) manual.focus();
            }

            async function submitEnrolment() {
                if (busy || !enrolCard) return;

                const payload = {
                    rfid_id: enrolCard,
                    driver_name: enrolName.value.trim(),
                    mobile_number: enrolMobile.value.trim(),
                    vehicle_number: enrolVehicle.value.trim(),
                    vehicle_type: enrolType.value,
                    lot: kioskLot,
                    kiosk: kioskKey,
                };

                // Catch the obvious slips locally so the attendant isn't bounced to a
                // server error for a typo.
                if (!payload.driver_name || !payload.vehicle_number) {
                    enrolError('Driver name and vehicle number are required.');
                    return;
                }
                if (!/^\d{10}$/.test(payload.mobile_number)) {
                    enrolError('Mobile number must be 10 digits.');
                    return;
                }

                busy = true;
                manual.disabled = true;
                enrolSubmit.disabled = true;
                enrolError('');

                try {
                    const res = await fetch('/api/rfid-scan/enrol', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload),
                    });

                    let data = {};
                    try {
                        data = await res.json();
                    } catch (e) {
                        /* ignore */
                    }

                    if (data.success) {
                        const card = enrolCard;
                        closeEnrolment();
                        showResult(data.status, card, data.time, null, data.amount, data.vehicle_number, data
                            .driver_name);
                        pushRecent(data.status, card, data.amount, data.vehicle_number, data.driver_name, data
                            .entry_id, data.vehicle_type, kioskKey);
                    } else {
                        // Validation errors arrive keyed by field; show the first one.
                        const first = data.errors ? Object.values(data.errors)[0] : null;
                        enrolError(Array.isArray(first) ? first[0] : (data.error || 'Could not register this card'));
                    }
                } catch (e) {
                    enrolError('Network error');
                } finally {
                    busy = false;
                    manual.disabled = false;
                    enrolSubmit.disabled = false;
                }
            }

            // Seeded with the entries already rendered server-side so a live broadcast for one of them isn't duplicated.
            const shownEntryIds = new Set(@json($recentScans->map(fn($scan) => $scan['entry_id'] . ':' . $scan['status'])->values()));

            // 'kiosk' is the kiosk that handled the scan. It differs per call site: the browser's own
            // scans always come from the kiosk this page is tied to, but a reader broadcast
            // names the kiosk that handled the scan.
            // Exposed so the live-row contract can be tested: pushRecent is the one function
            // that builds rows in JS, and it used to fall out of step with the Blade row.
            window.__kioskTest = { pushRecent };

            function pushRecent(status, code, amount, vehicleNumber, driverName, entryId, vehicleType, kiosk) {
                // The API response and the 'rfid' broadcast both fire for the same scan; skip the repeat.
                // An entry has separate parked/exit events, so key the dedup on both.
                if (entryId != null) {
                    const dedupKey = entryId + ':' + status;
                    if (shownEntryIds.has(dedupKey)) return;
                    shownEntryIds.add(dedupKey);
                }

                const placeholder = recentList.querySelector('li');
                if (placeholder && placeholder.closest('ul') === recentList && recentList.children.length === 1 &&
                    placeholder.textContent.includes('appear')) {
                    recentList.innerHTML = '';
                }

                // Clone the server-rendered row rather than building markup here. The two copies used
                // to drift: a field added to Blade never reached the live rows (the kiosk
                // tag went missing that way). Filling slots in a clone cannot drift.
                const li = rowTemplate.content.firstElementChild.cloneNode(true);

                const slot = (name) => li.querySelector('[data-slot="' + name + '"]');

                // A slot that has no value for this scan is removed rather than left blank.
                const fill = (name, value, fallback) => {
                    const el = slot(name);
                    if (!el) return;
                    if (value === null || value === undefined || value === '') {
                        el.remove();
                        return;
                    }
                    el.textContent = fallback !== undefined ? fallback : String(value);
                };

                li.setAttribute('data-card-code', code || '');
                li.setAttribute('data-scan-status', status);
                li.setAttribute('data-kiosk', kiosk || '');

                const parked = status === 'parked';
                const icon = slot('icon');
                if (icon) {
                    icon.className = 'fas ' + (parked ?
                        'fa-arrow-right-to-bracket text-emerald-300' :
                        'fa-arrow-right-from-bracket text-rose-300');
                }
                const typeIcon = slot('type-icon');
                if (typeIcon) {
                    if (vehicleType === 'two_wheeler') {
                        typeIcon.className = 'fas fa-motorcycle text-sm text-sky-300';
                    } else if (vehicleType === 'four_wheeler') {
                        typeIcon.className = 'fas fa-car text-sm text-teal-300';
                    } else {
                        typeIcon.remove();
                    }
                }
                // Passed a fallback because 'Parked' and 'Exit' are never falsy, and
                // fill() drops a slot that resolves to an empty string.
                slot('status').textContent = parked ? 'Parked' : 'Exit';
                fill('vehicle-number', vehicleNumber || code);
                fill('driver-name', driverName);
                fill('card', code ? 'Card ' + code : '');
                fill('amount', amount != null ? '₹' + amount : '');
                fill('kiosk', kiosk);
                fill('time', new Date().toLocaleString([], {
                    day: '2-digit',
                    month: 'short',
                    hour: '2-digit',
                    minute: '2-digit'
                }));

                recentList.prepend(li);
                while (recentList.children.length > 6) {
                    recentList.removeChild(recentList.lastChild);
                }
            }

            async function submitScan(code) {
                if (busy || !code) return;
                if (!kioskLot) {
                    hint('This kiosk is not linked to a parking lot');
                    return;
                }
                busy = true;
                manual.disabled = true;

                try {
                    const res = await fetch('/api/rfid-scan', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            rfid_id: code,
                            lot: kioskLot,
                            kiosk: kioskKey,
                        }),
                    });

                    let data = {};
                    try {
                        data = await res.json();
                    } catch (e) {
                        /* ignore */
                    }

                    if (data.success) {
                        // A card we've never seen needs enrolling once before it can park.
                        if (data.status === 'enrolment_required') {
                            openEnrolment(data.rfid_id || code);
                            return;
                        }
                        // Otherwise the card identifies the vehicle, so a successful scan is
                        // always a completed visit.
                        showResult(data.status, data.rfid_id, data.time, null, data.amount, data.vehicle_number, data
                            .driver_name);
                        pushRecent(data.status, data.rfid_id, data.amount, data.vehicle_number, data.driver_name, data
                            .entry_id, data.vehicle_type, kioskKey);
                        // Refocus so the attendant can scan or type the next card.
                        manual.focus();
                    } else {
                        showResult(null, code, null, data.error || 'Scan failed');
                    }
                } catch (e) {
                    showResult(null, code, null, 'Network error');
                } finally {
                    busy = false;
                    manual.disabled = false;
                    manual.value = '';
                    manual.focus();
                }
            }

            // The gate panes are indicators only. Attract the manual field once so the
            // attendant can type a card straight after page load.
            if (kioskType) manual.focus();

            resultClose.addEventListener('click', () => closeResult());
            enrolClose.addEventListener('click', () => closeEnrolment());
            enrolCancel.addEventListener('click', () => closeEnrolment());
            enrolSubmit.addEventListener('click', () => submitEnrolment());
            // Enter anywhere in the form submits; the kiosk is keyboard-driven.
            enrolOverlay.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') {
                    e.preventDefault();
                    submitEnrolment();
                }
            });

            // Close the overlay when the click lands on the dimmed backdrop, not the card itself.
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) closeResult();
            });

            manual.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter' || busy) return;
                e.preventDefault();
                const code = manual.value.trim().toUpperCase();
                if (!kioskType) {
                    hint('This kiosk has no gate type');
                    return;
                }
                if (!code) {
                    hint('Type a card number');
                    return;
                }
                submitScan(code);
            });

            document.addEventListener('keydown', (e) => {
                if (e.target === manual || busy || e.ctrlKey || e.metaKey || e.altKey) return;

                if (e.key === 'Enter') {
                    const code = buffer.trim();
                    buffer = '';
                    readout.textContent = '';
                    if (!kioskType) {
                        hint('This kiosk has no gate type');
                        return;
                    }
                    if (!code) {
                        hint('No card scanned');
                        return;
                    }
                    submitScan(code);
                } else if (e.key === 'Escape') {
                    // Clear a half-typed card without affecting the kiosk.
                    buffer = '';
                    readout.textContent = '';
                } else if (/^[A-Za-z0-9]$/.test(e.key)) {
                    buffer += e.key.toUpperCase();
                    readout.textContent = buffer;
                }
            });

            const clockMobileEl = document.getElementById('kiosk-clock-mobile');
            const dateMobileEl = document.getElementById('kiosk-date-mobile');

            function tick() {
                const now = new Date();
                const time = now.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                const date = now.toLocaleDateString([], {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long'
                });
                clockEl.textContent = time;
                dateEl.textContent = date;
                if (clockMobileEl) clockMobileEl.textContent = time;
                if (dateMobileEl) dateMobileEl.textContent = date;
            }
            setInterval(tick, 1000);
            tick();

// Live-update the kiosk from its own broadcast channel so any scan
            // (e.g. the hardware reader) shows up on this page immediately. Events are
            // scoped to this kiosk's key, so entry/exit kiosks never see each other's scans.
            (function attachEchoListener() {
                if (window.Echo && kioskKey) {
                    window.Echo.channel('kiosk.' + kioskKey).listen('RfidScanned', (e) => {
                        // A scan from the hardware reader for a card we've never seen.
                        if (e.status === 'enrolment_required') {
                            openEnrolment(e.rfid_id);
                            return;
                        }
                        showResult(e.status === 'error' ? null : e.status, e.rfid_id, new Date()
                            .toLocaleString(), e.message, e.amount, e.vehicle_number, e.driver_name);
                        // This scan came from the reader, so the handling kiosk is this one,
                        // not necessarily the kiosk this browser is bound to.
                        if (e.status === 'parked' || e.status === 'exit') pushRecent(e.status, e.rfid_id, e
                            .amount, e.vehicle_number, e.driver_name, e.entry_id, e.vehicle_type, e.kiosk);
                    });
                    return;
                }
                setTimeout(attachEchoListener, 200);
            })();
        })();
    </script>
</div>
