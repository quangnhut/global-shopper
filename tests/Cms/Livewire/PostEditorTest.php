<?php

declare(strict_types=1);

use Livewire\Livewire;
use Shopper\Cms\Actions\ResolveDefaultCategoryAction;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Enums\PostVisibility;
use Shopper\Cms\Livewire\Pages\Pages\Editor as PageEditor;
use Shopper\Cms\Livewire\Pages\Posts\Editor;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;
use Shopper\Core\Models\Upload;
use Tests\Core\Stubs\User;

uses(Tests\Cms\TestCase::class);

beforeEach(function (): void {
    $this->admin = $this->makeAdminUser();
    $this->actingAs($this->admin);
});

function writer(array $permissions = ['browse_posts', 'add_posts', 'edit_posts', 'delete_posts']): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

describe(Editor::class, function (): void {
    it('saves a new draft, assigns the author and opens the edit screen', function (): void {
        Livewire::test(Editor::class)
            ->fillForm(['title' => 'My first draft', 'content' => '<p>Hello</p>'])
            ->callAction('saveDraft')
            ->assertHasNoFormErrors();

        $post = Post::query()->firstOrFail();

        expect($post)
            ->title->toBe('My first draft')
            ->slug->toBe('my-first-draft')
            ->status->toBe(PostStatus::Draft)
            ->author_id->toBe($this->admin->id)
            ->and($post->categories->pluck('id')->all())->toBe([resolve(ResolveDefaultCategoryAction::class)->execute()->id]);
    });

    it('redirects to the edit screen after creating', function (): void {
        $component = Livewire::test(Editor::class)
            ->fillForm(['title' => 'Redirect me'])
            ->callAction('saveDraft');

        $component->assertRedirect(route('shopper.cms.posts.edit', Post::query()->firstOrFail()));
    });

    it('publishes immediately or schedules with a future date', function (): void {
        Livewire::test(Editor::class)
            ->fillForm(['title' => 'Now'])
            ->callAction('publish');

        Livewire::test(Editor::class)
            ->fillForm(['title' => 'Later', 'published_at' => now()->addDays(3)->format('Y-m-d H:i:s')])
            ->assertActionHasLabel('publish', __('shopper-cms::posts.actions.schedule'))
            ->callAction('publish');

        expect(Post::query()->where('title', 'Now')->value('status'))->toBe(PostStatus::Published)
            ->and(Post::query()->where('title', 'Later')->first()->status)->toBe(PostStatus::Scheduled);
    });

    it('saves categories, tags and the primary category', function (): void {
        [$news, $guides] = PostCategory::factory()->count(2)->create();

        Livewire::test(Editor::class)
            ->fillForm([
                'title' => 'Tagged',
                'categories' => [$news->id, $guides->id],
                'primary_category_id' => $guides->id,
                'tags' => ['laravel', 'cms'],
            ])
            ->callAction('saveDraft');

        $post = Post::query()->firstOrFail();

        expect($post->categories->pluck('id')->all())->toEqualCanonicalizing([$news->id, $guides->id])
            ->and($post->primary_category_id)->toBe($guides->id)
            ->and($post->tags->pluck('name')->all())->toEqualCanonicalizing(['laravel', 'cms']);
    });

    it('requires a password for password-protected visibility', function (): void {
        Livewire::test(Editor::class)
            ->fillForm(['title' => 'Locked', 'visibility' => PostVisibility::Password->value])
            ->callAction('publish')
            ->assertHasFormErrors(['password' => 'required']);

        expect(Post::query()->count())->toBe(0);
    });

    it('makes a post private through the visibility', function (): void {
        Livewire::test(Editor::class)
            ->fillForm(['title' => 'Secret', 'visibility' => PostVisibility::Private->value])
            ->callAction('publish');

        expect(Post::query()->firstOrFail()->status)->toBe(PostStatus::Private);
    });

    it('stores the featured image from the uploads library', function (): void {
        $upload = Upload::query()->create([
            'name' => 'cover.png', 'file_name' => 'cover.png', 'path' => 'uploads/cover.png',
            'disk' => 'public', 'mime_type' => 'image/png', 'extension' => 'png', 'size' => 10,
        ]);

        Livewire::test(Editor::class)
            ->fillForm(['title' => 'With cover', 'featured_upload_id' => $upload->id])
            ->callAction('saveDraft');

        expect(Post::query()->firstOrFail()->featuredImage->is($upload))->toBeTrue();
    });

    it('updates a published post and keeps it published', function (): void {
        $post = Post::factory()->published()->create(['title' => 'Old']);

        Livewire::test(Editor::class, ['post' => $post])
            ->assertActionHasLabel('publish', __('shopper-cms::posts.actions.update'))
            ->assertActionHidden('saveDraft')
            ->fillForm(['title' => 'New'])
            ->callAction('publish');

        expect($post->refresh())
            ->title->toBe('New')
            ->status->toBe(PostStatus::Published);
    });

    it('lets contributors only submit for review', function (): void {
        $this->actingAs(writer());

        Livewire::test(Editor::class)
            ->assertActionHasLabel('publish', __('shopper-cms::posts.actions.submit_for_review'))
            ->fillForm(['title' => 'Please review'])
            ->callAction('publish');

        expect(Post::query()->firstOrFail()->status)->toBe(PostStatus::Pending);
    });

    it('does not let contributors make a post private', function (): void {
        $this->actingAs(writer());

        Livewire::test(Editor::class)
            ->fillForm(['title' => 'Sneaky', 'visibility' => PostVisibility::Private->value])
            ->callAction('saveDraft')
            ->assertHasFormErrors(['visibility']);

        expect(Post::query()->count())->toBe(0);
    });

    it('refuses to open posts the user cannot edit', function (): void {
        $post = Post::factory()->draft()->create(['author_id' => $this->admin->id]);

        $this->actingAs(writer());

        Livewire::test(Editor::class, ['post' => $post])->assertForbidden();
    });

    it('returns 404 for content of another type', function (): void {
        $page = Post::factory()->page()->create();

        Livewire::test(Editor::class, ['post' => $page])->assertNotFound();
    });

    it('moves the post to the trash and back', function (): void {
        $post = Post::factory()->draft()->create();

        Livewire::test(Editor::class, ['post' => $post])
            ->callAction('trash')
            ->assertRedirect(route('shopper.cms.posts.index'));

        $post = Post::query()->withTrashed()->findOrFail($post->id);

        Livewire::test(Editor::class, ['post' => $post])
            ->assertSee(__('shopper-cms::posts.form.trashed_notice'))
            ->assertActionHidden('publish')
            ->callAction('restore');

        expect($post->refresh()->trashed())->toBeFalse();
    });

    it('opens trashed content from its edit url', function (): void {
        $this->configureStore();
        $post = Post::factory()->trashed()->create();

        $this->get(route('shopper.cms.posts.edit', $post))->assertOk();
    });
})->group('cms', 'livewire');

describe(PageEditor::class, function (): void {
    it('saves page attributes', function (): void {
        $parent = Post::factory()->page()->create();

        Livewire::test(PageEditor::class)
            ->fillForm([
                'title' => 'Team',
                'parent_id' => $parent->id,
                'template' => 'full-width',
                'menu_order' => 3,
            ])
            ->callAction('publish');

        expect(Post::query()->where('title', 'Team')->firstOrFail())
            ->type->toBe('page')
            ->parent_id->toBe($parent->id)
            ->template->toBe('full-width')
            ->menu_order->toBe(3)
            ->and(Post::query()->where('title', 'Team')->first()->categories)->toBeEmpty();
    });
})->group('cms', 'livewire');
