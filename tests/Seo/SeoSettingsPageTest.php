<?php

declare(strict_types=1);

use Filament\Actions\Contracts\HasActions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Shopper\Core\Actions\StoreUploadAction;
use Shopper\Livewire\Components\Products\Form\Seo as ProductSeoForm;
use Shopper\Seo\Actions\GenerateSiteIconsAction;
use Shopper\Seo\Livewire\Components\EntrySeoPanel;
use Shopper\Seo\Livewire\Pages\SeoSettingsPage;
use Shopper\Seo\Settings\SeoSettings;
use Tests\Core\Stubs\User;

uses(Tests\Seo\TestCase::class);

beforeEach(function (): void {
    $this->actingAs($this->makeAdminUser());
});

describe(SeoSettingsPage::class, function (): void {
    it('saves every tab', function (): void {
        Livewire::test(SeoSettingsPage::class)
            ->fillForm([
                'general.site_name' => 'Shopper Demo',
                'general.separator' => '|',
                'titles.product.title' => '%title% %sep% Buy online',
                'titles.post.noindex' => true,
                'social.twitter_handle' => '@shopperlabs',
                'social.profiles' => ['https://instagram.com/shopper'],
                'schema.entity' => 'organization',
                'schema.local_business_type' => 'ElectronicsStore',
                'indexing.noindex_paths' => [['pattern' => '/wishlist*', 'nofollow' => false]],
                'tracking.gtm_id' => 'GTM-AB12CD3',
                'tracking.verification_google' => 'abc123-XYZ_',
                'robots.content' => "User-agent: *\nDisallow: /private/",
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = resolve(SeoSettings::class);

        expect($settings->get('general.site_name'))->toBe('Shopper Demo')
            ->and($settings->get('general.separator'))->toBe('|')
            ->and($settings->get('titles.product.title'))->toBe('%title% %sep% Buy online')
            ->and($settings->get('titles.post.noindex'))->toBeTrue()
            ->and($settings->get('social.twitter_handle'))->toBe('shopperlabs')
            ->and($settings->get('social.profiles'))->toBe(['https://instagram.com/shopper'])
            ->and($settings->get('schema.local_business_type'))->toBe('ElectronicsStore')
            ->and($settings->get('indexing.noindex_paths'))->toBe([['pattern' => '/wishlist*', 'nofollow' => false]])
            ->and($settings->get('tracking.gtm_id'))->toBe('GTM-AB12CD3')
            ->and($settings->get('robots.content'))->toContain('Disallow: /private/');
    });

    it('validates tracking IDs and verification codes', function (): void {
        Livewire::test(SeoSettingsPage::class)
            ->fillForm([
                'tracking.gtm_id' => '<script>alert(1)</script>',
                'tracking.ga4_id' => 'UA-123',
                'tracking.verification_google' => '<meta name="x">',
                'social.twitter_handle' => 'not a handle!',
                'schema.search_url' => '/search?q=',
            ])
            ->call('save')
            ->assertHasFormErrors([
                'tracking.gtm_id', 'tracking.ga4_id', 'tracking.verification_google', 'social.twitter_handle', 'schema.search_url',
            ]);
    });

    it('only lets `manage_seo_scripts` change custom code', function (): void {
        resolve(SeoSettings::class)->save('tracking', ['custom_head' => '<!-- trusted -->']);

        $editor = User::factory()->create();
        $editor->givePermissionTo('manage_seo');
        $this->actingAs($editor);

        Livewire::test(SeoSettingsPage::class)
            ->assertFormFieldIsDisabled('tracking.custom_head')
            ->set('data.tracking.custom_head', '<script>evil()</script>')
            ->call('save');

        expect(resolve(SeoSettings::class)->get('tracking.custom_head'))->toBe('<!-- trusted -->');
    });

    it('requires `manage_seo`', function (): void {
        $this->configureStore();
        $user = User::factory()->create();
        $user->givePermissionTo('access_dashboard');

        $this->actingAs($user)->get(route('shopper.seo.settings'))->assertRedirect(route('shopper.forbidden'));
    });

    it('adds a row to the no-index paths repeater through its `add` action', function (): void {
        $component = Livewire::test(SeoSettingsPage::class);

        $before = count($component->get('data.indexing.noindex_paths') ?? []);

        $component
            ->call('mountAction', 'add', [], ['schemaComponent' => 'form.indexing.noindex_paths'])
            ->assertHasNoErrors();

        expect($component->get('data.indexing.noindex_paths'))->toHaveCount($before + 1);
    });

    it('can mount the actions rendered by its schema fields', function (string $component): void {
        expect(is_subclass_of($component, HasActions::class))->toBeTrue()
            ->and(method_exists($component, 'mountAction'))->toBeTrue();
    })->with([
        SeoSettingsPage::class,
        EntrySeoPanel::class,
        ProductSeoForm::class,
    ]);
})->group('seo', 'livewire');

describe(GenerateSiteIconsAction::class, function (): void {
    it('creates favicon, touch icon and manifest sizes from the website avatar', function (): void {
        Storage::fake(config('shopper.media.uploads.disk'));
        $upload = resolve(StoreUploadAction::class)->execute(UploadedFile::fake()->image('avatar.png', 600, 600));

        Livewire::test(SeoSettingsPage::class)
            ->fillForm(['general.site_icon_upload_id' => $upload->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $disk = Storage::disk(config('shopper.media.uploads.disk'));

        foreach (GenerateSiteIconsAction::SIZES as $size) {
            $disk->assertExists("seo/icons/icon-{$size}.png");
            expect(getimagesize($disk->path("seo/icons/icon-{$size}.png"))[0])->toBe($size);
        }

        $disk->assertExists('seo/icons/site.webmanifest');
        expect(resolve(SeoSettings::class)->get('general.site_icons.180'))->toBe('seo/icons/icon-180.png');
    });
})->group('seo');
