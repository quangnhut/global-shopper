<?php

declare(strict_types=1);

use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;
use Shopper\Core\Models\Product;
use Shopper\Seo\SeoManager;
use Shopper\Seo\Settings\SeoSettings;
use Shopper\Seo\Support\SeoCache;
use Shopper\Seo\Support\SeoFields;

uses(Tests\Seo\TestCase::class);

beforeEach(function (): void {
    config(['app.url' => 'https://shop.test', 'shopper.cms.frontend_url' => null]);

    $this->settings = resolve(SeoSettings::class);
    $this->settings->save('general', ['site_name' => 'Acme', 'tagline' => 'Things people love', 'separator' => '|']);
    $this->manager = resolve(SeoManager::class);
});

describe('titles and descriptions', function (): void {
    it('uses the entry title first, then the type template', function (): void {
        $post = Post::factory()->published()->create(['title' => 'Hello world', 'excerpt' => 'A short summary.']);

        expect($this->manager->build($post))
            ->title->toBe('Hello world | Acme')
            ->description->toBe('A short summary.');

        $this->settings->save('titles', ['post' => ['title' => '%title% %sep% %category% %sep% %sitename%']]);

        expect($this->manager->build($post)->title)->toBe('Hello world | Acme');

        SeoFields::save($post, ['title' => 'Custom %sep% %sitename%', 'description' => 'Hand written.']);

        expect($this->manager->build($post))
            ->title->toBe('Custom | Acme')
            ->description->toBe('Hand written.');
    });

    it('falls back to the default template when the type has none', function (): void {
        $this->settings->save('titles', ['post' => ['title' => null, 'description' => null]]);
        $post = Post::factory()->published()->create(['title' => 'Plain', 'excerpt' => 'Summary.']);

        expect($this->manager->build($post))
            ->title->toBe('Plain | Acme')
            ->description->toBe('Summary.');
    });

    it('builds the description from the content, HTML stripped and cut at a word boundary', function (): void {
        $content = '<p>'.str_repeat('Lorem <strong>ipsum</strong> dolor sit amet, ', 20).'</p>';
        $post = Post::factory()->published()->create(['excerpt' => null, 'content' => $content]);

        $description = $this->manager->build($post)->description;

        expect($description)
            ->not->toContain('<')
            ->toEndWith('…')
            ->and(mb_strlen((string) $description))->toBeLessThanOrEqual(161)
            ->and(mb_substr((string) $description, -2, 1))->not->toBe(' ');
    });

    it('never puts protected content in the description', function (): void {
        $post = Post::factory()->published()->create(['excerpt' => 'Secret summary', 'password' => bcrypt('secret')]);

        expect($this->manager->build($post)->description)->toBeNull();
    });

    it('reads shop titles from their own columns and renders product variables', function (): void {
        $this->settings->save('titles', ['product' => ['title' => '%title% %sep% %brand% %sep% %sitename%']]);
        $product = Product::factory()->publish()->create(['name' => 'Desk lamp', 'brand_id' => null, 'description' => '<p>Warm light.</p>']);

        expect($this->manager->build($product))
            ->title->toBe('Desk lamp | Acme')
            ->description->toBe('Warm light.');

        $product->forceFill(['seo_title' => 'Best desk lamp', 'seo_description' => 'Bright and warm.'])->save();

        expect($this->manager->build($product))
            ->title->toBe('Best desk lamp')
            ->description->toBe('Bright and warm.');
    });

    it('makes paginated titles unique', function (): void {
        $category = PostCategory::factory()->create(['name' => 'News']);

        expect($this->manager->build($category, ['page' => 2, 'last_page' => 5])->title)->toBe('News | Acme | Page 2 of 5')
            ->and($this->manager->build($category, ['page' => 3])->title)->toBe('News | Acme | Page 3');
    });

    it('renders the home page, search and 404 templates', function (): void {
        expect($this->manager->build('/'))
            ->type->toBe('home')
            ->title->toBe('Acme | Things people love')
            ->description->toBe('Things people love')
            ->canonical->toBe('https://shop.test/')
            ->and($this->manager->build('/search', ['search' => 'lamps']))
            ->type->toBe('search')
            ->title->toBe('lamps | Acme')
            ->robots->toStartWith('noindex')
            ->and($this->manager->build('/missing', ['not_found' => true]))
            ->type->toBe('not_found')
            ->title->toBe('Page not found | Acme')
            ->canonical->toBeNull()
            ->jsonLd->toBeNull();
    });
});

describe('canonical URLs', function (): void {
    it('points to the permalink, or to the override', function (): void {
        $post = Post::factory()->published()->create(['slug' => 'hello']);
        $url = $this->manager->build($post)->canonical;

        expect($url)->toStartWith('https://shop.test/')->toContain('hello');

        SeoFields::save($post, ['canonical_url' => '/elsewhere']);

        expect($this->manager->build($post)->canonical)->toBe('https://shop.test/elsewhere');
    });

    it('keeps page ≥ 2 canonical to itself and links prev/next', function (): void {
        $category = PostCategory::factory()->create(['slug' => 'news']);
        $category->posts()->attach(Post::factory()->published()->create());
        $data = $this->manager->build($category, ['page' => 2, 'last_page' => 3]);

        expect($data->canonical)->toBe('https://shop.test/category/news/?page=2')
            ->and(collect($data->links)->pluck('href', 'rel')->all())->toMatchArray([
                'canonical' => 'https://shop.test/category/news/?page=2',
                'prev' => 'https://shop.test/category/news/',
                'next' => 'https://shop.test/category/news/?page=3',
            ]);
    });

    it('uses the storefront paths of shop entities', function (): void {
        config(['shopper.seo.frontend_url' => 'https://store.example']);
        $product = Product::factory()->publish()->create(['slug' => 'desk-lamp']);

        expect($this->manager->build($product)->canonical)->toBe('https://store.example/products/desk-lamp');
    });
});

