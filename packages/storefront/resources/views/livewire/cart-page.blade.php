<div class="sf-container py-6">
    <h1 class="text-xl font-bold text-gray-900">{{ __('shopper-storefront::cart.title') }}</h1>

    @include('shopper-storefront::partials.cart-notices')

    @if ($this->lines === [])
        <div class="py-20 text-center">
            <p class="text-gray-500">{{ __('shopper-storefront::cart.empty') }}</p>
            <a href="{{ \Shopper\Storefront\Support\StorefrontUrls::home() }}" class="sf-button mt-6">{{ __('shopper-storefront::cart.continue') }}</a>
        </div>
    @else
        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem] lg:items-start">
            <section class="min-w-0 rounded-xl bg-white px-4 ring-1 ring-gray-100" wire:loading.class="opacity-60">
                @include('shopper-storefront::partials.cart-lines', ['lines' => $this->lines])
            </section>

            <aside class="rounded-xl bg-white p-4 ring-1 ring-gray-100 lg:sticky lg:top-4">
                <h2 class="mb-3 font-semibold text-gray-900">{{ __('shopper-storefront::cart.summary') }}</h2>
                @include('shopper-storefront::partials.cart-summary', ['summary' => $this->summary])
                <a href="{{ \Shopper\Storefront\Support\StorefrontUrls::to('checkout') }}" class="sf-button mt-4 w-full">{{ __('shopper-storefront::cart.checkout') }}</a>
                <a href="{{ \Shopper\Storefront\Support\StorefrontUrls::home() }}" class="mt-3 block text-center text-sm text-brand-700 hover:underline">{{ __('shopper-storefront::cart.continue') }}</a>
            </aside>
        </div>
    @endif
</div>
