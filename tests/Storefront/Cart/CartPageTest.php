<?php

declare(strict_types=1);

use Livewire\Livewire;
use Shopper\Cart\Models\Cart;
use Shopper\Cart\Models\CartLine;
use Shopper\Storefront\Actions\MergeCartOnLoginAction;
use Shopper\Storefront\Livewire\Pages\CartPage;
use Tests\Core\Stubs\User;

uses(Tests\Storefront\PublicRoutesTestCase::class);

require_once __DIR__.'/../Support/helpers.php';

beforeEach(function (): void {
    storefrontVnd();
});

it('renders the empty cart page with noindex', function (): void {
    $this->get(route('shopper.storefront.cart'))
        ->assertOk()
        ->assertSee(__('shopper-storefront::cart.empty'))
        ->assertSee('noindex', false);

    expect(Cart::query()->count())->toBe(0);
});

it('lists the lines with totals', function (): void {
    $product = storefrontProduct(['name' => 'Áo thun'], amount: 199_000);
    storefrontStock($product, 10);
    storefrontSessionCart([[$product, 2]]);

    $this->get(route('shopper.storefront.cart'))
        ->assertOk()
        ->assertSee('Áo thun')
        ->assertSee('398.000');
});

it('changes quantities, capped at the stock', function (): void {
    $product = storefrontProduct();
    storefrontStock($product, 3);
    $cart = storefrontSessionCart([[$product, 1]]);
    $line = $cart->lines()->sole();

    Livewire::test(CartPage::class)
        ->call('increment', $line->id)
        ->assertDispatched('cart-updated')
        ->call('setQuantity', $line->id, 10)
        ->assertHasErrors('lines.'.$line->id);

    expect($line->fresh()->quantity)->toBe(3);

    Livewire::test(CartPage::class)->call('decrement', $line->id);

    expect($line->fresh()->quantity)->toBe(2);
});

it('removes a line, also with quantity zero', function (): void {
    $first = storefrontProduct();
    $second = storefrontProduct();
    storefrontStock($first, 3);
    storefrontStock($second, 3);
    $cart = storefrontSessionCart([[$first, 1], [$second, 1]]);
    [$lineA, $lineB] = $cart->lines()->orderBy('id')->get()->all();

    Livewire::test(CartPage::class)
        ->call('remove', $lineA->id)
        ->call('setQuantity', $lineB->id, 0);

    expect(CartLine::query()->count())->toBe(0);
});

it('cannot touch a line of another cart', function (): void {
    $product = storefrontProduct();
    storefrontStock($product, 3);
    $otherCart = Cart::query()->create(['currency_code' => 'VND']);
    $otherLine = resolve(Shopper\Cart\CartManager::class)->add($otherCart, $product, 1);
    storefrontSessionCart([[$product, 1]]);

    Livewire::test(CartPage::class)
        ->call('remove', $otherLine->id)
        ->call('setQuantity', $otherLine->id, 3)
        ->assertHasErrors('lines.'.$otherLine->id);

    expect($otherLine->fresh()->quantity)->toBe(1);
});

it('refreshes prices and drops products no longer sold when the page opens', function (): void {
    $kept = storefrontProduct(['name' => 'Mũ'], amount: 100_000);
    $hidden = storefrontProduct(['name' => 'Giày']);
    storefrontStock($kept, 5);
    storefrontStock($hidden, 5);
    $cart = storefrontSessionCart([[$kept, 1], [$hidden, 1]]);

    $kept->prices()->update(['amount' => 120_000]);
    $hidden->update(['is_visible' => false]);

    Livewire::test(CartPage::class)
        ->assertSet('notices', fn (array $notices): bool => count($notices) === 2)
        ->assertSee(shopper_money_format(120_000, 'VND'));

    expect($cart->lines()->count())->toBe(1)
        ->and((int) $cart->lines()->sole()->unit_price_amount)->toBe(120_000);
});

it('caps quantities above the stock left when the page opens', function (): void {
    $product = storefrontProduct();
    storefrontStock($product, 5);
    $cart = storefrontSessionCart([[$product, 4]]);
    storefrontStock($product, -3);

    Livewire::test(CartPage::class);

    expect($cart->lines()->sole()->quantity)->toBe(2);
});

it('keeps the guest cart when the customer signs in', function (): void {
    $product = storefrontProduct();
    storefrontStock($product, 5);
    $cart = storefrontSessionCart([[$product, 1]]);
    $user = User::factory()->create();

    resolve(MergeCartOnLoginAction::class)->execute($user);

    expect($cart->fresh()->customer_id)->toBe($user->id)
        ->and(session('shopper_cart'))->toBe($cart->id);
});

it('merges the guest cart into the cart saved on an earlier visit', function (): void {
    $product = storefrontProduct();
    $other = storefrontProduct();
    storefrontStock($product, 3);
    storefrontStock($other, 3);
    $user = User::factory()->create();
    $saved = Cart::query()->create(['currency_code' => 'VND', 'customer_id' => $user->id]);
    resolve(Shopper\Cart\CartManager::class)->add($saved, $product, 2);
    $guest = storefrontSessionCart([[$product, 2], [$other, 1]]);

    resolve(MergeCartOnLoginAction::class)->execute($user);

    expect(Cart::query()->find($guest->id))->toBeNull()
        ->and(session('shopper_cart'))->toBe($saved->id)
        ->and($saved->lines()->where('purchasable_id', $product->id)->value('quantity'))->toBe(3)
        ->and($saved->lines()->where('purchasable_id', $other->id)->value('quantity'))->toBe(1);
});

it('picks up the saved cart on sign-in when the guest had none', function (): void {
    $user = User::factory()->create(['password' => bcrypt('Matkhau!2026x')]);
    $saved = Cart::query()->create(['currency_code' => 'VND', 'customer_id' => $user->id]);

    $this->post(route('shopper.storefront.login.store'), ['email' => $user->email, 'password' => 'Matkhau!2026x'])->assertRedirect();

    expect(session('shopper_cart'))->toBe($saved->id);
});
