<?php

declare(strict_types=1);

use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;
use Shopper\Cms\Models\PostTag;
use Shopper\Core\Models\Brand;
use Shopper\Core\Models\Category;
use Shopper\Core\Models\Currency;
use Shopper\Core\Models\Product;
use Shopper\Core\Models\Review;
use Shopper\Seo\SeoManager;
use Shopper\Seo\Settings\SeoSettings;
use Shopper\Seo\Support\SeoFields;
use Tests\Core\Stubs\User;

uses(Tests\Seo\TestCase::class);

beforeEach(function (): void {
    config(['app.url' => 'https://shop.test', 'shopper.cms.frontend_url' => null]);

    $this->settings = resolve(SeoSettings::class);
    $this->settings->save('general', ['site_name' => 'Acme', 'tagline' => 'Good things']);
    $this->manager = resolve(SeoManager::class);
});

/**
 * @return array<string, array<string, mixed>> nodes keyed by `@type`
 */
function graphOf(array $jsonLd): array
{
    expect($jsonLd['@context'])->toBe('https://schema.org')
        ->and(json_decode((string) json_encode($jsonLd), true))->toBe($jsonLd);

    return collect($jsonLd['@graph'])->keyBy(fn (array $node): string => (string) $node['@type'])->all();
}

/**
 * Every `{"@id": …}` reference points to a node of the same graph.
 */
function assertReferencesResolve(array $jsonLd): void
{
    $ids = collect($jsonLd['@graph'])->pluck('@id')->filter()->all();
    $references = [];

    array_walk_recursive($jsonLd['@graph'], function (mixed $value, string|int $key) use (&$references): void {
        if ($key === '@id') {
            $references[] = $value;
        }
    });

    expect(array_diff(array_unique($references), $ids))->toBe([]);
}

it('describes the site on every page', function (): void {
    $this->settings->save('schema', ['search_url' => '/search?q={search_term_string}', 'email' => 'hello@acme.test']);
    $this->settings->save('social', ['twitter_handle' => 'acme', 'profiles' => ['https://instagram.com/acme']]);

    $jsonLd = $this->manager->build('/')->jsonLd;
    $graph = graphOf($jsonLd);

    expect($graph['WebSite'])
        ->toMatchArray(['@id' => 'https://shop.test/#website', 'name' => 'Acme', 'publisher' => ['@id' => 'https://shop.test/#organization']])
        ->and($graph['WebSite']['potentialAction'][0]['target']['urlTemplate'])->toBe('https://shop.test/search?q={search_term_string}')
        ->and($graph['Organization'])->toMatchArray(['name' => 'Acme', 'email' => 'hello@acme.test', 'url' => 'https://shop.test/'])
        ->and($graph['Organization']['sameAs'])->toBe(['https://x.com/acme', 'https://instagram.com/acme'])
        ->and($graph['WebPage']['about'])->toBe(['@id' => 'https://shop.test/#organization']);

    assertReferencesResolve($jsonLd);
});

it('uses a Person or a LocalBusiness subtype when configured', function (): void {
    $this->settings->save('schema', ['entity' => 'person', 'name' => 'Jane Doe']);

    $graph = graphOf($this->manager->build('/')->jsonLd);

    expect($graph)->toHaveKey('Person')->not->toHaveKey('Organization')
        ->and($graph['Person']['@id'])->toBe('https://shop.test/#person')
        ->and($graph['WebSite']['publisher'])->toBe(['@id' => 'https://shop.test/#person']);

    $this->settings->save('schema', ['entity' => 'organization', 'local_business_type' => 'Store', 'opening_hours' => ['Mo-Fr 09:00-18:00']]);

    expect(graphOf($this->manager->build('/')->jsonLd)['Store']['openingHours'])->toBe(['Mo-Fr 09:00-18:00']);
});

it('describes a post as an article with breadcrumbs', function (): void {
    $author = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $parent = PostCategory::factory()->create(['name' => 'Guides', 'slug' => 'guides']);
    $category = PostCategory::factory()->create(['name' => 'Lamps', 'slug' => 'lamps', 'parent_id' => $parent->id]);
    $post = Post::factory()->published()->create(['title' => 'Choosing a lamp', 'author_id' => $author->id, 'primary_category_id' => $category->id]);
    $post->categories()->attach($category);
    $post->tags()->attach(PostTag::factory()->create(['name' => 'lighting']));

    $jsonLd = $this->manager->build($post->refresh())->jsonLd;
    $graph = graphOf($jsonLd);

    expect($graph['BlogPosting'])
        ->toHaveKeys(['headline', 'datePublished', 'dateModified', 'author', 'publisher', 'mainEntityOfPage'])
        ->headline->toBe('Choosing a lamp')
        ->author->toBe(['@type' => 'Person', 'name' => 'Ada Lovelace'])
        ->articleSection->toBe(['Lamps'])
        ->keywords->toBe(['lighting'])
        ->and(collect($graph['BreadcrumbList']['itemListElement'])->pluck('name')->all())
        ->toBe(['Home', 'Guides', 'Lamps', 'Choosing a lamp'])
        ->and($graph['WebPage']['breadcrumb'])->toBe(['@id' => $graph['BreadcrumbList']['@id']]);

    assertReferencesResolve($jsonLd);
});

