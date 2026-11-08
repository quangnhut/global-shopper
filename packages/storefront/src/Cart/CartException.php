<?php

declare(strict_types=1);

namespace Shopper\Storefront\Cart;

use RuntimeException;

/**
 * A cart problem the customer can read (out of stock, unavailable product…).
 */
class CartException extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self(__('shopper-storefront::cart.errors.unavailable'));
    }

    public static function outOfStock(string $name): self
    {
        return new self(__('shopper-storefront::cart.errors.out_of_stock', ['name' => $name]));
    }

    public static function lineNotFound(): self
    {
        return new LineNotFoundException(__('shopper-storefront::cart.errors.line_not_found'));
    }
}
