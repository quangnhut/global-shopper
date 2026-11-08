<?php

declare(strict_types=1);

namespace Shopper\OnePay\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Shopper\Core\Models\Order;

final class OnePayPaymentSucceeded
{
    use Dispatchable;

    public function __construct(public Order $order, public string $reference) {}
}
