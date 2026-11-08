<?php

declare(strict_types=1);

namespace Shopper\Storefront\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Shopper\Core\Models\Contracts\Order as OrderContract;
use Shopper\Core\Models\Order;
use Shopper\Storefront\Checkout\CheckoutPaymentMethods;
use Shopper\Storefront\Checkout\OnePayCheckout;
use Shopper\Storefront\Checkout\OrderAccess;
use Shopper\Storefront\Settings\StorefrontSettings;
use Shopper\Storefront\Support\StorefrontCache;

final class CheckoutController
{
    public function show(): View
    {
        return view('shopper-storefront::checkout', StorefrontCache::layout());
    }

    /**
     * "Pay with OnePay" again for an unpaid order of this customer or session.
     */
    public function payWithOnePay(string $number): RedirectResponse
    {
        $order = $this->findOrder($number);

        abort_unless(OnePayCheckout::needsRedirect($order), 404);

        $url = OnePayCheckout::start($order);

        return $url !== null
            ? redirect()->away($url)
            : redirect()->route('shopper.storefront.order.thanks', $order->number);
    }

    /**
     * Order confirmation, for the customer it belongs to or the session that placed it.
     */
    public function thanks(string $number, StorefrontSettings $settings): View
    {
        $order = $this->findOrder($number);

        return view('shopper-storefront::order-thanks', [
            ...StorefrontCache::layout(),
            'order' => $order,
            'kind' => CheckoutPaymentMethods::kindOf($order->paymentMethod),
            'settings' => $settings,
            'bank' => $settings->bankAccount(),
        ]);
    }

    private function findOrder(string $number): Order
    {
        /** @var Order|null $order */
        $order = resolve(OrderContract::class)::query()
            ->with(['items', 'paymentMethod'])
            ->where('number', $number)
            ->first();

        abort_if($order === null || ! OrderAccess::allows($order), 404);

        return $order;
    }
}
