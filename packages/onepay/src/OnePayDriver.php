<?php

declare(strict_types=1);

namespace Shopper\OnePay;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Shopper\Payment\DataTransferObjects\PaymentResult;
use Shopper\Payment\DataTransferObjects\WebhookResult;
use Shopper\Payment\Drivers\Driver;

/**
 * OnePay domestic gateway: the customer is redirected to OnePay with a signed
 * URL, comes back to the return URL, and OnePay confirms server to server (IPN).
 */
final class OnePayDriver extends Driver
{
    public const string CODE = 'onepay';

    /**
     * @param  array{merchant_id: ?string, access_code: ?string, hash_secret: ?string, query_user?: ?string, query_password?: ?string}  $credentials
     * @param  array{payment: string, query: string}  $urls
     */
    public function __construct(
        private readonly array $credentials,
        private readonly array $urls,
        private readonly string $title = 'Shopper',
        private readonly ?string $cardList = 'DOMESTIC',
    ) {}

    public function code(): string
    {
        return self::CODE;
    }

    public function name(): string
    {
        return 'OnePay';
    }

    public function isConfigured(): bool
    {
        return filled($this->credentials['merchant_id'] ?? null)
            && filled($this->credentials['access_code'] ?? null)
            && filled($this->credentials['hash_secret'] ?? null);
    }

    public function supportsRefunds(): bool
    {
        return false;
    }

    public function signer(): OnePaySigner
    {
        return new OnePaySigner((string) $this->credentials['hash_secret']);
    }

    /**
     * Context: `order_number` (required), `return_url` (required), `ip`, `locale` (vi|en), `again_url`.
     *
     * @param  array<string, mixed>  $context
     */
    public function initiatePayment(int $amount, string $currency, array $context = []): PaymentResult
    {
        if (! $this->isConfigured()) {
            return PaymentResult::failed(__('shopper-onepay::messages.not_configured'));
        }

        if (mb_strtoupper($currency) !== 'VND') {
            return PaymentResult::failed(__('shopper-onepay::messages.currency'));
        }

        $orderNumber = (string) ($context['order_number'] ?? '');
        // One reference per attempt, so "pay again" never reuses a reference OnePay already saw.
        $reference = mb_substr($orderNumber, 0, 32).'-'.Str::upper(Str::random(6));

        $parameters = [
            'vpc_Version' => '2',
            'vpc_Command' => 'pay',
            'vpc_Currency' => 'VND',
            'vpc_Merchant' => (string) $this->credentials['merchant_id'],
            'vpc_AccessCode' => (string) $this->credentials['access_code'],
            'vpc_Locale' => ($context['locale'] ?? 'vi') === 'en' ? 'en' : 'vn',
            'vpc_ReturnURL' => (string) ($context['return_url'] ?? ''),
            'vpc_MerchTxnRef' => $reference,
            'vpc_OrderInfo' => mb_substr($orderNumber, 0, 32),
            'vpc_Amount' => (string) ($amount * 100),
            'vpc_TicketNo' => (string) ($context['ip'] ?? '127.0.0.1'),
            'vpc_CardList' => (string) $this->cardList,
            'AgainLink' => (string) ($context['again_url'] ?? $context['return_url'] ?? ''),
            'Title' => $this->title,
        ];

        $parameters['vpc_SecureHash'] = $this->signer()->sign($parameters);

        return new PaymentResult(
            success: true,
            status: 'pending',
            reference: $reference,
            redirectUrl: $this->urls['payment'].'?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986),
            amount: $amount,
        );
    }

    /**
     * QueryDR: asks OnePay what happened to a payment reference.
     */
    public function retrievePayment(string $reference): PaymentResult
    {
        if (blank($this->credentials['query_user'] ?? null) || blank($this->credentials['query_password'] ?? null)) {
            return PaymentResult::failed(__('shopper-onepay::messages.query_not_configured'), $reference);
        }

        $parameters = [
            'vpc_Command' => 'queryDR',
            'vpc_Version' => '2',
            'vpc_MerchTxnRef' => $reference,
            'vpc_Merchant' => (string) $this->credentials['merchant_id'],
            'vpc_AccessCode' => (string) $this->credentials['access_code'],
            'vpc_User' => (string) $this->credentials['query_user'],
            'vpc_Password' => (string) $this->credentials['query_password'],
        ];
        $parameters['vpc_SecureHash'] = $this->signer()->sign($parameters);

        try {
            $response = Http::asForm()->timeout(10)->post($this->urls['query'], $parameters);
        } catch (ConnectionException) {
            return PaymentResult::failed(__('shopper-onepay::messages.unreachable'), $reference);
        }

        parse_str(mb_trim($response->body()), $result);
        if (! $response->successful() || ($result['vpc_DRExists'] ?? 'N') !== 'Y') {
            return new PaymentResult(success: false, status: 'not_found', reference: $reference, message: __('shopper-onepay::messages.not_found'));
        }

        if (isset($result['vpc_SecureHash']) && ! $this->signer()->verify($result)) {
            return PaymentResult::failed(__('shopper-onepay::messages.bad_signature'), $reference);
        }

        $payment = OnePayResponse::fromParameters($result);

        return new PaymentResult(
            success: $payment->succeeded(),
            status: $payment->succeeded() ? 'paid' : 'failed',
            reference: $reference,
            amount: $payment->amount,
            message: $payment->message(),
            data: $payment->toArray(),
        );
    }

    /**
     * IPN and browser return: checks the signature and reads the result.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function handleWebhook(array $payload, array $headers = []): WebhookResult
    {
        if (! $this->isConfigured() || ! $this->signer()->verify($payload)) {
            return new WebhookResult(action: 'invalid');
        }

        $payment = OnePayResponse::fromParameters($payload);

        if ($payment->reference === '') {
            return new WebhookResult(action: 'invalid');
        }

        return new WebhookResult(
            action: $payment->succeeded() ? 'payment.succeeded' : 'payment.failed',
            reference: $payment->reference,
            amount: $payment->amount,
            data: [...$payment->toArray(), 'message' => $payment->message()],
        );
    }
}
