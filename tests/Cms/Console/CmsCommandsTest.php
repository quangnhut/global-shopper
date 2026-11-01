<?php

declare(strict_types=1);

use Shopper\Cms\Console\EmptyTrashCommand;
use Shopper\Cms\Console\PublishScheduledPostsCommand;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Models\Post;
use Shopper\Models\Role;

uses(Tests\Cms\TestCase::class);

describe(PublishScheduledPostsCommand::class, function (): void {
    it('publishes only scheduled posts that are due', function (): void {
        $due = Post::factory()->create(['status' => PostStatus::Scheduled, 'published_at' => now()->subMinute()]);
        $later = Post::factory()->scheduled()->create();

        $this->artisan('shopper:cms:publish-scheduled')->assertSuccessful();

        expect($due->refresh()->status)->toBe(PostStatus::Published)
            ->and($later->refresh()->status)->toBe(PostStatus::Scheduled);
    });
})->group('cms');

describe(EmptyTrashCommand::class, function (): void {
    it('deletes only items trashed longer than `trash_days` ago', function (): void {
        $old = Post::factory()->create(['deleted_at' => now()->subDays(31)]);
        $recent = Post::factory()->create(['deleted_at' => now()->subDays(29)]);
        $live = Post::factory()->create();

        $this->artisan('shopper:cms:empty-trash')->assertSuccessful();

        expect(Post::query()->withTrashed()->pluck('id')->all())->toEqualCanonicalizing([$recent->id, $live->id])
            ->and(Post::query()->withTrashed()->find($old->id))->toBeNull();
    });

    it('never deletes when `trash_days` is 0', function (): void {
        config(['shopper.cms.trash_days' => 0]);
        Post::factory()->create(['deleted_at' => now()->subYears(2)]);

        $this->artisan('shopper:cms:empty-trash')->assertSuccessful();

        expect(Post::query()->withTrashed()->count())->toBe(1);
    });
})->group('cms');

describe('cms permissions', function (): void {
    it('creates the WordPress-style permissions and grants them to administrators', function (): void {
        $admin = Role::query()->where('name', config('shopper.admin.roles.admin'))->firstOrFail();

        foreach (['browse_posts', 'add_pages', 'publish_posts', 'edit_others_pages'] as $permission) {
            expect($admin->hasPermissionTo($permission))->toBeTrue();
        }
    });

    it('schedules the cms commands', function (): void {
        $events = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($event): string => $event->command);

        expect($events->contains(fn (string $command): bool => str_contains($command, 'shopper:cms:publish-scheduled')))->toBeTrue()
            ->and($events->contains(fn (string $command): bool => str_contains($command, 'shopper:cms:empty-trash')))->toBeTrue();
    });
})->group('cms');
