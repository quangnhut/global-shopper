<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        {{-- Title, description, canonical, robots, Open Graph, JSON-LD, GTM (shopper/seo). --}}
        <x-shopper-seo::head :for="$seoFor ?? null" :context="$seoContext ?? []" />
        <link
            rel="stylesheet"
            href="{{ asset('vendor/shopper-storefront/storefront.css') }}?v={{ is_file(public_path('vendor/shopper-storefront/storefront.css')) ? filemtime(public_path('vendor/shopper-storefront/storefront.css')) : '1' }}"
        />
        @livewireStyles
    </head>
    <body class="sf-body">
        <x-shopper-seo::body-open />

        <a href="#main" class="sf-skip-link">{{ __('shopper-storefront::storefront.skip_to_content') }}</a>

        @include('shopper-storefront::partials.header', ['header' => $header])

        <main id="main" class="min-h-[50vh]">
            @if (session('status'))
                <div class="sf-container mt-4">
                    <p class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200" role="status">
                        {{ session('status') }}
                    </p>
                </div>
            @endif

            @if ($errors->has('cart'))
                <div class="sf-container mt-4">
                    <p class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200" role="alert">{{ $errors->first('cart') }}</p>
                </div>
            @endif

            @yield('content')
        </main>

        @include('shopper-storefront::partials.footer', ['footer' => $footer])

        @includeWhen(\Illuminate\Support\Facades\Route::has('shopper.storefront.cart.lines.store'), 'shopper-storefront::partials.cart-scripts')
        @livewireScripts
        <x-shopper-seo::body-close />
    </body>
</html>
