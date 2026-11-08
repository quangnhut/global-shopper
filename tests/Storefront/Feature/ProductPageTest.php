<?php

declare(strict_types=1);

use Shopper\Core\Models\Category;
use Shopper\Core\Models\ProductVariant;

uses(Tests\Storefront\PublicRoutesTestCase::class);

require_once __DIR__.'/../Support/helpers.php';

beforeEach(function (): void {
    storefrontVnd();
});

it('serves the product page at the SEO product path', function (): void {
    $product = storefrontProduct(['name' => 'iPhone 16 Pro', 'slug' => 'iphone-16-pro', 'sku' => 'SKU-1'], amount: 24_975_000, compareAmount: 27_475_000);
    storefrontStock($product, 5);

    $this->get('/products/iphone-16-pro')
        ->assertOk()
        ->assertSee('<h1 class="mt-1 text-2xl font-bold text-gray-900">iPhone 16 Pro</h1>', false)
        ->assertSee(shopper_money_format(24_975_000, 'VND'))
        ->assertSee(shopper_money_format(27_475_000, 'VND'))
        ->assertSee('SKU-1')
        ->assertSee(__('shopper-storefront::catalog.product.in_stock', [], 'vi'))
        ->assertSee('name="product_id" value="'.$product->id.'"', false)
        ->assertSee('name="quantity"', false);
});

it('answers 404 for unknown, hidden and unpriced products', function (): void {
    storefrontProduct(['slug' => 'hidden', 'is_visible' => false]);
    storefrontProduct(['slug' => 'unpriced'], amount: null);
    storefrontProduct(['slug' => 'later', 'published_at' => now()->addWeek()]);

    foreach (['nope', 'hidden', 'unpriced', 'later'] as $slug) {
        $this->get('/products/'.$slug)->assertNotFound();
    }
});

it('shows a sold out product without the buy form', function (): void {
    storefrontProduct(['slug' => 'sold-out']);

    $this->get('/products/sold-out')
        ->assertOk()
        ->assertSee(__('shopper-storefront::storefront.product.out_of_stock', [], 'vi'))
        ->assertDontSee('name="quantity"', false);
});

it('lets the customer choose a variant', function (): void {
    $product = storefrontVariantPricedProduct(990_000);
    $product->update(['slug' => 'ao-thun']);
    $first = $product->variants()->first();
    $first->update(['name' => 'Size M']);
    storefrontStock($first, 3);
    $second = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Size L']);
    $second->prices()->create(['amount' => 1_090_000, 'currency_id' => storefrontVnd()->id]);

    $this->get('/products/ao-thun')
        ->assertOk()
        ->assertSee('name="variant_id"', false)
        ->assertSee('Size M')
        ->assertSee('Size L')
        ->assertSee('Size L – '.shopper_money_format(1_090_000, 'VND').' ('.__('shopper-storefront::storefront.product.out_of_stock', [], 'vi').')')
        ->assertSee(__('shopper-storefront::catalog.product.from', [], 'vi'));
});

it('cleans the description HTML', function (): void {
    $product = storefrontProduct(['slug' => 'clean', 'description' => '<p>Màn hình <strong>6,3 inch</strong></p><script>alert(1)</script>']);
    storefrontStock($product, 1);

    $this->get('/products/clean')
        ->assertOk()
        ->assertSee('<strong>6,3 inch</strong>', false)
        ->assertDontSee('alert(1)', false);
});

it('shows the category breadcrumb from the root and related products', function (): void {
    $parent = Category::factory()->create(['name' => 'Electronics', 'is_enabled' => true, 'parent_id' => null]);
    $child = Category::factory()->create(['name' => 'Phones', 'is_enabled' => true, 'parent_id' => $parent->id]);
    $product = storefrontProduct(['name' => 'iPhone 16 Pro', 'slug' => 'iphone-16-pro']);
    $related = storefrontProduct(['name' => 'Galaxy S25']);
    $hidden = storefrontProduct(['name' => 'Hidden phone', 'is_visible' => false]);
    $product->categories()->attach($child->id);
    $related->categories()->attach($child->id);
    $hidden->categories()->attach($child->id);

    $this->get('/products/iphone-16-pro')
        ->assertOk()
        ->assertSeeInOrder(['Electronics', 'Phones', 'iPhone 16 Pro'])
        ->assertSee('/categories/'.$child->slug, false)
        ->assertSee(__('shopper-storefront::catalog.product.related', [], 'vi'))
        ->assertSee('Galaxy S25')
        ->assertDontSee('Hidden phone');
});

it('makes the product card links work', function (): void {
    $product = storefrontProduct(['name' => 'Tai nghe', 'slug' => 'tai-nghe']);
    storefrontStock($product, 2);

    $this->get(route('shopper.storefront.search', ['q' => 'Tai']))->assertSee('/products/tai-nghe', false);
    $this->get('/products/tai-nghe')->assertOk();
});
