@extends('shopper-cms-public::layout')

@section('title', $post->title)

@section('content')
    <h1>{{ $post->title }}</h1>
    <p>{{ __('shopper-cms::public.protected') }}</p>

    <form method="post" action="{{ route('shopper.cms.public.unlock', $post) }}">
        @csrf
        <label>
            {{ __('shopper-cms::public.password') }}
            <input type="password" name="post_password" required autocomplete="off" />
        </label>
        <button type="submit">{{ __('shopper-cms::public.enter') }}</button>
        @error('post_password')
            <p role="alert">{{ $message }}</p>
        @enderror
    </form>
@endsection
