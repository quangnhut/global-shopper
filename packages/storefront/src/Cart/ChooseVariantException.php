<?php

declare(strict_types=1);

namespace Shopper\Storefront\Cart;

/**
 * The product has options: the customer must pick one of `$variants` first.
 */
final class ChooseVariantException extends CartException
{
    /**
     * @param  array<int, array{id: int, name: string, price: string, in_stock: bool}>  $variants
     */
    public function __construct(public readonly string $productName, public readonly array $variants)
    {
        parent::__construct(__('shopper-storefront::cart.choose_variant', ['name' => $productName]));
    }
}
