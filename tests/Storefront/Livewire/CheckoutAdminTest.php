<?php

declare(strict_types=1);

use Livewire\Livewire;
use Shopper\Core\Models\Order;
use Shopper\Core\Models\PaymentMethod;
use Shopper\Storefront\Livewire\Pages\HeaderFooterSettings;
use Shopper\Storefront\Settings\StorefrontSettings;

uses(Tests\Storefront\TestCase::class);

require_once __DIR__.'/../Support/helpers.php';

beforeEach(function (): void {
    $this->configureStore();
    $this->actingAs($this->makeAdminUser());
});

it('saves the bank account and pickup note', function (): void {
    Livewire::test(HeaderFooterSettings::class)
        ->fillForm([
            'bank_name' => 'Vietcombank',
            'bank_bin' => '970436',
            'bank_account_number' => '0123 456 789',
            'bank_account_name' => 'CONG TY ABC',
            'pickup_note' => 'Tầng 3, quầy lễ tân',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(resolve(StorefrontSettings::class)->bankAccount())->toBe(['bin' => '970436', 'bank' => 'Vietcombank', 'number' => '0123456789', 'name' => 'CONG TY ABC'])
        ->and(resolve(StorefrontSettings::class)->get('pickup_note'))->toBe('Tầng 3, quầy lễ tân');
});

it('refuses a bank BIN that is not 6 digits', function (): void {
    Livewire::test(HeaderFooterSettings::class)
        ->fillForm(['bank_bin' => 'VCB'])
        ->call('save')
        ->assertHasFormErrors(['bank_bin']);
});

it('has no bank account until every part is filled in', function (): void {
    resolve(StorefrontSettings::class)->save(['bank_bin' => '970436', 'bank_account_number' => '0123']);

    expect(resolve(StorefrontSettings::class)->bankAccount())->toBeNull();
});

it('shows the storefront contact on the admin order page', function (): void {
    $method = PaymentMethod::query()->where('slug', 'office')->firstOrFail();
    $order = Order::factory()->create([
        'payment_method_id' => $method->id,
        'notes' => 'Chiều thứ 7',
        'metadata' => ['source' => 'storefront', 'contact' => ['name' => 'Nguyễn <i>A</i>', 'phone' => '0901234567']],
    ]);
    $other = Order::factory()->create();

    $this->get(route('shopper.orders.detail', $order))
        ->assertOk()
        ->assertSee(__('shopper-storefront::admin.order.title'))
        ->assertSee('Nguyễn &lt;i&gt;A&lt;/i&gt;', false)
        ->assertSee('tel:0901234567', false)
        ->assertSee('Chiều thứ 7');

    $this->get(route('shopper.orders.detail', $other))
        ->assertOk()
        ->assertDontSee(__('shopper-storefront::admin.order.title'));
});
