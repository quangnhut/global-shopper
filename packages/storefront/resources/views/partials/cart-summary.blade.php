@php
    /** @var array{subtotal: int, discount: int, tax: int, total: int, count: int, currency: string} $summary */
@endphp

<dl class="space-y-2 text-sm">
    <div class="flex justify-between text-gray-600">
        <dt>{{ trans_choice('shopper-storefront::cart.subtotal', $summary['count'], ['count' => $summary['count']]) }}</dt>
        <dd><span translate="no">{{ shopper_money_format($summary['subtotal'], $summary['currency']) }}</span></dd>
    </div>
    @if ($summary['discount'] > 0)
        <div class="flex justify-between text-emerald-700">
            <dt>{{ __('shopper-storefront::cart.discount') }}</dt>
            <dd>-<span translate="no">{{ shopper_money_format($summary['discount'], $summary['currency']) }}</span></dd>
        </div>
    @endif
    @if ($summary['tax'] > 0)
        <div class="flex justify-between text-gray-600">
            <dt>{{ __('shopper-storefront::cart.tax') }}</dt>
            <dd><span translate="no">{{ shopper_money_format($summary['tax'], $summary['currency']) }}</span></dd>
        </div>
    @endif
    <div class="flex justify-between border-t border-gray-100 pt-3 text-base font-bold text-gray-900">
        <dt>{{ __('shopper-storefront::cart.total') }}</dt>
        <dd class="text-brand-600"><span translate="no">{{ shopper_money_format($summary['total'], $summary['currency']) }}</span></dd>
    </div>
</dl>
