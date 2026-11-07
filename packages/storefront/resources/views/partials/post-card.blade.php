@php
    /** @var \Shopper\Storefront\Data\PostCardData $post */
@endphp

<article class="sf-card group relative flex h-full flex-col overflow-hidden">
    <div class="aspect-video overflow-hidden bg-gray-100">
        @if ($post->image)
            <img src="{{ $post->image }}" alt="" class="size-full object-cover transition duration-300 group-hover:scale-105" loading="lazy" decoding="async" />
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-2 p-4">
        @if ($post->publishedAt)
            <time datetime="{{ $post->publishedAt->toDateString() }}" class="text-xs text-gray-500">{{ $post->publishedAt->translatedFormat('d/m/Y') }}</time>
        @endif
        <h3 class="line-clamp-2 font-semibold text-gray-900">
            <a href="{{ $post->url }}" class="after:absolute after:inset-0 hover:text-brand-700">{{ $post->title }}</a>
        </h3>
        @if ($post->excerpt)
            <p class="line-clamp-3 text-sm text-gray-600">{{ $post->excerpt }}</p>
        @endif
    </div>
</article>
