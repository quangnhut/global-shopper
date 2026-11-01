<?php

declare(strict_types=1);

use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;
use Shopper\Core\Models\Product;
use Shopper\Seo\Settings\SeoSettings;

uses(Tests\Seo\TestCase::class);

beforeEach(function (): void {
    config(['app.url' => 'https://shop.test', 'shopper.cms.frontend_url' => null]);
    resolve(SeoSettings::class)->save('general', ['site_name' => 'Acme']);
});

it('returns the SEO data of a CMS path', function (): void {
    Post::factory()->page()->published()->create(['title' => 'About us', 'slug' => 'about']);

    $this->getJson('/api/seo?path=/about/')
        ->assertOk()
        ->assertJsonPath('data.type', 'page')
        ->assertJsonPath('data.title', 'About us - Acme')
        ->assertJsonPath('data.canonical', 'https://shop.test/about/')
        ->assertJsonPath('data.json_ld.@context', 'https://schema.org')
        ->assertJsonStructure(['data' => ['title', 'description', 'canonical', 'robots', 'meta', 'links', 'json_ld', 'gtm', 'ga4']]);
});

it('returns the SEO data of a shop path', function (): void {
    Product::factory()->publish()->create(['name' => 'Desk lamp', 'slug' => 'desk-lamp']);

    $this->getJson('/api/seo?path=/products/desk-lamp')
        ->assertOk()
        ->assertJsonPath('data.type', 'product')
        ->assertJsonPath('data.title', 'Desk lamp - Acme');
});

it('falls back to URL rules and site defaults for other paths', function (): void {
    Post::factory()->page()->draft()->create(['title' => 'Secret draft', 'slug' => 'secret']);

    $this->getJson('/api/seo?path=/checkout')
        ->assertOk()
        ->assertJsonPath('data.type', 'path')
        ->assertJsonPath('data.robots', 'noindex, nofollow');

    $this->getJson('/api/seo?path=/secret/')
        ->assertOk()
        ->assertJsonPath('data.type', 'path')
        ->assertJsonMissing(['title' => 'Secret draft - Acme']);
});

it('supports pagination, search and 404 context', function (): void {
    $category = PostCategory::factory()->create(['name' => 'News', 'slug' => 'news']);
    $category->posts()->attach(Post::factory()->published()->create());

    $this->getJson('/api/seo?path=/category/news/&page=2&last_page=4')
        ->assertJsonPath('data.title', 'News - Acme - Page 2 of 4')
        ->assertJsonPath('data.canonical', 'https://shop.test/category/news/?page=2');

    $this->getJson('/api/seo?path=/search&search=lamps')->assertJsonPath('data.type', 'search');
    $this->getJson('/api/seo?path=/nope&not_found=1')->assertJsonPath('data.type', 'not_found');
});

it('validates the path', function (): void {
    $this->getJson('/api/seo')->assertUnprocessable()->assertJsonValidationErrors('path');
});

it('embeds the SEO data in the CMS resolve endpoint', function (): void {
    Post::factory()->published()->create(['title' => 'Hello world', 'slug' => 'hello-world']);
    $category = PostCategory::factory()->create(['name' => 'News', 'slug' => 'news']);
    $category->posts()->attach(Post::factory()->published()->create());

    $post = $this->getJson('/api/cms/resolve?path=/hello-world/')->assertOk();

    expect($post->json('seo.title'))->toBe('Hello world - Acme')
        ->and($post->json('seo.json_ld.@graph'))->toBeArray();

    $this->getJson('/api/cms/resolve?path=/category/news/')
        ->assertOk()
        ->assertJsonPath('seo.title', 'News - Acme')
        ->assertJsonPath('seo.type', 'post_category');
});
