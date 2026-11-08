@php
    /** @var array<int, \Shopper\Storefront\Data\CartLineData> $lines */
@endphp

<ul class="divide-y divide-gray-100" aria-label="{{ __('shopper-storefront::cart.lines') }}">
    @foreach ($lines as $line)
        <li class="flex gap-3 py-4" wire:key="line-{{ $line->id }}">
            <div class="size-20 shrink-0 overflow-hidden rounded-lg bg-gray-50 ring-1 ring-gray-100">
                @if ($line->image)
                    <img src="{{ $line->image }}" alt="" class="size-full object-contain p-1" loading="lazy" decoding="async" />
                @else
                    <div class="flex size-full items-center justify-center text-gray-300"><x-shopper-storefront::icon name="cart" class="size-8" /></div>
                @endif
            </div>

            <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="font-medium text-gray-900">
                        @if ($line->url)
                            <a href="{{ $line->url }}" class="hover:text-brand-700">{{ $line->name }}</a>
                        @else
                            {{ $line->name }}
                        @endif
                    </p>
                    @if ($line->variant)
                        <p class="text-sm text-gray-500">{{ $line->variant }}</p>
                    @endif
                    <p class="text-sm text-gray-600">{{ $line->formattedUnitPrice() }}</p>
                    @error('lines.'.$line->id)
                        <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 sm:flex-col sm:flex-nowrap sm:items-end">
                    <div class="flex items-center gap-2">
                        <div class="sf-qty" role="group" aria-label="{{ __('shopper-storefront::cart.quantity_for', ['name' => $line->name]) }}">
                            <button type="button" wire:click="decrement({{ $line->id }})" @disabled($line->quantity <= 1) aria-label="{{ __('shopper-storefront::cart.decrease') }}">
                                <x-shopper-storefront::icon name="minus" class="size-4" />
                            </button>
                            <input
                                type="number"
                                inputmode="numeric"
                                min="1"
                                max="{{ $line->maxQuantity }}"
                                value="{{ $line->quantity }}"
                                wire:change="setQuantity({{ $line->id }}, $event.target.value)"
                                aria-label="{{ __('shopper-storefront::cart.quantity') }}"
                            />
                            <button type="button" wire:click="increment({{ $line->id }})" @disabled($line->quantity >= $line->maxQuantity) aria-label="{{ __('shopper-storefront::cart.increase') }}">
                                <x-shopper-storefront::icon name="plus" class="size-4" />
                            </button>
                        </div>
                        <button
                            type="button"
                            wire:click="remove({{ $line->id }})"
                            class="flex size-9 items-center justify-center rounded-lg text-gray-500 hover:bg-red-50 hover:text-red-600"
                            aria-label="{{ __('shopper-storefront::cart.remove', ['name' => $line->name]) }}"
                        >
                            <x-shopper-storefront::icon name="trash" class="size-5" />
                        </button>
                    </div>
                    <p class="font-semibold text-gray-900">{{ $line->formattedTotal() }}</p>
                </div>
            </div>
        </li>
    @endforeach
</ul>
