@php
    /**
     * @var \Shopper\Core\Models\Product $product
     * @var \Shopper\Storefront\Data\ProductCardData $card
     * @var array<int, string> $images
     * @var array<int, array{id: int, name: string, price: string, in_stock: bool}> $variants
     */
    $canBuy = $card->inStock && \Illuminate\Support\Facades\Route::has('shopper.storefront.cart.lines.store');
@endphp

@extends('shopper-storefront::layout', ['seoFor' => $product])

@section('content')
    <div class="sf-container py-6">
        <nav aria-label="{{ __('shopper-storefront::catalog.breadcrumb') }}" class="text-sm text-gray-500">
            <ol class="flex flex-wrap items-center gap-1">
                <li><a href="{{ \Shopper\Storefront\Support\StorefrontUrls::home() }}" class="hover:text-brand-700">{{ __('shopper-storefront::catalog.home') }}</a></li>
                @foreach ($breadcrumbs as $crumb)
                    <li aria-hidden="true">/</li>
                    <li><a href="{{ $crumb['url'] }}" class="hover:text-brand-700">{{ $crumb['label'] }}</a></li>
                @endforeach
                <li aria-hidden="true">/</li>
                <li aria-current="page" class="font-medium text-gray-700">{{ $product->name }}</li>
            </ol>
        </nav>

        <div class="mt-4 grid gap-6 rounded-xl bg-white p-4 ring-1 ring-gray-100 md:grid-cols-2 md:p-6">
            {{-- Gallery --}}
            <div class="min-w-0" x-data="{ current: 0 }">
                <div class="relative aspect-square overflow-hidden rounded-xl bg-gray-50 ring-1 ring-gray-100">
                    @forelse ($images as $index => $image)
                        <img
                            src="{{ $image }}"
                            alt="{{ $index === 0 ? $product->name : '' }}"
                            class="size-full object-contain"
                            @if ($index > 0) x-cloak loading="lazy" @else fetchpriority="high" @endif
                            x-show="current === {{ $index }}"
                        />
                    @empty
                        <div class="flex size-full items-center justify-center text-gray-300"><x-shopper-storefront::icon name="cart" class="size-20" /></div>
                    @endforelse
                    @if ($card->discountPercent)
                        <span class="absolute left-3 top-3 rounded-md bg-brand-600 px-2 py-1 text-sm font-bold text-white">-{{ $card->discountPercent }}%</span>
                    @endif
                </div>
                @if (count($images) > 1)
                    <ul class="mt-3 flex gap-2 overflow-x-auto" aria-label="{{ __('shopper-storefront::catalog.product.gallery') }}">
                        @foreach ($images as $index => $image)
                            <li class="shrink-0">
                                <button
                                    type="button"
                                    class="size-16 overflow-hidden rounded-lg ring-1 ring-gray-200"
                                    x-bind:class="current === {{ $index }} ? 'ring-2 ring-brand-600' : ''"
                                    x-on:click="current = {{ $index }}"
                                    aria-label="{{ __('shopper-storefront::catalog.product.image', ['number' => $index + 1]) }}"
                                >
                                    <img src="{{ $image }}" alt="" class="size-full object-cover" loading="lazy" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Buy box --}}
            <div class="min-w-0">
                @if ($product->brand)
                    <p class="text-sm font-medium text-brand-700">{{ $product->brand->name }}</p>
                @endif
                <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ $product->name }}</h1>
                @if ($product->sku)
                    <p class="mt-1 text-sm text-gray-500">{{ __('shopper-storefront::catalog.product.sku') }}: {{ $product->sku }}</p>
                @endif

                <div class="mt-4 flex flex-wrap items-baseline gap-3">
                    <p class="text-3xl font-bold text-brand-600">
                        @if ($variants !== [] && count(array_unique(array_column($variants, 'price'))) > 1)
                            <span class="text-base font-medium text-gray-500">{{ __('shopper-storefront::catalog.product.from') }}</span>
                        @endif
                        {{ $card->formattedPrice() }}
                    </p>
                    @if ($card->formattedComparePrice())
                        <p class="text-lg text-gray-500 line-through"><span class="sr-only">{{ __('shopper-storefront::storefront.product.compare_price') }}</span> {{ $card->formattedComparePrice() }}</p>
                    @endif
                </div>

                <p @class(['mt-3 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium', 'bg-emerald-50 text-emerald-700' => $card->inStock, 'bg-gray-100 text-gray-600' => ! $card->inStock])>
                    <x-shopper-storefront::icon :name="$card->inStock ? 'check' : 'close'" class="size-4" />
                    {{ $card->inStock ? __('shopper-storefront::catalog.product.in_stock') : __('shopper-storefront::storefront.product.out_of_stock') }}
                </p>

                @if ($canBuy)
                    <form
                        method="POST"
                        action="{{ route('shopper.storefront.cart.lines.store') }}"
                        class="mt-6 space-y-4"
                        x-data="sfAddToCart"
                        x-on:submit.prevent="submit"
                    >
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}" />

                        @if (count($variants) > 1)
                            <div>
                                <label for="sf-variant" class="block text-sm font-medium text-gray-700">{{ __('shopper-storefront::catalog.product.option') }}</label>
                                <select id="sf-variant" name="variant_id" class="sf-input mt-1" required>
                                    @foreach ($variants as $variant)
                                        <option value="{{ $variant['id'] }}" @disabled(! $variant['in_stock'])>
                                            {{ $variant['name'] }} – {{ $variant['price'] }}{{ $variant['in_stock'] ? '' : ' ('.__('shopper-storefront::storefront.product.out_of_stock').')' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @elseif (count($variants) === 1)
                            <input type="hidden" name="variant_id" value="{{ $variants[0]['id'] }}" />
                        @endif

                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label for="sf-quantity" class="block text-sm font-medium text-gray-700">{{ __('shopper-storefront::cart.quantity') }}</label>
                                <input id="sf-quantity" type="number" name="quantity" value="1" min="1" max="{{ max(1, $maxQuantity) }}" inputmode="numeric" class="sf-input mt-1 w-24" />
                            </div>
                            <button type="submit" class="sf-button flex-1 gap-2 py-3 text-base" x-bind:disabled="busy">
                                <x-shopper-storefront::icon name="cart" class="size-5" />
                                <span x-text="busy ? @js(__('shopper-storefront::cart.adding')) : @js(__('shopper-storefront::cart.add'))">{{ __('shopper-storefront::cart.add') }}</span>
                            </button>
                        </div>
                    </form>
                @endif

                <ul class="mt-6 space-y-2 border-t border-gray-100 pt-4 text-sm text-gray-600">
                    <li class="flex items-center gap-2"><x-shopper-storefront::icon name="store" class="size-5 text-brand-600" /> {{ __('shopper-storefront::catalog.product.pickup') }}</li>
                    @if ($header->hotline ?? null)
                        <li class="flex items-center gap-2"><x-shopper-storefront::icon name="phone" class="size-5 text-brand-600" /> {{ __('shopper-storefront::catalog.product.hotline') }} <a href="tel:{{ preg_replace('/\s+/', '', $header->hotline) }}" class="font-semibold text-gray-900">{{ $header->hotline }}</a></li>
                    @endif
                </ul>
            </div>
        </div>

        @if ($description)
            <section class="mt-6 rounded-xl bg-white p-4 ring-1 ring-gray-100 md:p-6" aria-labelledby="sf-description">
                <h2 id="sf-description" class="text-lg font-semibold text-gray-900">{{ __('shopper-storefront::catalog.product.description') }}</h2>
                <div class="sf-prose mt-3">{!! $description !!}</div>
            </section>
        @endif

        @if ($related !== [])
            <section class="mt-8" aria-labelledby="sf-related">
                <h2 id="sf-related" class="text-lg font-bold uppercase text-gray-900">{{ __('shopper-storefront::catalog.product.related') }}</h2>
                <ul class="sf-grid mt-4" style="--sf-columns: 5">
                    @foreach ($related as $item)
                        <li>@include('shopper-storefront::partials.product-card', ['product' => $item])</li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
