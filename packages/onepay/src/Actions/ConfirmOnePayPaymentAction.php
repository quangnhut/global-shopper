<?php

declare(strict_types=1);

namespace Shopper\OnePay\Actions;

use Illuminate\Support\Facades\DB;
use Shopper\Core\Enum\PaymentStatus;
use Shopper\Core\Models\Contracts\Order as OrderContract;
use Shopper\Core\Models\Order;
use Shopper\OnePay\ConfirmationOutcome;
use Shopper\OnePay\Events\OnePayPaymentFailed;
use Shopper\OnePay\Events\OnePayPaymentSucceeded;
use Shopper\OnePay\OnePayDriver;
use Shopper\Payment\DataTransferObjects\WebhookResult;
use Shopper\Payment\Enum\TransactionStatus;
use Shopper\Payment\Enum\TransactionType;
use Shopper\Payment\Models\PaymentTransaction;

/**
 * Applies a signature-checked OnePay result to its order. Safe to run twice for the
 * same result (IPN retries, IPN and browser return arriving together).
 */
final class ConfirmOnePayPaymentAction
{
    public function execute(WebhookResult $result): ConfirmationOutcome
    {
        if ($result->reference === null || ! in_array($result->action, ['payment.succeeded', 'payment.failed'], true)) {
            return ConfirmationOutcome::Invalid;
        }

        $initiated = PaymentTransaction::query()
            ->where('driver', OnePayDriver::CODE)
            ->where('type', TransactionType::Initiate)
            ->where('reference', $result->reference)
            ->first();

        if ($initiated === null || $initiated->order_id === null) {
            return ConfirmationOutcome::UnknownReference;
        }

        return DB::transaction(function () use ($result, $initiated): ConfirmationOutcome {
            /** @var Order $order */
            $order = resolve(OrderContract::class)::query()->lockForUpdate()->findOrFail($initiated->order_id);

            $recorded = PaymentTransaction::query()
                ->where('order_id', $order->id)
                ->where('driver', OnePayDriver::CODE)
                ->where('reference', $result->reference)
                ->whereIn('type', [TransactionType::Capture, TransactionType::Authorize])
                ->exists();

            if ($order->payment_status === PaymentStatus::Paid || $recorded) {
                return $order->payment_status === PaymentStatus::Paid ? ConfirmationOutcome::AlreadyPaid : ConfirmationOutcome::Failed;
            }

            $message = (string) ($result->data['message'] ?? '');

            if ($result->action === 'payment.failed') {
                $this->record($order, $initiated, TransactionType::Authorize, TransactionStatus::Failed, $result, $message);

                DB::afterCommit(fn () => OnePayPaymentFailed::dispatch($order, (string) $result->reference, (string) ($result->data['code'] ?? ''), $message));

                return ConfirmationOutcome::Failed;
            }

            if ((int) $result->amount !== (int) $order->price_amount) {
                $note = __('shopper-onepay::messages.amount_mismatch', ['paid' => (int) $result->amount, 'expected' => (int) $order->price_amount]);
                $this->record($order, $initiated, TransactionType::Capture, TransactionStatus::Failed, $result, $note);

                DB::afterCommit(fn () => OnePayPaymentFailed::dispatch($order, (string) $result->reference, 'amount', $note));

                return ConfirmationOutcome::AmountMismatch;
            }

            $this->record($order, $initiated, TransactionType::Capture, TransactionStatus::Success, $result, $message);
            $order->update(['payment_status' => PaymentStatus::Paid]);

            DB::afterCommit(fn () => OnePayPaymentSucceeded::dispatch($order, (string) $result->reference));

            return ConfirmationOutcome::Paid;
        });
    }

    private function record(Order $order, PaymentTransaction $initiated, TransactionType $type, TransactionStatus $status, WebhookResult $result, string $notes): void
    {
        PaymentTransaction::query()->create([
            'order_id' => $order->id,
            'payment_method_id' => $initiated->payment_method_id,
            'driver' => OnePayDriver::CODE,
            'type' => $type,
            'status' => $status,
            'amount' => (int) $result->amount,
            'currency_code' => $order->currency_code,
            'reference' => $result->reference,
            'data' => $result->data,
            'notes' => $notes !== '' ? $notes : null,
        ]);
    }
}
