<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        {{-- Title, description, canonical, robots, Open Graph, Twitter, JSON-LD, GTM (shopper/seo). --}}
        <x-shopper-seo::head :for="$seoSubject ?? null" :context="$seoContext ?? []" />
        <style>
            :root { color-scheme: light dark; --muted: #6b7280; }
            body { margin: 0; font-family: system-ui, sans-serif; line-height: 1.7; background: Canvas; color: CanvasText; }
            header, main, footer { max-width: 760px; margin: 0 auto; padding: 24px 16px; }
            header a { font-weight: 600; text-decoration: none; color: inherit; }
            .meta { color: var(--muted); font-size: 0.9rem; }
            .content img { max-width: 100%; height: auto; }
            .alignleft { float: left; margin: 0 1em 1em 0; }
            .aligncenter { display: block; margin: 0 auto 1em; }
            .alignright { float: right; margin: 0 0 1em 1em; }
            .terms a { margin-right: 0.5em; }
            .full-width main { max-width: 1100px; }
        </style>
    </head>
    <body class="@yield('body_class')">
        <x-shopper-seo::body-open />
        <header><a href="{{ url('/') }}">{{ config('app.name') }}</a></header>
        <main>@yield('content')</main>
        <x-shopper-seo::body-close />
    </body>
</html>
