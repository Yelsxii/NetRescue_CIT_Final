@props(['name', 'size' => 20])
@php($attrs = $attributes->merge(['class' => 'icon']))
<svg {{ $attrs }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
            @break
        @case('history')
            <path d="M12 8v5l3 2"/><path d="M3.05 11a9 9 0 1 0 .5-3.5"/><path d="M3 3v5h5"/>
            @break
        @case('incident')
            <path d="M12 3 3 19h18L12 3Z"/><path d="M12 9v4M12 17h.01"/>
            @break
        @case('devices')
            <rect x="3" y="5" width="18" height="12" rx="2"/><path d="M8 21h8M12 17v4"/>
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14"/>
            @break
        @case('download')
            <path d="M12 3v12m0 0 5-5m-5 5-5-5"/><path d="M5 21h14"/>
            @break
        @case('settings')
            <path d="M4 7h16M4 12h16M4 17h16"/><circle cx="8" cy="7" r="2.2" fill="currentColor" stroke="none"/><circle cx="16" cy="12" r="2.2" fill="currentColor" stroke="none"/><circle cx="10" cy="17" r="2.2" fill="currentColor" stroke="none"/>
            @break
        @case('online')
            <path d="M5 12.5a10 10 0 0 1 14 0"/><path d="M8.5 16a5 5 0 0 1 7 0"/><circle cx="12" cy="19.2" r="1.2" fill="currentColor" stroke="none"/>
            @break
        @case('offline')
            <path d="M5 12.5a10 10 0 0 1 14 0"/><path d="M8.5 16a5 5 0 0 1 7 0"/><circle cx="12" cy="19.2" r="1.2" fill="currentColor" stroke="none"/><path d="M4 4l16 16"/>
            @break
        @case('sun')
            <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
            @break
        @case('moon')
            <path d="M21 14.5A8.5 8.5 0 1 1 9.5 3 7 7 0 0 0 21 14.5Z"/>
            @break
        @case('activity')
            <path d="M3 12h4l2-7 4 14 2-7h6"/>
            @break
        @case('wifi')
            <path d="M5 12.5a10 10 0 0 1 14 0"/><path d="M8.5 16a5 5 0 0 1 7 0"/><path d="M12 20h.01"/>
            @break
        @case('globe')
            <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>
            @break
        @case('dns')
            <rect x="4" y="4" width="16" height="6" rx="2"/><rect x="4" y="14" width="16" height="6" rx="2"/><path d="M8 7h.01M8 17h.01M12 7h5M12 17h5"/>
            @break
        @case('check')
            <path d="m5 12 4 4L19 6"/>
            @break
        @case('alert')
            <path d="M10.3 4.2 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            @break
        @case('wrench')
            <path d="M14.7 6.3a4 4 0 0 0-5.1 5.1L3.5 17.5a2 2 0 1 0 3 3l6.1-6.1a4 4 0 0 0 5.1-5.1l-2.6 2.6-3-3 2.6-2.6Z"/>
            @break
        @case('chevron')
            <path d="m9 18 6-6-6-6"/>
            @break
        @case('shield')
            <path d="M12 3 5 6v5c0 5 3 8 7 10 4-2 7-5 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/>
            @break
        @case('refresh')
            <path d="M20 7v5h-5"/><path d="M4 17v-5h5"/><path d="M18.5 9A7 7 0 0 0 6 6.5L4 12M5.5 15A7 7 0 0 0 18 17.5l2-5.5"/>
            @break
        @case('search')
            <circle cx="11" cy="11" r="6.5"/><path d="m20 20-3.5-3.5"/>
            @break
        @default
            <circle cx="12" cy="12" r="9"/>
    @endswitch
</svg>
