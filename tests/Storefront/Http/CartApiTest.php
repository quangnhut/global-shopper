<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Shopper\Cart\Models\Cart;
use Shopper\Core\Models\Order;
use Shopper\Storefront\Jobs\SendTelegramOrderMessage;
use Shopper\Storefront\Settings\StorefrontSettings;

uses(Tests\Storefront\TestCase::class);

require_once __DIR__.'/../Support/helpers.php';

beforeEach(function (): void {
    storefrontVnd();
    Bus::fake([SendTelegramOrderMessage::class]);

    $this->product = storefrontProduct(['name' => 'Áo thun'], amount: 199_000);
    storefrontStock($this->product, 5);
});

function apiAdd(array $data, ?string $token = null): Illuminate\Testing\TestResponse
{
    return test()->withHeaders(array_filter(['X-Cart-Token' => $token]))->postJson(route('shopper.storefront.api.cart.lines.store'), $data);
}

it('creates a cart on the first add and returns its token', function (): void {
    $response = apiAdd(['product_id' => $this->product->id, 'quantity' => 2])
        ->assertCreated()
        ->assertJsonPath('data.lines.0.name', 'Áo thun')
        ->assertJsonPath('data.lines.0.quantity', 2)
        ->assertJsonPath('data.summary.total', 398_000);

    $token = $response->json('data.token');

    expect($token)->toBeString()->toHaveLength(48)
        ->and($response->headers->get('X-Cart-Token'))->toBe($token)
        ->and(Cart::query()->sole()->metadata['token'])->toBe($token);

    apiAdd(['product_id' => $this->product->id], $token)->assertJsonPath('data.lines.0.quantity', 3);

    expect(Cart::query()->count())->toBe(1);
});

it('reads, updates and removes lines of the token cart only', function (): void {
    $token = apiAdd(['product_id' => $this->product->id])->json('data.token');
    $lineId = Cart::query()->sole()->lines()->sole()->id;

    $this->withHeaders(['X-Cart-Token' => $token])->getJson(route('shopper.storefront.api.cart'))
        ->assertOk()->assertJsonPath('data.summary.count', 1);

    $this->withHeaders(['X-Cart-Token' => $token])->patchJson(route('shopper.storefront.api.cart.lines.update', $lineId), ['quantity' => 9])
        ->assertOk()
        ->assertJsonPath('data.lines.0.quantity', 5)
        ->assertJsonPath('message', __('shopper-storefront::cart.errors.only_left', ['quantity' => 5]));

    $this->withHeaders(['X-Cart-Token' => 'someone-else'])->deleteJson(route('shopper.storefront.api.cart.lines.destroy', $lineId))->assertNotFound();
    $this->deleteJson(route('shopper.storefront.api.cart.lines.destroy', $lineId))->assertNotFound();

    $this->withHeaders(['X-Cart-Token' => $token])->deleteJson(route('shopper.storefront.api.cart.lines.destroy', $lineId))
        ->assertOk()->assertJsonPath('data.lines', []);
});

it('returns an empty cart without a token, and creates nothing', function (): void {
    $this->getJson(route('shopper.storefront.api.cart'))->assertOk()->assertJsonPath('data.token', null)->assertJsonPath('data.lines', []);

    expect(Cart::query()->count())->toBe(0);
});

it('answers 409 with the variants to choose from', function (): void {
    $product = storefrontVariantPricedProduct();
    Shopper\Core\Models\ProductVariant::factory()->create(['product_id' => $product->id])->prices()->create(['amount' => 1000, 'currency_id' => storefrontVnd()->id]);

    apiAdd(['product_id' => $product->id])->assertStatus(409)->assertJsonCount(2, 'variants');
});

it('lists the checkout payment methods', function (): void {
    $this->getJson(route('shopper.storefront.api.checkout.payment-methods'))
        ->assertOk()
        ->assertJsonPath('data.0.kind', 'office')
        ->assertJsonPath('data.1.kind', 'bank_transfer')
        ->assertJsonCount(2, 'data');
});

it('places an order with VietQR details for a bank transfer', function (): void {
    resolve(StorefrontSettings::class)->save(['bank_bin' => '970436', 'bank_name' => 'VCB', 'bank_account_number' => '0123456789', 'bank_account_name' => 'CONG TY ABC']);
    $token = apiAdd(['product_id' => $this->product->id, 'quantity' => 2])->json('data.token');

    $response = $this->withHeaders(['X-Cart-Token' => $token])->postJson(route('shopper.storefront.api.checkout'), [
        'name' => 'Phạm C',
        'phone' => '+84 901 234 567',
        'payment_method' => 'bank-transfer',
        'accept_terms' => true,
    ])->assertCreated();

    $order = Order::query()->sole();

    $response->assertJsonPath('data.number', $order->number)
        ->assertJsonPath('data.total', 398_000)
        ->assertJsonPath('data.payment_kind', 'bank_transfer')
        ->assertJsonPath('data.bank_transfer.reference', $order->number)
        ->assertJsonPath('data.redirect_url', null);

    expect($response->json('data.bank_transfer.qr_url'))->toStartWith('https://img.vietqr.io/image/970436-0123456789-compact2.png?amount=398000')
        ->and($order->metadata['contact']['phone'])->toBe('0901234567')
        ->and(Cart::query()->sole()->isCompleted())->toBeTrue();

    Bus::assertDispatched(SendTelegramOrderMessage::class);

    $this->withHeaders(['X-Cart-Token' => $token])->postJson(route('shopper.storefront.api.checkout'), [
        'name' => 'Phạm C', 'phone' => '0901234567', 'payment_method' => 'office', 'accept_terms' => true,
    ])->assertUnprocessable();

    expect(Order::query()->count())->toBe(1);
});

it('validates the checkout', function (): void {
    $token = apiAdd(['product_id' => $this->product->id])->json('data.token');

    $this->withHeaders(['X-Cart-Token' => $token])->postJson(route('shopper.storefront.api.checkout'), ['payment_method' => 'paypal'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'phone', 'payment_method', 'accept_terms']);
});
