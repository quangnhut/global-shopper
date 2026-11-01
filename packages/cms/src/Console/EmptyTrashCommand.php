<?php

declare(strict_types=1);

namespace Shopper\Cms\Console;

use Illuminate\Console\Command;
use Shopper\Cms\Actions\ForceDeletePostAction;
use Shopper\Cms\Models\Post;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'shopper:cms:empty-trash')]
final class EmptyTrashCommand extends Command
{
    protected $signature = 'shopper:cms:empty-trash';

    protected $description = 'Permanently delete posts and pages that have been in the trash longer than `shopper.cms.trash_days`.';

    public function handle(ForceDeletePostAction $forceDelete): int
    {
        $days = (int) config('shopper.cms.trash_days');

        if ($days <= 0) {
            $this->components->info('Emptying the trash automatically is disabled.');

            return self::SUCCESS;
        }

        $deleted = 0;

        Post::configuredClass()::query()
            ->onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays($days))
            ->each(function (Post $post) use ($forceDelete, &$deleted): void {
                $forceDelete->execute($post);
                $deleted++;
            });

        $this->components->info("Deleted {$deleted} item(s) from the trash.");

        return self::SUCCESS;
    }
}
