@php
    /**
     * @var \Shopper\Core\Models\Category $category
     * @var \Illuminate\Pagination\LengthAwarePaginator $products
     */
@endphp

@extends('shopper-storefront::layout', ['seoFor' => $category, 'seoContext' => ['page' => $products->currentPage(), 'last_page' => $products->lastPage()]])

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
                <li aria-current="page" class="font-medium text-gray-700">{{ $category->name }}</li>
            </ol>
        </nav>

        <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-gray-900">{{ $category->name }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ trans_choice('shopper-storefront::catalog.count', $products->total(), ['count' => $products->total()]) }}</p>
            </div>

            @if ($products->total() > 1)
                <form method="GET" class="flex items-center gap-2 text-sm">
                    <label for="sf-sort" class="text-gray-600">{{ __('shopper-storefront::catalog.sort.label') }}</label>
                    <select id="sf-sort" name="sort" class="sf-input w-auto py-1.5" onchange="this.form.submit()">
                        @foreach (\Shopper\Storefront\Actions\ListCategoryProductsAction::SORTS as $option)
                            <option value="{{ $option }}" @selected($sort === $option)>{{ __('shopper-storefront::catalog.sort.'.$option) }}</option>
                        @endforeach
                    </select>
                    <noscript><button type="submit" class="sf-button-secondary py-1.5">{{ __('shopper-storefront::catalog.sort.apply') }}</button></noscript>
                </form>
            @endif
        </div>

        @if (filled($category->description))
            <p class="mt-3 max-w-3xl text-sm text-gray-600">{{ $category->description }}</p>
        @endif

        @if ($children !== [])
            <ul class="mt-4 flex flex-wrap gap-2" aria-label="{{ __('shopper-storefront::catalog.subcategories') }}">
                @foreach ($children as $child)
                    <li><a href="{{ $child['url'] }}" class="inline-block rounded-full bg-white px-3 py-1.5 text-sm text-gray-700 ring-1 ring-gray-200 hover:text-brand-700 hover:ring-brand-300">{{ $child['label'] }}</a></li>
                @endforeach
            </ul>
        @endif

        @if ($products->isEmpty())
            <div class="py-20 text-center">
                <p class="text-gray-500">{{ __('shopper-storefront::catalog.empty') }}</p>
                <a href="{{ \Shopper\Storefront\Support\StorefrontUrls::home() }}" class="sf-button mt-6">{{ __('shopper-storefront::cart.continue') }}</a>
            </div>
        @else
            <ul class="sf-grid mt-6" style="--sf-columns: 5">
                @foreach ($products as $product)
                    <li>@include('shopper-storefront::partials.product-card', ['product' => $product, 'eager' => $loop->index < 5])</li>
                @endforeach
            </ul>

            <div class="mt-8">{{ $products->links() }}</div>
        @endif
    </div>
@endsection
