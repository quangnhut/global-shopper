<?php

declare(strict_types=1);

use Livewire\Livewire;
use Shopper\Cms\Actions\SyncPostTermsAction;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Livewire\Pages\Pages\Index as PageIndex;
use Shopper\Cms\Livewire\Pages\Posts\Index;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;
use Tests\Core\Stubs\User;

uses(Tests\Cms\TestCase::class);

beforeEach(function (): void {
    $this->admin = $this->makeAdminUser();
    $this->actingAs($this->admin);
});

function contributor(array $permissions = ['browse_posts', 'add_posts', 'edit_posts', 'delete_posts']): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

describe(Index::class, function (): void {
    it('sends users without `browse_posts` to the forbidden page', function (): void {
        $this->configureStore();
        $user = User::factory()->create();
        $user->givePermissionTo('access_dashboard');

        $this->actingAs($user)
            ->get(route('shopper.cms.posts.index'))
            ->assertRedirect(route('shopper.forbidden'));
    });

    it('is reachable from its admin route', function (): void {
        $this->configureStore();

        $this->get(route('shopper.cms.posts.index'))
            ->assertOk()
            ->assertSeeLivewire(Index::class);
    });

    it('shows WordPress status tabs with counts and hides empty ones', function (): void {
        Post::factory()->count(2)->published()->create(['author_id' => $this->admin->id]);
        Post::factory()->draft()->create();
        Post::factory()->published()->trashed()->create();
        Post::factory()->page()->draft()->create();

        $tabs = collect(Livewire::test(Index::class)->instance()->getCachedTabs())
            ->map(fn ($tab) => $tab->getBadge());

        expect($tabs->all())->toEqual(['all' => 3, 'mine' => 2, 'publish' => 2, 'draft' => 1, 'trash' => 1]);
    });

    it('lists only trashed items in the trash tab', function (): void {
        $live = Post::factory()->create();
        $trashed = Post::factory()->trashed()->create();

        Livewire::test(Index::class)
            ->assertCanSeeTableRecords([$live])
            ->assertCanNotSeeTableRecords([$trashed])
            ->set('activeTab', 'trash')
            ->assertCanSeeTableRecords([$trashed])
            ->assertCanNotSeeTableRecords([$live]);
    });

    it('only lists its own post type', function (): void {
        $post = Post::factory()->create();
        $page = Post::factory()->page()->create();

        Livewire::test(PageIndex::class)
            ->assertCanSeeTableRecords([$page])
            ->assertCanNotSeeTableRecords([$post]);
    });

    it('trashes and restores without losing the status', function (): void {
        $post = Post::factory()->pending()->create();

        Livewire::test(Index::class)->callTableAction('trash', $post);

        expect($post->refresh()->trashed())->toBeTrue();

        Livewire::test(Index::class)
            ->set('activeTab', 'trash')
            ->callTableAction('restore', $post);

        expect($post->refresh())
            ->trashed()->toBeFalse()
            ->status->toBe(PostStatus::Pending);
    });

    it('only deletes permanently from the trash', function (): void {
        $live = Post::factory()->create();
        $trashed = Post::factory()->trashed()->create();

        Livewire::test(Index::class)
            ->assertTableActionHidden('forceDelete', $live)
            ->set('activeTab', 'trash')
            ->callTableAction('forceDelete', $trashed);

        expect(Post::query()->withTrashed()->find($trashed->id))->toBeNull();
    });

    it('only offers `Empty trash` in the trash tab', function (): void {
        Post::factory()->trashed()->create();

        Livewire::test(Index::class)
            ->assertActionHidden('emptyTrash')
            ->set('activeTab', 'trash')
            ->assertActionVisible('emptyTrash');
    });

    it('empties the trash', function (): void {
        Post::factory()->count(2)->trashed()->create();
        $live = Post::factory()->create();

        Livewire::test(Index::class)
            ->set('activeTab', 'trash')
            ->callAction('emptyTrash');

        expect(Post::query()->withTrashed()->pluck('id')->all())->toBe([$live->id]);
    });

    it('lets authors edit only their own posts without `edit_others_posts`', function (): void {
        $author = contributor(['browse_posts', 'edit_posts', 'publish_posts']);
        $own = Post::factory()->create(['author_id' => $author->id]);
        $others = Post::factory()->create(['author_id' => $this->admin->id]);

        $this->actingAs($author);

        Livewire::test(Index::class)
            ->assertTableActionVisible('edit', $own)
            ->assertTableActionHidden('edit', $others)
            ->assertTableActionHidden('quickEdit', $others);
    });

    it('stops contributors from editing their published posts', function (): void {
        $contributor = contributor();
        $draft = Post::factory()->draft()->create(['author_id' => $contributor->id]);
        $published = Post::factory()->published()->create(['author_id' => $contributor->id]);

        $this->actingAs($contributor);

        Livewire::test(Index::class)
            ->assertTableActionVisible('edit', $draft)
            ->assertTableActionHidden('edit', $published)
            ->assertTableActionHidden('trash', $published);
    });

    it('quick edits the title, status and categories', function (): void {
        $category = PostCategory::factory()->create();
        $post = Post::factory()->draft()->create();

        Livewire::test(Index::class)
            ->callTableAction('quickEdit', $post, data: [
                'title' => 'Quick title',
                'status' => PostStatus::Published->value,
                'categories' => [$category->id],
                'tags' => ['quick'],
            ])
            ->assertHasNoTableActionErrors();

        expect($post->refresh())
            ->title->toBe('Quick title')
            ->status->toBe(PostStatus::Published)
            ->and($post->categories->pluck('id')->all())->toBe([$category->id])
            ->and($post->tags->pluck('name')->all())->toBe(['quick']);
    });

    it('does not let contributors publish through quick edit', function (): void {
        $contributor = contributor();
        $post = Post::factory()->draft()->create(['author_id' => $contributor->id]);

        $this->actingAs($contributor);

        Livewire::test(Index::class)
            ->callTableAction('quickEdit', $post, data: ['title' => 'x', 'status' => PostStatus::Published->value])
            ->assertHasTableActionErrors(['status']);

        expect($post->refresh()->status)->toBe(PostStatus::Draft);
    });

    it('skips posts the user cannot delete in bulk trash', function (): void {
        $contributor = contributor();
        $own = Post::factory()->draft()->create(['author_id' => $contributor->id]);
        $others = Post::factory()->draft()->create(['author_id' => $this->admin->id]);

        $this->actingAs($contributor);

        Livewire::test(Index::class)
            ->callTableBulkAction('trash', [$own, $others])
            ->assertNotified();

        expect($own->refresh()->trashed())->toBeTrue()
            ->and($others->refresh()->trashed())->toBeFalse();
    });

    it('adds categories in bulk edit without removing existing ones', function (): void {
        [$news, $deals] = PostCategory::factory()->count(2)->create();
        $first = resolve(SyncPostTermsAction::class)->execute(Post::factory()->draft()->create(), [$news->id]);
        $second = Post::factory()->draft()->create();

        Livewire::test(Index::class)
            ->callTableBulkAction('bulkEdit', [$first, $second], data: [
                'status' => PostStatus::Pending->value,
                'categories' => [$deals->id],
            ]);

        expect($first->refresh()->status)->toBe(PostStatus::Pending)
            ->and($first->categories()->pluck('id')->all())->toEqualCanonicalizing([$news->id, $deals->id])
            ->and($second->categories()->pluck('id')->all())->toBe([$deals->id]);
    });

    it('filters by month', function (): void {
        $october = Post::factory()->create(['published_at' => '2026-10-10 10:00:00']);
        $september = Post::factory()->create(['published_at' => '2026-09-10 10:00:00']);

        Livewire::test(Index::class)
            ->filterTable('month', '2026-10')
            ->assertCanSeeTableRecords([$october])
            ->assertCanNotSeeTableRecords([$september]);
    });

    it('lists child pages right under their parent', function (): void {
        $about = Post::factory()->page()->create(['title' => 'About', 'menu_order' => 2]);
        $contact = Post::factory()->page()->create(['title' => 'Contact', 'menu_order' => 3]);
        $team = Post::factory()->page()->create(['title' => 'Team', 'parent_id' => $about->id, 'menu_order' => 0]);
        $home = Post::factory()->page()->create(['title' => 'Home', 'menu_order' => 1]);

        Livewire::test(PageIndex::class)
            ->assertCanSeeTableRecords([$home, $about, $team, $contact], inOrder: true);
    });

    it('prefixes child pages with their depth', function (): void {
        $parent = Post::factory()->page()->create(['title' => 'About']);
        $child = Post::factory()->page()->create(['title' => 'Team', 'parent_id' => $parent->id]);

        Livewire::test(PageIndex::class)
            ->assertTableColumnFormattedStateSet('title', '— Team', $child);
    });
})->group('cms', 'livewire');
