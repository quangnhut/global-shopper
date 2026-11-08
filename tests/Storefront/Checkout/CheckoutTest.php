<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Shopper\Core\Enum\PaymentStatus;
use Shopper\Core\Enum\ShippingStatus;
use Shopper\Core\Models\Order;
use Shopper\Core\Models\PaymentMethod;
use Shopper\Storefront\Checkout\OrderAccess;
use Shopper\Storefront\Http\Middleware\SetStorefrontLocale;
use Shopper\Storefront\Jobs\SendTelegramOrderMessage;
use Shopper\Storefront\Livewire\Pages\CheckoutPage;
use Shopper\Storefront\Settings\StorefrontSettings;
use Tests\Core\Stubs\User;

uses(Tests\Storefront\PublicRoutesTestCase::class);

require_once __DIR__.'/../Support/helpers.php';

beforeEach(function (): void {
    storefrontVnd();
    Bus::fake([SendTelegramOrderMessage::class]);

    $this->product = storefrontProduct(['name' => 'Áo thun'], amount: 199_000);
    storefrontStock($this->product, 10);
});

function checkoutForm(array $overrides = []): \Livewire\Features\SupportTesting\Testable
{
    $component = Livewire::test(CheckoutPage::class);

    foreach ([
        'name' => 'Nguyễn Văn A',
        'phone' => '0901 234 567',
        'email' => 'A@Example.com',
        'note' => 'Chiều thứ 7',
        'paymentMethod' => 'office',
        'acceptTerms' => true,
        ...$overrides,
    ] as $field => $value) {
        $component->set($field, $value);
    }

    return $component;
}

it('sends an empty cart back to the cart page', function (): void {
    Livewire::test(CheckoutPage::class)->assertRedirect(route('shopper.storefront.cart'));
});

it('renders the checkout page with noindex and the pickup box', function (): void {
    storefrontSessionCart([[$this->product, 2]]);

    $this->get(route('shopper.storefront.checkout'))
        ->assertOk()
        ->assertSee('noindex', false)
        ->assertSee(__('shopper-storefront::checkout.pickup.title', [], 'vi'))
        ->assertSee('Thanh toán tại văn phòng')
        ->assertSee('Chuyển khoản ngân hàng (VietQR)')
        ->assertDontSee('Thẻ ATM nội địa (OnePay)');
});

it('offers only enabled methods of the three', function (): void {
    storefrontSessionCart([[$this->product, 1]]);
    PaymentMethod::query()->where('slug', 'bank-transfer')->update(['is_enabled' => false]);
    PaymentMethod::query()->create(['title' => 'PayPal', 'slug' => 'paypal', 'is_enabled' => true]);

    Livewire::test(CheckoutPage::class)
        ->assertSet('paymentMethod', 'office')
        ->assertSee('Thanh toán tại văn phòng')
        ->assertDontSee('VietQR')
        ->assertDontSee('PayPal');
});

it('places a pickup order paid at the office', function (): void {
    $cart = storefrontSessionCart([[$this->product, 2]]);

    checkoutForm()
        ->call('placeOrder')
        ->assertHasNoErrors()
        ->assertDispatched('cart-updated')
        ->assertRedirect(route('shopper.storefront.order.thanks', Order::query()->sole()->number));

    $order = Order::query()->with('items')->sole();

    expect($order->price_amount)->toBe(398_000)
        ->and($order->currency_code)->toBe('VND')
        ->and($order->paymentMethod->slug)->toBe('office')
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->shipping_status)->toBe(ShippingStatus::Unfulfilled)
        ->and($order->shipping_option_id)->toBeNull()
        ->and($order->shipping_address_id)->toBeNull()
        ->and($order->customer_id)->toBeNull()
        ->and($order->notes)->toBe('Chiều thứ 7')
        ->and($order->metadata['contact'])->toBe(['name' => 'Nguyễn Văn A', 'phone' => '0901234567', 'email' => 'a@example.com'])
        ->and($order->metadata['pickup'])->toBeTrue()
        ->and($order->metadata['cart_id'])->toBe($cart->id)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->quantity)->toBe(2)
        ->and($cart->fresh()->isCompleted())->toBeTrue()
        ->and(session('shopper_cart'))->toBeNull()
        ->and(session(OrderAccess::SESSION_KEY))->toBe([$order->id])
        ->and($this->product->fresh()->getStock())->toBe(8);

    Bus::assertDispatched(SendTelegramOrderMessage::class, fn (SendTelegramOrderMessage $job): bool => $job->orderId === $order->id);
});

it('lets the customer change quantities on the checkout page', function (): void {
    $cart = storefrontSessionCart([[$this->product, 1]]);
    $line = $cart->lines()->sole();

    checkoutForm()
        ->call('increment', $line->id)
        ->call('increment', $line->id)
        ->assertSee(shopper_money_format(597_000, 'VND'))
        ->call('placeOrder');

    expect(Order::query()->sole()->price_amount)->toBe(597_000);
});

it('normalises +84 phone numbers', function (): void {
    storefrontSessionCart([[$this->product, 1]]);

    checkoutForm(['phone' => '+84 912.345.678'])->call('placeOrder')->assertHasNoErrors();

    expect(Order::query()->sole()->metadata['contact']['phone'])->toBe('0912345678');
});

