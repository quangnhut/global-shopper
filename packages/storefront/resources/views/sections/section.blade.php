@php
    /** @var \Shopper\Storefront\Data\SectionData $section */
    $isProducts = $section->type === \Shopper\Storefront\Enums\HomeSectionType::Products;
    $isCarousel = $section->layout === \Shopper\Storefront\Enums\HomeSectionLayout::Carousel;
    $style = $section->style->value;
    $countdown = $section->style->hasCountdown() && $section->endsAt !== null;
    $onDark = $style === 'hot_deal';
    $mutedText = $onDark ? 'text-white/85' : 'text-gray-500';
@endphp

<section
    class="sf-section sf-section--{{ $style }}"
    aria-labelledby="section-{{ $section->id }}"
    @if ($isCarousel) x-data="{ scroll(direction) { const track = this.$refs.track; track.scrollBy({ left: direction * track.clientWidth * 0.9, behavior: 'smooth' }) } }" @endif
>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <h2 id="section-{{ $section->id }}" class="sf-section-title">
                @if ($style === 'hot_deal')
                    <x-shopper-storefront::icon name="fire" class="size-6" />
                @endif
                {{ $section->title }}
            </h2>

            @if ($countdown)
                <div
                    class="flex items-center gap-1 text-sm font-semibold {{ $onDark ? 'text-white' : 'text-gray-900' }}"
                    x-data="{
                        end: {{ $section->endsAt->getTimestamp() * 1000 }},
                        left: 0,
                        tick() { this.left = Math.max(0, Math.floor((this.end - Date.now()) / 1000)) },
                        pad(value) { return String(value).padStart(2, '0') },
                    }"
                    x-init="tick(); setInterval(() => tick(), 1000)"
                    x-show="left > 0"
                    role="timer"
                    aria-label="{{ __('shopper-storefront::storefront.section.ends_in') }}"
                >
                    <span class="{{ $onDark ? 'text-white' : 'text-gray-600' }}">{{ __('shopper-storefront::storefront.section.ends_in') }}</span>
                    <span class="sf-countdown" x-text="pad(Math.floor(left / 86400))"></span><span class="text-xs {{ $mutedText }}">{{ __('shopper-storefront::storefront.section.days') }}</span>
                    <span class="sf-countdown" x-text="pad(Math.floor(left % 86400 / 3600))"></span>:
                    <span class="sf-countdown" x-text="pad(Math.floor(left % 3600 / 60))"></span>:
                    <span class="sf-countdown" x-text="pad(left % 60)"></span>
                </div>
            @endif
        </div>

        <div class="flex items-center gap-2">
            @if ($section->viewAllUrl)
                <a href="{{ $section->viewAllUrl }}" class="text-sm font-medium hover:underline {{ $onDark ? 'text-white' : 'text-brand-700' }}">{{ __('shopper-storefront::storefront.section.view_all') }}</a>
            @endif
            @if ($isCarousel)
                <button type="button" class="sf-carousel-button" x-on:click="scroll(-1)" aria-label="{{ __('shopper-storefront::storefront.section.previous') }}"><x-shopper-storefront::icon name="chevron-left" /></button>
                <button type="button" class="sf-carousel-button" x-on:click="scroll(1)" aria-label="{{ __('shopper-storefront::storefront.section.next') }}"><x-shopper-storefront::icon name="chevron-right" /></button>
            @endif
        </div>
    </div>

    @if (filled($section->subtitle))
        <p class="mt-1 text-sm {{ $mutedText }}">{{ $section->subtitle }}</p>
    @endif

    <ul
        @if ($isCarousel) x-ref="track" @endif
        class="{{ $isCarousel ? 'sf-carousel' : 'sf-grid' }} mt-4"
        style="--sf-columns: {{ max(2, min(6, $section->columns)) }}"
    >
        @foreach ($section->items as $item)
            <li class="{{ $isCarousel ? 'sf-carousel-item' : '' }}">
                @if ($isProducts)
                    @include('shopper-storefront::partials.product-card', ['product' => $item, 'badge' => $section->style->badge(), 'eager' => ($eager ?? false) && $loop->index < 6])
                @else
                    @include('shopper-storefront::partials.post-card', ['post' => $item])
                @endif
            </li>
        @endforeach
    </ul>
</section>
