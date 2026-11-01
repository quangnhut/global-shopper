@extends('shopper-cms-public::layout')

@section('title', $term->name)

@php($permalinks = resolve(\Shopper\Cms\Permalinks\PermalinkGenerator::class))

@section('content')
    <h1>{{ $term->name }}</h1>

    @if ($term->description)
        <p class="meta">{{ $term->description }}</p>
    @endif

    @forelse ($posts as $post)
        <article>
            <h2><a href="{{ $permalinks->path($post) }}">{{ $post->title }}</a></h2>
            <p class="meta">{{ $post->published_at?->translatedFormat('j F Y') }}</p>
            @unless ($post->isPasswordProtected())
                <p>{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $post->content), 200) }}</p>
            @endunless
        </article>
    @empty
        <p>{{ __('shopper-cms::public.empty') }}</p>
    @endforelse

    {{-- Same URLs as the canonical and rel=prev/next links: page 1 has no `?page=1`. --}}
    @if ($posts->hasPages())
        @php($base = $permalinks->path($term))
        <nav class="meta">
            @unless ($posts->onFirstPage())
                <a href="{{ $posts->currentPage() === 2 ? $base : $base.'?page='.($posts->currentPage() - 1) }}" rel="prev">{{ __('shopper-cms::public.previous') }}</a>
            @endunless
            @if ($posts->hasMorePages())
                <a href="{{ $base.'?page='.($posts->currentPage() + 1) }}" rel="next">{{ __('shopper-cms::public.next') }}</a>
            @endif
        </nav>
    @endif
@endsection
