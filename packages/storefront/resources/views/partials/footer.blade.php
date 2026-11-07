@php
    /** @var \Shopper\Storefront\Data\FooterData $footer */
    $company = $footer->company;
    $phoneHref = filled($company['phone'] ?? null) ? 'tel:' . preg_replace('/[^0-9+]/', '', $company['phone']) : null;
    $external = fn (?string $url): bool => $url !== null && preg_match('#^https?://#i', $url) === 1 && ! str_starts_with($url, url('/'));
@endphp

<footer class="mt-12 border-t border-gray-200 bg-white text-sm text-gray-600">
    <div class="sf-container grid gap-8 py-10 sm:grid-cols-2 lg:grid-cols-4">
        <section aria-labelledby="footer-company">
            <h2 id="footer-company" class="mb-3 text-base font-semibold text-gray-900">
                {{ $company['name'] ?? ($company['store_name'] ?? config('app.name')) }}
            </h2>
            <ul class="space-y-2">
                @if (filled($company['address'] ?? null))
                    <li class="flex gap-2"><x-shopper-storefront::icon name="map-pin" class="mt-0.5 size-4 text-gray-400" /><span>{{ $company['address'] }}</span></li>
                @endif
                @if (filled($company['business_registration'] ?? null))
                    <li>
                        {{ __('shopper-storefront::storefront.footer.business_registration') }}: {{ $company['business_registration'] }}
                        @if (filled($company['registration_issued'] ?? null))
                            <span class="block text-xs text-gray-500">{{ $company['registration_issued'] }}</span>
                        @endif
                    </li>
                @endif
                @if ($phoneHref)
                    <li class="flex gap-2"><x-shopper-storefront::icon name="phone" class="mt-0.5 size-4 text-gray-400" /><a href="{{ $phoneHref }}" class="hover:text-brand-700">{{ __('shopper-storefront::storefront.header.hotline') }}: {{ $company['phone'] }}</a></li>
                @endif
                @if (filled($company['email'] ?? null))
                    <li class="flex gap-2"><x-shopper-storefront::icon name="mail" class="mt-0.5 size-4 text-gray-400" /><a href="mailto:{{ $company['email'] }}" class="hover:text-brand-700">{{ $company['email'] }}</a></li>
                @endif
                @if (filled($company['working_hours'] ?? null))
                    <li class="flex gap-2"><x-shopper-storefront::icon name="clock" class="mt-0.5 size-4 text-gray-400" /><span class="whitespace-pre-line">{{ $company['working_hours'] }}</span></li>
                @endif
            </ul>
        </section>

        @foreach ($footer->columns as $index => $column)
            <section aria-labelledby="footer-column-{{ $index }}" x-data="{ open: false }" class="border-t border-gray-100 pt-4 sm:border-0 sm:pt-0">
                <h2 id="footer-column-{{ $index }}" class="text-base font-semibold text-gray-900">
                    <button type="button" class="flex w-full items-center justify-between sm:pointer-events-none" x-on:click="open = ! open" x-bind:aria-expanded="open">
                        {{ $column['title'] }}
                        <x-shopper-storefront::icon name="chevron-down" class="size-4 sm:hidden" />
                    </button>
                </h2>
                <ul class="mt-3 hidden space-y-2 sm:block" x-bind:class="open && '!block'">
                    @foreach ($column['links'] as $link)
                        <li>
                            <a href="{{ $link->url }}" class="hover:text-brand-700" @if ($external($link->url)) target="_blank" rel="noopener" @endif>{{ $link->label }}</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        @if ($footer->shippingPartners !== [] || $footer->paymentPartners !== [] || $footer->badges !== [] || $footer->socials !== [])
            <section class="space-y-5 sm:col-span-2 lg:col-span-1" aria-label="{{ __('shopper-storefront::storefront.footer.partners') }}">
                @foreach (['shipping' => $footer->shippingPartners, 'payment' => $footer->paymentPartners] as $group => $partners)
                    @if ($partners !== [])
                        <div>
                            <h2 class="mb-2 text-sm font-semibold text-gray-900">{{ __('shopper-storefront::storefront.footer.' . $group) }}</h2>
                            <ul class="flex flex-wrap gap-2">
                                @foreach ($partners as $partner)
                                    <li>
                                        @php($tag = $partner->url ? 'a' : 'span')
                                        <{{ $tag }} @if ($partner->url) href="{{ $partner->url }}" target="_blank" rel="noopener nofollow" @endif class="flex h-9 min-w-14 items-center justify-center rounded-md border border-gray-200 bg-white px-2 grayscale transition hover:grayscale-0" title="{{ $partner->label }}">
                                            @if ($partner->image)
                                                <img src="{{ $partner->image }}" alt="{{ $partner->label }}" class="max-h-6 w-auto max-w-20 object-contain" loading="lazy" />
                                            @else
                                                <span class="text-xs font-semibold text-gray-700">{{ $partner->label }}</span>
                                            @endif
                                        </{{ $tag }}>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach

                @if ($footer->badges !== [])
                    <ul class="flex flex-wrap items-center gap-3">
                        @foreach ($footer->badges as $badge)
                            <li>
                                @php($tag = $badge->url ? 'a' : 'span')
                                <{{ $tag }} @if ($badge->url) href="{{ $badge->url }}" target="_blank" rel="noopener" @endif title="{{ $badge->label }}">
                                    @if ($badge->image)
                                        <img src="{{ $badge->image }}" alt="{{ $badge->label }}" class="h-12 w-auto" loading="lazy" />
                                    @else
                                        <span class="inline-flex rounded-md border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-700">{{ $badge->label }}</span>
                                    @endif
                                </{{ $tag }}>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($footer->socials !== [])
                    <ul class="flex gap-3">
                        @foreach ($footer->socials as $social)
                            <li><a href="{{ $social->url }}" target="_blank" rel="noopener" class="font-medium hover:text-brand-700">{{ $social->label }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    </div>

    <div class="border-t border-gray-100 py-4 text-center text-xs text-gray-500">
        © {{ now()->year }} {{ rtrim($company['name'] ?? ($company['store_name'] ?? config('app.name')), '.') }}. {{ __('shopper-storefront::storefront.footer.rights') }}
    </div>
</footer>
