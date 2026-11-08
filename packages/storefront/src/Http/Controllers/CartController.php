<?php

declare(strict_types=1);

namespace Shopper\Storefront\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Shopper\Cart\Exceptions\InsufficientStockException;
use Shopper\Storefront\Actions\AddToCartAction;
use Shopper\Storefront\Cart\CartException;
use Shopper\Storefront\Cart\ChooseVariantException;
use Shopper\Storefront\Cart\StorefrontCart;
use Shopper\Storefront\Support\StorefrontCache;

final class CartController
{
    public function show(): View
    {
        return view('shopper-storefront::cart', StorefrontCache::layout());
    }

    /**
     * "Add to cart" from a product card. Answers JSON to the card's script and
     * redirects back when the form is posted without JavaScript.
     */
    public function store(Request $request, AddToCartAction $addToCart, StorefrontCart $carts): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.StorefrontCart::MAX_QUANTITY],
        ]);

        try {
            $result = $addToCart->execute((int) $data['product_id'], isset($data['variant_id']) ? (int) $data['variant_id'] : null, (int) ($data['quantity'] ?? 1));
        } catch (ChooseVariantException $exception) {
            return $request->expectsJson()
                ? response()->json(['message' => $exception->getMessage(), 'product' => $exception->productName, 'variants' => $exception->variants], 409)
                : back()->withErrors(['cart' => $exception->getMessage()]);
        } catch (CartException|InsufficientStockException $exception) {
            $message = $exception instanceof CartException ? $exception->getMessage() : __('shopper-storefront::cart.errors.unavailable');

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->withErrors(['cart' => $message]);
        }

        $message = $result['capped']
            ? __('shopper-storefront::cart.added_capped', ['quantity' => $result['added']])
            : __('shopper-storefront::cart.added');

        if (! $request->expectsJson()) {
            return back()->with('status', $message);
        }

        return response()->json([
            'message' => $message,
            'count' => (int) $carts->current()?->lines()->sum('quantity'),
            'cart_url' => route('shopper.storefront.cart'),
        ]);
    }
}
