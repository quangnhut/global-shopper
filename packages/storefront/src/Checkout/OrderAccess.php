<?php

declare(strict_types=1);

namespace Shopper\Storefront\Checkout;

use Illuminate\Support\Facades\Auth;
use Shopper\Core\Models\Order;
use Shopper\Storefront\Http\Controllers\Auth\LoginController;

/**
 * Who may see an order's confirmation page: the customer it belongs to, or the
 * browser session that placed it (guests).
 */
final class OrderAccess
{
    public const string SESSION_KEY = 'shopper_storefront_orders';

    /** Orders a session remembers, newest kept. */
    private const int REMEMBERED = 20;

    public static function remember(Order $order): void
    {
        $ids = array_values(array_unique([...(array) session(self::SESSION_KEY, []), $order->id]));

        session()->put(self::SESSION_KEY, array_slice($ids, -self::REMEMBERED));
    }

    public static function allows(Order $order): bool
    {
        $userId = Auth::guard(LoginController::guard())->id();

        if ($order->customer_id !== null && $userId !== null && (int) $order->customer_id === (int) $userId) {
            return true;
        }

        return in_array($order->id, (array) session(self::SESSION_KEY, []), true);
    }
}
