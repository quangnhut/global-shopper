<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Shopper\Cms\Models\Post;
use Shopper\Core\Models\Product;
use Shopper\Seo\Settings\SeoSettings;
use Shopper\Seo\Support\SeoFields;

uses(Tests\Seo\TestCase::class);

beforeEach(function (): void {
    config(['app.url' => 'https://shop.test', 'shopper.cms.frontend_url' => null]);

    $this->settings = resolve(SeoSettings::class);
    $this->settings->save('general', ['site_name' => 'Acme', 'separator' => '-']);
    $this->settings->save('social', ['twitter_handle' => '@acme', 'facebook_app_id' => '12345']);
});

function renderHead(mixed $for = null, array $context = []): string
{
    return Blade::render('<x-shopper-seo::head :for="$for" :context="$context" />', ['for' => $for, 'context' => $context]);
}

it('renders the head of a post', function (): void {
    $post = Post::factory()->published()->create(['title' => 'Hello world', 'slug' => 'hello-world', 'excerpt' => 'Short summary.']);

    $html = renderHead($post);

    expect($html)
        ->toContain('<title>Hello world - Acme</title>')
        ->toContain('<meta name="description" content="Short summary." />')
        ->toContain('<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />')
        ->toContain('<meta property="og:type" content="article" />')
        ->toContain('<meta property="og:title" content="Hello world - Acme" />')
        ->toContain('<meta property="og:site_name" content="Acme" />')
        ->toContain('<meta property="article:published_time"')
        ->toContain('<meta property="fb:app_id" content="12345" />')
        ->toContain('<meta name="twitter:card" content="summary_large_image" />')
        ->toContain('<meta name="twitter:site" content="@acme" />')
        ->toMatch('#<link rel="canonical" href="https://shop\.test/[^"]*hello-world/?" />#')
        ->toContain('<script type="application/ld+json">');
});

it('renders the head of a page, a product and the home page', function (): void {
    $page = Post::factory()->page()->published()->create(['title' => 'About us', 'slug' => 'about']);
    $product = Product::factory()->publish()->create(['name' => 'Desk lamp', 'slug' => 'desk-lamp']);

    expect(renderHead($page))
        ->toContain('<title>About us - Acme</title>')
        ->toContain('<meta property="og:type" content="website" />')
        ->toContain('<link rel="canonical" href="https://shop.test/about/" />')
        ->and(renderHead($product))
        ->toContain('<title>Desk lamp - Acme</title>')
        ->toContain('<meta property="og:type" content="product" />')
        ->toContain('<link rel="canonical" href="https://shop.test/products/desk-lamp" />')
        ->and(renderHead('/'))
        ->toContain('<title>Acme</title>')
        ->toContain('<link rel="canonical" href="https://shop.test/" />');
});

it('uses the current request path when no subject is given', function (): void {
    Route::get('/checkout', fn () => Blade::render('<x-shopper-seo::head />'));

    $this->get('/checkout')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow" />', false);
});

it('escapes titles, descriptions and JSON-LD', function (): void {
    $post = Post::factory()->published()->create([
        'title' => '</title><script>alert(1)</script>',
        'excerpt' => '"><img src=x onerror=alert(2)>',
    ]);
    SeoFields::save($post, ['og_title' => '"/><script>alert(3)</script>', 'breadcrumb_title' => '</script><script>alert(4)</script>']);

    $html = renderHead($post);

    expect($html)
        ->not->toContain('<script>alert')
        ->not->toContain('<img src=x')
        ->toContain('<title>alert(1) - Acme</title>')
        ->toContain('<meta property="og:title" content="&quot;/&gt;&lt;script&gt;alert(3)&lt;/script&gt;" />')
        ->toContain('\u003C/script\u003E\u003Cscript\u003Ealert(4)');

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    expect(json_decode($matches[1], true))->toBeArray();
});

it('outputs GTM in head and body only when the container ID is valid', function (): void {
    $this->settings->save('tracking', ['gtm_id' => 'GTM-ABC123']);

    expect(renderHead('/'))->toContain("'dataLayer','GTM-ABC123'")
        ->and(Blade::render('<x-shopper-seo::body-open />'))->toContain('ns.html?id=GTM-ABC123');

    $this->settings->save('tracking', ['gtm_id' => "GTM-1');alert('x"]);

    expect(renderHead('/'))->not->toContain('googletagmanager')->not->toContain('alert')
        ->and(Blade::render('<x-shopper-seo::body-open />'))->not->toContain('googletagmanager');
});

it('uses GA4 only without GTM', function (): void {
    $this->settings->save('tracking', ['ga4_id' => 'G-XYZ789']);

    expect(renderHead('/'))->toContain('gtag/js?id=G-XYZ789');

    $this->settings->save('tracking', ['gtm_id' => 'GTM-ABC123']);

    expect(renderHead('/'))->not->toContain('gtag/js');
});

it('outputs verification codes and icons', function (): void {
    $this->settings->save('tracking', ['verification_google' => 'abc-123_XYZ', 'verification_bing' => '<script>']);
    $this->settings->save('general', ['site_icons' => [16 => 'seo/icons/icon-16.png', 32 => 'seo/icons/icon-32.png', 180 => 'seo/icons/icon-180.png']]);

    expect(renderHead('/'))
        ->toContain('<meta name="google-site-verification" content="abc-123_XYZ" />')
        ->not->toContain('msvalidate.01')
        ->toMatch('#<link rel="icon" href="[^"]+icon-32\.png" sizes="32x32" type="image/png" />#')
        ->toMatch('#<link rel="apple-touch-icon" href="[^"]+icon-180\.png" sizes="180x180" />#')
        ->toMatch('#<link rel="manifest" href="[^"]+site\.webmanifest" />#');
});

it('outputs custom code only when set', function (): void {
    expect(renderHead('/'))->not->toContain('custom-')
        ->and(Blade::render('<x-shopper-seo::body-open />'))->toBe('')
        ->and(Blade::render('<x-shopper-seo::body-close />'))->toBe('');

    $this->settings->save('tracking', [
        'custom_head' => '<meta name="custom-head" content="1">',
        'custom_body_open' => '<div id="custom-open"></div>',
        'custom_body_close' => '<script id="custom-close"></script>',
    ]);

    expect(renderHead('/'))->toContain('<meta name="custom-head" content="1">')
        ->and(Blade::render('<x-shopper-seo::body-open />'))->toContain('<div id="custom-open"></div>')
        ->and(Blade::render('<x-shopper-seo::body-close />'))->toContain('<script id="custom-close"></script>');
});

it('adds X-Robots-Tag to non-HTML responses on noindex paths', function (): void {
    Route::middleware('shopper-seo.robots')->get('/account/invoice.pdf', fn () => response('pdf', 200, ['Content-Type' => 'application/pdf']));
    Route::middleware('shopper-seo.robots')->get('/files/guide.pdf', fn () => response('pdf', 200, ['Content-Type' => 'application/pdf']));

    $this->get('/account/invoice.pdf')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('/files/guide.pdf')->assertHeaderMissing('X-Robots-Tag');
});
