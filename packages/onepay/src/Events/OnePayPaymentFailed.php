<?php

declare(strict_types=1);

namespace Shopper\OnePay\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Shopper\Core\Models\Order;

final class OnePayPaymentFailed
{
    use Dispatchable;

    public function __construct(public Order $order, public string $reference, public string $code, public string $message) {}
}
