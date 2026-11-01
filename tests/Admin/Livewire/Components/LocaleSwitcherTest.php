<?php

declare(strict_types=1);

use Livewire\Livewire;
use Shopper\Core\Models\Country;
use Shopper\Livewire\Components\LocaleSwitcher;
use Tests\Core\Stubs\User;

uses(Tests\Seo\TestCase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

describe(LocaleSwitcher::class, function (): void {
    it('offers English and Vietnamese', function (): void {
        expect(array_keys(config('shopper.admin.locales')))->toBe(['en', 'vi']);

        Livewire::test(LocaleSwitcher::class)
            ->assertSee('Tiếng Việt');
    });

    it('stores the chosen locale in the session', function (): void {
        Livewire::test(LocaleSwitcher::class)
            ->call('switchLocale', 'vi')
            ->assertRedirect();

        expect(session('shopper_locale'))->toBe('vi');
    });

    it('ignores a locale that is not configured', function (): void {
        Livewire::test(LocaleSwitcher::class)
            ->call('switchLocale', 'xx')
            ->assertNoRedirect();

        expect(session('shopper_locale'))->toBeNull();
    });

    it('translates every package and the country names into Vietnamese', function (): void {
        app()->setLocale('vi');

        expect(__('shopper::pages/products.menu'))->toBe('Sản phẩm')
            ->and(__('shopper-core::status.payment.paid'))->toBe('Đã thanh toán')
            ->and(__('shopper-cart::messages.discount.expired'))->toBe('Mã giảm giá đã hết hạn.')
            ->and(__('shopper-cms::posts.add_new_post'))->toBe('Thêm bài viết mới')
            ->and(__('shopper-seo::settings.title'))->toBe('Cài đặt SEO')
            ->and((new Country(['name' => 'Viet Nam', 'cca2' => 'VN']))->translated_name)->toBe('Việt Nam');
    });
})->group('livewire', 'components', 'locale');
