<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Shopper\Core\Enum\PaymentStatus;
use Shopper\OnePay\Actions\ConfirmOnePayPaymentAction;
use Shopper\OnePay\ConfirmationOutcome;
use Shopper\OnePay\Events\OnePayPaymentFailed;
use Shopper\OnePay\Events\OnePayPaymentSucceeded;
use Shopper\OnePay\OnePayDriver;
use Shopper\OnePay\OnePayResponse;
use Shopper\OnePay\OnePaySigner;
use Shopper\Payment\Enum\TransactionStatus;
use Shopper\Payment\Enum\TransactionType;
use Shopper\Payment\Facades\Payment;
use Shopper\Payment\Models\PaymentTransaction;

uses(Tests\OnePay\TestCase::class);

require_once __DIR__.'/helpers.php';

function onePayDriver(): OnePayDriver
{
    /** @var OnePayDriver */
    return Payment::driver('onepay');
}

describe('signature', function (): void {
    it('matches a reference HMAC-SHA256 computed outside PHP', function (): void {
        $parameters = [
            'vpc_Version' => '2', 'vpc_Command' => 'pay', 'vpc_Currency' => 'VND', 'vpc_Merchant' => 'ONEPAY',
            'vpc_AccessCode' => 'D67342C2', 'vpc_Locale' => 'vn', 'vpc_ReturnURL' => 'https://shop.test/onepay/return',
            'vpc_MerchTxnRef' => 'ORD-1-ABC123', 'vpc_OrderInfo' => 'ORD-1', 'vpc_Amount' => '54800000',
            'vpc_TicketNo' => '127.0.0.1', 'AgainLink' => 'https://shop.test', 'Title' => 'Shop', 'user_Note' => '',
            'vpc_SecureHashType' => 'SHA256',
        ];

        expect(OnePaySigner::stringToSign($parameters))
            ->toBe('vpc_AccessCode=D67342C2&vpc_Amount=54800000&vpc_Command=pay&vpc_Currency=VND&vpc_Locale=vn&vpc_MerchTxnRef=ORD-1-ABC123&vpc_Merchant=ONEPAY&vpc_OrderInfo=ORD-1&vpc_ReturnURL=https://shop.test/onepay/return&vpc_TicketNo=127.0.0.1&vpc_Version=2')
            ->and((new OnePaySigner('A3EFDFABA8653DF2342E8DAC29B51AF0'))->sign($parameters))
            ->toBe('464A5F821D7E026EC76FABB9D271811911EA01DAD1FD78FE906FA06F155AE291');
    });

    it('rejects tampered or missing hashes', function (): void {
        $signer = new OnePaySigner('A3EFDFABA8653DF2342E8DAC29B51AF0');
        $parameters = ['vpc_Amount' => '100', 'vpc_MerchTxnRef' => 'A'];
        $parameters['vpc_SecureHash'] = mb_strtolower($signer->sign($parameters));

        expect($signer->verify($parameters))->toBeTrue()
            ->and($signer->verify([...$parameters, 'vpc_Amount' => '999']))->toBeFalse()
            ->and($signer->verify(['vpc_Amount' => '100']))->toBeFalse();
    });
});

describe('driver', function (): void {
    it('builds a signed redirect with the amount in hundredths', function (): void {
        $result = onePayDriver()->initiatePayment(548_000, 'VND', ['order_number' => 'ORD-1', 'return_url' => 'https://shop.test/onepay/return', 'ip' => '1.2.3.4', 'locale' => 'vi']);

        parse_str((string) parse_url((string) $result->redirectUrl, PHP_URL_QUERY), $query);

        expect($result->success)->toBeTrue()
            ->and($result->redirectUrl)->toStartWith('https://mtf.onepay.vn/paygate/vpcpay.op?')
            ->and($query)->toMatchArray(['vpc_Amount' => '54800000', 'vpc_Currency' => 'VND', 'vpc_CardList' => 'DOMESTIC', 'vpc_OrderInfo' => 'ORD-1', 'vpc_TicketNo' => '1.2.3.4', 'vpc_Locale' => 'vn'])
            ->and($query['vpc_MerchTxnRef'])->toStartWith('ORD-1-')->toBe($result->reference)
            ->and(onePayDriver()->signer()->verify($query))->toBeTrue();
    });

    it('uses a new reference for every attempt', function (): void {
        $first = onePayDriver()->initiatePayment(1000, 'VND', ['order_number' => 'ORD-1', 'return_url' => 'x']);
        $second = onePayDriver()->initiatePayment(1000, 'VND', ['order_number' => 'ORD-1', 'return_url' => 'x']);

        expect($first->reference)->not->toBe($second->reference);
    });

    it('refuses other currencies and missing credentials', function (): void {
        expect(onePayDriver()->initiatePayment(10, 'USD')->success)->toBeFalse();

        $fresh = new OnePayDriver(['merchant_id' => null, 'access_code' => null, 'hash_secret' => null], ['payment' => 'x', 'query' => 'y']);
        expect($fresh->isConfigured())->toBeFalse()
            ->and($fresh->initiatePayment(10, 'VND')->success)->toBeFalse();
    });

    it('reads payment status with QueryDR', function (): void {
        Http::fake(['mtf.onepay.vn/*' => Http::response('vpc_DRExists=Y&vpc_TxnResponseCode=0&vpc_Amount=54800000&vpc_MerchTxnRef=REF-1')]);

        $result = onePayDriver()->retrievePayment('REF-1');

        expect($result->success)->toBeTrue()->and($result->amount)->toBe(548_000);
        Http::assertSent(fn (Request $request): bool => $request['vpc_Command'] === 'queryDR' && $request['vpc_User'] === 'op01' && isset($request['vpc_SecureHash']));
    });

    it('reports an unknown payment from QueryDR', function (): void {
        Http::fake(['mtf.onepay.vn/*' => Http::response('vpc_DRExists=N')]);

        expect(onePayDriver()->retrievePayment('REF-1')->success)->toBeFalse();
    });

    it('maps response codes to messages', function (): void {
        app()->setLocale('vi');

        expect(OnePayResponse::describe('0'))->toBe('Giao dịch thành công.')
            ->and(OnePayResponse::describe('99'))->toBe('Bạn đã hủy giao dịch.')
            ->and(OnePayResponse::describe('777'))->toContain('777');
    });
});

