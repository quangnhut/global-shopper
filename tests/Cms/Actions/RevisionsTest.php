<?php

declare(strict_types=1);

use Shopper\Cms\Actions\ForceDeletePostAction;
use Shopper\Cms\Actions\SavePostAction;
use Shopper\Cms\Actions\TrashPostAction;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Models\PostRevision;
use Tests\Core\Stubs\User;

uses(Tests\Cms\TestCase::class);

beforeEach(function (): void {
    $this->save = resolve(SavePostAction::class);
});

describe('revisions', function (): void {
    it('keeps a copy of the post after each save that changes it', function (): void {
        $user = User::factory()->create();
        $this->actingAs($user);

        $post = $this->save->execute(['title' => 'First', 'content' => '<p>One</p>']);
        $post = $this->save->execute(['title' => 'Second', 'content' => '<p>Two</p>'], $post);

        expect($post->revisions()->pluck('title')->all())->toBe(['Second', 'First'])
            ->and($post->revisions()->first())
            ->content->toBe('<p>Two</p>')
            ->user_id->toBe($user->id);
    });

    it('skips saves that do not change title, content or excerpt', function (): void {
        $post = $this->save->execute(['title' => 'Same', 'content' => '<p>Same</p>']);

        $this->save->execute(['title' => 'Same', 'status' => PostStatus::Pending], $post);

        expect($post->revisions()->count())->toBe(1);
    });

    it('keeps only the latest `revisions.limit` revisions', function (): void {
        config(['shopper.cms.revisions.limit' => 2]);
        $post = $this->save->execute(['title' => 'v1']);

        foreach (['v2', 'v3', 'v4'] as $title) {
            $post = $this->save->execute(['title' => $title], $post);
        }

        expect($post->revisions()->pluck('title')->all())->toBe(['v4', 'v3']);
    });

    it('stores nothing when revisions are disabled', function (): void {
        config(['shopper.cms.revisions.enabled' => false]);

        $post = $this->save->execute(['title' => 'No history']);

        expect(PostRevision::query()->count())->toBe(0)
            ->and($post->exists)->toBeTrue();
    });

    it('keeps revisions in the trash and deletes them with the post', function (): void {
        $post = $this->save->execute(['title' => 'Doomed']);
        $this->save->execute(['title' => 'Doomed v2'], $post);

        resolve(TrashPostAction::class)->execute($post);

        expect(PostRevision::query()->count())->toBe(2);

        resolve(ForceDeletePostAction::class)->execute($post);

        expect(PostRevision::query()->count())->toBe(0);
    });

    it('keeps the previous version of content that had no history yet', function (): void {
        $post = Shopper\Cms\Models\Post::factory()->create(['title' => 'Before revisions', 'content' => '<p>Old</p>']);

        $this->save->execute(['title' => 'After', 'content' => '<p>New</p>'], $post);

        expect($post->revisions()->reorder('id')->pluck('title')->all())->toBe(['Before revisions', 'After'])
            ->and($post->revisions()->reorder('id')->first()->content)->toBe('<p>Old</p>');
    });

    it('stores the sanitized content', function (): void {
        $post = $this->save->execute(['title' => 'x', 'content' => '<p>ok</p><script>alert(1)</script>']);

        expect($post->revisions()->first()->content)->toBe('<p>ok</p>');
    });
})->group('cms');
