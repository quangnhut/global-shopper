@extends('shopper-storefront::layout', ['seoContext' => ['search' => $term]])

@section('content')
    <div class="sf-container py-6">
        <h1 class="text-xl font-bold text-gray-900">
            @if ($term === '')
                {{ __('shopper-storefront::storefront.search.title') }}
            @else
                {{ __('shopper-storefront::storefront.search.results_for', ['query' => $term]) }}
            @endif
        </h1>

        @if ($term !== '')
            <p class="mt-1 text-sm text-gray-500">{{ trans_choice('shopper-storefront::storefront.search.count', $results->total(), ['count' => $results->total()]) }}</p>
        @endif

        @if ($results->isEmpty())
            <p class="py-20 text-center text-gray-500">
                {{ $term === '' ? __('shopper-storefront::storefront.search.empty_query') : __('shopper-storefront::storefront.search.no_results', ['query' => $term]) }}
            </p>
        @else
            <ul class="sf-grid mt-6" style="--sf-columns: 5">
                @foreach ($results as $product)
                    <li>@include('shopper-storefront::partials.product-card', ['product' => $product, 'eager' => $loop->index < 5])</li>
                @endforeach
            </ul>

            <div class="mt-8">{{ $results->links() }}</div>
        @endif
    </div>
@endsection
