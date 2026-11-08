<?php

declare(strict_types=1);

use Livewire\Livewire;
use Shopper\Core\Models\Legal;
use Shopper\Storefront\Enums\HomeSectionLayout;
use Shopper\Storefront\Enums\HomeSectionStyle;
use Shopper\Storefront\Livewire\Components\HeaderSearch;
use Shopper\Storefront\Livewire\Components\MiniCart;
use Shopper\Storefront\Models\HomeSection;
use Shopper\Storefront\Settings\StorefrontSettings;
use Tests\Core\Stubs\User;

uses(Tests\Storefront\PublicRoutesTestCase::class);

require_once __DIR__.'/../Support/helpers.php';

describe('home page', function (): void {
    it('renders the sections top to bottom with prices in VND, in Vietnamese', function (): void {
        $product = storefrontProduct(['name' => 'MacBook Pro 14"'], amount: 49_975_000, compareAmount: 53_725_000);
        storefrontAttach(HomeSection::factory()->create(['title' => 'Sản phẩm mới', 'position' => 2, 'style' => HomeSectionStyle::New]), [$product]);
        storefrontAttach(HomeSection::factory()->create(['title' => 'Deal hot', 'position' => 1, 'style' => HomeSectionStyle::HotDeal, 'layout' => HomeSectionLayout::Carousel, 'ends_at' => now()->addDay()]), [$product]);
        storefrontAttach(HomeSection::factory()->posts()->create(['title' => 'Tin tức', 'position' => 3]), [storefrontPost(['title' => 'Mẹo chọn laptop'])]);

        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="vi"', false)
            ->assertSeeInOrder(['Deal hot', 'Sản phẩm mới', 'Tin tức'])
            ->assertSee('MacBook Pro 14&quot;', false)
            ->assertSee('49.975.000')
            ->assertSee('-7%')
            ->assertSee('role="timer"', false)
            ->assertSee('Mẹo chọn laptop')
            ->assertSee('vendor/shopper-storefront/storefront.css', false);
    });

    it('escapes section titles, product names and header settings', function (): void {
        $product = storefrontProduct(['name' => '<img src=x onerror=alert(1)>']);
        storefrontAttach(HomeSection::factory()->create(['title' => '<script>alert(1)</script>']), [$product]);
        resolve(StorefrontSettings::class)->save(['announcement' => '<b>Sale</b>', 'hotline' => '1900 <i>1234</i>']);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x', false)
            ->assertDontSee('<b>Sale</b>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    });

    it('shows sign-in links to guests and the account menu to customers', function (): void {
        $this->get('/')
            ->assertSee(route('shopper.storefront.login'), false)
            ->assertSee(route('shopper.storefront.register'), false)
            ->assertDontSee(route('shopper.storefront.logout'), false);

        $this->actingAs(User::factory()->create(['first_name' => 'An']))
            ->get('/')
            ->assertSee('An')
            ->assertSee(route('shopper.storefront.logout'), false);
    });

    it('shows the menu categories, hotline, company details and policy links', function (): void {
        Shopper\Core\Models\Category::factory()->create(['name' => 'Laptop', 'slug' => 'laptop', 'is_enabled' => true, 'parent_id' => null]);
        $refund = Legal::query()->create(['title' => 'Chính sách đổi trả', 'slug' => 'doi-tra', 'content' => '<p>Đổi trả trong 7 ngày.</p>', 'is_enabled' => true]);
        resolve(StorefrontSettings::class)->save([
            'hotline' => '1900 1234',
            'business_registration' => '0312345678',
            'footer_columns' => [['title' => 'Chính sách', 'links' => [['type' => 'legal', 'id' => $refund->id, 'url' => null, 'label' => null]]]],
        ]);

        $this->get('/')
            ->assertSee('Laptop')
            ->assertSee('tel:19001234', false)
            ->assertSee('0312345678')
            ->assertSee(route('shopper.storefront.legal', 'doi-tra'), false);
    });
});

describe('policy pages', function (): void {
    it('shows enabled policies with sanitized content and hides disabled ones', function (): void {
        Legal::query()->create(['title' => 'Đổi trả', 'slug' => 'doi-tra', 'content' => '<p>Trong 7 ngày.</p><script>alert(1)</script>', 'is_enabled' => true]);
        Legal::query()->create(['title' => 'Draft', 'slug' => 'draft-policy', 'content' => 'x', 'is_enabled' => false]);

        $this->get(route('shopper.storefront.legal', 'doi-tra'))
            ->assertOk()
            ->assertSee('Trong 7 ngày.')
            ->assertDontSee('<script>alert(1)</script>', false);

        $this->get(route('shopper.storefront.legal', 'draft-policy'))->assertNotFound();
        $this->get(route('shopper.storefront.legal', 'missing'))->assertNotFound();
    });
});

describe('search', function (): void {
    it('lists matching products and marks the page noindex', function (): void {
        storefrontProduct(['name' => 'MacBook Air 13']);
        storefrontProduct(['name' => 'iPhone 17']);

        $this->get(route('shopper.storefront.search', ['q' => 'macbook']))
            ->assertOk()
            ->assertSee('MacBook Air 13')
            ->assertDontSee('iPhone 17')
            ->assertSee('noindex', false);
    });

    it('suggests products in the header search', function (): void {
        storefrontProduct(['name' => 'MacBook Air 13']);

        Livewire::test(HeaderSearch::class)
            ->set('q', 'm')
            ->assertDontSee('MacBook Air 13')
            ->set('q', 'macb')
            ->assertSee('MacBook Air 13');
    });
});

describe('mini cart', function (): void {
    it('shows zero without creating a cart, then the session cart lines', function (): void {
        Livewire::test(MiniCart::class)->assertSee(__('shopper-storefront::storefront.cart.empty'));

        expect(Shopper\Cart\Models\Cart::query()->count())->toBe(0);

        $product = storefrontProduct(['name' => 'Tai nghe']);
        $cart = Shopper\Cart\Models\Cart::query()->create(['currency_code' => 'VND']);
        $cart->lines()->create([
            'purchasable_type' => $product->getMorphClass(),
            'purchasable_id' => $product->id,
            'quantity' => 2,
            'unit_price_amount' => 1_500_000,
        ]);
        session([config('shopper.cart.session.key') => $cart->id]);

        Livewire::test(MiniCart::class)
            ->assertSee('Tai nghe')
            ->assertSee(__('shopper-storefront::storefront.cart.count', ['count' => 2]))
            ->assertSee(shopper_money_format(3_000_000, 'VND'));
    });
});
