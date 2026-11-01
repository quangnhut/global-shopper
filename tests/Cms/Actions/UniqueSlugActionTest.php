<?php

declare(strict_types=1);

use Shopper\Cms\Actions\UniqueSlugAction;
use Shopper\Cms\Models\Post;

uses(Tests\Cms\TestCase::class);

beforeEach(function (): void {
    $this->action = resolve(UniqueSlugAction::class);
});

describe(UniqueSlugAction::class, function (): void {
    it('appends `-2`, `-3` to duplicate slugs like WordPress', function (): void {
        Post::factory()->create(['slug' => 'hello-world']);
        Post::factory()->create(['slug' => 'hello-world-2']);

        expect($this->action->execute('Hello World', 'post'))->toBe('hello-world-3');
    });

    it('keeps slugs unique per post type only', function (): void {
        Post::factory()->create(['slug' => 'about']);

        expect($this->action->execute('About', 'page'))->toBe('about');
    });

    it('lets pages with different parents share a slug', function (): void {
        $about = Post::factory()->page()->create(['slug' => 'about']);
        $careers = Post::factory()->page()->create(['slug' => 'careers']);
        Post::factory()->page()->create(['slug' => 'team', 'parent_id' => $about->id]);

        expect($this->action->execute('Team', 'page', $careers->id))->toBe('team')
            ->and($this->action->execute('Team', 'page', $about->id))->toBe('team-2');
    });

    it('ignores the post being updated', function (): void {
        $post = Post::factory()->create(['slug' => 'hello-world']);

        expect($this->action->execute('Hello World', 'post', ignoreId: $post->id))->toBe('hello-world');
    });

    it('falls back to `untitled` when nothing can be slugged', function (): void {
        expect($this->action->execute('!!!', 'post'))->toBe('untitled');
    });
})->group('cms');
