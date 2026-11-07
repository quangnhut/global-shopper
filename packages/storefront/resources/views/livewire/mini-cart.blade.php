@php($count = $this->count())
<div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
    <button
        type="button"
        class="relative flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-white/10"
        x-on:click="open = ! open"
        x-bind:aria-expanded="open"
        aria-label="{{ __('shopper-storefront::storefront.cart.label', ['count' => $count]) }}"
    >
        <span class="relative">
            <x-shopper-storefront::icon name="cart" class="size-6" />
            <span class="absolute -right-2.5 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-400 px-1 text-[11px] font-bold text-gray-900">{{ $count > 99 ? '99+' : $count }}</span>
        </span>
        <span class="hidden text-sm font-semibold sm:block">{{ __('shopper-storefront::storefront.cart.title') }}</span>
    </button>

    <div x-cloak x-show="open" x-transition.opacity class="absolute right-0 z-40 mt-2 w-80 rounded-xl bg-white p-3 text-sm text-gray-800 shadow-lg ring-1 ring-black/5">
        @if ($count === 0)
            <p class="py-6 text-center text-gray-500">{{ __('shopper-storefront::storefront.cart.empty') }}</p>
        @else
            <ul class="max-h-72 divide-y divide-gray-100 overflow-y-auto">
                @foreach ($this->cart->lines as $line)
                    <li class="flex justify-between gap-3 py-2">
                        <span class="min-w-0"><span class="block truncate">{{ $line->purchasable?->name ?? '—' }}</span><span class="text-xs text-gray-500">× {{ $line->quantity }}</span></span>
                        <span class="shrink-0 font-medium">{{ shopper_money_format((int) ($line->unit_price_amount * $line->quantity), $this->cart->currency_code) }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-3 flex justify-between border-t border-gray-100 pt-3 font-semibold">
                <span>{{ __('shopper-storefront::storefront.cart.subtotal') }}</span>
                <span class="text-brand-600">{{ shopper_money_format($this->subtotal(), $this->cart->currency_code) }}</span>
            </div>
        @endif
    </div>
</div>
