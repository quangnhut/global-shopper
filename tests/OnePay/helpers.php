<?php

declare(strict_types=1);

use Shopper\Core\Models\Order;
use Shopper\Core\Models\PaymentMethod;
use Shopper\OnePay\Actions\StartOnePayPaymentAction;
use Shopper\OnePay\OnePayDriver;
use Shopper\Payment\Facades\Payment;

if (! function_exists('onePayOrder')) {
    function onePayOrder(int $amount = 548_000): Order
    {
        $method = PaymentMethod::query()->firstOrCreate(['slug' => 'onepay'], ['title' => 'OnePay', 'driver' => 'onepay', 'is_enabled' => true]);

        return Order::factory()->create([
            'number' => 'ORD-'.random_int(1000, 9999),
            'price_amount' => $amount,
            'currency_code' => 'VND',
            'payment_method_id' => $method->id,
            'status' => Shopper\Core\Enum\OrderStatus::New,
            'payment_status' => Shopper\Core\Enum\PaymentStatus::Pending,
        ]);
    }

    /**
     * Starts an attempt and returns its OnePay reference (`vpc_MerchTxnRef`).
     */
    function onePayStart(Order $order): string
    {
        $url = resolve(StartOnePayPaymentAction::class)->execute($order, ['ip' => '127.0.0.1']);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return (string) $query['vpc_MerchTxnRef'];
    }

    /**
     * What OnePay sends back (return URL or IPN), signed with the test secret.
     *
     * @return array<string, string>
     */
    function onePayCallback(string $reference, int $amount, string $code = '0', array $overrides = []): array
    {
        $parameters = [
            'vpc_Command' => 'pay',
            'vpc_Locale' => 'vn',
            'vpc_CurrencyCode' => 'VND',
            'vpc_MerchTxnRef' => $reference,
            'vpc_Merchant' => 'ONEPAY',
            'vpc_OrderInfo' => 'ORD',
            'vpc_Amount' => (string) ($amount * 100),
            'vpc_TxnResponseCode' => $code,
            'vpc_TransactionNo' => '1234567',
            'vpc_Message' => 'Approved',
            ...$overrides,
        ];

        /** @var OnePayDriver $driver */
        $driver = Payment::driver('onepay');
        $parameters['vpc_SecureHash'] = $driver->signer()->sign($parameters);

        return $parameters;
    }
}