it('validates the form', function (array $overrides, string $field): void {
    storefrontSessionCart([[$this->product, 1]]);

    checkoutForm($overrides)->call('placeOrder')->assertHasErrors($field);

    expect(Order::query()->count())->toBe(0);
    Bus::assertNotDispatched(SendTelegramOrderMessage::class);
})->with([
    'missing name' => [['name' => ' '], 'name'],
    'bad phone' => [['phone' => '12345'], 'phone'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'terms not accepted' => [['acceptTerms' => false], 'acceptTerms'],
    'unknown method' => [['paymentMethod' => 'paypal'], 'paymentMethod'],
    'onepay without credentials' => [['paymentMethod' => 'onepay'], 'paymentMethod'],
]);

it('stops when a price changed since the cart was shown', function (): void {
    storefrontSessionCart([[$this->product, 1]]);
    $component = checkoutForm();

    $this->product->prices()->update(['amount' => 250_000]);

    $component->call('placeOrder')
        ->assertHasErrors('cart')
        ->assertSet('notices', fn (array $notices): bool => count($notices) === 1);

    expect(Order::query()->count())->toBe(0);

    $component->call('placeOrder')->assertHasNoErrors();

    expect(Order::query()->sole()->price_amount)->toBe(250_000);
});

it('stops when the stock ran out since the cart was shown', function (): void {
    $cart = storefrontSessionCart([[$this->product, 5]]);
    $component = checkoutForm();

    storefrontStock($this->product, -7);

    $component->call('placeOrder')->assertHasErrors('cart');

    expect(Order::query()->count())->toBe(0)
        ->and($cart->lines()->sole()->quantity)->toBe(3);
});

it('makes one order when "place order" is clicked twice', function (): void {
    storefrontSessionCart([[$this->product, 1]]);
    $component = checkoutForm();

    $component->call('placeOrder');
    $number = Order::query()->sole()->number;

    $component->call('placeOrder')->assertRedirect(route('shopper.storefront.order.thanks', $number));

    expect(Order::query()->count())->toBe(1);
    Bus::assertDispatchedTimes(SendTelegramOrderMessage::class, 1);
});

it('attaches the order to the signed-in customer and pre-fills the form', function (): void {
    $user = User::factory()->create(['first_name' => 'An', 'last_name' => 'Trần', 'email' => 'an@example.com']);
    $this->actingAs($user);
    storefrontSessionCart([[$this->product, 1]]);

    Livewire::test(CheckoutPage::class)
        ->assertSet('email', 'an@example.com')
        ->assertSet('name', fn (string $name): bool => str_contains($name, 'An'))
        ->set('phone', '0901234567')
        ->set('acceptTerms', true)
        ->call('placeOrder')
        ->assertHasNoErrors();

    expect(Order::query()->sole()->customer_id)->toBe($user->id);
});

it('shows the office instructions on the thank-you page', function (): void {
    storefrontSessionCart([[$this->product, 1]]);
    checkoutForm()->call('placeOrder');
    $order = Order::query()->sole();

    $this->get(route('shopper.storefront.order.thanks', $order->number))
        ->assertOk()
        ->assertSee('noindex', false)
        ->assertSee($order->number)
        ->assertSee('Áo thun')
        ->assertSee(__('shopper-storefront::checkout.office.instructions', ['number' => $order->number], 'vi'));
});

it('shows the VietQR code and bank details for a bank transfer', function (): void {
    resolve(StorefrontSettings::class)->save([
        'bank_bin' => '970436',
        'bank_name' => 'Vietcombank',
        'bank_account_number' => '0123 456 789',
        'bank_account_name' => 'CONG TY ABC',
    ]);
    storefrontSessionCart([[$this->product, 1]]);
    checkoutForm(['paymentMethod' => 'bank-transfer'])->call('placeOrder');
    $order = Order::query()->sole();

    $this->get(route('shopper.storefront.order.thanks', $order->number))
        ->assertOk()
        ->assertSee('https://img.vietqr.io/image/970436-0123456789-compact2.png?amount=199000&amp;addInfo='.rawurlencode($order->number), false)
        ->assertSee('Vietcombank')
        ->assertSee('CONG TY ABC');
});

it('hides the thank-you page from other visitors', function (): void {
    $order = Order::factory()->create(['number' => 'ORD-999']);

    $this->get(route('shopper.storefront.order.thanks', 'ORD-999'))->assertNotFound();
    $this->get(route('shopper.storefront.order.thanks', 'NOPE'))->assertNotFound();

    $owner = User::factory()->create();
    $order->update(['customer_id' => $owner->id]);

    $this->actingAs(User::factory()->create())->get(route('shopper.storefront.order.thanks', 'ORD-999'))->assertNotFound();
    $this->actingAs($owner)->get(route('shopper.storefront.order.thanks', 'ORD-999'))->assertOk();
});

it('keeps the storefront language on Livewire updates', function (): void {
    expect(Livewire::getPersistentMiddleware())->toContain(SetStorefrontLocale::class);
});
