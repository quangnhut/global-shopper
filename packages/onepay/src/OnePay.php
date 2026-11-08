<?php

declare(strict_types=1);

namespace Shopper\OnePay;

use Closure;
use Illuminate\Http\RedirectResponse;
use Shopper\Core\Models\Order;

/**
 * Where the browser goes after OnePay sends it back. The storefront sets this to
 * its order confirmation page; without it the customer lands on the home page.
 * Kept in the container, not in a static, so each application has its own.
 */
final class OnePay
{
    private const string AFTER_RETURN = 'shopper.onepay.after-return';

    /**
     * @param  Closure(?Order, ConfirmationOutcome, string): RedirectResponse  $callback
     */
    public static function redirectAfterReturnUsing(Closure $callback): void
    {
        app()->instance(self::AFTER_RETURN, $callback);
    }

    public static function redirectAfterReturn(?Order $order, ConfirmationOutcome $outcome, string $message): RedirectResponse
    {
        if (app()->bound(self::AFTER_RETURN)) {
            /** @var Closure(?Order, ConfirmationOutcome, string): RedirectResponse $callback */
            $callback = app(self::AFTER_RETURN);

            return $callback($order, $outcome, $message);
        }

        return redirect('/')->with('status', $message);
    }
}
