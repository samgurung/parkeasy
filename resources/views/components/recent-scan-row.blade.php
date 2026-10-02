{{--
    Single source of truth for a "recent scans" row.

    Server-rendered rows are produced by this partial, and the live-scan JS clones
    the <template> built from it and fills the text. Keeping one copy of the markup
    is deliberate: when the row lived in both Blade and JS, the two silently drifted
    and the live rows lost fields (the kiosk tag went missing).
--}}
@props(['scan'])
<li data-card-code="{{ $scan['rfid_id'] }}" data-scan-status="{{ $scan['status'] }}"
    data-kiosk="{{ $scan['kiosk'] }}"
    class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold">
    <i data-slot="icon"
        class="fas {{ $scan['status'] === 'parked' ? 'fa-arrow-right-to-bracket text-emerald-300' : 'fa-arrow-right-from-bracket text-rose-300' }}"></i>
    <span data-slot="status" class="uppercase tracking-widest">{{ $scan['status'] === 'parked' ? 'Parked' : 'Exit' }}</span>
    @if ($scan['vehicle_type'] === 'two_wheeler')
        <i data-slot="type-icon" class="fas fa-motorcycle text-sm text-sky-300"></i>
    @elseif ($scan['vehicle_type'] === 'four_wheeler')
        <i data-slot="type-icon" class="fas fa-car text-sm text-teal-300"></i>
    @endif
    <span data-slot="vehicle-number" class="font-mono tracking-widest text-white/80">{{ $scan['vehicle_number'] }}</span>
    @if ($scan['driver_name'])
        <span data-slot="driver-name" class="text-white/60">{{ $scan['driver_name'] }}</span>
    @endif
    @if ($scan['rfid_id'])
        <span data-slot="card" class="font-mono text-xs text-sky-300/80">Card {{ $scan['rfid_id'] }}</span>
    @endif
    @if ($scan['amount'] !== null)
        <span data-slot="amount" class="text-amber-300">₹{{ $scan['amount'] }}</span>
    @endif
    @if ($scan['kiosk'])
        <span data-slot="kiosk" class="font-mono text-[0.65rem] uppercase tracking-widest text-white/35">{{ $scan['kiosk'] }}</span>
    @endif
    <span data-slot="time" class="ml-auto text-xs text-white/40">{{ $scan['time']->format('d M, h:i A') }}</span>
</li>