<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Shopper\Storefront\Livewire\Pages\Sections\Index;
use Shopper\Storefront\Models\HomeSection;
use Shopper\Storefront\Support\StorefrontCache;
use Tests\Core\Stubs\User;

uses(Tests\Storefront\TestCase::class);

require_once __DIR__.'/../Support/helpers.php';

beforeEach(function (): void {
    $this->configureStore();
    $this->actingAs($this->makeAdminUser());
});

describe(Index::class, function (): void {
    it('lists sections by position, top of the page first', function (): void {
        $second = HomeSection::factory()->create(['title' => 'Deal hot', 'position' => 2]);
        $first = HomeSection::factory()->create(['title' => 'Khuyến mãi', 'position' => 1]);

        Livewire::test(Index::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$first, $second], inOrder: true);
    });

    it('is reachable from the admin and listed in the sidebar', function (): void {
        $this->get(route('shopper.storefront.sections.index'))
            ->assertOk()
            ->assertSee(__('shopper-storefront::admin.menu.homepage'));
    });

    it('reorders sections by drag and drop', function (): void {
        [$a, $b, $c] = [
            HomeSection::factory()->create(['position' => 1]),
            HomeSection::factory()->create(['position' => 2]),
            HomeSection::factory()->create(['position' => 3]),
        ];

        Livewire::test(Index::class)
            ->call('reorderTable', [(string) $c->id, (string) $a->id, (string) $b->id]);

        expect(HomeSection::query()->ordered()->pluck('id')->all())->toBe([$c->id, $a->id, $b->id]);
    });

    it('refreshes the cached home page after a reorder', function (): void {
        $product = storefrontProduct();
        $first = storefrontAttach(HomeSection::factory()->create(['title' => 'First', 'position' => 1]), [$product]);
        $second = storefrontAttach(HomeSection::factory()->create(['title' => 'Second', 'position' => 2]), [$product]);

        expect(array_column(StorefrontCache::home()->sections, 'title'))->toBe(['First', 'Second']);

        Livewire::test(Index::class)->call('reorderTable', [(string) $second->id, (string) $first->id]);

        expect(array_column(StorefrontCache::home()->sections, 'title'))->toBe(['Second', 'First']);
    });

    it('counts picked items that are hidden on the storefront', function (): void {
        $section = storefrontAttach(HomeSection::factory()->create(), [
            storefrontProduct(),
            storefrontProduct(['is_visible' => false]),
        ]);

        Livewire::test(Index::class)
            ->assertTableColumnFormattedStateSet('items_count', __('shopper-storefront::admin.sections.items_shown', ['shown' => 1, 'picked' => 2]), $section);
    });

    it('duplicates a section with its items, disabled at the bottom', function (): void {
        $section = storefrontAttach(HomeSection::factory()->create(['title' => 'Deal hot', 'position' => 1]), [storefrontProduct(), storefrontProduct()]);
        HomeSection::factory()->create(['position' => 5]);

        Livewire::test(Index::class)
            ->callAction(TestAction::make('duplicate')->table($section));

        $copy = HomeSection::query()->latest('id')->first();

        expect($copy->title)->toBe('Deal hot (copy)')
            ->and($copy->is_enabled)->toBeFalse()
            ->and($copy->position)->toBe(6)
            ->and($copy->items->pluck('itemable_id')->all())->toBe($section->items->pluck('itemable_id')->all());
    });

    it('toggles and deletes sections', function (): void {
        $section = HomeSection::factory()->create();

        Livewire::test(Index::class)
            ->call('updateTableColumnState', 'is_enabled', (string) $section->id, false);

        expect($section->refresh()->is_enabled)->toBeFalse();

        Livewire::test(Index::class)
            ->callAction(TestAction::make('delete')->table($section));

        expect(HomeSection::query()->count())->toBe(0);
    });

    it('is forbidden without the `manage_homepage` permission', function (): void {
        $this->actingAs(User::factory()->create());

        Livewire::test(Index::class)->assertForbidden();
    });
});
