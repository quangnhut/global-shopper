<?php

declare(strict_types=1);

namespace Shopper\OnePay;

/**
 * A payment result sent by OnePay (browser return or IPN), already signature-checked.
 */
final readonly class OnePayResponse
{
    public function __construct(
        public string $reference,
        public string $code,
        public int $amount,
        public ?string $transactionNo,
        public string $orderInfo,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function fromParameters(array $parameters): self
    {
        return new self(
            reference: (string) ($parameters['vpc_MerchTxnRef'] ?? ''),
            code: (string) ($parameters['vpc_TxnResponseCode'] ?? ''),
            // OnePay amounts are in hundredths of the currency unit (VND × 100).
            amount: intdiv((int) ($parameters['vpc_Amount'] ?? 0), 100),
            transactionNo: isset($parameters['vpc_TransactionNo']) ? (string) $parameters['vpc_TransactionNo'] : null,
            orderInfo: (string) ($parameters['vpc_OrderInfo'] ?? ''),
        );
    }

    public static function describe(string $code): string
    {
        $key = "shopper-onepay::responses.{$code}";
        $message = __($key);

        return $message === $key ? __('shopper-onepay::responses.unknown', ['code' => $code]) : $message;
    }

    public function succeeded(): bool
    {
        return $this->code === '0';
    }

    public function message(): string
    {
        return self::describe($this->code);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'code' => $this->code,
            'amount' => $this->amount,
            'transaction_no' => $this->transactionNo,
            'order_info' => $this->orderInfo,
        ];
    }
}
