@php
    /**
     * @var \Shopper\Core\Models\Order $order
     * @var ?\Shopper\Storefront\Enums\PaymentKind $kind
     * @var \Shopper\Storefront\Settings\StorefrontSettings $settings
     * @var array{bin: string, bank: string, number: string, name: string}|null $bank
     */
    $currency = $order->currency_code;
    $contact = (array) ($order->metadata['contact'] ?? []);
    $paid = in_array($order->payment_status, [\Shopper\Core\Enum\PaymentStatus::Paid, \Shopper\Core\Enum\PaymentStatus::Authorized], true)
        && $kind === \Shopper\Storefront\Enums\PaymentKind::OnePay;
    $address = $settings->address();
    $hours = $settings->get('working_hours');
@endphp

@extends('shopper-storefront::layout', ['seoContext' => ['title' => __('shopper-storefront::checkout.thanks.title'), 'noindex' => true]])

@section('content')
    <div class="sf-container max-w-3xl py-8">
        <div class="rounded-xl bg-white p-6 text-center ring-1 ring-gray-100">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                <x-shopper-storefront::icon name="check" class="size-7" />
            </div>
            <h1 class="mt-3 text-xl font-bold text-gray-900">{{ __('shopper-storefront::checkout.thanks.title') }}</h1>
            <p class="mt-1 text-gray-600">{{ __('shopper-storefront::checkout.thanks.number') }} <strong class="text-gray-900">{{ $order->number }}</strong></p>
            <p class="mt-1 text-sm text-gray-500">{{ __('shopper-storefront::checkout.thanks.contact_soon', ['phone' => $contact['phone'] ?? '']) }}</p>
        </div>

        <section class="mt-6 rounded-xl bg-white p-6 ring-1 ring-gray-100" aria-labelledby="sf-pay-title">
            <h2 id="sf-pay-title" class="font-semibold text-gray-900">{{ $order->paymentMethod?->title ?? __('shopper-storefront::checkout.fields.payment_method') }}</h2>

            @switch($kind)
                @case(\Shopper\Storefront\Enums\PaymentKind::BankTransfer)
                    @if ($bank)
                        <div class="mt-4 grid items-start gap-6 sm:grid-cols-[14rem_1fr]">
                            <img
                                src="{{ \Shopper\Storefront\Support\VietQr::imageUrl($bank, (int) $order->price_amount, $order->number) }}"
                                alt="{{ __('shopper-storefront::checkout.thanks.qr_alt', ['number' => $order->number]) }}"
                                width="224"
                                height="270"
                                class="mx-auto w-56 rounded-lg ring-1 ring-gray-100"
                            />
                            <dl class="space-y-2 text-sm">
                                <div><dt class="text-gray-500">{{ __('shopper-storefront::checkout.bank.bank') }}</dt><dd class="font-medium text-gray-900">{{ $bank['bank'] }}</dd></div>
                                <div><dt class="text-gray-500">{{ __('shopper-storefront::checkout.bank.account_number') }}</dt><dd class="font-mono text-base font-semibold text-gray-900">{{ $bank['number'] }}</dd></div>
                                <div><dt class="text-gray-500">{{ __('shopper-storefront::checkout.bank.account_name') }}</dt><dd class="font-medium text-gray-900">{{ $bank['name'] }}</dd></div>
                                <div><dt class="text-gray-500">{{ __('shopper-storefront::checkout.bank.amount') }}</dt><dd class="text-base font-bold text-brand-600">{{ shopper_money_format((int) $order->price_amount, $currency) }}</dd></div>
                                <div><dt class="text-gray-500">{{ __('shopper-storefront::checkout.bank.content') }}</dt><dd class="font-mono text-base font-semibold text-gray-900">{{ $order->number }}</dd></div>
                            </dl>
                        </div>
                        <p class="mt-4 text-sm text-gray-600">{{ __('shopper-storefront::checkout.bank.confirm') }}</p>
                    @else
                        <p class="mt-2 text-sm text-gray-600">{{ __('shopper-storefront::checkout.bank.missing', ['number' => $order->number]) }}</p>
                    @endif
                    @break

                @case(\Shopper\Storefront\Enums\PaymentKind::OnePay)
                    @if ($paid)
                        <p class="mt-2 text-sm font-medium text-emerald-700">{{ __('shopper-storefront::checkout.onepay.paid') }}</p>
                    @else
                        <p class="mt-2 text-sm text-gray-600">{{ __('shopper-storefront::checkout.onepay.pending') }}</p>
                        @if (\Illuminate\Support\Facades\Route::has('shopper.storefront.onepay.pay') && $order->isNotCancelled())
                            <form method="POST" action="{{ route('shopper.storefront.onepay.pay', $order->number) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="sf-button">{{ __('shopper-storefront::checkout.onepay.pay_again') }}</button>
                            </form>
                        @endif
                    @endif
                    @break

                @default
                    <p class="mt-2 text-sm text-gray-600">{{ __('shopper-storefront::checkout.office.instructions', ['number' => $order->number]) }}</p>
            @endswitch

            @if ($address)
                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
                    <p class="font-medium text-gray-900">{{ __('shopper-storefront::checkout.pickup.title') }}</p>
                    <p>{{ $address }}</p>
                    @if ($hours)
                        <p>{{ __('shopper-storefront::checkout.pickup.hours', ['hours' => $hours]) }}</p>
                    @endif
                </div>
            @endif
        </section>

        <section class="mt-6 rounded-xl bg-white p-6 ring-1 ring-gray-100" aria-labelledby="sf-items-title">
            <h2 id="sf-items-title" class="font-semibold text-gray-900">{{ __('shopper-storefront::checkout.thanks.items') }}</h2>
            <ul class="mt-2 divide-y divide-gray-100 text-sm">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-4 py-2">
                        <span>{{ $item->name }} <span class="text-gray-500">× {{ $item->quantity }}</span></span>
                        <span class="font-medium">{{ shopper_money_format($item->unit_price_amount * $item->quantity, $currency) }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 flex justify-between border-t border-gray-100 pt-3 text-base font-bold">
                <span>{{ __('shopper-storefront::cart.total') }}</span>
                <span class="text-brand-600">{{ shopper_money_format((int) $order->price_amount, $currency) }}</span>
            </p>
            @if (filled($order->notes))
                <p class="mt-3 text-sm text-gray-600"><span class="font-medium">{{ __('shopper-storefront::checkout.fields.note') }}:</span> {{ $order->notes }}</p>
            @endif
        </section>

        <div class="mt-6 text-center">
            <a href="{{ \Shopper\Storefront\Support\StorefrontUrls::home() }}" class="sf-button">{{ __('shopper-storefront::cart.continue') }}</a>
        </div>
    </div>
@endsection
