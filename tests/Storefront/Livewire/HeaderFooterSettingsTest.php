<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use Shopper\Core\Models\Legal;
use Shopper\Storefront\Livewire\Pages\HeaderFooterSettings;
use Shopper\Storefront\Settings\StorefrontSettings;
use Tests\Core\Stubs\User;

uses(Tests\Storefront\TestCase::class);

require_once __DIR__.'/../Support/helpers.php';

beforeEach(function (): void {
    $this->configureStore();
    $this->actingAs($this->makeAdminUser());
});

describe(HeaderFooterSettings::class, function (): void {
    it('saves header, company, footer links and badges', function (): void {
        $page = storefrontPost(['type' => 'page', 'title' => 'Giới thiệu']);
        $legal = Legal::query()->create(['title' => 'Chính sách đổi trả', 'slug' => 'refund', 'is_enabled' => true]);

        $undo = Repeater::fake();

        Livewire::test(HeaderFooterSettings::class)
            ->fillForm([
                'hotline' => '1900 1234',
                'announcement' => 'Miễn phí vận chuyển đơn từ 500.000 ₫',
                'working_hours' => '8:00 – 21:00',
                'business_registration' => '0312345678',
                'bct_url' => 'http://online.gov.vn/Home/WebDetails/123',
                'footer_columns' => [
                    ['title' => 'Về chúng tôi', 'links' => [
                        ['type' => 'page', 'id' => $page->id, 'label' => null],
                        ['type' => 'url', 'url' => '/lien-he', 'label' => 'Liên hệ'],
                    ]],
                    ['title' => 'Chính sách', 'links' => [
                        ['type' => 'legal', 'id' => $legal->id, 'label' => null],
                    ]],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $undo();

        $settings = resolve(StorefrontSettings::class);

        expect($settings->get('hotline'))->toBe('1900 1234')
            ->and($settings->get('business_registration'))->toBe('0312345678')
            ->and($settings->get('footer_columns'))->toBe([
                ['title' => 'Về chúng tôi', 'links' => [
                    ['type' => 'page', 'id' => $page->id, 'url' => null, 'label' => null],
                    ['type' => 'url', 'id' => null, 'url' => '/lien-he', 'label' => 'Liên hệ'],
                ]],
                ['title' => 'Chính sách', 'links' => [
                    ['type' => 'legal', 'id' => $legal->id, 'url' => null, 'label' => null],
                ]],
            ]);
    });

    it('adds footer columns with the repeater button', function (): void {
        Livewire::test(HeaderFooterSettings::class)
            ->callAction(TestAction::make('add')->schemaComponent('footer_columns', schema: 'form'))
            ->assertHasNoErrors()
            ->assertCount('data.footer_columns', 1);
    });

    it('rejects script URLs in footer links', function (): void {
        $undo = Repeater::fake();

        Livewire::test(HeaderFooterSettings::class)
            ->fillForm([
                'footer_columns' => [
                    ['title' => 'Links', 'links' => [['type' => 'url', 'url' => 'javascript:alert(1)', 'label' => 'x']]],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['footer_columns.0.links.0.url']);

        $undo();

        expect(resolve(StorefrontSettings::class)->get('footer_columns'))->toBe([]);
    });

    it('warns about legally required details that are missing', function (): void {
        Livewire::test(HeaderFooterSettings::class)
            ->assertSee(__('shopper-storefront::admin.settings.legal_warning'))
            ->assertSee(__('shopper-storefront::admin.settings.legal_details.business_registration'));
    });

    it('is forbidden without the `manage_homepage` permission', function (): void {
        $this->actingAs(User::factory()->create());

        Livewire::test(HeaderFooterSettings::class)->assertForbidden();
    });
});
