<?php

declare(strict_types=1);

namespace Shopper\Storefront\Support;

use Shopper\Cart\CartManager;
use Shopper\Cart\Models\Cart;
use Shopper\Cart\Models\CartLine;
use Shopper\Core\Models\ProductVariant;
use Shopper\Storefront\Data\CartLineData;

/**
 * Lines and totals of a cart, for the cart and checkout pages and the JSON API.
 */
final readonly class CartPresenter
{
    public function __construct(private CartManager $cartManager) {}

    public function load(Cart $cart): Cart
    {
        $cart->load('lines.purchasable');
        $cart->lines->loadMorph('purchasable', [
            ProductVariant::class => ['product.media'],
            config('shopper.models.product') => ['media'],
        ]);

        return $cart;
    }

    /**
     * @return array<int, CartLineData>
     */
    public function lines(Cart $cart): array
    {
        return $cart->lines
            ->sortBy('id')
            ->map(fn (CartLine $line): CartLineData => CartLineData::fromLine($line, $cart->currency_code))
            ->values()
            ->all();
    }

    /**
     * @return array{subtotal: int, discount: int, tax: int, total: int, count: int, currency: string}
     */
    public function summary(?Cart $cart): array
    {
        if ($cart === null || $cart->lines->isEmpty()) {
            return ['subtotal' => 0, 'discount' => 0, 'tax' => 0, 'total' => 0, 'count' => 0, 'currency' => shopper_currency()];
        }

        $context = $this->cartManager->calculate($cart);

        return [
            'subtotal' => $context->subtotal,
            'discount' => $context->discountTotal,
            'tax' => $context->taxInclusive ? 0 : $context->taxTotal,
            'total' => $context->total,
            'count' => (int) $cart->lines->sum('quantity'),
            'currency' => $cart->currency_code,
        ];
    }
}
