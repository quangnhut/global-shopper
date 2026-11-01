{{-- Right before </body>: <x-shopper-seo::body-close /> --}}
@php
    $tracking = resolve(\Shopper\Seo\SeoManager::class)->tracking();
@endphp

@if ($tracking['custom_body_close'])
    {!! $tracking['custom_body_close'] !!}
@endif
