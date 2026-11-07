<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Shopper\Core\Models\Inventory;
use Shopper\Core\Models\Legal;
use Shopper\Core\Models\Setting;
use Shopper\Storefront\Actions\SaveHomeSectionAction;
use Shopper\Storefront\Enums\HomeSectionStyle;
use Shopper\Storefront\Models\HomeSection;
use Shopper\Storefront\Settings\StorefrontSettings;
use Shopper\Storefront\Support\StorefrontCache;

uses(Tests\Storefront\TestCase::class);

require_once __DIR__.'/../Support/helpers.php';

function storefrontHome(): array
{
    return test()->getJson(route('shopper.storefront.api.home'))->assertOk()->json();
}

describe('GET /api/storefront/home', function (): void {
    it('returns header, live sections in position order, and footer', function (): void {
        $product = storefrontProduct(['name' => 'MacBook Pro 14"'], amount: 49_975_000, compareAmount: 53_725_000);
        $post = storefrontPost(['title' => 'Mẹo chọn laptop']);

        storefrontAttach(HomeSection::factory()->create(['title' => 'Second', 'position' => 2]), [$product]);
        storefrontAttach(HomeSection::factory()->create(['title' => 'First', 'position' => 1, 'style' => HomeSectionStyle::Sale]), [$product]);
        storefrontAttach(HomeSection::factory()->posts()->create(['title' => 'Tin tức', 'position' => 3]), [$post]);
        storefrontAttach(HomeSection::factory()->disabled()->create(['title' => 'Off', 'position' => 0]), [$product]);
        storefrontAttach(HomeSection::factory()->create(['title' => 'Later', 'position' => 0, 'starts_at' => now()->addDay()]), [$product]);
        storefrontAttach(HomeSection::factory()->create(['title' => 'Empty', 'position' => 0]), [storefrontProduct(['is_visible' => false])]);

        $home = storefrontHome();

        expect(array_keys($home))->toBe(['header', 'sections', 'footer'])
            ->and(array_column($home['sections'], 'title'))->toBe(['First', 'Second', 'Tin tức'])
            ->and($home['sections'][0]['badge'])->toBe(__('shopper-storefront::sections.badges.sale'))
            ->and($home['sections'][0]['items'][0])->toMatchArray([
                'id' => $product->id,
                'name' => 'MacBook Pro 14"',
                'price' => ['amount' => 49_975_000, 'formatted' => shopper_money_format(49_975_000, 'VND')],
                'compare_price' => ['amount' => 53_725_000, 'formatted' => shopper_money_format(53_725_000, 'VND')],
                'discount_percent' => 7,
                'currency' => 'VND',
                'in_stock' => false,
            ])
            ->and($home['sections'][0]['items'][0]['url'])->toEndWith('/products/'.$product->slug)
            ->and($home['sections'][2]['items'][0])->toMatchArray(['id' => $post->id, 'title' => 'Mẹo chọn laptop'])
            ->and($home['sections'][2]['items'][0]['url'])->toContain($post->slug);
    });

    it('reports stock for simple products and products with variants', function (): void {
        $inventory = Inventory::factory()->create();
        $inStock = storefrontProduct();
        $inStock->mutateStock($inventory->id, 5);
        $variantProduct = storefrontVariantPricedProduct(990_000);
        $variantProduct->variants()->first()->mutateStock($inventory->id, 2);

        storefrontAttach(HomeSection::factory()->create(), [$inStock, $variantProduct, storefrontProduct()]);

        $items = storefrontHome()['sections'][0]['items'];

        expect(array_column($items, 'in_stock'))->toBe([true, true, false])
            ->and($items[1]['price']['amount'])->toBe(990_000);
    });

    it('shows the hot deal countdown only for hot deal sections', function (): void {
        $product = storefrontProduct();
        $ends = now()->addDays(2)->startOfMinute();
        storefrontAttach(HomeSection::factory()->create(['position' => 1, 'style' => HomeSectionStyle::HotDeal, 'ends_at' => $ends]), [$product]);
        storefrontAttach(HomeSection::factory()->create(['position' => 2, 'ends_at' => $ends]), [$product]);

        $sections = storefrontHome()['sections'];

        expect($sections[0]['ends_at'])->toBe($ends->toIso8601String())
            ->and($sections[1]['ends_at'])->toBeNull();
    });

    it('uses a fixed number of queries whatever the number of sections', function (): void {
        $count = function (): int {
            StorefrontCache::flush();
            DB::flushQueryLog();
            DB::enableQueryLog();
            storefrontHome();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        storefrontAttach(HomeSection::factory()->create(), [storefrontProduct(), storefrontVariantPricedProduct()]);
        storefrontAttach(HomeSection::factory()->posts()->create(), [storefrontPost()]);
        $count(); // warm the settings caches the page shares with every request
        $few = $count();

        foreach (range(1, 5) as $i) {
            storefrontAttach(HomeSection::factory()->create(), [storefrontProduct(), storefrontProduct(), storefrontVariantPricedProduct()]);
            storefrontAttach(HomeSection::factory()->posts()->create(), [storefrontPost(), storefrontPost()]);
        }

        expect($count())->toBe($few);
    });

    it('serves the cached page until something it shows changes', function (): void {
        $product = storefrontProduct(['name' => 'Old name'], amount: 1_000_000);
        $section = storefrontAttach(HomeSection::factory()->create(['title' => 'Deal']), [$product]);

        storefrontHome();

        DB::enableQueryLog();
        storefrontHome();
        expect(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'storefront_home_sections') && ! str_contains($query['query'], 'starts_at'))->count())->toBe(0);
        DB::disableQueryLog();

        $product->prices()->first()->update(['amount' => 900_000]);
        expect(storefrontHome()['sections'][0]['items'][0]['price']['amount'])->toBe(900_000);

        $product->update(['name' => 'New name']);
        expect(storefrontHome()['sections'][0]['items'][0]['name'])->toBe('New name');

        resolve(SaveHomeSectionAction::class)->execute($section, ['title' => 'Deal hot'], [$product->id]);
        expect(storefrontHome()['sections'][0]['title'])->toBe('Deal hot');

        resolve(StorefrontSettings::class)->save(['hotline' => '1900 1234']);
        expect(storefrontHome()['header']['hotline'])->toBe('1900 1234');

        Setting::query()->updateOrCreate(['key' => 'name'], ['value' => 'Shop Global', 'locked' => true]);
        expect(storefrontHome()['header']['store_name'])->toBe('Shop Global');
    });

    it('never caches past the next scheduled start or end', function (): void {
        config(['shopper.storefront.cache_ttl' => 3600]);

        expect(StorefrontCache::ttl())->toBe(3600);

        HomeSection::factory()->create(['starts_at' => now()->addMinutes(10)]);
        HomeSection::factory()->create(['ends_at' => now()->addMinutes(5)]);
        HomeSection::factory()->disabled()->create(['starts_at' => now()->addMinute()]);

        expect(StorefrontCache::ttl())->toBeGreaterThan(290)->toBeLessThanOrEqual(300);
    });

    it('builds the header menu and the footer from the settings', function (): void {
        $laptops = Shopper\Core\Models\Category::factory()->create(['name' => 'Laptop', 'slug' => 'laptop', 'is_enabled' => true, 'parent_id' => null, 'position' => 2]);
        $phones = Shopper\Core\Models\Category::factory()->create(['name' => 'Điện thoại', 'slug' => 'dien-thoai', 'is_enabled' => true, 'parent_id' => null, 'position' => 1]);
        Shopper\Core\Models\Category::factory()->create(['name' => 'Gaming', 'slug' => 'gaming', 'is_enabled' => true, 'parent_id' => $laptops->id]);
        Shopper\Core\Models\Category::factory()->create(['name' => 'Hidden', 'slug' => 'hidden', 'is_enabled' => false, 'parent_id' => null]);

        $about = storefrontPost(['type' => 'page', 'title' => 'Giới thiệu']);
        $draft = storefrontPost(['type' => 'page', 'title' => 'Draft', 'status' => Shopper\Cms\Enums\PostStatus::Draft]);
        $refund = Legal::query()->create(['title' => 'Chính sách đổi trả', 'slug' => 'refund', 'is_enabled' => true]);

        foreach (['name' => 'Shop Global', 'legal_name' => 'Công ty TNHH Shop Global', 'email' => 'hello@shop.vn', 'phone_number' => '028 3822 1234', 'street_address' => '12 Nguyễn Huệ', 'city' => 'TP. HCM', 'facebook_link' => 'https://facebook.com/shop'] as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'locked' => true]);
        }

        resolve(StorefrontSettings::class)->save([
            'working_hours' => '8:00 – 21:00',
            'business_registration' => '0312345678',
            'bct_url' => 'http://online.gov.vn/Home/WebDetails/1',
            'footer_columns' => [[
                'title' => 'Về chúng tôi',
                'links' => [
                    ['type' => 'page', 'id' => $about->id, 'url' => null, 'label' => null],
                    ['type' => 'page', 'id' => $draft->id, 'url' => null, 'label' => null],
                    ['type' => 'legal', 'id' => $refund->id, 'url' => null, 'label' => 'Đổi trả'],
                    ['type' => 'url', 'id' => null, 'url' => '/lien-he', 'label' => 'Liên hệ'],
                ],
            ]],
            'extra_partners' => [['name' => 'GHN', 'group' => 'shipping', 'url' => 'https://ghn.vn', 'logo' => null]],
        ]);

        ['header' => $header, 'footer' => $footer] = storefrontHome();

        expect(array_column($header['categories'], 'label'))->toBe(['Điện thoại', 'Laptop'])
            ->and($header['categories'][1]['children'][0]['label'])->toBe('Gaming')
            ->and($header['categories'][1]['url'])->toEndWith('/categories/laptop')
            ->and($header['hotline'])->toBe('028 3822 1234')
            ->and($footer['company'])->toMatchArray([
                'name' => 'Công ty TNHH Shop Global',
                'address' => '12 Nguyễn Huệ, TP. HCM',
                'business_registration' => '0312345678',
                'email' => 'hello@shop.vn',
                'working_hours' => '8:00 – 21:00',
            ])
            ->and(array_column($footer['columns'][0]['links'], 'label'))->toBe(['Giới thiệu', 'Đổi trả', 'Liên hệ'])
            ->and($footer['columns'][0]['links'][1]['url'])->toEndWith('/policies/'.$refund->slug)
            ->and($footer['columns'][0]['links'][2]['url'])->toEndWith('/lien-he')
            ->and($footer['badges'][0])->toMatchArray(['label' => 'Bộ Công Thương', 'url' => 'http://online.gov.vn/Home/WebDetails/1'])
            ->and(collect($footer['partners']['shipping'])->firstWhere('label', 'GHN'))->toMatchArray(['url' => 'https://ghn.vn'])
            ->and(collect($footer['partners']['shipping'])->every(fn (array $partner): bool => $partner['label'] === 'GHN' || isset($partner['image'])))->toBeTrue()
            ->and($footer['socials'][0]['url'])->toBe('https://facebook.com/shop');
    });
});

describe('GET /api/storefront/search', function (): void {
    it('finds showable products by name or SKU, every word must match', function (): void {
        $match = storefrontProduct(['name' => 'MacBook Pro 14"', 'sku' => 'MBP-14']);
        storefrontProduct(['name' => 'MacBook Air', 'sku' => 'MBA-13']);
        storefrontProduct(['name' => 'MacBook Pro hidden', 'is_visible' => false]);

        $this->getJson(route('shopper.storefront.api.search', ['q' => 'macbook pro']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.id', $match->id);

        $this->getJson(route('shopper.storefront.api.search', ['q' => 'mbp-14']))
            ->assertJsonPath('items.0.id', $match->id);
    });

    it('treats LIKE wildcards literally and validates the query', function (): void {
        storefrontProduct(['name' => 'Anything']);

        $this->getJson(route('shopper.storefront.api.search', ['q' => '%']))->assertJsonPath('total', 0);
        $this->getJson(route('shopper.storefront.api.search', ['q' => str_repeat('a', 101)]))->assertUnprocessable();
        $this->getJson(route('shopper.storefront.api.search', ['q' => 'x', 'per_page' => 500]))->assertUnprocessable();
    });
});
