@extends('shopper-storefront::layout', ['seoContext' => []])

@section('content')
    <article class="sf-container max-w-3xl py-8">
        <h1 class="text-2xl font-bold text-gray-900">{{ $legal->title }}</h1>
        <div class="sf-prose mt-6">{!! $content !!}</div>
    </article>
@endsection
