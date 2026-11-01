<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="robots" content="noindex, nofollow" />
        <title>{{ __('shopper-cms::posts.preview.title') }}: {{ $post->title }}</title>
        <style>
            :root { color-scheme: light dark; }
            body { margin: 0; font-family: system-ui, sans-serif; background: Canvas; color: CanvasText; }
            .notice { padding: 10px 16px; font-size: 14px; background: #fef3c7; color: #78350f; }
            main { max-width: 760px; margin: 0 auto; padding: 32px 16px; }
            h1 { font-size: 2rem; line-height: 1.2; margin: 0 0 24px; }
            iframe { width: 100%; min-height: 70vh; border: 0; background: Canvas; }
        </style>
    </head>
    <body>
        <div class="notice">
            {{ __('shopper-cms::posts.preview.notice', ['time' => $expiresAt->diffForHumans()]) }}
        </div>

        <main>
            <h1>{{ $post->title !== '' ? $post->title : __('shopper-cms::posts.no_title') }}</h1>

            {{-- Content is not sanitized until the editor plan (04): render it in a sandbox without scripts. --}}
            <iframe sandbox srcdoc="{{ $post->content }}" title="{{ $post->title }}"></iframe>
        </main>
    </body>
</html>
