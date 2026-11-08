@php
    /**
     * Google Translate language picker. `$languages`: code => [label, short, flag]; `$variant`: header (dropdown) or drawer (list).
     * The current language lives in the `sfTranslate` Alpine store (translate-scripts), read from Google's `googtrans` cookie.
     * `translate="no"` + `notranslate`: Google must never rewrite the language names.
     *
     * @var array<string, array{label: string, short: string, flag: string}> $languages
     */
    $variant ??= 'header';
    $pageLanguage = (string) config('shopper.storefront.translate.page_language', 'vi');
    $codes = array_keys($languages);
@endphp

@if ($variant === 'drawer')
    <div class="notranslate border-b border-gray-100 p-4 text-sm" translate="no">
        <p class="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase">{{ __('shopper-storefront::storefront.header.language') }}</p>
        <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="{{ __('shopper-storefront::storefront.header.language') }}">
            @foreach ($languages as $code => $language)
                <button
                    type="button"
                    role="radio"
                    class="flex flex-col items-center gap-1 rounded-lg px-2 py-2 ring-1 ring-gray-200"
                    x-bind:class="$store.sfTranslate.current === @js($code) ? 'bg-brand-50 font-semibold text-brand-700 ring-brand-300' : 'hover:bg-gray-50'"
                    x-bind:aria-checked="($store.sfTranslate.current === @js($code)).toString()"
                    aria-checked="{{ $code === $pageLanguage ? 'true' : 'false' }}"
                    x-on:click="$store.sfTranslate.choose(@js($code)); drawer = false"
                    lang="{{ $code }}"
                >
                    <x-shopper-storefront::flag :code="$language['flag']" class="h-4 w-6" />
                    <span class="text-xs">{{ $language['label'] }}</span>
                </button>
            @endforeach
        </div>
    </div>
@else
    <div
        class="notranslate relative"
        translate="no"
        x-data="{
            open: false,
            active: 0,
            codes: @js($codes),
            toggle() {
                this.open ? this.close() : this.show()
            },
            show() {
                this.open = true
                this.active = Math.max(0, this.codes.indexOf($store.sfTranslate.current))
                this.$nextTick(() => this.$refs.list.focus())
            },
            close(focus = true) {
                this.open = false
                if (focus) this.$refs.button.focus()
            },
            move(step) {
                this.active = (this.active + step + this.codes.length) % this.codes.length
            },
            pick(code) {
                this.close()
                $store.sfTranslate.choose(code)
            },
        }"
        x-on:click.outside="open && close(false)"
        x-on:keydown.escape.stop="open && close()"
    >
        <button
            type="button"
            x-ref="button"
            class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 hover:bg-white/10"
            x-on:click="toggle()"
            x-on:keydown.arrow-down.prevent="show()"
            x-bind:aria-expanded="open.toString()"
            aria-expanded="false"
            aria-haspopup="listbox"
            x-bind:aria-label="@js(__('shopper-storefront::storefront.header.language')) + ': ' + (@js(array_map(fn (array $language): string => $language['label'], $languages))[$store.sfTranslate.current] ?? '')"
            aria-label="{{ __('shopper-storefront::storefront.header.language') }}: {{ $languages[$pageLanguage]['label'] ?? '' }}"
            x-bind:title="$store.sfTranslate.loading ? @js(__('shopper-storefront::storefront.header.translating')) : null"
        >
            @foreach ($languages as $code => $language)
                <span class="flex items-center gap-1.5" @if ($code !== $pageLanguage) x-cloak @endif x-show="$store.sfTranslate.current === @js($code)">
                    <x-shopper-storefront::flag :code="$language['flag']" />
                    <span class="hidden text-sm font-semibold sm:inline" lang="{{ $code }}">{{ $language['short'] }}</span>
                </span>
            @endforeach
            <svg x-show="$store.sfTranslate.loading" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3" />
                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
            </svg>
            <x-shopper-storefront::icon name="chevron-down" class="size-4" x-show="! $store.sfTranslate.loading" />
        </button>

        <ul
            x-cloak
            x-show="open"
            x-transition.opacity
            x-ref="list"
            tabindex="-1"
            role="listbox"
            aria-label="{{ __('shopper-storefront::storefront.header.language') }}"
            x-bind:aria-activedescendant="'sf-lang-' + active"
            x-on:keydown.arrow-down.prevent="move(1)"
            x-on:keydown.arrow-up.prevent="move(-1)"
            x-on:keydown.home.prevent="active = 0"
            x-on:keydown.end.prevent="active = codes.length - 1"
            x-on:keydown.enter.prevent="pick(codes[active])"
            x-on:keydown.space.prevent="pick(codes[active])"
            x-on:keydown.tab="close(false)"
            class="absolute right-0 z-40 mt-2 w-48 rounded-xl bg-white p-1.5 text-sm text-gray-800 shadow-lg ring-1 ring-black/5 focus:outline-none"
        >
            @foreach ($languages as $code => $language)
                <li
                    id="sf-lang-{{ $loop->index }}"
                    role="option"
                    lang="{{ $code }}"
                    class="flex cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2"
                    x-bind:class="active === {{ $loop->index }} && 'bg-gray-100'"
                    x-bind:aria-selected="($store.sfTranslate.current === @js($code)).toString()"
                    x-on:mouseenter="active = {{ $loop->index }}"
                    x-on:click="pick(@js($code))"
                >
                    <x-shopper-storefront::flag :code="$language['flag']" />
                    <span class="flex-1" x-bind:class="$store.sfTranslate.current === @js($code) && 'font-semibold text-brand-700'">{{ $language['label'] }}</span>
                    <svg x-show="$store.sfTranslate.current === @js($code)" class="size-4 text-brand-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" />
                    </svg>
                </li>
            @endforeach
        </ul>
    </div>
@endif
