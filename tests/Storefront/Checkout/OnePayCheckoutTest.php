<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Shopper\Core\Enum\PaymentStatus;
use Shopper\Core\Models\Order;
use Shopper\Payment\Enum\TransactionType;
use Shopper\Payment\Models\PaymentTransaction;
use Shopper\Storefront\Checkout\OrderAccess;
use Shopper\Storefront\Jobs\SendTelegramOrderMessage;
use Shopper\Storefront\Livewire\Pages\CheckoutPage;
use Shopper\Storefront\Telegram\OrderTelegramMessage;

uses(Tests\Storefront\PublicRoutesTestCase::class);

require_once __DIR__.'/../Support/helpers.php';
require_once __DIR__.'/../../OnePay/helpers.php';

beforeEach(function (): void {
    storefrontVnd();
    Bus::fake([SendTelegramOrderMessage::class]);

    config()->set('shopper.payment.drivers.onepay.enabled', true);
    config()->set('shopper.payment.drivers.onepay.credentials', [
        'merchant_id' => 'ONEPAY', 'access_code' => 'D67342C2', 'hash_secret' => 'A3EFDFABA8653DF2342E8DAC29B51AF0',
        'query_user' => 'op01', 'query_password' => 'op123456',
    ]);
    config()->set('shopper.payment.drivers.onepay.urls', ['payment' => 'https://mtf.onepay.vn/paygate/vpcpay.op', 'query' => 'https://mtf.onepay.vn/msp/api/v1/vpc/invoices/queries']);

    $this->product = storefrontProduct(amount: 548_000);
    storefrontStock($this->product, 5);
});

function placeOnePayOrder(): Order
{
    storefrontSessionCart([[test()->product, 1]]);

    $component = Livewire::test(CheckoutPage::class)
        ->assertSee('Thẻ ATM nội địa (OnePay)')
        ->set('name', 'Lê Thị B')
        ->set('phone', '0987654321')
        ->set('paymentMethod', 'onepay')
        ->set('acceptTerms', true)
        ->call('placeOrder')
        ->assertHasNoErrors();

    $order = Order::query()->sole();
    test()->onePayRedirect = $component->effects['redirect'] ?? null;

    return $order;
}

function referenceOf(Order $order): string
{
    return (string) PaymentTransaction::query()->where('order_id', $order->id)->where('type', TransactionType::Initiate)->latest('id')->value('reference');
}

it('sends the customer to OnePay after placing the order', function (): void {
    $order = placeOnePayOrder();

    expect($this->onePayRedirect)->toStartWith('https://mtf.onepay.vn/paygate/vpcpay.op?')
        ->toContain('vpc_Amount=54800000')
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and(referenceOf($order))->toStartWith($order->number.'-');

    Bus::assertDispatched(SendTelegramOrderMessage::class, fn (SendTelegramOrderMessage $job): bool => $job->event === OrderTelegramMessage::NEW);
});

it('hides OnePay when it is switched off', function (): void {
    config()->set('shopper.payment.drivers.onepay.enabled', false);
    storefrontSessionCart([[$this->product, 1]]);

    Livewire::test(CheckoutPage::class)->assertDontSee('Thẻ ATM nội địa (OnePay)');
});

it('comes back to the order page as paid when OnePay confirms', function (): void {
    $order = placeOnePayOrder();
    $reference = referenceOf($order);
    Http::fake(['mtf.onepay.vn/*' => Http::response("vpc_DRExists=Y&vpc_TxnResponseCode=0&vpc_Amount=54800000&vpc_MerchTxnRef={$reference}")]);

    $this->get(route('shopper.onepay.return', onePayCallback($reference, 548_000)))
        ->assertRedirect(route('shopper.storefront.order.thanks', $order->number))
        ->assertSessionHas('status');

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
    Bus::assertDispatched(SendTelegramOrderMessage::class, fn (SendTelegramOrderMessage $job): bool => $job->event === OrderTelegramMessage::PAID);

    $this->get(route('shopper.storefront.order.thanks', $order->number))
        ->assertSee(__('shopper-storefront::checkout.onepay.paid', [], 'vi'))
        ->assertDontSee(__('shopper-storefront::checkout.onepay.pay_again', [], 'vi'));
});

it('offers to pay again after a cancelled payment', function (): void {
    $order = placeOnePayOrder();

    $this->get(route('shopper.onepay.return', onePayCallback(referenceOf($order), 548_000, '99')))
        ->assertRedirect(route('shopper.storefront.order.thanks', $order->number))
        ->assertSessionHasErrors('cart');

    Bus::assertDispatched(SendTelegramOrderMessage::class, fn (SendTelegramOrderMessage $job): bool => $job->event === OrderTelegramMessage::PAYMENT_FAILED);

    $this->get(route('shopper.storefront.order.thanks', $order->number))
        ->assertSee(route('shopper.storefront.onepay.pay', $order->number), false);

    $response = $this->post(route('shopper.storefront.onepay.pay', $order->number));

    expect($response->headers->get('Location'))->toStartWith('https://mtf.onepay.vn/')
        ->and(PaymentTransaction::query()->where('order_id', $order->id)->where('type', TransactionType::Initiate)->count())->toBe(2);
});

it('does not let another visitor pay for an order', function (): void {
    $order = placeOnePayOrder();
    session()->forget(OrderAccess::SESSION_KEY);

    $this->post(route('shopper.storefront.onepay.pay', $order->number))->assertNotFound();
});

it('marks the order paid from the IPN and alerts the shop', function (): void {
    $order = placeOnePayOrder();

    $this->post(route('shopper.onepay.ipn'), onePayCallback(referenceOf($order), 548_000))->assertSeeText('responsecode=1', false);

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
    Bus::assertDispatched(SendTelegramOrderMessage::class, fn (SendTelegramOrderMessage $job): bool => $job->event === OrderTelegramMessage::PAID);
});

it('answers in the storefront language when the customer comes back', function (): void {
    $order = placeOnePayOrder();

    $this->get(route('shopper.onepay.return', onePayCallback(referenceOf($order), 548_000, '99')))
        ->assertSessionHasErrors(['cart' => 'Bạn đã hủy giao dịch.']);
});
