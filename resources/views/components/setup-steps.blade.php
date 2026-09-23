{{-- Mini workflow stepper shown on the admin setup pages. --}}
{{-- @props(['current' => 'lots'|'floors'|'kiosks']) --}}

@props(['current' => 'lots'])

@php
    $steps = [
        'lots'   => ['route' => 'admin.lots',   'label' => 'Lots'],
        'floors' => ['route' => 'admin.floors', 'label' => 'Floors & Slots'],
        'kiosks' => ['route' => 'admin.kiosks', 'label' => 'Kiosks'],
    ];
@endphp

<ol class="mt-2 flex flex-wrap items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.18em]">
    @foreach ($steps as $key => $step)
        <li class="flex items-center gap-1.5">
            @if ($key === $current)
                <span class="flex items-center gap-1.5 rounded-lg border border-sky-400/40 bg-sky-500/15 px-2.5 py-1 text-sky-100">
                    <span class="flex h-4 w-4 items-center justify-center rounded bg-sky-400/25 text-[10px] font-black text-sky-200">{{ $loop->iteration }}</span>
                    <span>{{ $step['label'] }}</span>
                </span>
            @else
                <a href="{{ route($step['route']) }}"
                   class="flex items-center gap-1.5 rounded-lg border border-transparent px-2.5 py-1 text-white/40 transition hover:bg-white/10 hover:text-white">
                    <span class="flex h-4 w-4 items-center justify-center rounded bg-white/10 text-[10px] font-black text-white/50">{{ $loop->iteration }}</span>
                    <span>{{ $step['label'] }}</span>
                </a>
            @endif
            @if (! $loop->last)
                <i class="fas fa-chevron-right text-[8px] text-white/25"></i>
            @endif
        </li>
    @endforeach
</ol>