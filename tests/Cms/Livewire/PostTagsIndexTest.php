<?php

declare(strict_types=1);

use Livewire\Livewire;
use Shopper\Cms\Actions\SyncPostTermsAction;
use Shopper\Cms\Livewire\Pages\PostTags\Index;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostTag;
use Tests\Core\Stubs\User;

uses(Tests\Cms\TestCase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('browse_post_tags');
    $this->actingAs($this->user);
});

describe(Index::class, function (): void {
    it('sends users without `browse_post_tags` to the forbidden page', function (): void {
        $this->configureStore();
        $user = User::factory()->create();
        $user->givePermissionTo('access_dashboard');
        $this->actingAs($user);

        $this->get(route('shopper.cms.post-tags.index'))
            ->assertRedirect(route('shopper.forbidden'));
    });

    it('is reachable from its admin route', function (): void {
        $this->configureStore();
        $this->user->givePermissionTo('access_dashboard');

        $this->get(route('shopper.cms.post-tags.index'))
            ->assertOk()
            ->assertSeeLivewire(Index::class);
    });

    it('lists tags with their post count', function (): void {
        $tag = PostTag::factory()->create();
        resolve(SyncPostTermsAction::class)->execute(Post::factory()->create(), tags: [$tag->id]);

        Livewire::test(Index::class)
            ->assertCanSeeTableRecords([$tag])
            ->assertTableColumnStateSet('posts_count', 1, $tag);
    });

    it('creates a tag with a generated slug', function (): void {
        $this->user->givePermissionTo('add_post_tags');

        Livewire::test(Index::class)
            ->assertActionVisible('create')
            ->callAction('create', data: ['name' => 'Open Source', 'slug' => ''])
            ->assertHasNoActionErrors();

        expect(PostTag::query()->where('slug', 'open-source')->exists())->toBeTrue();
    });

    it('edits and deletes a tag', function (): void {
        $this->user->givePermissionTo(['edit_post_tags', 'delete_post_tags']);
        $tag = PostTag::factory()->create(['name' => 'old']);

        Livewire::test(Index::class)
            ->callTableAction('edit', $tag, data: ['name' => 'New name', 'slug' => 'new-name'])
            ->assertHasNoTableActionErrors();

        expect($tag->refresh()->slug)->toBe('new-name');

        Livewire::test(Index::class)->callTableAction('delete', $tag);

        expect(PostTag::query()->count())->toBe(0);
    });

    it('hides edit and delete without permission', function (): void {
        $tag = PostTag::factory()->create();

        Livewire::test(Index::class)
            ->assertTableActionHidden('edit', $tag)
            ->assertTableActionHidden('delete', $tag);
    });
})->group('cms', 'livewire');
