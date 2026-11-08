<?php

declare(strict_types=1);

namespace Shopper\Storefront\Actions;

use Illuminate\Support\Facades\Auth;
use Shopper\Cart\Actions\CreateOrderFromCartAction;
use Shopper\Cart\Data\OrderDetails;
use Shopper\Cart\Exceptions\CartCompletedException;
use Shopper\Cart\Exceptions\DiscountLimitReachedException;
use Shopper\Cart\Exceptions\InsufficientStockException;
use Shopper\Cart\Models\Cart;
use Shopper\Core\Models\Contracts\Order as OrderContract;
use Shopper\Core\Models\Order;
use Shopper\Storefront\Cart\StorefrontCart;
use Shopper\Storefront\Checkout\CheckoutData;
use Shopper\Storefront\Checkout\CheckoutException;
use Shopper\Storefront\Checkout\CheckoutPaymentMethods;
use Shopper\Storefront\Checkout\OrderAccess;
use Shopper\Storefront\Http\Controllers\Auth\LoginController;
use Shopper\Storefront\Jobs\SendTelegramOrderMessage;

/**
 * Turns the session cart into an order (pickup at the office, no shipping) and tells the shop on Telegram.
 */
final readonly class PlaceOrderAction
{
    public function __construct(
        private RefreshCartAction $refreshCart,
        private CreateOrderFromCartAction $createOrder,
        private CheckoutPaymentMethods $paymentMethods,
        private StorefrontCart $carts,
    ) {}

    public function execute(Cart $cart, CheckoutData $data): Order
    {
        $method = $this->paymentMethods->find($data->paymentMethod);

        if ($method === null) {
            throw new CheckoutException(__('shopper-storefront::checkout.errors.payment_method'));
        }

        $notices = $this->refreshCart->execute($cart);

        if ($notices !== []) {
            throw new CheckoutException(__('shopper-storefront::checkout.errors.cart_changed'), $notices);
        }

        if (! $cart->lines()->exists()) {
            throw new CheckoutException(__('shopper-storefront::checkout.errors.empty'));
        }

        $customerId = Auth::guard(LoginController::guard())->id();

        if ($customerId !== null && $cart->customer_id === null) {
            $cart->update(['customer_id' => $customerId]);
        }

        try {
            $order = $this->createOrder->execute($cart, new OrderDetails(
                paymentMethodId: $method->id,
                notes: $data->note,
                metadata: [
                    'source' => 'storefront',
                    'cart_id' => $cart->id,
                    'pickup' => true,
                    'contact' => array_filter(['name' => $data->name, 'phone' => $data->phone, 'email' => $data->email]),
                    'payment_kind' => CheckoutPaymentMethods::kindOf($method)?->value,
                    'locale' => app()->getLocale(),
                    'ip' => $data->ip,
                ],
            ));
        } catch (CartCompletedException) {
            // Second click on "Place order": show the order the first click made.
            $order = $this->orderOf($cart);

            if ($order === null) {
                throw new CheckoutException(__('shopper-storefront::cart.errors.completed'));
            }

            $this->finish($order);

            return $order;
        } catch (InsufficientStockException $exception) {
            throw new CheckoutException(__('shopper-storefront::checkout.errors.stock'));
        } catch (DiscountLimitReachedException $exception) {
            throw new CheckoutException($exception->getMessage());
        }

        $this->finish($order);

        SendTelegramOrderMessage::dispatch($order->id);

        return $order;
    }

    private function finish(Order $order): void
    {
        $this->carts->forget();

        if (! $this->carts->usesToken()) {
            OrderAccess::remember($order);
        }
    }

    private function orderOf(Cart $cart): ?Order
    {
        /** @var Order|null */
        return resolve(OrderContract::class)::query()
            ->where('metadata->cart_id', $cart->id)
            ->latest('id')
            ->first();
    }
}
