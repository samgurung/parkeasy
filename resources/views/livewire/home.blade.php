<div class="relative min-h-screen w-full overflow-hidden bg-[#070312] text-white flex flex-col" data-kiosk-root>

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
                <div class="text-right hidden lg:block">
                    <div id="kiosk-clock" class="text-xl font-bold tabular-nums drop-shadow"></div>
                    <div id="kiosk-date" class="text-xs text-white/60"></div>
                </div>
                <button id="menu-toggle" type="button" aria-label="Toggle menu" aria-expanded="false"
                    class="flex h-11 w-11 items-center justify-center rounded-xl border border-white/20 bg-white/10 text-lg text-white/80 transition hover:bg-white/20 lg:hidden">
                    <i id="menu-icon" class="fas fa-bars"></i>
                </button>
            </div>
        </header>

        <div id="mobile-menu" class="mt-3 hidden flex-col gap-2 lg:hidden">
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
        <div class="mt-3 flex items-center justify-between gap-4 lg:hidden">
            <div id="kiosk-clock-mobile" class="text-lg font-bold tabular-nums drop-shadow"></div>
            <div id="kiosk-date-mobile" class="text-xs text-white/60"></div>
        </div>
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
        <p id="kiosk-hint" class="mt-6 text-center text-lg text-white/85">Tap a direction, then scan your card</p>

        <main class="grid flex-1 grid-cols-1 items-stretch gap-8 sm:grid-cols-2 my-8">
            <button id="scan-entry" type="button"
                class="kiosk-btn group relative flex flex-col items-center justify-center gap-5 rounded-[2.5rem] bg-gradient-to-br from-emerald-400 to-teal-600 p-10 text-white shadow-[0_25px_80px_-15px_rgba(16,185,129,.55)]">
                <i
                    class="fas fa-arrow-right-to-bracket kiosk-btn-icon text-7xl sm:text-8xl transition-transform duration-300 group-hover:scale-110"></i>
                <span class="text-5xl sm:text-6xl font-black tracking-[0.2em] drop-shadow-lg">ENTRY</span>
                <span class="kiosk-btn-tag text-sm font-bold uppercase tracking-[0.35em] text-white/85">Entering</span>
            </button>

            <button id="scan-exit" type="button"
                class="kiosk-btn group relative flex flex-col items-center justify-center gap-5 rounded-[2.5rem] bg-gradient-to-br from-rose-500 to-orange-500 p-10 text-white shadow-[0_25px_80px_-15px_rgba(244,63,94,.55)]">
                <i
                    class="fas fa-arrow-right-from-bracket kiosk-btn-icon text-7xl sm:text-8xl transition-transform duration-300 group-hover:scale-110"></i>
                <span class="text-5xl sm:text-6xl font-black tracking-[0.2em] drop-shadow-lg">EXIT</span>
                <span class="kiosk-btn-tag text-sm font-bold uppercase tracking-[0.35em] text-white/85">Leaving</span>
            </button>
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
                    <li
                        class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold">
                        <i
                            class="fas {{ $scan['status'] === 'parked' ? 'fa-arrow-right-to-bracket text-emerald-300' : 'fa-arrow-right-from-bracket text-rose-300' }}"></i>
                        <span
                            class="uppercase tracking-widest">{{ $scan['status'] === 'parked' ? 'Parked' : 'Exit' }}</span>
                        <span class="font-mono tracking-widest text-white/80">{{ $scan['vehicle_number'] }}</span>
                        @if ($scan['driver_name'])
                            <span class="text-white/60">{{ $scan['driver_name'] }}</span>
                        @endif
                        @if ($scan['rfid_id'])
                            <span class="font-mono text-xs text-sky-300/80">Card {{ $scan['rfid_id'] }}</span>
                        @endif
                        @if ($scan['amount'] !== null)
                            <span class="text-amber-300">₹{{ $scan['amount'] }}</span>
                        @endif
                        <span class="ml-auto text-xs text-white/40">{{ $scan['time']->format('d M, h:i A') }}</span>
                    </li>
                @empty
                    <li class="text-sm italic text-white/40">Scans will appear here.</li>
                @endforelse
            </ul>
        </section>

        <footer class="mt-6 flex items-center justify-between text-xs text-white/50">
            <span><i class="fas fa-bolt text-amber-400"></i> Live via Reverb</span>
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

    <div id="details-overlay"
        class="fixed  inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-md">
        <form id="details-form"
            class="result-pop relative w-full md:w-1/2 max-w-lg rounded-[2.5rem] border border-white/20 bg-white/15 p-10 shadow-2xl backdrop-blur-2xl">
            <button type="button" id="details-close"
                class="absolute right-6 top-6 text-2xl text-white/60 hover:text-white" aria-label="Close">
                <i class="fas fa-xmark"></i>
            </button>
            <div class="mb-6 text-center">
                <i class="fas fa-clipboard-list text-6xl text-sky-300"></i>
                <div class="mt-4 text-3xl font-black uppercase tracking-[0.2em]">Parking Details</div>
                <div id="details-card-code"
                    class="mt-2 font-mono text-xl font-bold tracking-[0.3em] text-emerald-300">
                </div>
            </div>
            <div class="flex flex-col gap-4">
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60" for="driver-name">Driver
                        name</label>
                    <input id="driver-name" name="driver_name" type="text" required autocomplete="off"
                        class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-lg text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60"
                        for="vehicle-number">Vehicle registration number</label>
                    <input id="vehicle-number" name="vehicle_number" type="text" required autocomplete="off"
                        class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-lg uppercase tracking-widest text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60"
                        for="mobile-number">Mobile number</label>
                    <input id="mobile-number" name="mobile_number" type="tel" required autocomplete="off"
                        maxlength="10"
                        class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-lg tracking-widest text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                </div>
                <div id="details-error" class="hidden text-sm font-semibold text-rose-300"></div>
                <button type="submit"
                    class="mt-2 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-600 py-3 text-lg font-black uppercase tracking-[0.2em] text-white shadow-lg">
                    Confirm parking
                </button>
            </div>
        </form>
    </div>

    <div id="register-overlay"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-md">
        <form id="register-form"
            class="result-pop relative w-full max-w-lg rounded-[2.5rem] border border-white/20 bg-white/15 p-10 shadow-2xl backdrop-blur-2xl">
            <button type="button" id="register-close"
                class="absolute right-6 top-6 text-2xl text-white/60 hover:text-white" aria-label="Close">
                <i class="fas fa-xmark"></i>
            </button>
            <div class="mb-6 text-center">
                <i class="fas fa-id-card text-6xl text-amber-300"></i>
                <div class="mt-4 text-3xl font-black uppercase tracking-[0.2em]">Register Card</div>
                <div class="mt-2 text-sm text-white/60">This card isn't registered yet</div>
                <div id="register-card-code" class="mt-2 font-mono text-xl font-bold tracking-[0.3em] text-amber-300">
                </div>
            </div>
            <div class="flex flex-col gap-4">
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60"
                        for="register-name">Driver's
                        name</label>
                    <input id="register-name" name="name" type="text" required autocomplete="off"
                        class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-lg text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60"
                        for="register-phone">Phone number</label>
                    <input id="register-phone" name="phone" type="tel" required autocomplete="off"
                        maxlength="10" pattern="[0-9]{10}" title="Enter a 10-digit phone number"
                        class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-lg tracking-widest text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-[0.2em] text-white/60"
                        for="register-vehicle-number">Vehicle registration number</label>
                    <input id="register-vehicle-number" name="vehicle_number" type="text" required
                        autocomplete="off"
                        class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-lg uppercase tracking-widest text-white placeholder-white/40 outline-none focus:border-sky-400 focus:bg-white/15" />
                </div>
                <div id="register-error" class="hidden text-sm font-semibold text-rose-300"></div>
                <button type="submit"
                    class="mt-2 rounded-xl bg-gradient-to-br from-amber-400 to-orange-600 py-3 text-lg font-black uppercase tracking-[0.2em] text-white shadow-lg">
                    Register
                </button>
            </div>
        </form>
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

        .kiosk-btn {
            transition: transform 0.15s ease, box-shadow 0.2s ease, filter 0.2s ease;
            will-change: transform;
        }

        .kiosk-btn:hover {
            transform: translateY(-4px) scale(1.015);
            filter: brightness(1.07);
        }

        .kiosk-btn:active {
            transform: scale(0.96);
        }

        .kiosk-btn.armed {
            box-shadow: 0 0 0 5px rgba(255, 255, 255, 0.25), 0 30px 90px -12px rgba(255, 255, 255, 0.35);
            animation: kioskPulse 1.5s ease-in-out infinite;
        }

        .kiosk-btn.armed .kiosk-btn-tag {
            color: #ffe9a8;
        }

        .kiosk-btn.armed .kiosk-btn-icon {
            animation: kioskWiggle 1.1s ease-in-out infinite;
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

            const entryBtn = document.getElementById('scan-entry');
            const exitBtn = document.getElementById('scan-exit');
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
            const clockEl = document.getElementById('kiosk-clock');
            const dateEl = document.getElementById('kiosk-date');
            const detailsOverlay = document.getElementById('details-overlay');
            const detailsForm = document.getElementById('details-form');
            const detailsCode = document.getElementById('details-card-code');
            const detailsError = document.getElementById('details-error');
            const detailsClose = document.getElementById('details-close');
            const registerOverlay = document.getElementById('register-overlay');
            const registerForm = document.getElementById('register-form');
            const registerCode = document.getElementById('register-card-code');
            const registerError = document.getElementById('register-error');
            const registerClose = document.getElementById('register-close');

            let armed = null;
            let buffer = '';
            let busy = false;
            let overlayTimer = null;
            let pendingRfid = null;

            // Parking lot number this kiosk is registered against (?kiosk=<key> in the URL).
            const kioskLot = @json($kioskLotNumber);
            // Kiosk key from the URL; used to scope broadcasts and armed-mode to this kiosk.
            const kioskKey = @json($kioskKey);

            function chipShow(label, bgClass) {
                chipEl.classList.remove('hidden');
                chipEl.className = 'rounded-full px-4 py-2 text-xs font-black uppercase tracking-[0.2em] text-white ' +
                    bgClass;
                chipEl.textContent = label;
            }

            function chipHide() {
                chipEl.classList.add('hidden');
            }

            function setArmed(dir) {
                armed = (armed === dir) ? null : dir;
                entryBtn.classList.toggle('armed', armed === 'entry');
                exitBtn.classList.toggle('armed', armed === 'exit');

                if (armed === 'entry') {
                    chipShow('Entry ready — scan card', 'bg-emerald-500');
                } else if (armed === 'exit') {
                    chipShow('Exit ready — scan card', 'bg-rose-500');
                } else {
                    chipHide();
                }

                if (armed) manual.focus();

                // Tell the backend which direction is armed so the external RFID reader (which
                // posts a scan without a 'type') honors the button pressed on this kiosk.
                fetch('/api/kiosk-mode', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        mode: armed,
                        kiosk: kioskKey,
                    }),
                }).catch(() => {
                    /* ignore */
                });
            }

            function hint(text) {
                hintEl.textContent = text;
                hintEl.style.color = '#fcd34d';
                setTimeout(() => {
                    hintEl.textContent = 'Tap a direction, then scan your card';
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

            function openDetailsForm(code) {
                if (pendingRfid === code) return;
                pendingRfid = code;
                detailsForm.reset();
                detailsError.classList.add('hidden');
                detailsCode.textContent = code || '';
                detailsOverlay.classList.remove('hidden');
                detailsOverlay.classList.add('flex');
                document.getElementById('driver-name').focus();
            }

            function closeDetailsForm() {
                pendingRfid = null;
                detailsOverlay.classList.add('hidden');
                detailsOverlay.classList.remove('flex');
            }

            function openRegisterForm(code) {
                if (pendingRfid === code) return;
                pendingRfid = code;
                registerForm.reset();
                registerError.classList.add('hidden');
                registerCode.textContent = code || '';
                registerOverlay.classList.remove('hidden');
                registerOverlay.classList.add('flex');
                document.getElementById('register-name').focus();
            }

            function closeRegisterForm() {
                pendingRfid = null;
                registerOverlay.classList.add('hidden');
                registerOverlay.classList.remove('flex');
            }

            // Seeded with the entries already rendered server-side so a live broadcast for one of them isn't duplicated.
            const shownEntryIds = new Set(@json($recentScans->map(fn($scan) => $scan['entry_id'] . ':' . $scan['status'])->values()));

            function pushRecent(status, code, amount, vehicleNumber, driverName, entryId) {
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

                const li = document.createElement('li');
                li.className =
                    'flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold';
                li.innerHTML = '<i class="fas ' +
                    (status === 'parked' ? 'fa-arrow-right-to-bracket text-emerald-300' :
                        'fa-arrow-right-from-bracket text-rose-300') +
                    '"></i><span class="uppercase tracking-widest">' + (status === 'parked' ? 'Parked' : 'Exit') +
                    '</span><span class="font-mono tracking-widest text-white/80">' + (vehicleNumber || code || '') +
                    '</span>' + (driverName ? '<span class="text-white/60">' + driverName + '</span>' : '') +
                    (code ? '<span class="font-mono text-xs text-sky-300/80">Card ' + code + '</span>' : '') +
                    (amount != null ? '<span class="text-amber-300">₹' + amount + '</span>' : '') +
                    '<span class="ml-auto text-xs text-white/40">' + new Date().toLocaleString([], {
                        day: '2-digit',
                        month: 'short',
                        hour: '2-digit',
                        minute: '2-digit'
                    }) + '</span>';

                recentList.prepend(li);
                while (recentList.children.length > 6) {
                    recentList.removeChild(recentList.lastChild);
                }
            }

            async function submitScan(code, dir) {
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
                            type: dir,
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
                        if (data.status === 'details_required') {
                            openDetailsForm(data.rfid_id);
                        } else {
                            showResult(data.status, data.rfid_id, data.time, null, data.amount, data
                                .vehicle_number, data.driver_name);
                            pushRecent(data.status, data.rfid_id, data.amount, data.vehicle_number, data
                                .driver_name, data.entry_id);
                        }
                        // Stay armed so the attendant can keep scanning the same direction.
                    } else if (data.status === 'unregistered') {
                        openRegisterForm(data.rfid_id || code);
                    } else {
                        showResult(null, code, null, data.error || 'Scan failed');
                    }
                } catch (e) {
                    showResult(null, code, null, 'Network error');
                } finally {
                    busy = false;
                    manual.disabled = false;
                    manual.value = '';
                    if (armed) manual.focus();
                }
            }

            entryBtn.addEventListener('click', () => setArmed('entry'));
            exitBtn.addEventListener('click', () => setArmed('exit'));

            const menuToggle = document.getElementById('menu-toggle');
            const mobileMenu = document.getElementById('mobile-menu');
            const menuIcon = document.getElementById('menu-icon');

            menuToggle.addEventListener('click', () => {
                const isHidden = mobileMenu.classList.toggle('hidden');
                mobileMenu.classList.toggle('flex', !isHidden);
                menuToggle.setAttribute('aria-expanded', String(!isHidden));
                menuIcon.classList.toggle('fa-bars', isHidden);
                menuIcon.classList.toggle('fa-xmark', !isHidden);
            });

            // Clicking the background (anything that isn't a control) un-arms
            // the selected direction so an accidental tap doesn't arm the kiosk.
            document.addEventListener('click', (e) => {
                if (armed && !e.target.closest('button, a, select, input, label, nav')) {
                    setArmed(null);
                }
            });

            registerClose.addEventListener('click', () => closeRegisterForm());
            detailsClose.addEventListener('click', () => closeDetailsForm());
            resultClose.addEventListener('click', () => closeResult());

            // Close a form overlay when the click lands on the dimmed backdrop, not the form itself.
            registerOverlay.addEventListener('click', (e) => {
                if (e.target === registerOverlay) closeRegisterForm();
            });
            detailsOverlay.addEventListener('click', (e) => {
                if (e.target === detailsOverlay) closeDetailsForm();
            });
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) closeResult();
            });

            registerForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!pendingRfid) return;

                const name = document.getElementById('register-name').value.trim();
                const phone = document.getElementById('register-phone').value.trim();
                const vehicleNumber = document.getElementById('register-vehicle-number').value.trim()
                    .toUpperCase();

                if (!name) {
                    registerError.textContent = 'Driver\'s name is required';
                    registerError.classList.remove('hidden');
                    return;
                }
                if (!/^[0-9]{10}$/.test(phone)) {
                    registerError.textContent = 'Enter a valid 10-digit phone number';
                    registerError.classList.remove('hidden');
                    return;
                }
                if (!vehicleNumber) {
                    registerError.textContent = 'Vehicle registration number is required';
                    registerError.classList.remove('hidden');
                    return;
                }

                const submitBtn = registerForm.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                registerError.classList.add('hidden');

                try {
                    const res = await fetch('/api/rfid-scan/register', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            rfid_id: pendingRfid,
                            name: name,
                            phone: phone,
                            vehicle_number: vehicleNumber,
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
                        closeRegisterForm();
                        showResult('parked', data.rfid_id, data.time, null, null, data.vehicle_number, data
                            .driver_name);
                        pushRecent('parked', data.rfid_id, null, data.vehicle_number, data.driver_name, data
                            .entry_id);
                    } else {
                        const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                        registerError.textContent = firstError || data.error ||
                            'Could not register card, please try again';
                        registerError.classList.remove('hidden');
                    }
                } catch (err) {
                    registerError.textContent = 'Network error, please try again';
                    registerError.classList.remove('hidden');
                } finally {
                    submitBtn.disabled = false;
                }
            });

            detailsForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!pendingRfid) return;

                const submitBtn = detailsForm.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                detailsError.classList.add('hidden');

                try {
                    const res = await fetch('/api/rfid-scan/details', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            rfid_id: pendingRfid,
                            driver_name: document.getElementById('driver-name').value
                                .trim(),
                            vehicle_number: document.getElementById('vehicle-number').value
                                .trim().toUpperCase(),
                            mobile_number: document.getElementById('mobile-number').value
                                .trim(),
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
                        closeDetailsForm();
                        showResult('parked', data.rfid_id, data.time, null, null, data.vehicle_number, data
                            .driver_name);
                        pushRecent('parked', data.rfid_id, null, data.vehicle_number, data.driver_name, data
                            .entry_id);
                    } else {
                        detailsError.textContent = data.error || 'Could not save details, please try again';
                        detailsError.classList.remove('hidden');
                    }
                } catch (err) {
                    detailsError.textContent = 'Network error, please try again';
                    detailsError.classList.remove('hidden');
                } finally {
                    submitBtn.disabled = false;
                }
            });

            manual.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter' || busy) return;
                e.preventDefault();
                const code = manual.value.trim().toUpperCase();
                if (!armed) {
                    hint('Press ENTRY or EXIT first');
                    return;
                }
                if (!code) {
                    hint('Type a card number');
                    return;
                }
                submitScan(code, armed);
            });

            document.addEventListener('keydown', (e) => {
                if (e.target === manual || busy || e.ctrlKey || e.metaKey || e.altKey) return;

                if (e.key === 'Enter') {
                    const code = buffer.trim();
                    buffer = '';
                    readout.textContent = '';
                    if (!armed) {
                        hint('Press ENTRY or EXIT first');
                        return;
                    }
                    if (!code) {
                        hint('No card scanned');
                        return;
                    }
                    submitScan(code, armed);
                } else if (e.key === 'Escape') {
                    buffer = '';
                    readout.textContent = '';
                    setArmed(null);
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
                        if (e.status === 'unregistered') {
                            openRegisterForm(e.rfid_id);
                            return;
                        }
                        if (e.status === 'details_required') {
                            closeRegisterForm();
                            openDetailsForm(e.rfid_id);
                            return;
                        }

                        showResult(e.status === 'error' ? null : e.status, e.rfid_id, new Date()
                            .toLocaleString(), e.message, e.amount, e.vehicle_number, e.driver_name);
                        if (e.status === 'parked' || e.status === 'exit') pushRecent(e.status, e.rfid_id, e
                            .amount, e.vehicle_number, e.driver_name, e.entry_id);
                    });
                    return;
                }
                setTimeout(attachEchoListener, 200);
            })();
        })();
    </script>
</div>
