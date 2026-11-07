<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Core\Models\Product;
use Shopper\Storefront\Models\HomeSection;
use Shopper\Storefront\Support\SectionItems;

uses(Tests\Storefront\TestCase::class);

require_once __DIR__.'/helpers.php';

describe(SectionItems::class, function (): void {
    it('returns picked products in the admin order', function (): void {
        [$a, $b, $c] = [storefrontProduct(), storefrontProduct(), storefrontProduct()];
        $section = storefrontAttach(HomeSection::factory()->create(), [$c, $a, $b]);

        expect(storefrontResolvedIds($section))->toBe([$c->id, $a->id, $b->id]);
    });

    it('skips products that are hidden, unpublished or without a store-currency price', function (): void {
        $shown = storefrontProduct();
        $hidden = storefrontProduct(['is_visible' => false]);
        $future = storefrontProduct(['published_at' => now()->addWeek()]);
        $noPrice = storefrontProduct(amount: null);
        $variantPriced = storefrontVariantPricedProduct();

        $section = storefrontAttach(HomeSection::factory()->create(), [$hidden, $shown, $future, $noPrice, $variantPriced]);

        expect(storefrontResolvedIds($section))->toBe([$shown->id, $variantPriced->id]);
    });

    it('skips products whose only price is in another currency', function (): void {
        storefrontVnd();
        setupCurrencies(['USD', 'VND'], 'VND');
        $usdOnly = Product::factory()->publish()->create();
        $usdOnly->prices()->create(['amount' => 4999, 'currency_id' => Shopper\Core\Models\Currency::query()->where('code', 'USD')->value('id')]);

        $section = storefrontAttach(HomeSection::factory()->create(), [$usdOnly]);

        expect(storefrontResolvedIds($section))->toBe([]);
    });

    it('only shows published, public posts without a password', function (): void {
        $shown = storefrontPost();
        $draft = storefrontPost(['status' => PostStatus::Draft]);
        $private = storefrontPost(['status' => PostStatus::Private]);
        $locked = storefrontPost(['password' => 'secret']);
        $scheduled = storefrontPost(['status' => PostStatus::Scheduled, 'published_at' => now()->addDay()]);
        $page = storefrontPost(['type' => 'page']);

        $section = storefrontAttach(HomeSection::factory()->posts()->create(), [$draft, $private, $shown, $locked, $scheduled, $page]);

        expect(storefrontResolvedIds($section))->toBe([$shown->id]);
    });

    it('ignores items whose type does not match the section', function (): void {
        $product = storefrontProduct();
        $post = storefrontPost();

        $products = storefrontAttach(HomeSection::factory()->create(), [$post, $product]);
        $posts = storefrontAttach(HomeSection::factory()->posts()->create(), [$product, $post]);

        expect(storefrontResolvedIds($products))->toBe([$product->id])
            ->and(storefrontResolvedIds($posts))->toBe([$post->id]);
    });

    it('applies the section limit after skipping hidden items', function (): void {
        $hidden = storefrontProduct(['is_visible' => false]);
        [$a, $b, $c] = [storefrontProduct(), storefrontProduct(), storefrontProduct()];

        $section = storefrontAttach(HomeSection::factory()->create(['limit' => 2]), [$hidden, $a, $b, $c]);

        expect(storefrontResolvedIds($section))->toBe([$a->id, $b->id]);
    });

    it('loads all sections with one products query and one posts query', function (): void {
        $sections = collect(range(1, 4))->map(fn (): HomeSection => storefrontAttach(
            HomeSection::factory()->create(),
            [storefrontProduct(), storefrontProduct()],
        ));
        $sections->push(storefrontAttach(HomeSection::factory()->posts()->create(), [storefrontPost(), storefrontPost()]));

        $fresh = HomeSection::query()->with('items')->get();

        DB::enableQueryLog();
        $resolved = resolve(SectionItems::class)->resolve($fresh);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($queries)->toBeLessThanOrEqual(3)
            ->and(collect($resolved)->map->count()->sum())->toBe(10);
    });
});
