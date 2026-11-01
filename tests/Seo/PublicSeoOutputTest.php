<?php

declare(strict_types=1);

use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;
use Shopper\Core\Models\Category;
use Shopper\Core\Models\Product;
use Shopper\Seo\SeoManager;
use Shopper\Seo\Settings\SeoSettings;

uses(Tests\Cms\PublicRoutesTestCase::class);

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'shopper.cms.frontend_url' => null]);
    resolve(SeoSettings::class)->save('general', ['site_name' => 'Acme']);
});

it('renders the SEO head of server-rendered posts', function (): void {
    Post::factory()->published()->create(['title' => 'Hello world', 'slug' => 'hello-world']);

    $this->get('/hello-world/')
        ->assertOk()
        ->assertSee('<title>Hello world - Acme</title>', false)
        ->assertSee('<link rel="canonical" href="http://localhost/hello-world/" />', false);
});

it('describes an archive as the term, not as the last post of its loop', function (): void {
    $category = PostCategory::factory()->create(['name' => 'News', 'slug' => 'news']);
    $category->posts()->attach(Post::factory()->published()->create(['title' => 'Last post in the loop']));

    $this->get('/category/news/')
        ->assertOk()
        ->assertSee('<title>News - Acme</title>', false)
        ->assertSee('<link rel="canonical" href="http://localhost/category/news/" />', false)
        ->assertDontSee('Last post in the loop - Acme', false)
        ->assertSee('"@type":"CollectionPage"', false);
});

it('answers 404 past the last archive page and links pages like the canonical', function (): void {
    $category = PostCategory::factory()->create(['slug' => 'news']);
    $category->posts()->attach(Post::factory()->published()->count(11)->create());

    $this->get('/category/news/?page=3')->assertNotFound();

    $this->get('/category/news/?page=2')
        ->assertOk()
        ->assertSee('href="/category/news/" rel="prev"', false)
        ->assertDontSee('page=1', false);
});

it('serves both slash spellings with the same canonical', function (): void {
    Post::factory()->published()->create(['slug' => 'hello-world']);

    $this->get('/hello-world')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="http://localhost/hello-world/" />', false);
});

it('never indexes a page past the last one', function (): void {
    $category = PostCategory::factory()->create(['name' => 'News']);
    $category->posts()->attach(Post::factory()->published()->create());

    expect(resolve(SeoManager::class)->build($category, ['page' => 4, 'last_page' => 1]))
        ->robots->toStartWith('noindex')
        ->title->toBe('News - Acme - Page 4');
});

it('counts subcategories when deciding whether a category is empty', function (): void {
    $parent = Category::factory()->create(['is_enabled' => true]);
    $child = Category::factory()->create(['is_enabled' => true, 'parent_id' => $parent->id]);
    Product::factory()->publish()->create()->categories()->attach($child);

    $parentTerm = PostCategory::factory()->create();
    $childTerm = PostCategory::factory()->create(['parent_id' => $parentTerm->id]);
    $childTerm->posts()->attach(Post::factory()->published()->create());

    expect(resolve(SeoManager::class)->build($parent)->robots)->toStartWith('index')
        ->and(resolve(SeoManager::class)->build($parentTerm)->robots)->toStartWith('index');
});

it('lists the home page in the sitemap and dates term archives', function (): void {
    $category = PostCategory::factory()->create(['slug' => 'news']);
    $post = Post::factory()->published()->create();
    $category->posts()->attach($post);

    expect($this->get('/sitemap.xml')->getContent())->toContain('http://localhost/sitemap-home-1.xml')
        ->and($this->get('/sitemap-home-1.xml')->assertOk()->getContent())->toContain('<loc>http://localhost/</loc>')
        ->and($this->get('/sitemap-post_category-1.xml')->getContent())->toContain('<lastmod>'.$post->updated_at->toAtomString().'</lastmod>');
});
