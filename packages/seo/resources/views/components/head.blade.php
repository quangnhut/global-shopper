{{--
    SEO head: <x-shopper-seo::head :for="$post" :context="['page' => 2, 'last_page' => 5]" />
    `for` is a model, a path, a SeoData instance, or null for the current request path.
    Everything is escaped except the custom head code, which only `manage_seo_scripts` users can save.
--}}
@props(['for' => null, 'context' => []])

@php
    $seo = $for instanceof \Shopper\Seo\Data\SeoData ? $for : resolve(\Shopper\Seo\SeoManager::class)->for($for, $context);
@endphp

<title>{{ $seo->title }}</title>
@foreach ($seo->meta as $meta)
    @isset($meta['property'])
        <meta property="{{ $meta['property'] }}" content="{{ $meta['content'] }}" />
    @else
        <meta name="{{ $meta['name'] }}" content="{{ $meta['content'] }}" />
    @endisset
@endforeach
@foreach ($seo->links as $link)
    <link {!! collect($link)->map(fn ($value, $attribute) => e($attribute).'="'.e($value).'"')->implode(' ') !!} />
@endforeach
@if ($seo->jsonLd)
    <script type="application/ld+json">{!! json_encode($seo->jsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
@if ($seo->gtm)
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $seo->gtm }}');</script>
@elseif ($seo->ga4)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $seo->ga4 }}"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $seo->ga4 }}');</script>
@endif
@if ($seo->customHead)
    {!! $seo->customHead !!}
@endif
