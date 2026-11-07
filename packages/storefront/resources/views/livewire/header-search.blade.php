<form
    action="{{ route('shopper.storefront.search') }}"
    method="GET"
    role="search"
    class="relative"
    x-data="{ open: false }"
    x-on:click.outside="open = false"
    x-on:keydown.escape="open = false"
>
    <label for="sf-search" class="sr-only">{{ __('shopper-storefront::storefront.search.label') }}</label>
    <input
        id="sf-search"
        type="search"
        name="q"
        wire:model.live.debounce.300ms="q"
        x-on:focus="open = true"
        x-on:input="open = true"
        maxlength="100"
        autocomplete="off"
        placeholder="{{ __('shopper-storefront::storefront.search.placeholder') }}"
        class="w-full rounded-lg border-0 bg-white py-2.5 pl-4 pr-12 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-brand-300"
    />
    <button type="submit" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-gray-500 hover:text-brand-700" aria-label="{{ __('shopper-storefront::storefront.search.submit') }}">
        <x-shopper-storefront::icon name="search" />
    </button>

    @php($suggestions = $this->suggestions)
    @if (mb_strlen(trim($q)) >= 2)
        <div x-cloak x-show="open" class="absolute inset-x-0 top-full z-40 mt-1 overflow-hidden rounded-xl bg-white text-gray-800 shadow-lg ring-1 ring-black/5">
            @forelse ($suggestions['items'] as $item)
                <a href="{{ $item->url }}" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50">
                    @if ($item->image)
                        <img src="{{ $item->image }}" alt="" class="size-10 shrink-0 rounded object-contain" loading="lazy" />
                    @else
                        <span class="size-10 shrink-0 rounded bg-gray-100"></span>
                    @endif
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm">{{ $item->name }}</span>
                        <span class="block text-sm font-semibold text-brand-600">{{ $item->formattedPrice() }}</span>
                    </span>
                </a>
            @empty
                <p class="px-3 py-3 text-sm text-gray-500">{{ __('shopper-storefront::storefront.search.no_results', ['query' => $q]) }}</p>
            @endforelse

            @if ($suggestions['total'] > count($suggestions['items']))
                <button type="submit" class="block w-full border-t border-gray-100 px-3 py-2 text-center text-sm font-medium text-brand-700 hover:bg-gray-50">
                    {{ __('shopper-storefront::storefront.search.see_all', ['count' => $suggestions['total']]) }}
                </button>
            @endif
        </div>
    @endif
</form>
