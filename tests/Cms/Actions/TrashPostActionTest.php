<?php

declare(strict_types=1);

use Shopper\Cms\Actions\ForceDeletePostAction;
use Shopper\Cms\Actions\RestorePostAction;
use Shopper\Cms\Actions\SavePostAction;
use Shopper\Cms\Actions\TrashPostAction;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Models\Post;

uses(Tests\Cms\TestCase::class);

describe(TrashPostAction::class, function (): void {
    it('keeps the status and frees the slug', function (): void {
        $post = Post::factory()->draft()->create(['slug' => 'hello-world']);

        resolve(TrashPostAction::class)->execute($post);

        $trashed = Post::query()->withTrashed()->find($post->id);

        expect($trashed->trashed())->toBeTrue()
            ->and($trashed->status)->toBe(PostStatus::Draft)
            ->and($trashed->slug)->toBe('hello-world__trashed')
            ->and(resolve(SavePostAction::class)->execute(['title' => 'Hello World'])->slug)->toBe('hello-world');
    });
})->group('cms');

describe(RestorePostAction::class, function (): void {
    it('restores the status and the original slug', function (): void {
        $post = Post::factory()->published()->create(['slug' => 'hello-world', 'metadata' => ['keep' => true]]);
        resolve(TrashPostAction::class)->execute($post);

        resolve(RestorePostAction::class)->execute($post);

        expect($post->refresh())
            ->trashed()->toBeFalse()
            ->status->toBe(PostStatus::Published)
            ->slug->toBe('hello-world')
            ->metadata->toBe(['keep' => true]);
    });

    it('picks the next free slug when the original was taken', function (): void {
        $post = Post::factory()->create(['slug' => 'hello-world']);
        resolve(TrashPostAction::class)->execute($post);
        resolve(SavePostAction::class)->execute(['title' => 'Hello World']);

        expect(resolve(RestorePostAction::class)->execute($post)->slug)->toBe('hello-world-2');
    });
})->group('cms');

describe(ForceDeletePostAction::class, function (): void {
    it('moves child pages up to the deleted page parent', function (): void {
        $root = Post::factory()->page()->create();
        $parent = Post::factory()->page()->create(['parent_id' => $root->id]);
        $child = Post::factory()->page()->create(['parent_id' => $parent->id]);
        $trashedChild = Post::factory()->page()->trashed()->create(['parent_id' => $parent->id]);

        resolve(ForceDeletePostAction::class)->execute($parent);

        expect(Post::query()->withTrashed()->find($parent->id))->toBeNull()
            ->and($child->refresh()->parent_id)->toBe($root->id)
            ->and(Post::query()->withTrashed()->find($trashedChild->id)->parent_id)->toBe($root->id);
    });
})->group('cms');