it('lets the Schema settings and the entry choose the article type', function (): void {
    $post = Post::factory()->published()->create();

    $this->settings->save('titles', ['post' => ['schema_type' => null]]);
    $this->settings->save('schema', ['article_type' => 'NewsArticle']);

    expect(graphOf($this->manager->build($post)->jsonLd))->toHaveKey('NewsArticle');

    SeoFields::save($post, ['schema_type' => 'Article']);

    expect(graphOf($this->manager->build($post)->jsonLd))->toHaveKey('Article')->not->toHaveKey('NewsArticle');
});

it('describes pages with their WebPage type override and parent breadcrumbs', function (): void {
    $parent = Post::factory()->page()->published()->create(['title' => 'Company', 'slug' => 'company']);
    $page = Post::factory()->page()->published()->create(['title' => 'Contact', 'slug' => 'contact', 'parent_id' => $parent->id]);
    SeoFields::save($page, ['schema_type' => 'ContactPage', 'breadcrumb_title' => 'Contact us']);

    $graph = graphOf($this->manager->build($page)->jsonLd);

    expect($graph)->toHaveKey('ContactPage')->not->toHaveKey('BlogPosting')->not->toHaveKey('WebPage')
        ->and(collect($graph['BreadcrumbList']['itemListElement'])->pluck('name')->all())->toBe(['Home', 'Company', 'Contact us']);
});

it('describes terms and shop taxonomies as collection pages', function (): void {
    $term = PostCategory::factory()->create(['name' => 'News']);
    $brand = Brand::factory()->create(['name' => 'Lumen', 'is_enabled' => true]);
    $category = Category::factory()->create(['name' => 'Lighting', 'is_enabled' => true]);

    foreach ([$term, $brand, $category] as $model) {
        $jsonLd = $this->manager->build($model)->jsonLd;

        expect(graphOf($jsonLd))->toHaveKeys(['CollectionPage', 'BreadcrumbList', 'WebSite', 'Organization']);
        assertReferencesResolve($jsonLd);
    }
});

it('describes a product with its offer, brand and approved reviews', function (): void {
    setupCurrencies();
    $brand = Brand::factory()->create(['name' => 'Lumen']);
    $product = Product::factory()->virtual()->create([
        'name' => 'Desk lamp', 'sku' => 'LAMP-1', 'barcode' => '4006381333931', 'brand_id' => $brand->id,
        'is_visible' => true, 'published_at' => now()->subDay(),
    ]);
    $product->prices()->create(['amount' => 4999, 'currency_id' => Currency::query()->where('code', 'USD')->value('id')]);

    foreach ([[5, true], [4, true], [1, false]] as [$rating, $approved]) {
        Review::factory()->create([
            'reviewrateable_type' => $product->getMorphClass(),
            'reviewrateable_id' => $product->id,
            'rating' => $rating,
            'approved' => $approved,
        ]);
    }

    $jsonLd = $this->manager->build($product->fresh())->jsonLd;
    $node = graphOf($jsonLd)['Product'];

    expect($node)
        ->toHaveKeys(['name', 'url', 'sku', 'gtin13', 'brand', 'offers', 'aggregateRating', 'review', 'mainEntityOfPage'])
        ->brand->toBe(['@type' => 'Brand', 'name' => 'Lumen'])
        ->and($node['offers'])->toMatchArray([
            '@type' => 'Offer',
            'price' => '49.99',
            'priceCurrency' => 'USD',
            'availability' => 'https://schema.org/InStock',
        ])
        ->and($node['aggregateRating'])->toMatchArray(['ratingValue' => 4.5, 'reviewCount' => 2])
        ->and($node['review'])->toHaveCount(2);

    assertReferencesResolve($jsonLd);
});

it('reports stock-tracked products without stock as out of stock', function (): void {
    setupCurrencies();
    $product = Product::factory()->standard()->create(['is_visible' => true, 'published_at' => now()->subDay(), 'allow_backorder' => false]);
    $product->prices()->create(['amount' => 1000, 'currency_id' => Currency::query()->where('code', 'USD')->value('id')]);

    expect(graphOf($this->manager->build($product->fresh())->jsonLd)['Product']['offers']['availability'])
        ->toBe('https://schema.org/OutOfStock');
});

it('merges the entry custom JSON-LD last', function (): void {
    $page = Post::factory()->page()->published()->create();
    SeoFields::save($page, ['schema_custom' => json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [['@type' => 'Question', 'name' => 'Why?']],
    ])], canEditScripts: true);

    $graph = $this->manager->build($page)->jsonLd['@graph'];

    expect(end($graph))->toMatchArray(['@type' => 'FAQPage'])->not->toHaveKey('@context');
});
