{{-- Right after <body>: <x-shopper-seo::body-open /> --}}
@php
    $tracking = resolve(\Shopper\Seo\SeoManager::class)->tracking();
@endphp

@if ($tracking['gtm'])
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $tracking['gtm'] }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
@endif
@if ($tracking['custom_body_open'])
    {!! $tracking['custom_body_open'] !!}
@endif