describe('confirmation', function (): void {
    beforeEach(function (): void {
        Event::fake([OnePayPaymentSucceeded::class, OnePayPaymentFailed::class]);
        $this->order = onePayOrder(548_000);
        $this->reference = onePayStart($this->order);
    });

    it('records the start of each attempt', function (): void {
        expect(PaymentTransaction::query()->where('type', TransactionType::Initiate)->sole()->reference)->toBe($this->reference);
    });

    it('marks the order paid once, however many times OnePay calls', function (): void {
        $result = onePayDriver()->handleWebhook(onePayCallback($this->reference, 548_000));
        $confirm = resolve(ConfirmOnePayPaymentAction::class);

        expect($confirm->execute($result))->toBe(ConfirmationOutcome::Paid)
            ->and($confirm->execute($result))->toBe(ConfirmationOutcome::AlreadyPaid)
            ->and($this->order->fresh()->payment_status)->toBe(PaymentStatus::Paid)
            ->and(PaymentTransaction::query()->where('type', TransactionType::Capture)->where('status', TransactionStatus::Success)->count())->toBe(1);

        Event::assertDispatchedTimes(OnePayPaymentSucceeded::class, 1);
    });

    it('does not mark paid when the amount differs', function (): void {
        $result = onePayDriver()->handleWebhook(onePayCallback($this->reference, 1_000));

        expect(resolve(ConfirmOnePayPaymentAction::class)->execute($result))->toBe(ConfirmationOutcome::AmountMismatch)
            ->and($this->order->fresh()->payment_status)->toBe(PaymentStatus::Pending);

        Event::assertDispatched(OnePayPaymentFailed::class);
    });

    it('records a declined payment', function (): void {
        $result = onePayDriver()->handleWebhook(onePayCallback($this->reference, 548_000, '1'));

        expect(resolve(ConfirmOnePayPaymentAction::class)->execute($result))->toBe(ConfirmationOutcome::Failed)
            ->and($this->order->fresh()->payment_status)->toBe(PaymentStatus::Pending)
            ->and(PaymentTransaction::query()->where('status', TransactionStatus::Failed)->sole()->notes)->not->toBeEmpty();

        Event::assertDispatched(OnePayPaymentFailed::class, fn (OnePayPaymentFailed $event): bool => $event->code === '1');
    });

    it('ignores unknown references and bad signatures', function (): void {
        $confirm = resolve(ConfirmOnePayPaymentAction::class);

        expect($confirm->execute(onePayDriver()->handleWebhook(onePayCallback('NOPE-123', 548_000))))->toBe(ConfirmationOutcome::UnknownReference)
            ->and($confirm->execute(onePayDriver()->handleWebhook([...onePayCallback($this->reference, 548_000), 'vpc_Amount' => '1'])))->toBe(ConfirmationOutcome::Invalid)
            ->and($this->order->fresh()->payment_status)->toBe(PaymentStatus::Pending);
    });
});

describe('endpoints', function (): void {
    beforeEach(function (): void {
        $this->order = onePayOrder(548_000);
        $this->reference = onePayStart($this->order);
    });

    it('confirms through the IPN in OnePay\'s answer format, without CSRF', function (): void {
        $this->post(route('shopper.onepay.ipn'), onePayCallback($this->reference, 548_000))
            ->assertOk()
            ->assertSeeText('responsecode=1&desc=confirm-success', false);

        $this->get(route('shopper.onepay.ipn', onePayCallback($this->reference, 548_000)))->assertSeeText('responsecode=1', false);

        expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
    });

    it('asks OnePay to resend an IPN with a bad signature', function (): void {
        $this->post(route('shopper.onepay.ipn'), [...onePayCallback($this->reference, 548_000), 'vpc_SecureHash' => 'BAD'])
            ->assertSeeText('responsecode=0', false);

        expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::Pending);
    });

    it('does not trust a browser return that OnePay does not confirm', function (): void {
        Http::fake(['mtf.onepay.vn/*' => Http::response('vpc_DRExists=N')]);

        $this->get(route('shopper.onepay.return', onePayCallback($this->reference, 548_000)))->assertRedirect();

        expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::Pending);
    });

    it('marks paid on return when QueryDR confirms it', function (): void {
        Http::fake(['mtf.onepay.vn/*' => Http::response('vpc_DRExists=Y&vpc_TxnResponseCode=0&vpc_Amount=54800000&vpc_MerchTxnRef='.$this->reference)]);

        $this->get(route('shopper.onepay.return', onePayCallback($this->reference, 548_000)))->assertRedirect();

        expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
    });

    it('records a cancelled payment on return', function (): void {
        Http::fake();

        $this->get(route('shopper.onepay.return', onePayCallback($this->reference, 548_000, '99')))
            ->assertRedirect()
            ->assertSessionHas('status', __('shopper-onepay::responses.99'));

        Http::assertNothingSent();
        expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::Pending);
    });
});
