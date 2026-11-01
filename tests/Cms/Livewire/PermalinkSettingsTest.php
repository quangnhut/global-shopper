<?php

declare(strict_types=1);

use Livewire\Livewire;
use Shopper\Cms\Livewire\Pages\Settings\Permalinks;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Permalinks\PermalinkSettings;
use Shopper\Cms\Settings\PermalinkSetting;
use Shopper\Settings\SettingManager;
use Tests\Core\Stubs\User;

uses(Tests\Cms\TestCase::class);

beforeEach(function (): void {
    $this->actingAs($this->makeAdminUser());
});

describe(Permalinks::class, function (): void {
    it('is listed on the settings screen', function (): void {
        expect(app(SettingManager::class)->registered())->toHaveKey(PermalinkSetting::class);
    });

    it('starts on "Post name" with example URLs', function (): void {
        Livewire::test(Permalinks::class)
            ->assertFormSet(['preset' => 'post_name'])
            ->assertSee('/sample-post/')
            ->assertSee('/archives/123');
    });

    it('saves a preset and remembers the previous structure', function (): void {
        Livewire::test(Permalinks::class)
            ->fillForm(['preset' => 'month_and_name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = resolve(PermalinkSettings::class);

        expect($settings->structure()->value)->toBe('/%year%/%monthnum%/%postname%/')
            ->and($settings->history()[0]->value)->toBe('/%postname%/');
    });

    it('saves a valid custom structure, bases and the homepage', function (): void {
        $home = Post::factory()->page()->create();
        $blog = Post::factory()->page()->create();

        Livewire::test(Permalinks::class)
            ->fillForm([
                'preset' => 'custom',
                'custom_structure' => 'blog/%category%/%postname%/',
                'category_base' => 'Topics',
                'tag_base' => 'labels',
                'front_page_id' => $home->id,
                'posts_page_id' => $blog->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = resolve(PermalinkSettings::class);

        expect($settings->structure()->value)->toBe('/blog/%category%/%postname%/')
            ->and($settings->categoryBase())->toBe('topics')
            ->and($settings->tagBase())->toBe('labels')
            ->and($settings->frontPageId())->toBe($home->id)
            ->and($settings->postsPageId())->toBe($blog->id);
    });

    it('rejects invalid custom structures and reserved bases', function (): void {
        Livewire::test(Permalinks::class)
            ->fillForm(['preset' => 'custom', 'custom_structure' => '/%year%/%monthnum%/', 'category_base' => 'storage'])
            ->call('save')
            ->assertHasFormErrors(['custom_structure', 'category_base']);

        expect(resolve(PermalinkSettings::class)->structure()->value)->toBe('/%postname%/');
    });

    it('requires `manage_permalinks`', function (): void {
        $this->configureStore();
        $user = User::factory()->create();
        $user->givePermissionTo('access_dashboard');

        $this->actingAs($user)
            ->get(route('shopper.cms.settings.permalinks'))
            ->assertRedirect(route('shopper.forbidden'));
    });
})->group('cms', 'livewire');
