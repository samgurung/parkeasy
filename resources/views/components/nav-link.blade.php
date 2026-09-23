{{-- Single navigation pill used by the site navbar (desktop + mobile). --}}

@props(['route' => null, 'icon' => null, 'label' => null, 'active' => null, 'step' => null])

@php
    $isActive = $active ?? (is_string($route) && $route ? request()->routeIs($route) : false);
    $attributes = $attributes->merge([
        'href' => $route ? route($route) : '#',
        'aria-current' => $isActive ? 'page' : null,
    ]);
@endphp

<a
    {{ $attributes }}
    @class([
        'flex items-center gap-2 rounded-xl border px-3.5 py-2 text-sm font-bold transition',
        'border-sky-400/40 bg-sky-500/15 text-white shadow-sm shadow-sky-500/20' => $isActive,
        'border-white/10 bg-white/5 text-white/70 hover:border-white/20 hover:bg-white/10 hover:text-white' => ! $isActive,
    ])
>
    @if ($step)
        <span @class([
            'flex h-5 w-5 shrink-0 items-center justify-center rounded-md text-[10px] font-black',
            'bg-sky-400/25 text-sky-100' => $isActive,
            'bg-white/10 text-white/50' => ! $isActive,
        ])>{{ $step }}</span>
    @elseif ($icon)
        <i class="{{ $icon }} text-xs {{ $isActive ? 'text-sky-300' : 'text-white/40' }}"></i>
    @endif
    <span>{{ $label }}</span>
</a>