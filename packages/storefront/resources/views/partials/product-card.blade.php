@php
    /** @var \Shopper\Storefront\Data\ProductCardData $product */
@endphp

<article class="sf-card group relative flex h-full flex-col">
    <div class="relative aspect-square overflow-hidden rounded-t-xl bg-white">
        @if ($product->image)
            <img
                src="{{ $product->image }}"
                alt="{{ $product->name }}"
                class="size-full object-contain p-3 transition duration-300 group-hover:scale-105"
                loading="{{ ($eager ?? false) ? 'eager' : 'lazy' }}"
                decoding="async"
            />
        @else
            <div class="flex size-full items-center justify-center bg-gray-50 text-gray-300">
                <x-shopper-storefront::icon name="cart" class="size-12" />
            </div>
        @endif

        <div class="absolute left-2 top-2 flex flex-col items-start gap-1">
            @if ($product->discountPercent)
                <span class="rounded-md bg-brand-600 px-1.5 py-0.5 text-xs font-bold text-white">-{{ $product->discountPercent }}%</span>
            @endif
            @if (filled($badge ?? null))
                <span class="rounded-md bg-amber-400 px-1.5 py-0.5 text-xs font-bold text-gray-900">{{ $badge }}</span>
            @endif
        </div>

        @unless ($product->inStock)
            <span class="absolute inset-x-0 bottom-0 bg-gray-900/70 py-1 text-center text-xs font-semibold text-white">{{ __('shopper-storefront::storefront.product.out_of_stock') }}</span>
        @endunless
    </div>

    <div class="flex flex-1 flex-col gap-1 p-3">
        <h3 class="line-clamp-2 min-h-10 text-sm font-medium text-gray-800">
            <a href="{{ $product->url }}" class="after:absolute after:inset-0 hover:text-brand-700">{{ $product->name }}</a>
        </h3>
        <div class="mt-auto">
            <p class="text-base font-bold text-brand-600">{{ $product->formattedPrice() }}</p>
            @if ($product->formattedComparePrice())
                <p class="text-xs text-gray-500 line-through">
                    <span class="sr-only">{{ __('shopper-storefront::storefront.product.compare_price') }}</span>
                    {{ $product->formattedComparePrice() }}
                </p>
            @endif
        </div>
        @if (\Illuminate\Support\Facades\Route::has('shopper.storefront.cart.lines.store'))
            <form
                method="POST"
                action="{{ route('shopper.storefront.cart.lines.store') }}"
                class="relative z-10 mt-2"
                x-data="sfAddToCart"
                x-on:submit.prevent="submit"
            >
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}" />
                <button
                    type="submit"
                    class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-brand-50 px-2 py-2 text-sm font-semibold text-brand-700 ring-1 ring-brand-100 hover:bg-brand-600 hover:text-white disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500 disabled:ring-gray-200"
                    x-bind:disabled="busy"
                    @disabled(! $product->inStock)
                    aria-label="{{ $product->inStock ? __('shopper-storefront::cart.add').': '.$product->name : __('shopper-storefront::cart.out_of_stock').': '.$product->name }}"
                >
                    <x-shopper-storefront::icon name="cart" class="size-4" />
                    @if ($product->inStock)
                        <span x-text="busy ? @js(__('shopper-storefront::cart.adding')) : @js(__('shopper-storefront::cart.add'))">{{ __('shopper-storefront::cart.add') }}</span>
                    @else
                        <span>{{ __('shopper-storefront::cart.out_of_stock') }}</span>
                    @endif
                </button>
            </form>
        @endif
    </div>
</article>
