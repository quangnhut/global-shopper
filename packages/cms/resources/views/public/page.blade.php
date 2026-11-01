@extends('shopper-cms-public::layout')

@section('title', $post->title)
@section('body_class', $post->template ?? 'default')

@section('content')
    <article>
        <h1>{{ $post->title }}</h1>

        {{-- Content is sanitized when it is saved (HtmlSanitizer). --}}
        <div class="content">{!! $post->content !!}</div>
    </article>
@endsection
