<?php

declare(strict_types=1);

namespace Shopper\Storefront\Checkout;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Shopper\Core\Models\Order;
use Shopper\OnePay\Actions\StartOnePayPaymentAction;
use Shopper\OnePay\ConfirmationOutcome;
use Shopper\OnePay\OnePayException;
use Shopper\Storefront\Enums\PaymentKind;
use Shopper\Storefront\Support\StorefrontUrls;

/**
 * Glue between the checkout and the optional `shopper/onepay` package.
 */
final class OnePayCheckout
{
    public static function installed(): bool
    {
        return class_exists(StartOnePayPaymentAction::class);
    }

    public static function needsRedirect(Order $order): bool
    {
        return self::installed() && CheckoutPaymentMethods::kindOf($order->paymentMethod) === PaymentKind::OnePay;
    }

    /**
     * The OnePay URL for this order, or null with the reason flashed for the confirmation page.
     */
    public static function start(Order $order): ?string
    {
        try {
            return resolve(StartOnePayPaymentAction::class)->execute($order, [
                'ip' => request()->ip(),
                'locale' => app()->getLocale(),
                'again_url' => Route::has('shopper.storefront.order.thanks') ? route('shopper.storefront.order.thanks', $order->number) : null,
            ]);
        } catch (OnePayException $exception) {
            if (! request()->hasSession()) {
                return null;
            }

            session()->flash('errors', (new ViewErrorBag)->put('default', new MessageBag(['cart' => $exception->getMessage()])));

            return null;
        }
    }

    /**
     * Where the browser lands after OnePay: the order's confirmation page.
     */
    public static function afterReturn(?Order $order, ConfirmationOutcome $outcome, string $message): RedirectResponse
    {
        if (! Route::has('shopper.storefront.order.thanks')) {
            // Headless storefront: back to its home page, which reads the result from the API.
            return redirect()->to(StorefrontUrls::home())->with('status', $message);
        }

        if ($order === null || ! OrderAccess::allows($order)) {
            return redirect()->route('shopper.storefront.home')->withErrors(['cart' => $message]);
        }

        $redirect = redirect()->route('shopper.storefront.order.thanks', $order->number);

        return in_array($outcome, [ConfirmationOutcome::Paid, ConfirmationOutcome::AlreadyPaid], true)
            ? $redirect->with('status', $message)
            : $redirect->withErrors(['cart' => $message]);
    }
}
