@php
    /** @var \Shopper\Core\Models\Order $order */
    $contact = (array) ($order->metadata['contact'] ?? []);
@endphp

<x-shopper::card :title="__('shopper-storefront::admin.order.title')" class="mb-4">
    <div class="space-y-3 text-sm">
        <dl class="space-y-2">
            @foreach (['name', 'phone', 'email'] as $field)
                @if (filled($contact[$field] ?? null))
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('shopper-storefront::admin.order.'.$field) }}</dt>
                        <dd class="text-right font-medium text-gray-900 dark:text-white">
                            @if ($field === 'phone')
                                <a href="tel:{{ $contact['phone'] }}" class="hover:underline">{{ $contact['phone'] }}</a>
                            @elseif ($field === 'email')
                                <a href="mailto:{{ $contact['email'] }}" class="hover:underline">{{ $contact['email'] }}</a>
                            @else
                                {{ $contact[$field] }}
                            @endif
                        </dd>
                    </div>
                @endif
            @endforeach
            <div class="flex justify-between gap-3">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('shopper-storefront::admin.order.delivery') }}</dt>
                <dd class="text-right font-medium text-gray-900 dark:text-white">{{ __('shopper-storefront::admin.order.pickup') }}</dd>
            </div>
            @if ($order->paymentMethod)
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('shopper-storefront::admin.order.payment') }}</dt>
                    <dd class="text-right font-medium text-gray-900 dark:text-white">{{ $order->paymentMethod->title }}</dd>
                </div>
            @endif
        </dl>
        @if (filled($order->notes))
            <p class="rounded-lg bg-gray-50 p-2 text-gray-700 dark:bg-white/5 dark:text-gray-300"><span class="font-medium">{{ __('shopper-storefront::admin.order.note') }}:</span> {{ $order->notes }}</p>
        @endif
    </div>
</x-shopper::card>
