<?php

declare(strict_types=1);

namespace Shopper\Cms\Seo;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;
use Shopper\Cms\Models\PostTag;
use Shopper\Cms\Permalinks\PermalinkGenerator;
use Shopper\Seo\Contracts\SeoSource;
use Shopper\Seo\Schema\GraphContext;
use Shopper\Seo\Support\TemplateRenderer;

/**
 * Post categories and tags (archives) for the SEO output.
 */
final class TermSource implements SeoSource
{
    public function __construct(private readonly PermalinkGenerator $permalinks) {}

    public function url(Model $model): string
    {
        return $this->permalinks->url($model);
    }

    public function isPublic(Model $model): bool
    {
        return true;
    }

    /**
     * @param  PostCategory|PostTag  $model
     */
    public function variables(Model $model): array
    {
        $description = TemplateRenderer::excerpt($model->description);

        return [
            'title' => $model->name,
            'description' => $description,
            'excerpt' => $description,
            $model instanceof PostTag ? 'tag' : 'category' => $model->name,
        ];
    }

    public function image(Model $model): ?string
    {
        return null;
    }

    public function ogType(Model $model): string
    {
        return 'website';
    }

    public function publishedAt(Model $model): ?CarbonInterface
    {
        return null;
    }

    /**
     * The archive changes when one of its posts does.
     */
    public function modifiedAt(Model $model): ?CarbonInterface
    {
        $date = $this->publishedPosts($model)->max('updated_at');

        return $date === null ? null : Carbon::parse($date);
    }

    /**
     * @param  PostCategory|PostTag  $model
     */
    public function isEmpty(Model $model): bool
    {
        return $this->publishedPosts($model)->doesntExist();
    }

    /**
     * @param  PostCategory|PostTag  $model
     */
    public function breadcrumbs(Model $model): array
    {
        $trail = [];
        $term = $model;
        $seen = [];

        while ($term !== null && ! isset($seen[$term->id])) {
            $seen[$term->id] = true;
            array_unshift($trail, ['name' => $term->name, 'url' => $this->url($term)]);
            $term = $term instanceof PostCategory ? $term->parent : null;
        }

        return $trail;
    }

    public function schema(Model $model, GraphContext $context): array
    {
        return [];
    }

    public function sitemapQuery(string $type): Builder
    {
        $model = $type === 'post_tag' ? PostTag::class : PostCategory::class;

        return $model::query()
            ->whereHas('posts', fn (Builder $query) => $query
                ->where('status', PostStatus::Published)
                ->where('published_at', '<=', now()))
            ->orderBy('id');
    }

    public function sitemapImages(Model $model): array
    {
        return [];
    }

    /**
     * Published posts of a tag, or of a category and its subcategories (a parent category lists them too).
     *
     * @return Builder<Post>
     */
    private function publishedPosts(Model $model): Builder
    {
        $ids = $model instanceof PostCategory
            ? $model->descendantsAndSelf()->pluck('id')->all()
            : [$model->getKey()];
        $relation = $model instanceof PostTag ? 'tags' : 'categories';
        $table = $model->getTable();

        return Post::configuredClass()::query()
            ->published()
            ->whereHas($relation, fn (Builder $query) => $query->whereIn("{$table}.id", $ids));
    }
}
