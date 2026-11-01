@extends('shopper-cms-public::layout')

@section('title', $post->title)

@php($permalinks = resolve(\Shopper\Cms\Permalinks\PermalinkGenerator::class))

@section('content')
    <article>
        <h1>{{ $post->title }}</h1>
        <p class="meta">
            {{ $post->published_at?->translatedFormat('j F Y') }}
            @if ($post->author)
                · {{ $post->author->getAttribute('full_name') }}
            @endif
        </p>

        @if ($post->featuredImage)
            <img src="{{ $post->featuredImage->url }}" alt="" class="aligncenter" />
        @endif

        {{-- Content is sanitized when it is saved (HtmlSanitizer). --}}
        <div class="content">{!! $post->content !!}</div>

        <p class="meta terms">
            @foreach ($post->categories as $category)
                <a href="{{ $permalinks->path($category) }}">{{ $category->name }}</a>
            @endforeach

            @foreach ($post->tags as $tag)
                <a href="{{ $permalinks->path($tag) }}">#{{ $tag->name }}</a>
            @endforeach
        </p>
    </article>
@endsection
