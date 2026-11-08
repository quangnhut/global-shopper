<?php

declare(strict_types=1);

namespace Shopper\OnePay\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Shopper\Core\Models\Contracts\Order as OrderContract;
use Shopper\Core\Models\Order;
use Shopper\OnePay\Actions\ConfirmOnePayPaymentAction;
use Shopper\OnePay\ConfirmationOutcome;
use Shopper\OnePay\OnePay;
use Shopper\OnePay\OnePayDriver;
use Shopper\OnePay\OnePayResponse;
use Shopper\Payment\DataTransferObjects\WebhookResult;
use Shopper\Payment\Enum\TransactionType;
use Shopper\Payment\Facades\Payment;
use Shopper\Payment\Models\PaymentTransaction;

/**
 * The customer's browser coming back from OnePay. Its query string could be forged
 * or replayed, so a success is only applied after OnePay confirms it (QueryDR);
 * otherwise the IPN does it.
 */
final class ReturnController
{
    public function __invoke(Request $request, ConfirmOnePayPaymentAction $confirm): RedirectResponse
    {
        /** @var OnePayDriver $driver */
        $driver = Payment::driver(OnePayDriver::CODE);
        $result = $driver->handleWebhook($request->query());
        $order = $this->orderOf($result->reference);

        if ($result->action === 'invalid' || $order === null) {
            return OnePay::redirectAfterReturn($order, ConfirmationOutcome::Invalid, __('shopper-onepay::messages.invalid_return'));
        }

        $message = (string) ($result->data['message'] ?? '');

        if ($result->action === 'payment.failed') {
            $outcome = $confirm->execute($result);

            return OnePay::redirectAfterReturn($order->refresh(), $outcome, $message);
        }

        $check = $driver->retrievePayment((string) $result->reference);

        if (! $check->success) {
            // Not confirmed yet: the IPN will mark the order paid.
            return OnePay::redirectAfterReturn($order, ConfirmationOutcome::UnknownReference, __('shopper-onepay::messages.pending_confirmation'));
        }

        $outcome = $confirm->execute(new WebhookResult(
            action: 'payment.succeeded',
            reference: $check->reference,
            amount: $check->amount,
            data: [...$check->data, 'message' => $check->message ?? OnePayResponse::describe('0'), 'confirmed_by' => 'querydr'],
        ));

        return OnePay::redirectAfterReturn($order->refresh(), $outcome, $outcome === ConfirmationOutcome::AmountMismatch
            ? __('shopper-onepay::messages.amount_problem')
            : __('shopper-onepay::messages.paid'));
    }

    private function orderOf(?string $reference): ?Order
    {
        if ($reference === null) {
            return null;
        }

        $orderId = PaymentTransaction::query()
            ->where('driver', OnePayDriver::CODE)
            ->where('type', TransactionType::Initiate)
            ->where('reference', $reference)
            ->value('order_id');

        /** @var Order|null */
        return $orderId !== null ? resolve(OrderContract::class)::query()->find($orderId) : null;
    }
}