describe('robots', function (): void {
    beforeEach(function (): void {
        $this->post = Post::factory()->published()->create();
        $this->post->categories()->attach(PostCategory::factory()->create());
    });

    it('indexes by default with large image previews', function (): void {
        expect($this->manager->build($this->post)->robots)
            ->toBe('index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1');
    });

    it('follows the type default unless the entry says otherwise', function (): void {
        $this->settings->save('titles', ['post' => ['noindex' => true]]);

        expect($this->manager->build($this->post))->robots->toStartWith('noindex, follow')->canonical->toBeNull();

        SeoFields::save($this->post, ['robots_index' => 'yes']);

        expect($this->manager->build($this->post)->robots)->toStartWith('index, follow');
    });

    it('lets an explicit entry value beat URL patterns', function (): void {
        $this->settings->save('indexing', ['noindex_paths' => [['pattern' => '/*', 'nofollow' => true]]]);

        expect($this->manager->build($this->post)->robots)->toBe('noindex, nofollow');

        SeoFields::save($this->post, ['robots_index' => 'yes', 'robots_follow' => 'yes']);

        expect($this->manager->build($this->post)->robots)->toStartWith('index, follow');
    });

    it('matches noindex URL patterns for paths without an entry', function (): void {
        expect($this->manager->build('/checkout/payment')->robots)->toBe('noindex, nofollow')
            ->and($this->manager->build('/about-us')->robots)->toStartWith('index, follow');
    });

    it('adds advanced flags', function (): void {
        SeoFields::save($this->post, ['robots_advanced' => ['noarchive', 'nosnippet'], 'max_image_preview' => 'standard']);

        expect($this->manager->build($this->post)->robots)
            ->toBe('index, follow, noarchive, nosnippet, max-image-preview:standard, max-video-preview:-1');
    });

    it('noindexes empty terms, paginated archives and drafts when asked', function (): void {
        $empty = PostCategory::factory()->create();
        $this->settings->save('indexing', ['noindex_empty_terms' => true, 'noindex_paginated' => true]);

        expect($this->manager->build($empty)->robots)->toStartWith('noindex')
            ->and($this->manager->build($this->post->categories->first(), ['page' => 2])->robots)->toStartWith('noindex')
            ->and($this->manager->build(Post::factory()->draft()->create())->robots)->toStartWith('noindex');
    });

    it('lets "discourage search engines" win over everything', function (): void {
        SeoFields::save($this->post, ['robots_index' => 'yes']);
        $this->settings->save('general', ['discourage_search_engines' => true]);

        expect($this->manager->build($this->post))->robots->toBe('noindex, nofollow')->canonical->toBeNull()
            ->and($this->manager->build('/')->robots)->toBe('noindex, nofollow');
    });
});

describe('paths and cache', function (): void {
    it('resolves CMS and shop paths to their entries, but not drafts', function (): void {
        $page = Post::factory()->page()->published()->create(['slug' => 'about', 'title' => 'About us']);
        Product::factory()->publish()->create(['slug' => 'desk-lamp', 'name' => 'Desk lamp']);
        Post::factory()->page()->draft()->create(['slug' => 'secret', 'title' => 'Secret page']);

        expect($this->manager->build('/about/'))->type->toBe('page')->title->toBe('About us | Acme')
            ->and($this->manager->build('/products/desk-lamp'))->type->toBe('product')->title->toBe('Desk lamp | Acme')
            ->and($this->manager->build('/secret/'))->type->toBe('path')->title->not->toContain('Secret');

        expect($page->exists)->toBeTrue();
    });

    it('caches results until an entry, its SEO meta or the settings change', function (): void {
        config(['shopper.seo.cache_ttl' => 600]);
        $post = Post::factory()->published()->create(['title' => 'First']);

        expect($this->manager->for($post)->title)->toBe('First | Acme');

        Post::query()->whereKey($post->id)->update(['title' => 'Changed without events']);
        expect($this->manager->for($post->refresh())->title)->toBe('First | Acme');

        $version = SeoCache::version();
        $post->update(['title' => 'Second']);
        expect(SeoCache::version())->toBeGreaterThan($version)
            ->and($this->manager->for($post)->title)->toBe('Second | Acme');

        SeoFields::save($post, ['title' => 'From meta']);
        expect($this->manager->for($post)->title)->toBe('From meta');

        $this->settings->save('general', ['site_name' => 'Renamed']);
        SeoFields::save($post, ['title' => null]);
        expect($this->manager->for($post)->title)->toBe('Second | Renamed');
    });
});
