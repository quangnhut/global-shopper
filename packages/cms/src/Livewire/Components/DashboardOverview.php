<?php

declare(strict_types=1);

namespace Shopper\Cms\Livewire\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Models\Post;
use Shopper\Cms\PostTypes\PostType;
use Shopper\Cms\PostTypes\PostTypeRegistry;

/**
 * WordPress's "At a Glance" and "Recent Drafts" dashboard boxes.
 */
class DashboardOverview extends Component
{
    /**
     * @return array<int, array{type: PostType, published: int, drafts: int, pending: int, url: string}>
     */
    #[Computed]
    public function glance(): array
    {
        return collect($this->browsableTypes())
            ->map(function (PostType $type): array {
                $counts = Post::configuredClass()::query()
                    ->ofType($type->key)
                    ->toBase()
                    ->selectRaw('status, count(*) as aggregate')
                    ->groupBy('status')
                    ->pluck('aggregate', 'status');

                return [
                    'type' => $type,
                    'published' => (int) ($counts[PostStatus::Published->value] ?? 0),
                    'drafts' => (int) ($counts[PostStatus::Draft->value] ?? 0),
                    'pending' => (int) ($counts[PostStatus::Pending->value] ?? 0),
                    'url' => route('shopper.cms.'.Str::plural($type->key).'.index'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The current user's latest drafts, like WordPress.
     *
     * @return Collection<int, Post>
     */
    #[Computed]
    public function recentDrafts(): Collection
    {
        return Post::configuredClass()::query()
            ->whereIn('type', array_keys($this->browsableTypes()))
            ->where('status', PostStatus::Draft)
            ->where('author_id', shopper()->auth()->id())
            ->latest('updated_at')
            ->limit(5)
            ->get();
    }

    public function editUrl(Post $post): string
    {
        return route('shopper.cms.'.Str::plural($post->type).'.edit', $post);
    }

    public function render(): View
    {
        return view('shopper-cms::livewire.components.dashboard-overview');
    }

    /**
     * @return array<string, PostType>
     */
    private function browsableTypes(): array
    {
        $user = shopper()->auth()->user();

        return array_filter(
            resolve(PostTypeRegistry::class)->all(),
            fn (PostType $type): bool => (bool) $user?->can($type->permission('browse')),
        );
    }
}
