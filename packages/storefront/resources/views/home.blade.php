@extends('shopper-storefront::layout')

@section('content')
    <h1 class="sr-only">{{ $header->storeName ?? config('app.name') }}</h1>

    <div class="sf-container space-y-10 py-6">
        @forelse ($sections as $section)
            @include('shopper-storefront::sections.section', ['section' => $section, 'eager' => $loop->first])
        @empty
            <p class="py-24 text-center text-gray-500">{{ __('shopper-storefront::storefront.home.empty') }}</p>
        @endforelse
    </div>
@endsection
