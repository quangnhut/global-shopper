<?php

declare(strict_types=1);

use Livewire\Livewire;
use Shopper\Cms\Actions\RestoreRevisionAction;
use Shopper\Cms\Actions\SavePostAction;
use Shopper\Cms\Livewire\Pages\Posts\Editor;
use Shopper\Cms\Livewire\Pages\Posts\Revisions;
use Shopper\Cms\Models\PostRevision;
use Shopper\Cms\Support\RevisionDiff;
use Tests\Core\Stubs\User;

uses(Tests\Cms\TestCase::class);

beforeEach(function (): void {
    $this->admin = $this->makeAdminUser();
    $this->actingAs($this->admin);

    $save = resolve(SavePostAction::class);
    $this->post = $save->execute(['title' => 'Version one', 'content' => '<p>First paragraph</p>', 'author_id' => $this->admin->id]);
    $this->post = $save->execute(['title' => 'Version two', 'content' => '<p>Second paragraph</p>'], $this->post);
    $this->post = $save->execute(['title' => 'Version three', 'content' => '<p>Third paragraph</p>'], $this->post);
});

describe(RevisionDiff::class, function (): void {
    it('compares the HTML source as escaped text', function (): void {
        $from = new PostRevision(['title' => 'A', 'content' => '<p>Safe</p>']);
        $to = new PostRevision(['title' => 'A', 'content' => '<p>Safe</p><p>Added <script>alert(1)</script></p>']);

        $diffs = resolve(RevisionDiff::class)->compare($from, $to);

        expect($diffs)->toHaveKey('content')->not->toHaveKey('title')
            ->and($diffs['content'])->toContain('&lt;script&gt;')->not->toContain('<script>');
    });

    it('treats the first revision as added text', function (): void {
        $diffs = resolve(RevisionDiff::class)->compare(null, new PostRevision(['title' => 'New', 'content' => '<p>Body</p>']));

        expect($diffs)->toHaveKeys(['title', 'content']);
    });
})->group('cms');

describe(RestoreRevisionAction::class, function (): void {
    it('restores an old revision and records the restore as a new revision', function (): void {
        $first = $this->post->revisions()->reorder('id')->first();

        resolve(RestoreRevisionAction::class)->execute($first);

        expect($this->post->refresh())
            ->title->toBe('Version one')
            ->content->toBe('<p>First paragraph</p>')
            ->and($this->post->revisions()->count())->toBe(4)
            ->and($this->post->revisions()->first()->title)->toBe('Version one');
    });
})->group('cms');

describe(Revisions::class, function (): void {
    it('compares the newest revision with the previous one by default', function (): void {
        $component = Livewire::test(Revisions::class, ['post' => $this->post])
            ->assertSee(__('shopper-cms::revisions.current'))
            ->assertActionHidden('restore');

        expect($component->instance()->diffs['title'])->toContain('two', 'three')->not->toContain('one');
    });

    it('restores the selected revision', function (): void {
        $first = $this->post->revisions()->reorder('id')->first();

        Livewire::test(Revisions::class, ['post' => $this->post])
            ->set('toId', $first->id)
            ->assertActionVisible('restore')
            ->callAction('restore')
            ->assertRedirect(route('shopper.cms.posts.edit', $this->post));

        expect($this->post->refresh()->title)->toBe('Version one');
    });

    it('compares any two revisions', function (): void {
        [$first, , $third] = $this->post->revisions()->reorder('id')->get()->all();

        $component = Livewire::test(Revisions::class, ['post' => $this->post])
            ->set('compareTwo', true)
            ->set('fromId', $first->id)
            ->set('toId', $third->id);

        expect($component->instance()->fromRevision->id)->toBe($first->id)
            ->and($component->instance()->diffs['title'])->toContain('one', 'three');
    });

    it('is only available to users who can edit the post', function (): void {
        $writer = User::factory()->create();
        $writer->givePermissionTo(['browse_posts', 'edit_posts']);
        $this->actingAs($writer);

        Livewire::test(Revisions::class, ['post' => $this->post])->assertForbidden();
    });

    it('is linked from the editor publish box', function (): void {
        Livewire::test(Editor::class, ['post' => $this->post])
            ->assertSee(__('shopper-cms::revisions.label', ['count' => 3]))
            ->assertSeeHtml(route('shopper.cms.posts.revisions', $this->post));
    });
})->group('cms', 'livewire');
