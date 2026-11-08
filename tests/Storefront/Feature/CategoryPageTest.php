<?php

declare(strict_types=1);

use Shopper\Core\Models\Category;

uses(Tests\Storefront\PublicRoutesTestCase::class);

require_once __DIR__.'/../Support/helpers.php';

beforeEach(function (): void {
    storefrontVnd();

    $this->parent = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics', 'is_enabled' => true, 'parent_id' => null]);
    $this->child = Category::factory()->create(['name' => 'Laptops', 'slug' => 'laptops', 'is_enabled' => true, 'parent_id' => $this->parent->id]);
    // Child slugs get the parent's slug as a prefix (`electronics-laptops`).
    $this->childUrl = '/categories/'.$this->child->slug;
});

function inCategory(Category $category, string $name, int $amount, array $attributes = []): Shopper\Core\Models\Product
{
    $product = storefrontProduct(['name' => $name, ...$attributes], amount: $amount);
    $product->categories()->attach($category->id);

    return $product;
}

it('serves the category page at the SEO category path', function (): void {
    inCategory($this->child, 'MacBook Air', 27_000_000);

    $this->get($this->childUrl)
        ->assertOk()
        ->assertSee('<h1 class="text-xl font-bold text-gray-900">Laptops</h1>', false)
        ->assertSee('MacBook Air')
        ->assertSee('Electronics');
});

it('lists the products of sub-categories on the parent page', function (): void {
    inCategory($this->child, 'MacBook Air', 27_000_000);
    inCategory($this->parent, 'Sạc dự phòng', 500_000);

    $this->get('/categories/electronics')
        ->assertOk()
        ->assertSee('MacBook Air')
        ->assertSee('Sạc dự phòng')
        ->assertSee($this->childUrl, false);
});

it('hides products the storefront cannot show', function (): void {
    inCategory($this->child, 'Hidden laptop', 1_000, ['is_visible' => false]);
    inCategory($this->child, 'Unpriced laptop', 1_000)->prices()->delete();

    $this->get($this->childUrl)
        ->assertOk()
        ->assertDontSee('Hidden laptop')
        ->assertDontSee('Unpriced laptop')
        ->assertSee(__('shopper-storefront::catalog.empty', [], 'vi'));
});

it('sorts by price, including variant prices', function (string $sort, array $order): void {
    inCategory($this->child, 'Mid', 20_000_000);
    inCategory($this->child, 'Cheap', 10_000_000);
    $variantProduct = storefrontVariantPricedProduct(15_000_000);
    $variantProduct->update(['name' => 'Variant']);
    $variantProduct->categories()->attach($this->child->id);

    $this->get($this->childUrl.'?sort='.$sort)->assertOk()->assertSeeInOrder($order);
})->with([
    'low to high' => ['price_asc', ['Cheap', 'Variant', 'Mid']],
    'high to low' => ['price_desc', ['Mid', 'Variant', 'Cheap']],
    'name' => ['name', ['Cheap', 'Mid', 'Variant']],
]);

it('ignores an unknown sort', function (): void {
    inCategory($this->child, 'MacBook Air', 27_000_000);

    $this->get($this->childUrl.'?sort=drop-table')->assertOk()->assertSee('MacBook Air');
});

it('answers 404 for unknown, disabled or hidden-parent categories and pages past the end', function (): void {
    $this->get('/categories/nope')->assertNotFound();

    $this->parent->update(['is_enabled' => false]);
    $this->get('/categories/electronics')->assertNotFound();
    $this->get($this->childUrl)->assertNotFound();

    $this->parent->update(['is_enabled' => true]);
    $this->get($this->childUrl.'?page=5')->assertNotFound();
});

it('makes the header menu links work', function (): void {
    inCategory($this->child, 'MacBook Air', 27_000_000);

    $this->get('/')->assertOk()->assertSee('/categories/electronics', false);
    $this->get('/categories/electronics')->assertOk();
});
