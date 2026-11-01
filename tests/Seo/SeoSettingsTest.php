<?php

declare(strict_types=1);

use Shopper\Core\Models\Setting;
use Shopper\Models\Role;
use Shopper\Seo\Registry\SeoableRegistry;
use Shopper\Seo\SeoAddon;
use Shopper\Seo\Settings\SeoSettings;

uses(Tests\Seo\TestCase::class);

describe(SeoSettings::class, function (): void {
    it('starts with sensible defaults for every registered type', function (): void {
        $settings = resolve(SeoSettings::class);

        expect($settings->get('general.separator'))->toBe('-')
            ->and($settings->get('titles.product.title'))->toBe('%title% %sep% %sitename%')
            ->and($settings->get('titles.post.schema_type'))->toBe('BlogPosting')
            ->and($settings->get('titles.page.schema_type'))->toBe('WebPage')
            ->and($settings->get('titles.search.noindex'))->toBeTrue()
            ->and($settings->get('robots.content'))->toContain('Disallow: /cpanel/');
    });

    it('saves a group, keeps the other defaults and refreshes the cache', function (): void {
        $settings = resolve(SeoSettings::class);
        $settings->get('general.site_name');

        $settings->save('general', ['site_name' => 'My Shop', 'separator' => '|']);

        expect(resolve(SeoSettings::class)->group('general'))
            ->site_name->toBe('My Shop')
            ->separator->toBe('|')
            ->home_title->toBe('%sitename% %sep% %tagline%')
            ->and(Setting::query()->where('key', 'seo_general')->exists())->toBeTrue();
    });

    it('rejects unknown groups', function (): void {
        resolve(SeoSettings::class)->save('nope', []);
    })->throws(InvalidArgumentException::class);
})->group('seo');

describe(SeoableRegistry::class, function (): void {
    it('knows the shop entities and the CMS content', function (): void {
        $registry = resolve(SeoableRegistry::class);

        expect(array_keys($registry->all()))->toContain('product', 'category', 'brand', 'collection', 'post', 'page', 'post_category', 'post_tag');
    });

    it('tells posts and pages apart', function (): void {
        $registry = resolve(SeoableRegistry::class);

        expect($registry->keyFor(Shopper\Cms\Models\Post::factory()->make()))->toBe('post')
            ->and($registry->keyFor(Shopper\Cms\Models\Post::factory()->page()->make()))->toBe('page');
    });
})->group('seo');

describe(SeoAddon::class, function (): void {
    it('is registered and its permissions are granted to administrators', function (): void {
        $admin = Role::query()->where('name', config('shopper.admin.roles.admin'))->firstOrFail();

        expect(shopper()->hasAddon('seo'))->toBeTrue()
            ->and($admin->hasPermissionTo('manage_seo'))->toBeTrue()
            ->and($admin->hasPermissionTo('manage_seo_scripts'))->toBeTrue();
    });
})->group('seo');
