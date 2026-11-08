<?php

declare(strict_types=1);

namespace Shopper\Storefront\Checkout;

use RuntimeException;

/**
 * The order could not be placed; the message is for the customer.
 */
final class CheckoutException extends RuntimeException
{
    /**
     * @param  array<int, string>  $notices  what changed in the cart since it was shown
     */
    public function __construct(string $message, public readonly array $notices = [])
    {
        parent::__construct($message);
    }
}
