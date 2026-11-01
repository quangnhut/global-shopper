<?php

declare(strict_types=1);

use Livewire\Livewire;
use Shopper\Cms\Actions\ResolveDefaultCategoryAction;
use Shopper\Cms\Actions\SyncPostTermsAction;
use Shopper\Cms\Livewire\Pages\PostCategories\Index;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;
use Tests\Core\Stubs\User;

uses(Tests\Cms\TestCase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('browse_post_categories');
    $this->actingAs($this->user);

    $this->default = resolve(ResolveDefaultCategoryAction::class)->execute();
});

describe(Index::class, function (): void {
    it('sends users without `browse_post_categories` to the forbidden page', function (): void {
        $this->configureStore();
        $user = User::factory()->create();
        $user->givePermissionTo('access_dashboard');
        $this->actingAs($user);

        $this->get(route('shopper.cms.post-categories.index'))
            ->assertRedirect(route('shopper.forbidden'));
    });

    it('is reachable from its admin route', function (): void {
        $this->configureStore();
        $this->user->givePermissionTo('access_dashboard');

        $this->get(route('shopper.cms.post-categories.index'))
            ->assertOk()
            ->assertSeeLivewire(Index::class);
    });

    it('lists categories with their post count', function (): void {
        $category = PostCategory::factory()->create();
        resolve(SyncPostTermsAction::class)->execute(Post::factory()->create(), [$category->id]);
        resolve(SyncPostTermsAction::class)->execute(Post::factory()->create(), [$category->id]);

        Livewire::test(Index::class)
            ->assertCanSeeTableRecords([$this->default, $category])
            ->assertTableColumnStateSet('posts_count', 2, $category);
    });

    it('hides the `create` action without `add_post_categories`', function (): void {
        Livewire::test(Index::class)
            ->assertActionHidden('create');
    });

    it('creates a nested category', function (): void {
        $this->user->givePermissionTo('add_post_categories');
        $parent = PostCategory::factory()->create();

        Livewire::test(Index::class)
            ->callAction('create', data: ['name' => 'Release notes', 'slug' => '', 'parent_id' => $parent->id])
            ->assertHasNoActionErrors();

        expect(PostCategory::query()->where('slug', 'release-notes')->first())
            ->parent_id->toBe($parent->id);
    });

    it('cannot move a category under its own subcategory', function (): void {
        $this->user->givePermissionTo('edit_post_categories');
        $category = PostCategory::factory()->create();
        $child = PostCategory::factory()->create(['parent_id' => $category->id]);

        Livewire::test(Index::class)
            ->callTableAction('edit', $category, data: ['name' => $category->name, 'parent_id' => $child->id])
            ->assertHasTableActionErrors(['parent_id']);

        expect($category->refresh()->parent_id)->toBeNull();
    });

    it('never offers to delete the default category', function (): void {
        $this->user->givePermissionTo('delete_post_categories');
        $category = PostCategory::factory()->create();

        Livewire::test(Index::class)
            ->assertTableActionHidden('delete', $this->default)
            ->assertTableActionVisible('delete', $category);
    });

    it('moves posts to the default category when deleting', function (): void {
        $this->user->givePermissionTo('delete_post_categories');
        $category = PostCategory::factory()->create();
        $post = resolve(SyncPostTermsAction::class)->execute(Post::factory()->create(), [$category->id]);

        Livewire::test(Index::class)
            ->callTableAction('delete', $category);

        expect(PostCategory::query()->find($category->id))->toBeNull()
            ->and($post->categories()->pluck('id')->all())->toBe([$this->default->id]);
    });

    it('skips the default category in bulk deletes', function (): void {
        $this->user->givePermissionTo('delete_post_categories');
        $category = PostCategory::factory()->create();

        Livewire::test(Index::class)
            ->callTableBulkAction('delete', [$this->default, $category]);

        expect(PostCategory::query()->pluck('id')->all())->toBe([$this->default->id]);
    });
})->group('cms', 'livewire');
