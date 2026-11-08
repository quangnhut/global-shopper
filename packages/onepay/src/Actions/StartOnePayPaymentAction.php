<?php

declare(strict_types=1);

namespace Shopper\OnePay\Actions;

use Shopper\Core\Enum\PaymentStatus;
use Shopper\Core\Models\Order;
use Shopper\OnePay\OnePayException;
use Shopper\Payment\Services\PaymentProcessingService;

/**
 * Starts a OnePay attempt for an unpaid order and returns the URL to send the customer to.
 */
final readonly class StartOnePayPaymentAction
{
    public function __construct(private PaymentProcessingService $payments) {}

    /**
     * @param  array{ip?: ?string, locale?: ?string, again_url?: ?string}  $context
     */
    public function execute(Order $order, array $context = []): string
    {
        if ($order->payment_status === PaymentStatus::Paid || ! $order->isNotCancelled()) {
            throw new OnePayException(__('shopper-onepay::messages.not_payable'));
        }

        $result = $this->payments->initiate($order, [
            'return_url' => route('shopper.onepay.return'),
            'ip' => $context['ip'] ?? null,
            'locale' => $context['locale'] ?? app()->getLocale(),
            'again_url' => $context['again_url'] ?? null,
        ]);

        if (! $result->success || $result->redirectUrl === null) {
            throw new OnePayException($result->message ?? __('shopper-onepay::messages.unreachable'));
        }

        return $result->redirectUrl;
    }
}
