<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Shopper\Cms\Actions\PublishPostAction;
use Shopper\Cms\Actions\SavePostAction;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Enums\PostVisibility;
use Shopper\Cms\Models\Post;

uses(Tests\Cms\TestCase::class);

beforeEach(function (): void {
    $this->action = resolve(SavePostAction::class);
});

describe(SavePostAction::class, function (): void {
    it('creates a draft with a slug from the title', function (): void {
        $post = $this->action->execute(['title' => 'My First Post']);

        expect($post->refresh())
            ->type->toBe('post')
            ->slug->toBe('my-first-post')
            ->status->toBe(PostStatus::Draft)
            ->published_at->toBeNull();
    });

    it('slugifies an explicit slug and keeps it unique', function (): void {
        Post::factory()->create(['slug' => 'custom-url']);

        expect($this->action->execute(['title' => 'Anything', 'slug' => 'Custom URL'])->slug)->toBe('custom-url-2');
    });

    it('keeps the slug when the title changes', function (): void {
        $post = $this->action->execute(['title' => 'Original']);

        expect($this->action->execute(['title' => 'Renamed'], $post)->slug)->toBe('original');
    });

    it('sets the publish date when publishing without one', function (): void {
        $post = $this->action->execute(['title' => 'Live', 'status' => PostStatus::Published]);

        expect($post->status)->toBe(PostStatus::Published)
            ->and($post->published_at->toDateTimeString())->toBe(now()->toDateTimeString());
    });

    it('schedules a post published with a future date', function (): void {
        $post = $this->action->execute(['title' => 'Soon', 'status' => 'publish', 'published_at' => now()->addDays(2)]);

        expect($post->status)->toBe(PostStatus::Scheduled);
    });

    it('publishes a scheduled post whose date is in the past', function (): void {
        $post = $this->action->execute(['title' => 'Late', 'status' => 'future', 'published_at' => now()->subHour()]);

        expect($post->status)->toBe(PostStatus::Published);
    });

    it('requires a date to schedule', function (): void {
        $this->action->execute(['title' => 'No date', 'status' => PostStatus::Scheduled]);
    })->throws(ValidationException::class);

    it('makes a post private through the visibility', function (): void {
        $post = $this->action->execute(['title' => 'Secret', 'status' => 'publish', 'visibility' => PostVisibility::Private]);

        expect($post->status)->toBe(PostStatus::Private)
            ->and($post->visibility())->toBe(PostVisibility::Private);
    });

    it('protects a post with a password', function (): void {
        $post = $this->action->execute([
            'title' => 'Members',
            'status' => 'publish',
            'visibility' => 'password',
            'password' => 'open-sesame',
        ]);

        expect(Hash::check('open-sesame', $post->password))->toBeTrue()
            ->and($post->visibility())->toBe(PostVisibility::Password);

        $updated = $this->action->execute(['title' => 'Members only', 'visibility' => 'password'], $post);

        expect(Hash::check('open-sesame', $updated->password))->toBeTrue();
    });

    it('requires a password for password visibility', function (): void {
        $this->action->execute(['title' => 'Locked', 'visibility' => 'password']);
    })->throws(ValidationException::class);

    it('clears the password and the private status when made public', function (): void {
        $post = Post::factory()->private()->create(['password' => 'x']);

        $post = $this->action->execute(['visibility' => 'public'], $post);

        expect($post->status)->toBe(PostStatus::Published)
            ->and($post->password)->toBeNull();
    });

    it('ignores the parent of non-hierarchical types', function (): void {
        $parent = Post::factory()->create();

        expect($this->action->execute(['title' => 'Child', 'parent_id' => $parent->id])->parent_id)->toBeNull();
    });

    it('refuses to make a page its own ancestor', function (): void {
        $grandparent = Post::factory()->page()->create();
        $parent = Post::factory()->page()->create(['parent_id' => $grandparent->id]);
        $child = Post::factory()->page()->create(['parent_id' => $parent->id]);

        $this->action->execute(['parent_id' => $child->id], $grandparent);
    })->throws(ValidationException::class);

    it('re-checks the slug when a page moves to another parent', function (): void {
        $about = Post::factory()->page()->create();
        Post::factory()->page()->create(['slug' => 'team', 'parent_id' => $about->id]);
        $team = Post::factory()->page()->create(['slug' => 'team']);

        expect($this->action->execute(['parent_id' => $about->id], $team)->slug)->toBe('team-2');
    });

    it('rejects unknown post types', function (): void {
        $this->action->execute(['type' => 'portfolio', 'title' => 'Nope']);
    })->throws(ValidationException::class);
})->group('cms');

describe(PublishPostAction::class, function (): void {
    it('publishes a draft now', function (): void {
        $post = resolve(PublishPostAction::class)->execute(Post::factory()->draft()->create());

        expect($post->isPublished())->toBeTrue();
    });

    it('schedules a draft for a future date', function (): void {
        $post = resolve(PublishPostAction::class)->execute(Post::factory()->draft()->create(), now()->addDay());

        expect($post->isScheduled())->toBeTrue();
    });
})->group('cms');
