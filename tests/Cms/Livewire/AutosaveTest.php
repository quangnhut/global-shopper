<?php

declare(strict_types=1);

use Livewire\Livewire;
use Shopper\Cms\Actions\AutosavePostAction;
use Shopper\Cms\Actions\SavePostAction;
use Shopper\Cms\Enums\RevisionKind;
use Shopper\Cms\Livewire\Pages\Posts\Editor;
use Shopper\Cms\Livewire\Pages\Posts\Revisions;
use Shopper\Cms\Models\PostRevision;
use Tests\Core\Stubs\User;

uses(Tests\Cms\TestCase::class);

beforeEach(function (): void {
    $this->admin = $this->makeAdminUser();
    $this->actingAs($this->admin);

    $this->post = resolve(SavePostAction::class)->execute([
        'title' => 'Saved title',
        'content' => '<p>Saved</p>',
        'author_id' => $this->admin->id,
    ]);
});

describe(AutosavePostAction::class, function (): void {
    it('keeps one sanitized autosave per user without touching the post', function (): void {
        $action = resolve(AutosavePostAction::class);

        $action->execute($this->post, ['title' => 'Draft 1', 'content' => '<p>One</p>'], $this->admin->id);
        $action->execute($this->post, ['title' => 'Draft 2', 'content' => '<p>Two</p><script>x</script>'], $this->admin->id);
        $action->execute($this->post, ['title' => 'Other user'], User::factory()->create()->id);

        $autosaves = $this->post->autosaves()->get();

        expect($autosaves)->toHaveCount(2)
            ->and($autosaves->firstWhere('user_id', $this->admin->id))
            ->title->toBe('Draft 2')
            ->content->toBe('<p>Two</p>')
            ->and($this->post->refresh()->title)->toBe('Saved title')
            ->and($this->post->revisions()->count())->toBe(1);
    });
})->group('cms');

describe('editor autosave', function (): void {
    it('stores the unsaved form state', function (): void {
        Livewire::test(Editor::class, ['post' => $this->post])
            ->fillForm(['title' => 'Typing…', 'content' => '<p>Not saved yet</p>'])
            ->call('autosave');

        expect(PostRevision::query()->where('kind', RevisionKind::Autosave)->first())
            ->title->toBe('Typing…')
            ->user_id->toBe($this->admin->id)
            ->and($this->post->refresh()->title)->toBe('Saved title');
    });

    it('does not autosave content that was never saved', function (): void {
        Livewire::test(Editor::class)
            ->fillForm(['title' => 'Brand new'])
            ->call('autosave');

        expect(PostRevision::query()->where('kind', RevisionKind::Autosave)->count())->toBe(0);
    });

    it('shows a notice when the autosave is newer than the post', function (): void {
        $this->travel(5)->minutes();
        resolve(AutosavePostAction::class)->execute($this->post, ['title' => 'Unsaved work'], $this->admin->id);

        Livewire::test(Editor::class, ['post' => $this->post])
            ->assertSee(__('shopper-cms::revisions.autosave.notice'))
            ->assertSeeHtml(route('shopper.cms.posts.revisions', ['post' => $this->post, 'autosave' => 1]));
    });

    it('ignores autosaves that match the saved post', function (): void {
        $this->travel(5)->minutes();
        resolve(AutosavePostAction::class)->execute($this->post, ['title' => 'Saved title', 'content' => '<p>Saved</p>'], $this->admin->id);

        Livewire::test(Editor::class, ['post' => $this->post])
            ->assertDontSee(__('shopper-cms::revisions.autosave.notice'));
    });

    it('deletes the autosave when the post is saved', function (): void {
        resolve(AutosavePostAction::class)->execute($this->post, ['title' => 'Unsaved'], $this->admin->id);

        Livewire::test(Editor::class, ['post' => $this->post])
            ->fillForm(['title' => 'Saved for real'])
            ->callAction('saveDraft');

        expect($this->post->autosaves()->count())->toBe(0);
    });

    it('compares the autosave with the saved post when there are no revisions', function (): void {
        $legacy = Shopper\Cms\Models\Post::factory()->create(['title' => 'Legacy title', 'author_id' => $this->admin->id]);
        resolve(AutosavePostAction::class)->execute($legacy, ['title' => 'Legacy title edited', 'content' => $legacy->content], $this->admin->id);

        $diffs = Livewire::test(Revisions::class, ['post' => $legacy])->set('showAutosave', true)->instance()->diffs;

        expect($diffs)->toHaveKey('title')->not->toHaveKey('content');
    });

    it('compares and restores the autosave from the revisions screen', function (): void {
        $this->travel(5)->minutes();
        resolve(AutosavePostAction::class)->execute($this->post, ['title' => 'Recovered title', 'content' => '<p>Recovered</p>'], $this->admin->id);

        $component = Livewire::test(Revisions::class, ['post' => $this->post])
            ->set('showAutosave', true)
            ->assertSee(__('shopper-cms::revisions.autosave.badge'));

        expect($component->instance()->diffs['title'])->toContain('Recovered');

        $component->callAction('restore');

        expect($this->post->refresh()->title)->toBe('Recovered title')
            ->and($this->post->autosaves()->count())->toBe(0)
            ->and($this->post->revisions()->count())->toBe(2);
    });
})->group('cms', 'livewire');
