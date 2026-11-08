<?php

declare(strict_types=1);

namespace Shopper\Storefront\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shopper\Cart\Exceptions\CartCompletedException;
use Shopper\Cart\Exceptions\InsufficientStockException;
use Shopper\Core\Models\Order;
use Shopper\Core\Models\OrderItem;
use Shopper\Core\Models\PaymentMethod;
use Shopper\Storefront\Actions\AddToCartAction;
use Shopper\Storefront\Actions\PlaceOrderAction;
use Shopper\Storefront\Actions\RefreshCartAction;
use Shopper\Storefront\Actions\UpdateCartLineAction;
use Shopper\Storefront\Cart\CartException;
use Shopper\Storefront\Cart\ChooseVariantException;
use Shopper\Storefront\Cart\LineNotFoundException;
use Shopper\Storefront\Cart\StorefrontCart;
use Shopper\Storefront\Checkout\CheckoutData;
use Shopper\Storefront\Checkout\CheckoutException;
use Shopper\Storefront\Checkout\CheckoutPaymentMethods;
use Shopper\Storefront\Checkout\OnePayCheckout;
use Shopper\Storefront\Data\CartLineData;
use Shopper\Storefront\Enums\PaymentKind;
use Shopper\Storefront\Settings\StorefrontSettings;
use Shopper\Storefront\Support\CartPresenter;
use Shopper\Storefront\Support\VietQr;

/**
 * Cart and checkout for headless storefronts. The cart is named by the
 * `X-Cart-Token` header, returned on every response once a cart exists.
 */
final class CartApiController
{
    public function __construct(
        private readonly StorefrontCart $carts,
        private readonly CartPresenter $presenter,
    ) {}

    public function show(RefreshCartAction $refresh): JsonResponse
    {
        $cart = $this->carts->current();
        $notices = $cart !== null ? $refresh->execute($cart) : [];

        return $this->cartResponse($notices);
    }

    public function add(Request $request, AddToCartAction $addToCart): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.StorefrontCart::MAX_QUANTITY],
        ]);

        try {
            $result = $addToCart->execute((int) $data['product_id'], isset($data['variant_id']) ? (int) $data['variant_id'] : null, (int) ($data['quantity'] ?? 1));
        } catch (ChooseVariantException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'variants' => $exception->variants], 409);
        } catch (CartException|InsufficientStockException $exception) {
            return response()->json(['message' => $exception instanceof CartException ? $exception->getMessage() : __('shopper-storefront::cart.errors.unavailable')], 422);
        }

        return $this->cartResponse(status: 201, message: $result['capped'] ? __('shopper-storefront::cart.added_capped', ['quantity' => $result['added']]) : __('shopper-storefront::cart.added'));
    }

    public function update(Request $request, int $line, UpdateCartLineAction $updateLine): JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:'.StorefrontCart::MAX_QUANTITY]]);

        return $this->changeLine($line, (int) $data['quantity'], $updateLine);
    }

    public function destroy(int $line, UpdateCartLineAction $updateLine): JsonResponse
    {
        return $this->changeLine($line, 0, $updateLine);
    }

    public function paymentMethods(CheckoutPaymentMethods $methods): JsonResponse
    {
        return response()->json([
            'data' => $methods->available()->map(fn (PaymentMethod $method): array => [
                'slug' => $method->slug,
                'kind' => CheckoutPaymentMethods::kindOf($method)?->value,
                'title' => $method->title,
                'description' => $method->description,
            ])->values(),
        ]);
    }

    public function checkout(Request $request, PlaceOrderAction $placeOrder, CheckoutPaymentMethods $methods, StorefrontSettings $settings): JsonResponse
    {
        $request->merge(['phone' => CheckoutData::normalizePhone((string) $request->input('phone', ''))]);

        $validated = $request->validate(
            CheckoutData::rules($methods->available()->pluck('slug')->all()),
            CheckoutData::messages(),
            CheckoutData::attributes(),
        );

        $cart = $this->carts->current();

        if ($cart === null) {
            return response()->json(['message' => __('shopper-storefront::checkout.errors.empty')], 422);
        }

        try {
            $order = $placeOrder->execute($cart, CheckoutData::fromValidated($validated, $request->ip()));
        } catch (CheckoutException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'notices' => $exception->notices], 422);
        } catch (CartCompletedException) {
            return response()->json(['message' => __('shopper-storefront::cart.errors.completed')], 409);
        }

        return response()->json(['data' => $this->orderData($order, $settings)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderData(Order $order, StorefrontSettings $settings): array
    {
        $order->loadMissing(['items', 'paymentMethod']);
        $kind = CheckoutPaymentMethods::kindOf($order->paymentMethod);
        $bank = $kind === PaymentKind::BankTransfer ? $settings->bankAccount() : null;

        return [
            'number' => $order->number,
            'total' => (int) $order->price_amount,
            'total_formatted' => shopper_money_format((int) $order->price_amount, $order->currency_code),
            'currency' => $order->currency_code,
            'payment_method' => $order->paymentMethod?->slug,
            'payment_kind' => $kind?->value,
            'payment_status' => $order->payment_status->value,
            'pickup_address' => $settings->address(),
            'items' => $order->items->map(fn (OrderItem $item): array => ['name' => $item->name, 'quantity' => $item->quantity, 'unit_price' => (int) $item->unit_price_amount])->values(),
            'bank_transfer' => $bank !== null ? [
                ...$bank,
                'amount' => (int) $order->price_amount,
                'reference' => $order->number,
                'qr_url' => VietQr::imageUrl($bank, (int) $order->price_amount, $order->number),
            ] : null,
            'redirect_url' => OnePayCheckout::needsRedirect($order) ? OnePayCheckout::start($order) : null,
        ];
    }

    private function changeLine(int $lineId, int $quantity, UpdateCartLineAction $updateLine): JsonResponse
    {
        $cart = $this->carts->current();

        if ($cart === null) {
            return response()->json(['message' => __('shopper-storefront::cart.errors.line_not_found')], 404);
        }

        try {
            $kept = $updateLine->execute($cart, $lineId, $quantity);
        } catch (CartException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception instanceof LineNotFoundException ? 404 : 422);
        }

        return $this->cartResponse(message: $kept > 0 && $kept < $quantity ? __('shopper-storefront::cart.errors.only_left', ['quantity' => $kept]) : null);
    }

    /**
     * @param  array<int, string>  $notices
     */
    private function cartResponse(array $notices = [], int $status = 200, ?string $message = null): JsonResponse
    {
        $cart = $this->carts->current();
        $cart = $cart !== null ? $this->presenter->load($cart) : null;

        return response()->json(array_filter([
            'message' => $message,
            'data' => [
                'token' => $this->carts->token(),
                'lines' => $cart !== null ? array_map(fn (CartLineData $line): array => $line->toArray(), $this->presenter->lines($cart)) : [],
                'summary' => $this->presenter->summary($cart),
                'notices' => $notices,
            ],
        ], fn (mixed $value): bool => $value !== null), $status);
    }
}
