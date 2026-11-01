<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;
use Shopper\Core\Models\Product;
use Shopper\Seo\Jobs\GenerateSitemapsJob;
use Shopper\Seo\Settings\SeoSettings;
use Shopper\Seo\Support\SeoFields;

uses(Tests\Seo\TestCase::class);

beforeEach(function (): void {
    config(['app.url' => 'https://shop.test', 'shopper.cms.frontend_url' => null]);

    $this->settings = resolve(SeoSettings::class);
});

/**
 * @return array<int, string>
 */
function sitemapLocs(string $xml): array
{
    $document = simplexml_load_string($xml);
    $document->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');

    return array_map('strval', $document->xpath('//s:loc'));
}

it('lists one sitemap file per type with indexable entries', function (): void {
    Post::factory()->published()->create();
    Post::factory()->page()->published()->create();
    Product::factory()->publish()->create();

    $response = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertHeader('X-Robots-Tag', 'noindex, follow');

    expect($response->getContent())->not->toContain('xml-stylesheet')
        ->and(sitemapLocs($response->getContent()))->toBe([
            'https://shop.test/sitemap-home-1.xml',
            'https://shop.test/sitemap-product-1.xml',
            'https://shop.test/sitemap-post-1.xml',
            'https://shop.test/sitemap-page-1.xml',
        ]);
});

it('leaves out drafts, noindex and excluded entries', function (): void {
    $visible = Post::factory()->published()->create(['slug' => 'visible']);
    Post::factory()->draft()->create(['slug' => 'draft']);
    Post::factory()->published()->create(['slug' => 'protected', 'password' => bcrypt('secret')]);
    SeoFields::save(Post::factory()->published()->create(['slug' => 'hidden']), ['robots_index' => 'no']);
    SeoFields::save(Post::factory()->published()->create(['slug' => 'excluded']), ['exclude_from_sitemap' => true]);
    Product::factory()->create(['is_visible' => false, 'slug' => 'invisible']);

    $locs = sitemapLocs($this->get('/sitemap-post-1.xml')->assertOk()->getContent());

    expect($locs)->toHaveCount(1)
        ->and($locs[0])->toContain('visible')
        ->and($visible->exists)->toBeTrue();

    $this->get('/sitemap-product-1.xml')->assertNotFound();
});

it('respects type defaults, URL patterns and empty terms', function (): void {
    $category = PostCategory::factory()->create(['slug' => 'news']);
    $category->posts()->attach(Post::factory()->published()->create(['slug' => 'in-news']));
    PostCategory::factory()->create(['slug' => 'empty']);
    Post::factory()->page()->published()->create(['slug' => 'cart']);

    expect(sitemapLocs($this->get('/sitemap-post_category-1.xml')->getContent()))->toBe(['https://shop.test/category/news/']);

    $this->get('/sitemap-page-1.xml')->assertNotFound();

    $this->settings->save('titles', ['post' => ['noindex' => true]]);
    $this->get('/sitemap-post-1.xml')->assertNotFound();

    $this->settings->save('titles', ['post' => ['noindex' => false, 'sitemap' => false]]);
    expect(sitemapLocs($this->get('/sitemap.xml')->getContent()))->not->toContain('https://shop.test/sitemap-post-1.xml');
});

it('splits large types into several files', function (): void {
    config(['shopper.seo.sitemaps.per_page' => 2]);
    Post::factory()->published()->count(5)->create();

    expect(sitemapLocs($this->get('/sitemap.xml')->getContent()))->toBe([
        'https://shop.test/sitemap-home-1.xml',
        'https://shop.test/sitemap-post-1.xml',
        'https://shop.test/sitemap-post-2.xml',
        'https://shop.test/sitemap-post-3.xml',
    ])
        ->and(sitemapLocs($this->get('/sitemap-post-3.xml')->getContent()))->toHaveCount(1);

    $this->get('/sitemap-post-4.xml')->assertNotFound();
    $this->get('/sitemap-unknown-1.xml')->assertNotFound();
});

it('lists lastmod and images', function (): void {
    $post = Post::factory()->published()->create();

    $xml = $this->get('/sitemap-post-1.xml')->getContent();

    expect($xml)->toContain('<lastmod>'.$post->updated_at->toAtomString().'</lastmod>')
        ->toContain('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"');
});

it('rebuilds after a change, through a queued job when enabled', function (): void {
    config(['shopper.seo.cache_ttl' => 600]);
    $post = Post::factory()->published()->create(['slug' => 'first']);

    expect($this->get('/sitemap-post-1.xml')->getContent())->toContain('first');

    $post->update(['slug' => 'second']);

    expect($this->get('/sitemap-post-1.xml')->getContent())->toContain('second')->not->toContain('first');

    Queue::fake();
    config(['shopper.seo.sitemaps.queue' => true]);
    $post->update(['title' => 'Changed']);

    Queue::assertPushed(GenerateSitemapsJob::class);
});

it('serves an empty index and blocks robots while search engines are discouraged', function (): void {
    Post::factory()->published()->create();
    $this->settings->save('general', ['discourage_search_engines' => true]);

    expect(sitemapLocs($this->get('/sitemap.xml')->getContent()))->toBe([]);

    $this->get('/sitemap-post-1.xml')->assertNotFound();
    expect($this->get('/robots.txt')->assertOk()->getContent())->toBe("User-agent: *\nDisallow: /\n");
});

it('serves robots.txt with the sitemap line', function (): void {
    $this->settings->save('robots', ['content' => "User-agent: *\nDisallow: /private/"]);

    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    expect($this->get('/robots.txt')->getContent())
        ->toBe("User-agent: *\nDisallow: /private/\n\nSitemap: https://shop.test/sitemap.xml\n");
});

it('serves the optional XSL stylesheet', function (): void {
    config(['shopper.seo.sitemaps.stylesheet' => true]);

    expect($this->get('/sitemap.xml')->getContent())->toContain('<?xml-stylesheet type="text/xsl" href="/sitemap.xsl"?>');

    expect($this->get('/sitemap.xsl')->assertOk()->assertHeader('Content-Type', 'text/xsl; charset=UTF-8')->getContent())
        ->toContain('<xsl:stylesheet');
});

it('serves sitemaps and robots.txt under the API prefix with storefront URLs', function (): void {
    config(['shopper.seo.frontend_url' => 'https://store.example']);
    Post::factory()->published()->create();

    expect(sitemapLocs($this->get('/api/seo/sitemap.xml')->assertOk()->getContent()))->toBe(['https://store.example/sitemap-home-1.xml', 'https://store.example/sitemap-post-1.xml'])
        ->and($this->get('/api/seo/robots.txt')->getContent())->toContain('Sitemap: https://store.example/sitemap.xml');
});
