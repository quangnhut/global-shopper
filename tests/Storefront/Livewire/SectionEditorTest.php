<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use Shopper\Storefront\Enums\HomeSectionStyle;
use Shopper\Storefront\Enums\HomeSectionType;
use Shopper\Storefront\Livewire\Pages\Sections\Editor;
use Shopper\Storefront\Models\HomeSection;
use Shopper\Storefront\Support\ItemPicker;
use Tests\Core\Stubs\User;

uses(Tests\Storefront\TestCase::class);

require_once __DIR__.'/../Support/helpers.php';

beforeEach(function (): void {
    $this->configureStore();
    $this->actingAs($this->makeAdminUser());
});

describe(Editor::class, function (): void {
    it('creates a product section with ordered items at the bottom of the page', function (): void {
        HomeSection::factory()->create(['position' => 4]);
        [$first, $second] = [storefrontProduct(), storefrontProduct()];

        $undo = Repeater::fake();

        Livewire::test(Editor::class)
            ->fillForm([
                'type' => 'products',
                'title' => 'Deal hot',
                'style' => 'hot_deal',
                'layout' => 'carousel',
                'columns' => 5,
                'ends_at' => now()->addWeek()->format('Y-m-d H:i'),
                'items' => [['id' => $second->id], ['id' => $first->id]],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $undo();

        $section = HomeSection::query()->where('title', 'Deal hot')->firstOrFail();

        expect($section->type)->toBe(HomeSectionType::Products)
            ->and($section->style)->toBe(HomeSectionStyle::HotDeal)
            ->and($section->position)->toBe(5)
            ->and($section->items->pluck('itemable_id')->all())->toBe([$second->id, $first->id]);
    });

    it('edits a section without changing its type and replaces its items', function (): void {
        [$a, $b, $c] = [storefrontProduct(), storefrontProduct(), storefrontProduct()];
        $section = storefrontAttach(HomeSection::factory()->create(['title' => 'Old']), [$a, $b]);

        $undo = Repeater::fake();

        Livewire::test(Editor::class, ['section' => $section])
            ->assertFormSet(['title' => 'Old', 'items' => [['id' => $a->id], ['id' => $b->id]]])
            ->fillForm([
                'type' => 'posts',
                'title' => 'Sản phẩm mới',
                'items' => [['id' => $c->id], ['id' => $a->id]],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $undo();

        $section->refresh();

        expect($section->title)->toBe('Sản phẩm mới')
            ->and($section->type)->toBe(HomeSectionType::Products)
            ->and($section->items->pluck('itemable_id')->all())->toBe([$c->id, $a->id]);
    });

    it('creates a post section', function (): void {
        $post = storefrontPost(['title' => 'Mẹo chọn laptop']);

        $undo = Repeater::fake();

        Livewire::test(Editor::class)
            ->fillForm([
                'type' => 'posts',
                'title' => 'Tin tức',
                'style' => 'default',
                'layout' => 'grid',
                'columns' => 4,
                'items' => [['id' => $post->id]],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $undo();

        $section = HomeSection::query()->where('title', 'Tin tức')->firstOrFail();

        expect($section->type)->toBe(HomeSectionType::Posts)
            ->and($section->items->first()->itemable->is($post))->toBeTrue();
    });

    it('adds item rows with the repeater button', function (): void {
        Livewire::test(Editor::class)
            ->callAction(TestAction::make('add')->schemaComponent('items', schema: 'form'))
            ->callAction(TestAction::make('add')->schemaComponent('items', schema: 'form'))
            ->assertHasNoErrors()
            ->assertCount('data.items', 2);
    });

    it('validates the schedule, columns and "view all" link', function (): void {
        Livewire::test(Editor::class)
            ->fillForm([
                'title' => 'Flash sale',
                'columns' => 9,
                'view_all_url' => 'javascript:alert(1)',
                'starts_at' => now()->addDay()->format('Y-m-d H:i'),
                'ends_at' => now()->format('Y-m-d H:i'),
            ])
            ->call('save')
            ->assertHasFormErrors(['columns', 'view_all_url', 'ends_at']);

        expect(HomeSection::query()->count())->toBe(0);
    });

    it('is forbidden without the `manage_homepage` permission', function (): void {
        $this->actingAs(User::factory()->create());

        Livewire::test(Editor::class)->assertForbidden();
    });
});

describe(ItemPicker::class, function (): void {
    it('only offers products the storefront can show, and escapes labels', function (): void {
        $shown = storefrontProduct(['name' => 'Laptop <script>x</script>']);
        $hidden = storefrontProduct(['name' => 'Laptop hidden', 'is_visible' => false]);
        storefrontProduct(['name' => 'Laptop no price'], amount: null);

        $options = resolve(ItemPicker::class)->search(HomeSectionType::Products, 'Laptop');

        expect(array_keys($options))->toBe([$shown->id])
            ->and($options[$shown->id])->toContain('Laptop &lt;script&gt;')->not->toContain('<script>');

        $labels = resolve(ItemPicker::class)->labels(HomeSectionType::Products, [$hidden->id]);

        expect($labels[$hidden->id])->toContain(__('shopper-storefront::admin.sections.hidden_item'));
    });

    it('only offers published public posts', function (): void {
        $post = storefrontPost(['title' => 'Khuyến mãi tháng 11']);
        storefrontPost(['title' => 'Khuyến mãi nháp', 'status' => Shopper\Cms\Enums\PostStatus::Draft]);
        storefrontPost(['title' => 'Khuyến mãi trang', 'type' => 'page']);

        expect(array_keys(resolve(ItemPicker::class)->search(HomeSectionType::Posts, 'Khuyến mãi')))->toBe([$post->id]);
    });
});
