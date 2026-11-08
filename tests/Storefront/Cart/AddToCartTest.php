<?php

declare(strict_types=1);

use Shopper\Cart\Models\Cart;
use Shopper\Cart\Models\CartLine;
use Shopper\Core\Models\ProductVariant;

uses(Tests\Storefront\PublicRoutesTestCase::class);

require_once __DIR__.'/../Support/helpers.php';

beforeEach(function (): void {
    storefrontVnd();
});

function addToCart(array $data): Illuminate\Testing\TestResponse
{
    return test()->postJson(route('shopper.storefront.cart.lines.store'), $data);
}

it('creates the session cart on the first add and uses the database price', function (): void {
    $product = storefrontProduct(amount: 250_000);
    storefrontStock($product, 5);

    addToCart(['product_id' => $product->id, 'quantity' => 2, 'unit_price_amount' => 1])
        ->assertOk()
        ->assertJson(['count' => 2, 'cart_url' => route('shopper.storefront.cart')]);

    $line = CartLine::query()->sole();

    expect($line->quantity)->toBe(2)
        ->and((int) $line->unit_price_amount)->toBe(250_000)
        ->and(session('shopper_cart'))->toBe($line->cart_id);
});

it('adds to the same line when the product is added again', function (): void {
    $product = storefrontProduct();
    storefrontStock($product, 5);

    addToCart(['product_id' => $product->id]);
    addToCart(['product_id' => $product->id])->assertJson(['count' => 2]);

    expect(Cart::query()->count())->toBe(1)
        ->and(CartLine::query()->sole()->quantity)->toBe(2);
});

it('caps the quantity at the stock left', function (): void {
    $product = storefrontProduct();
    storefrontStock($product, 3);

    addToCart(['product_id' => $product->id, 'quantity' => 5])->assertOk()->assertJson(['count' => 3]);
    addToCart(['product_id' => $product->id])->assertUnprocessable();

    expect(CartLine::query()->sole()->quantity)->toBe(3);
});

it('refuses sold out, unpublished and unpriced products', function (Shopper\Core\Models\Product $product): void {
    addToCart(['product_id' => $product->id])->assertUnprocessable()->assertJsonStructure(['message']);

    expect(CartLine::query()->count())->toBe(0);
})->with([
    'sold out' => fn () => storefrontProduct(),
    'hidden' => fn () => tap(storefrontProduct(['is_visible' => false]), fn ($product) => storefrontStock($product, 5)),
    'no price' => fn () => tap(storefrontProduct(amount: null), fn ($product) => storefrontStock($product, 5)),
]);

it('asks which variant when the product has several', function (): void {
    $product = storefrontVariantPricedProduct(990_000);
    $second = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Đỏ']);
    $second->prices()->create(['amount' => 1_090_000, 'currency_id' => storefrontVnd()->id]);
    storefrontStock($second, 2);

    $response = addToCart(['product_id' => $product->id])->assertStatus(409);

    expect($response->json('variants'))->toHaveCount(2)
        ->and(collect($response->json('variants'))->firstWhere('id', $second->id))->toMatchArray(['name' => 'Đỏ', 'in_stock' => true]);

    addToCart(['product_id' => $product->id, 'variant_id' => $second->id])->assertOk();

    $line = CartLine::query()->sole();
    expect($line->purchasable_id)->toBe($second->id)
        ->and((int) $line->unit_price_amount)->toBe(1_090_000);
});

it('adds the only priced variant directly', function (): void {
    $product = storefrontVariantPricedProduct(990_000);
    $variant = $product->variants()->first();
    storefrontStock($variant, 4);

    addToCart(['product_id' => $product->id])->assertOk();

    expect(CartLine::query()->sole()->purchasable_id)->toBe($variant->id);
});

it('refuses a variant of another product', function (): void {
    $product = storefrontVariantPricedProduct();
    $other = storefrontVariantPricedProduct();

    addToCart(['product_id' => $product->id, 'variant_id' => $other->variants()->first()->id])->assertUnprocessable();
});

it('redirects back with a message without JavaScript', function (): void {
    $product = storefrontProduct();
    storefrontStock($product, 5);

    $this->from(route('shopper.storefront.home'))
        ->post(route('shopper.storefront.cart.lines.store'), ['product_id' => $product->id])
        ->assertRedirect(route('shopper.storefront.home'))
        ->assertSessionHas('status', __('shopper-storefront::cart.added'));
});

it('shows the add to cart button on product cards', function (): void {
    $product = storefrontProduct(['name' => 'Áo thun']);
    storefrontStock($product, 5);

    $this->get(route('shopper.storefront.search', ['q' => 'Áo']))
        ->assertOk()
        ->assertSee(route('shopper.storefront.cart.lines.store'), false)
        ->assertSee('name="product_id" value="'.$product->id.'"', false)
        ->assertSee('sfAddToCart', false);
});
