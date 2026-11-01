<?php

declare(strict_types=1);

namespace Shopper\Cms\Console;

use Illuminate\Console\Command;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Models\Post;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'shopper:cms:publish-scheduled')]
final class PublishScheduledPostsCommand extends Command
{
    protected $signature = 'shopper:cms:publish-scheduled';

    protected $description = 'Publish scheduled posts and pages whose publish date has passed.';

    public function handle(): int
    {
        $published = 0;

        Post::configuredClass()::query()
            ->where('status', PostStatus::Scheduled)
            ->where('published_at', '<=', now())
            ->each(function (Post $post) use (&$published): void {
                $post->forceFill(['status' => PostStatus::Published])->save();
                $published++;
            });

        $this->components->info("Published {$published} scheduled item(s).");

        return self::SUCCESS;
    }
}
