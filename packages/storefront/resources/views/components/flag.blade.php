@props(['code'])

{{-- Small 3:2 country flags as inline SVG (emoji flags show as two letters on Windows). Decorative: the language name sits next to it. --}}
<span {{ $attributes->class('inline-flex h-3.5 w-5 shrink-0 overflow-hidden rounded-sm ring-1 ring-black/15') }} aria-hidden="true">
    @switch($code)
        @case('vn')
            <svg viewBox="0 0 30 20" class="size-full" preserveAspectRatio="xMidYMid slice">
                <rect width="30" height="20" fill="#DA251D" />
                <polygon fill="#FFFF00" points="15.00,4.00 16.35,8.15 20.71,8.15 17.18,10.71 18.53,14.85 15.00,12.29 11.47,14.85 12.82,10.71 9.29,8.15 13.65,8.15" />
            </svg>
            @break
        @case('cn')
            <svg viewBox="0 0 30 20" class="size-full" preserveAspectRatio="xMidYMid slice">
                <rect width="30" height="20" fill="#EE1C25" />
                <g fill="#FFFF00">
                    <polygon points="5.00,2.00 5.67,4.07 7.85,4.07 6.09,5.35 6.76,7.43 5.00,6.15 3.24,7.43 3.91,5.35 2.15,4.07 4.33,4.07" />
                    <polygon points="9.14,2.51 9.62,1.97 9.25,1.34 9.91,1.63 10.39,1.08 10.33,1.80 11.00,2.09 10.29,2.25 10.22,2.97 9.85,2.35" />
                    <polygon points="11.01,4.14 11.66,3.82 11.56,3.10 12.07,3.62 12.72,3.30 12.38,3.95 12.88,4.47 12.17,4.34 11.83,4.99 11.73,4.27" />
                    <polygon points="11.04,6.73 11.76,6.70 11.96,6.00 12.21,6.68 12.94,6.66 12.37,7.10 12.62,7.79 12.01,7.38 11.44,7.83 11.64,7.13" />
                    <polygon points="9.22,8.38 9.90,8.63 10.35,8.06 10.32,8.79 11.00,9.05 10.30,9.24 10.26,9.96 9.87,9.36 9.16,9.55 9.62,8.98" />
                </g>
            </svg>
            @break
        @case('gb')
            @php($clip = 'sf-flag-gb-' . \Illuminate\Support\Str::random(6))
            <svg viewBox="0 0 60 30" class="size-full" preserveAspectRatio="xMidYMid slice">
                <clipPath id="{{ $clip }}"><path d="M30,15 h30 v15 z v15 h-30 z h-30 v-15 z v-15 h30 z" /></clipPath>
                <rect width="60" height="30" fill="#012169" />
                <path d="M0,0 L60,30 M60,0 L0,30" stroke="#FFFFFF" stroke-width="6" />
                <path d="M0,0 L60,30 M60,0 L0,30" clip-path="url(#{{ $clip }})" stroke="#C8102E" stroke-width="4" />
                <path d="M30,0 v30 M0,15 h60" stroke="#FFFFFF" stroke-width="10" />
                <path d="M30,0 v30 M0,15 h60" stroke="#C8102E" stroke-width="6" />
            </svg>
            @break
        @default
            <svg viewBox="0 0 24 24" class="size-full bg-white p-px text-gray-500" fill="none" stroke="currentColor" stroke-width="1.8">
                <circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" />
            </svg>
    @endswitch
</span>
